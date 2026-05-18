<?php

namespace App\Services;

use App\Repositories\EnseignantRepository;
use App\Models\Soutenance;

class PvService
{
    protected EnseignantRepository $enseignantRepository;

    public function __construct(EnseignantRepository $enseignantRepository)
    {
        $this->enseignantRepository = $enseignantRepository;
    }


    public function generatePvForStudent(Soutenance $soutenance, $customFolder = 'app/public')
    {
        $template = new \PhpOffice\PhpWord\TemplateProcessor(storage_path('template_pv.docx'));

        $currentYear = date('Y');
        $annee_univ = ($currentYear - 1) . '-' . $currentYear;
        $template->setValue('annee_univ', $annee_univ);

        $nom = $soutenance->projet->etudiant->nom;
        $prenom = $soutenance->projet->etudiant->prenom;

        $template->setValue('nom_etudiant', $nom . ' ' . $prenom);

        $filiere = strtoupper(trim($soutenance->projet->etudiant->filiere ?? ''));
        $box_id = '☐';
        $box_gi = '☐';
        $box_tdia = '☐';

        if (str_contains($filiere, 'TDIA') || str_contains($filiere, 'TRANSFORMATION')) {
            $box_tdia = '☒';
        } elseif (str_contains($filiere, 'GI') || str_contains($filiere, 'INFORMATIQUE')) {
            $box_gi = '☒';
        } elseif (str_contains($filiere, 'ID') || str_contains($filiere, 'DONN')) {
            $box_id = '☒';
        }

        $template->setValue('box_id', $box_id);
        $template->setValue('box_gi', $box_gi);
        $template->setValue('box_tdia', $box_tdia);

        $intitule_projet = $soutenance->projet->titre;
        $template->setValue('intitule_rapport', $intitule_projet);

        $encadrant = $soutenance->projet->encadrant;
        $template->setValue('nom_encadrant', $encadrant->nom . ' ' . $encadrant->prenom);

        $rapporteurs = $soutenance->jury->enseignants->where('pivot.role', '!=', 'President')->values();
        $count = $rapporteurs->count();
        $template->cloneRow('nom_jury', $count);
        foreach ($rapporteurs as $index => $prof) {
            $rowNumber = $index + 1;

            $template->setValue("nom_jury#{$rowNumber}", $prof->nom . ' ' . $prof->prenom);
            $template->setValue("jury_role#{$rowNumber}", $prof->pivot->role);
        }

        $date = optional($soutenance->creneau->date)?->format('d/m/Y');
        $template->setValue('date_soutenance', $date);

        $juryMembers = collect();

        // President
        $juryMembers->push($encadrant);

        // Rapporteurs
        foreach ($rapporteurs as $rapporteur) {
            $juryMembers->push($rapporteur);
        }

        // Fill up to 3 signatures
        for ($i = 0; $i < 3; $i++) {

            $member = $juryMembers[$i] ?? null;

            $template->setValue(
                'signature' . ($i + 1),
                $member
                    ? ('Pr. ' . $member->nom . ' ' . $member->prenom)
                    : ''
            );
        }
        $fileName = "Fiche_Evaluation_PFE_{$nom}_{$prenom}.docx";

        $savePath = storage_path($customFolder . '/' . $fileName);
        $template->saveAs($savePath);

        return $savePath;
    }

    public function organizePvsByTeacher()
    {
        $profs = $this->enseignantRepository->findAll();

        foreach ($profs as $prof) {
            $nom = $prof->nom;
            $prenom = $prof->prenom;

            $allSoutenances = $prof->soutenances;

            if ($allSoutenances->isNotEmpty()) {
                $folderName = "Pr_{$nom}_{$prenom}";
                $teacherFolderPath = 'temp_pvs/' . $folderName;

                if (!file_exists(storage_path($teacherFolderPath))) {
                    mkdir(storage_path($teacherFolderPath), 0777, true);
                }

                foreach ($allSoutenances as $soutenance) {
                    $this->generatePvForStudent($soutenance, $teacherFolderPath);
                }
            }
        }

        // THE ZIP COMPRESSION

        $zipFileName = 'Archive_PVs_PFE_' . date('Y') . '.zip';
        $zipFilePath = storage_path('app/public/' . $zipFileName);

        $zip = new \ZipArchive();

        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {

            $files = \Illuminate\Support\Facades\File::allFiles(storage_path('temp_pvs'));

            foreach ($files as $file) {
                $zip->addFile($file->getRealPath(), $file->getRelativePathname());
            }
            $zip->close();
        }

        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('temp_pvs'));

        return $zipFilePath;
    }
}
