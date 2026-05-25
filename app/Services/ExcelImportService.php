<?php

namespace App\Services;

use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Jury;
use App\Models\Projet;
use App\Models\Soutenance;
use App\Repositories\EnseignantRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportService
{
    protected EnseignantRepository $enseignantRepository;

    public function __construct(EnseignantRepository $enseignantRepository)
    {
        $this->enseignantRepository = $enseignantRepository;
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
}
