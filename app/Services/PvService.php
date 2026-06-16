<?php

namespace App\Services;

use App\Repositories\EnseignantRepository;
use App\Models\Configuration;
use App\Models\Soutenance;

class PvService
{
    protected EnseignantRepository $enseignantRepository;

    public function __construct(EnseignantRepository $enseignantRepository)
    {
        $this->enseignantRepository = $enseignantRepository;
    }

    /**
     * Keep names compact so the PV always fits on a single page, even with
     * long professor names (no line-wrapping in the jury/signature cells).
     */
    private function shortName(string $name, int $max = 38): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (mb_strlen($name) <= $max) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, $max - 1)).'…';
    }

    public function generatePvForStudent(Soutenance $soutenance, $customFolder = 'app/public')
    {
        $template = new \PhpOffice\PhpWord\TemplateProcessor(storage_path('template_pv.docx'));

        // Dynamic document header (school name, department, optional logo).
        $template->setValue('school_name', Configuration::get('school_name'));
        $template->setValue('department_name', Configuration::get('department_name'));

        $logoPath = Configuration::logoPath();
        if ($logoPath) {
            $template->setImageValue('school_logo', [
                'path'   => $logoPath,
                'width'  => 90,
                'height' => 90,
                'ratio'  => true,
            ]);
        } else {
            $template->setValue('school_logo', '');
        }

        $currentYear = date('Y');
        $annee_univ = ($currentYear - 1) . '-' . $currentYear;
        $template->setValue('annee_univ', $annee_univ);

        $nom = $soutenance->projet->etudiant->nom;
        $prenom = $soutenance->projet->etudiant->prenom;

        $template->setValue('nom_etudiant', $this->shortName($nom . ' ' . $prenom));

        // Fully dynamic filière name (no hardcoded GI/ID/TDIA checkboxes).
        $filiereName = $soutenance->projet->etudiant->filiere?->nom ?? '';
        $template->setValue('filiere_name', $filiereName);

        $encadrant = $soutenance->projet->encadrant;
        $template->setValue('nom_encadrant', $this->shortName($encadrant->nom . ' ' . $encadrant->prenom));

        $rapporteurs = $soutenance->jury->enseignants->where('pivot.role', '!=', 'President')->values();
        $count = $rapporteurs->count();
        $template->cloneRow('nom_jury', $count);
        foreach ($rapporteurs as $index => $prof) {
            $rowNumber = $index + 1;

            $template->setValue("nom_jury#{$rowNumber}", $this->shortName($prof->nom . ' ' . $prof->prenom));
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
                    ? $this->shortName('Pr. ' . $member->nom . ' ' . $member->prenom)
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
