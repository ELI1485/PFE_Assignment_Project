<?php

namespace App\Services;

use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Filiere;
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
     * Import a single Excel workbook that contains exactly 2 sheets:
     *   - Sheet 1 (index 0): Étudiants — ALL filières mixed together.
     *       Columns (header on row 1, data from row 2):
     *       CNE | Nom | Prénom | Filière | CNE Binôme | Nom Binôme | Prénom Binôme
     *       The "Filière" value is read from each row and can be ANY string.
     *       Filière records are auto-created (with a distinct color) when new.
     *   - Sheet 2 (index 1): Professeurs (header on row 2, data from row 3)
     *       Columns: Nom | Prénom | Discipline
     *
     * The full planning state (juries, soutenances, creneaux, projets,
     * etudiants and enseignants) is wiped beforehand so the imported file
     * becomes the authoritative source of truth. Filière records are kept so
     * their assigned colors stay stable across imports.
     */
    public function importUnified(UploadedFile $file): array
    {
        $allSheets = Excel::toArray([], $file);

        if (count($allSheets) < 2) {
            throw new \RuntimeException(
                "Le fichier doit contenir exactement 2 onglets : Étudiants (onglet 1) et Professeurs (onglet 2). " .
                "Veuillez respecter le modèle fourni."
            );
        }

        return DB::transaction(function () use ($allSheets) {
            // Wipe the planning + people state — the unified import is authoritative.
            DB::table('jury_enseignant')->delete();
            Soutenance::query()->delete();
            Jury::query()->delete();
            Creneau::query()->delete();
            Projet::query()->delete();
            Etudiant::query()->delete();
            Enseignant::query()->delete();

            // Sheet 1 — students (all filières mixed)
            $studentRows = $allSheets[0] ?? [];
            $totalEtudiants = $this->insertStudentsFromRows($studentRows);

            // Sheet 2 — professors
            $professorRows = $allSheets[1] ?? [];
            $totalEnseignants = $this->insertProfessorsFromRows($professorRows);

            \App\Models\Configuration::set('last_import_time', (string) now()->timestamp);

            return [
                'etudiants'   => $totalEtudiants,
                'enseignants' => $totalEnseignants,
            ];
        });
    }

    private function insertStudentsFromRows(array $rows): int
    {
        $count = 0;

        // Cache of filière name (lower-case) => Filiere model for this import.
        $filiereCache = [];

        foreach ($rows as $index => $row) {
            // Check headers on row 0
            if ($index === 0) {
                // If it doesn't look like a valid header with CNE/Nom/Prenom...
                if (count($row) < 4 || (stripos((string)($row[1] ?? ''), 'nom') === false && stripos((string)($row[2] ?? ''), 'pr') === false)) {
                    throw new \RuntimeException("Les colonnes de l'onglet Étudiants sont incorrectes. Veuillez respecter les colonnes du modèle Excel.");
                }
                continue;
            }

            $row = array_pad($row, 7, null);
            [
                $cne, $nom, $prenom, $filiereName,
                $cne2, $nom2, $prenom2,
            ] = $row;

            $nom    = trim((string) $nom);
            $prenom = trim((string) $prenom);
            if ($nom === '' || $prenom === '') {
                continue;
            }

            $filiere = $this->resolveFiliere(trim((string) $filiereName), $filiereCache);

            $etudiant = Etudiant::create([
                'cne'        => trim((string) $cne) ?: null,
                'nom'        => $nom,
                'prenom'     => $prenom,
                'filiere_id' => $filiere->id,
            ]);

            $etudiant2Id = null;
            $nom2    = trim((string) $nom2);
            $prenom2 = trim((string) $prenom2);
            if ($nom2 !== '' && $prenom2 !== '') {
                $etudiant2 = Etudiant::create([
                    'cne'        => trim((string) $cne2) ?: null,
                    'nom'        => $nom2,
                    'prenom'     => $prenom2,
                    'filiere_id' => $filiere->id,
                ]);
                $etudiant2Id = $etudiant2->id;
            }

            Projet::create([
                'cne'          => trim((string) $cne) ?: null,
                'etudiant_id'  => $etudiant->id,
                'etudiant2_id' => $etudiant2Id,
            ]);

            $count += $etudiant2Id ? 2 : 1;
        }

        return $count;
    }

    /**
     * Resolve (find or auto-create) a Filiere from a raw name read in the
     * student sheet. New filières get a distinct color from the palette.
     */
    private function resolveFiliere(string $name, array &$cache): Filiere
    {
        if ($name === '') {
            $name = 'Inconnue';
        }

        $key = mb_strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        // Colors already chosen during THIS import but possibly not yet seen
        // by the DB query inside findOrCreateByName.
        $extraUsed = array_map(fn (Filiere $f) => $f->couleur, array_values($cache));

        $filiere = Filiere::findOrCreateByName($name, $extraUsed);
        $cache[$key] = $filiere;

        return $filiere;
    }

    private function insertProfessorsFromRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $index => $row) {
            // Validate headers on the second row (index 1)
            if ($index === 1) {
                if (count($row) < 2 || (stripos((string)($row[0] ?? ''), 'nom') === false && stripos((string)($row[1] ?? ''), 'pr') === false)) {
                    throw new \RuntimeException("Les colonnes de l'onglet Professeurs sont incorrectes. Veuillez respecter les colonnes du modèle Excel.");
                }
            }
            
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
