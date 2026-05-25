<?php

namespace App\Services;

use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Jury;
use App\Models\Projet;
use App\Models\Salle;
use App\Models\Soutenance;
use App\Repositories\EnseignantRepository;
use App\Repositories\EtudiantRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportService
{
    protected EtudiantRepository $etudiantRepository;


    protected EnseignantRepository $enseignantRepository;

    public function __construct(EtudiantRepository $etudiantRepository, EnseignantRepository $enseignantRepository)
    {
        $this->etudiantRepository = $etudiantRepository;
        $this->enseignantRepository = $enseignantRepository;
    }

    /**
     * Import a single Excel workbook that contains two sheets:
     *   - Sheet 0 (Etudiants): CNE, Nom, Prenom, Filiere, CNE2, Nom2, Prenom2, Sujet, Langue
     *   - Sheet 1 (Professeurs): Nom, Prenom, Discipline (header on row 2, data from row 3)
     *
     * The full planning state (juries, soutenances, creneaux, projets,
     * etudiants and enseignants) is wiped beforehand so the imported file
     * becomes the authoritative source of truth.
     */
    public function extractFiliereFromFilename(string $filename): string
    {
        $filename = strtolower($filename);
        $normalizedFilename = str_replace(['é', 'è', 'ê', 'ë'], 'e', $filename);
        
        $filiere = 'TDIA';

        if (str_contains($filename, 'gi') || str_contains($normalizedFilename, 'genie informatique')) {
            $filiere = 'GI';
        }
        
        if (str_contains($filename, 'id') || str_contains($normalizedFilename, 'ingenierie des donnees')) {
            $filiere = 'ID';
        }

        if (str_contains($filename, 'tdia') || str_contains($normalizedFilename, 'transformation digitale') || str_contains($normalizedFilename, 'intelligence artificielle')) {
            $filiere = 'TDIA';
        }
        
        return $filiere;
    }

    /**
     * Import a single Excel workbook that contains exactly 4 sheets:
     *   - Sheet 0: Students for GI (Génie Informatique)
     *   - Sheet 1: Students for ID (Ingénierie des Données)
     *   - Sheet 2: Students for TDIA (Transformation Digitale & Intelligence Artificielle)
     *   - Sheet 3: Professors (header on row 2, data from row 3)
     *
     * The full planning state (juries, soutenances, creneaux, projets,
     * etudiants and enseignants) is wiped beforehand so the imported file
     * becomes the authoritative source of truth.
     */
    public function importUnified(UploadedFile $file): array
    {
        $allSheets = Excel::toArray([], $file);

        if (count($allSheets) < 4) {
            throw new \RuntimeException(
                "Le fichier doit contenir exactement 4 feuilles : Étudiants GI (feuille 1), Étudiants ID (feuille 2), Étudiants TDIA (feuille 3) et Professeurs (feuille 4). " .
                "Seulement " . count($allSheets) . " feuille(s) détectée(s)."
            );
        }

        // Sheet → filière mapping (by position)
        $sheetFiliereMap = [
            0 => 'GI',
            1 => 'ID',
            2 => 'TDIA',
        ];

        return DB::transaction(function () use ($allSheets, $sheetFiliereMap) {
            // Wipe the planning + people state — the unified import is authoritative.
            DB::table('jury_enseignant')->delete();
            Soutenance::query()->delete();
            Jury::query()->delete();
            Creneau::query()->delete();
            Projet::query()->delete();
            Etudiant::query()->delete();
            Enseignant::query()->delete();

            $totalEtudiants = 0;

            // Import students from sheets 0, 1, 2 with forced filière
            foreach ($sheetFiliereMap as $sheetIndex => $filiere) {
                $studentRows = $allSheets[$sheetIndex] ?? [];
                $totalEtudiants += $this->insertStudentsFromRows($studentRows, $filiere);
            }

            // Import professors from sheet 3
            $professorRows = $allSheets[3] ?? [];
            $totalEnseignants = $this->insertProfessorsFromRows($professorRows);

            return [
                'etudiants'   => $totalEtudiants,
                'enseignants' => $totalEnseignants,
            ];
        });
    }

    private function insertStudentsFromRows(array $rows, ?string $fallbackFiliere = null): int
    {
        $count = 0;

        foreach ($rows as $index => $row) {
            // Skip the header row.
            if ($index === 0) {
                continue;
            }

            $row = array_pad($row, 9, null);
            [
                $cne, $nom, $prenom, $filiere,
                $cne2, $nom2, $prenom2,
                $sujet, $langue,
            ] = $row;

            $nom    = trim((string) $nom);
            $prenom = trim((string) $prenom);
            if ($nom === '' || $prenom === '') {
                continue;
            }

            $resolvedFiliere = trim((string) $filiere) !== '' ? trim((string) $filiere) : ($fallbackFiliere ?: 'Inconnue');

            $etudiant = Etudiant::create([
                'cne'     => trim((string) $cne) ?: null,
                'nom'     => $nom,
                'prenom'  => $prenom,
                'filiere' => $resolvedFiliere,
            ]);

            $etudiant2Id = null;
            $nom2    = trim((string) $nom2);
            $prenom2 = trim((string) $prenom2);
            if ($nom2 !== '' && $prenom2 !== '') {
                $etudiant2 = Etudiant::create([
                    'cne'     => trim((string) $cne2) ?: null,
                    'nom'     => $nom2,
                    'prenom'  => $prenom2,
                    'filiere' => $resolvedFiliere,
                ]);
                $etudiant2Id = $etudiant2->id;
            }

            Projet::create([
                'cne'              => trim((string) $cne) ?: null,
                'etudiant_id'      => $etudiant->id,
                'etudiant2_id'     => $etudiant2Id,
                'sujet'            => trim((string) $sujet),
                'titre'            => trim((string) $sujet),
                'langue_soutenance' => trim((string) ($langue ?: 'Francais')),
            ]);

            $count += $etudiant2Id ? 2 : 1;
        }

        return $count;
    }

    private function insertProfessorsFromRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $index => $row) {
            // First two rows are headers / merged title.
            if ($index < 2) {
                continue;
            }

            [$nom, $prenom, $discipline] = array_pad($row, 3, null);
            $nom    = trim((string) $nom);
            $prenom = trim((string) $prenom);
            if ($nom === '' || $prenom === '') {
                continue;
            }

            if (! $this->enseignantRepository->findByNomPrenom($nom, $prenom)) {
                $this->enseignantRepository->create([
                    'nom'        => $nom,
                    'prenom'     => $prenom,
                    'discipline' => trim((string) ($discipline ?? '')),
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function importMaster(UploadedFile $file): array
    {
        $allSheets = Excel::toArray([], $file);
        $results = ['etudiants' => 0, 'enseignants' => 0, 'salles' => 0];

        $filiere = $this->extractFiliereFromFilename($file->getClientOriginalName());

        if (isset($allSheets[0])) {
            foreach ($allSheets[0] as $index => $row) {
                if ($index === 0) {
                    continue;
                }
                $row = array_pad($row, 5, null);
                [$cne, $nom, $prenom, $emailPerso, $emailAcad] = $row;
                if (empty($nom) || empty($prenom)) {
                    continue;
                }

                if (! $this->etudiantRepository->findByCne($cne)) {
                    $this->etudiantRepository->create([
                        'cne' => $cne,
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'filiere' => $filiere,
                        'email_personnel' => trim($emailPerso ?? ''),
                        'email_academique' => trim($emailAcad ?? ''),
                    ]);
                    $results['etudiants']++;
                }
            }
        }

        if (isset($allSheets[1])) {
            foreach ($allSheets[1] as $index => $row) {
                if ($index < 2) {
                    continue;
                }
                [$nom, $prenom, $discipline] = array_pad($row, 3, null);
                if (empty($nom) || empty($prenom)) {
                    continue;
                }

                if (! $this->enseignantRepository->findByNomPrenom($nom, $prenom)) {
                    $this->enseignantRepository->create([
                        'nom' => trim($nom),
                        'prenom' => trim($prenom),
                        'discipline' => trim($discipline ?? ''),
                    ]);
                    $results['enseignants']++;
                }
            }
        }

        if (isset($allSheets[2])) {
            foreach ($allSheets[2] as $index => $row) {
                if ($index === 0) {
                    continue;
                }
                [$nom, $cap] = array_pad($row, 2, null);
                if (empty($nom)) {
                    continue;
                }

                $normalized = Salle::normalizeNom((string) $nom);
                $exists = Salle::all()
                    ->contains(fn (Salle $salle) => Salle::normalizeNom($salle->nom) === $normalized);

                if (! $exists) {
                    Salle::create([
                        'nom' => trim((string) $nom),
                    ]);
                    $results['salles']++;
                }
            }
        }

        return $results;
    }

    public function import(UploadedFile $file, string $filiere = 'TDIA'): int
    {
        $rows = Excel::toArray([], $file)[0] ?? [];
        $count = 0;

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $row = array_pad($row, 8, null);

            [
                $cne,
                $nom,
                $prenom,
                $cne2,
                $nom2,
                $prenom2,
                $sujet,
                $langue
            ] = $row;

            $nom = trim((string) $nom);
            $prenom = trim((string) $prenom);
            if (empty($nom) || empty($prenom)) {
                continue;
            }

            $etudiant = Etudiant::create([
                'cne' => trim((string) $cne) ?: null,
                'nom' => $nom,
                'prenom' => $prenom,
                'filiere' => $filiere,
            ]);

            $etudiant2Id = null;
            $nom2 = trim((string) $nom2);
            $prenom2 = trim((string) $prenom2);
            if (! empty($nom2) && ! empty($prenom2)) {
                $etudiant2 = Etudiant::create([
                    'cne' => trim((string) $cne2) ?: null,
                    'nom' => $nom2,
                    'prenom' => $prenom2,
                    'filiere' => $filiere,
                ]);
                $etudiant2Id = $etudiant2->id;
            }

            Projet::create([
                'cne' => trim((string) $cne) ?: null,
                'etudiant_id' => $etudiant->id,
                'etudiant2_id' => $etudiant2Id,
                'sujet' => trim((string) $sujet),
                'titre' => trim((string) $sujet),
                'langue_soutenance' => trim((string) ($langue ?: 'Français')),
            ]);

            $count += $etudiant2Id ? 2 : 1;
        }

        return $count;
    }

    public function importEncadrants(UploadedFile $file): int
    {
        $rows = Excel::toArray([], $file)[0];
        $count = 0;
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }
            [$nom, $prenom, $discipline] = array_pad($row, 3, null);
            if (empty($nom) || empty($prenom)) {
                continue;
            }
            $existing = $this->enseignantRepository->findByNomPrenom($nom, $prenom);
            if (! $existing) {
                $this->enseignantRepository->create([
                    'nom' => trim($nom),
                    'prenom' => trim($prenom),
                    'discipline' => trim($discipline ?? ''),
                ]);
                $count++;
            }
        }

        return $count;
    }
}
