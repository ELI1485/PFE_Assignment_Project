<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\Projet;
use App\Models\Salle;
use App\Repositories\EnseignantRepository;
use App\Repositories\EtudiantRepository;
use Illuminate\Http\UploadedFile;
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

    public function importMaster(UploadedFile $file): array
    {
        $allSheets = Excel::toArray([], $file);
        $results = ['etudiants' => 0, 'enseignants' => 0, 'salles' => 0];

        $filename = strtolower($file->getClientOriginalName());
        $filiere = 'TDIA';
        if (str_contains($filename, 'gl')) {
            $filiere = 'GL';
        }
        if (str_contains($filename, 'bda')) {
            $filiere = 'BDA';
        }
        if (str_contains($filename, 'id')) {
            $filiere = 'ID';
        }

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
                        'specialite' => trim($discipline ?? ''),
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
                    'specialite' => trim($discipline ?? ''),
                ]);
                $count++;
            }
        }

        return $count;
    }
}
