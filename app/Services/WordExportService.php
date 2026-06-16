<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\JcTable;
use Illuminate\Support\Collection;
class WordExportService
{
    public function __construct(protected HistoryService $historyService) {}

    public function downloadSnapshot(object $snapshot, string $type)
    {
        $phpWord = new PhpWord;
        Settings::setOutputEscapingEnabled(true);
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 23811,
            'pageSizeH' => 16838,
            'marginTop' => 800,
            'marginBottom' => 800,
            'marginLeft' => 800,
            'marginRight' => 800,

        ]);

        $year = now()->month >= 9 ? now()->year : now()->year - 1;
        $anneeUniversitaire = $year.'/'.($year + 1);

        $center = ['alignment' => 'center'];

        $phpWord->addTableStyle('HeaderBox', [
            'borderSize' => 18,
            'borderColor' => '333333',
            'cellMargin' => 80,
            'alignment' => JcTable::CENTER,
        ]);
        $headerTable = $section->addTable('HeaderBox');
        $headerCell = $headerTable->addRow()->addCell(8000);

        $headerCell->addText(
            \App\Models\Configuration::get('etablissement'),
            ['bold' => true, 'size' => 12],
            array_merge($center, ['spaceAfter' => 40])
        );

        $headerCell->addText(
            \App\Models\Configuration::get('departement'),
            ['size' => 11],
            array_merge($center, ['spaceAfter' => 40])
        );

        if ($type === 'planning') {
            $headerCell->addText(
                "Planning des soutenances des Projets de Fin d'Etude",
                ['size' => 10],
                array_merge($center, ['spaceAfter' => 40])
            );
            $headerCell->addText(
                '('.\App\Models\Configuration::get('session').')',
                ['size' => 10, 'italic' => true],
                array_merge($center, ['spaceAfter' => 40])
            );
        } else {
            $headerCell->addText(
                "Affectation des encadrants de Projet de Fin d'Etude",
                ['size' => 10],
                array_merge($center, ['spaceAfter' => 40])
            );
        }

        $headerCell->addText(
            'Année Universitaire '.$anneeUniversitaire,
            ['size' => 10],
            array_merge($center, ['spaceAfter' => 0])
        );

        $section->addTextBreak(1);

        if ($type === 'affectation') {
            $legendStyle = ['cellMargin' => 40, 'borderSize' => 0, 'borderColor' => 'FFFFFF'];
            $phpWord->addTableStyle('LegendTable', $legendStyle);
            $legendTable = $section->addTable('LegendTable');

            $addLegend = function ($table, $color, $text) {
                $table->addRow(250);
                $table->addCell(500, ['bgColor' => $color])->addText('', ['size' => 8]);
                $table->addCell(8000)->addText($text, ['size' => 9]);
            };

            // Dynamic legend — built from the filières actually present in the
            // snapshot data, each with its own assigned color. No hardcoding.
            $legendRows = collect($snapshot->data)
                ->filter(fn ($r) => ! empty($r['filiere']))
                ->groupBy('filiere')
                ->map(fn ($items) => $items->first()['filiere_color'] ?? ($items->first()['bg'] ?? '#ffffff'));

            foreach ($legendRows as $filiereName => $color) {
                $addLegend($legendTable, ltrim((string) $color, '#'), 'Filière '.$filiereName);
            }

            $section->addTextBreak(1);
        }

        $rows = collect($snapshot->data);

        if ($type === 'planning') {
            $this->addPlanningTable($section, $rows);
        } else {
            $this->addAffectationTable($section, $rows);
        }

        $filename = $type.'_'.$snapshot->id.'.docx';
        $tempPath = storage_path('app/'.$filename);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    public function downloadLivePlanning()
    {
        $snapshot = $this->historyService->latest('planning');
        if (! $snapshot) {
            return back()->with('error', 'Aucun planning généré.');
        }

        return $this->downloadSnapshot($snapshot, 'planning');
    }

    public function downloadLiveAffectation()
    {
        $snapshot = $this->historyService->latest('affectation');
        if (! $snapshot) {
            return back()->with('error', 'Aucune affectation générée.');
        }

        return $this->downloadSnapshot($snapshot, 'affectation');
    }

    private function addPlanningTable(Section $section, Collection $rows): void
    {
        $phpWord = $section->getPhpWord();
        $phpWord->addTableStyle('PlanTable', [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 50,
            'layout' => 'fixed',
        ]);

        $table = $section->addTable('PlanTable');

        $headerBg = '000000';
        $boldCenter = ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'];
        $centerPara = ['alignment' => 'center'];

        // Determine the max number of jury members (examinateurs) across all rows
        $maxJuryMembers = $rows->max(fn($row) => count($row['examinateurs'] ?? []));
        $maxJuryMembers = max(2, $maxJuryMembers); // At least 2 columns

        // Build dynamic headers and widths
        $headers = ['ID', 'Encadrant'];
        $widths = [700, 3300];
        $juryColWidth = (int) floor(6600 / $maxJuryMembers); // Split the ~6600 space among jury members
        for ($j = 1; $j <= $maxJuryMembers; $j++) {
            $headers[] = "Membre de jury {$j}";
            $widths[] = $juryColWidth;
        }
        $headers = array_merge($headers, ['Date', 'Heure', 'Salle', 'Nom', 'Prénom', 'Filière']);
        $widths = array_merge($widths, [1800, 1600, 1400, 2500, 2500, 1600]);

        $table->addRow();
        foreach ($headers as $index => $header) {
            $table->addCell($widths[$index], ['bgColor' => $headerBg])->addText($header, $boldCenter, $centerPara);
        }

        foreach ($rows as $i => $row) {
            $bgBase = $i % 2 === 0 ? 'FFFFFF' : 'DDEBF7';

            $filiereColor = $row['filiere_color'] ?? PdfExportService::applyFiliereColor($row['filiere'] ?? '');

            $encadrant = $row['encadrant'] ?? '';
            $encColor = PdfExportService::getProfessorColor($encadrant);

            $examinateurs = $row['examinateurs'] ?? [];

            $table->addRow();

            $table->addCell($widths[0], ['bgColor' => ltrim($encColor, '#')])->addText($i + 1, ['bold' => true, 'size' => 8], $centerPara);

            $table->addCell($widths[1], ['bgColor' => ltrim($encColor, '#')])->addText($this->cleanProfessorPrefix($encadrant), ['bold' => true, 'size' => 8]);

            // Dynamic jury member columns
            for ($j = 0; $j < $maxJuryMembers; $j++) {
                $juryMember = $examinateurs[$j] ?? '';
                $jColor = PdfExportService::getProfessorColor($juryMember);
                $colIdx = 2 + $j;
                $table->addCell($widths[$colIdx], ['bgColor' => ltrim($jColor, '#')])->addText($this->cleanProfessorPrefix($juryMember), ['bold' => true, 'size' => 8]);
            }

            $baseColIdx = 2 + $maxJuryMembers;
            $table->addCell($widths[$baseColIdx], ['bgColor' => 'FFFF00'])->addText($row['date'] ?? '', ['bold' => true, 'size' => 8], $centerPara);

            $table->addCell($widths[$baseColIdx + 1], ['bgColor' => $bgBase])->addText($row['heure_debut'] ?? '', ['size' => 8], $centerPara);

            $table->addCell($widths[$baseColIdx + 2], ['bgColor' => $bgBase])->addText($row['salle'] ?? '', ['bold' => true, 'size' => 8], $centerPara);

            $nomCell = $table->addCell($widths[$baseColIdx + 3], ['bgColor' => ltrim($filiereColor, '#')]);
            $nomCell->addText(strtoupper($row['etudiant_nom'] ?? ''), ['size' => 8]);
            if (! empty($row['etudiant2_nom'])) {
                $nomCell->addText(strtoupper($row['etudiant2_nom']), ['size' => 8]);
            }

            $prenomCell = $table->addCell($widths[$baseColIdx + 4], ['bgColor' => ltrim($filiereColor, '#')]);
            $prenomCell->addText($row['etudiant_prenom'] ?? '', ['size' => 8]);
            if (! empty($row['etudiant2_prenom'])) {
                $prenomCell->addText($row['etudiant2_prenom'], ['size' => 8]);
            }

            $fText = $row['filiere'] ?? '';

            $table->addCell($widths[$baseColIdx + 5], ['bgColor' => ltrim($filiereColor, '#')])->addText($fText, ['bold' => true, 'size' => 8], $centerPara);
        }
    }

    private function addAffectationTable(Section $section, Collection $rows): void
    {
        $phpWord = $section->getPhpWord();
        $phpWord->addTableStyle('AffTable', [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'layout' => 'fixed',
        ]);
        $table = $section->addTable('AffTable');

        $boldCenter = ['bold' => true, 'size' => 9];
        $centerPara = ['alignment' => 'center'];
        $headerBg = '2F5496';
        $subBg = 'D9E1F2';

        $colWidths = [2000, 2000, 2250, 2250, 2250, 2250, 2250, 2250, 2250, 2250];

        $table->addRow();
        $table->addCell(4000, ['bgColor' => $headerBg, 'gridSpan' => 2])->addText('Encadrant', ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'], $centerPara);
        $table->addCell(18000, ['bgColor' => $headerBg, 'gridSpan' => 8])->addText('Etudiants encadrés', ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'], $centerPara);

        $headers = ['Nom', 'Prénom', 'Etudiant 1 Nom', 'Prénom', 'Etudiant 2 Nom', 'Prénom', 'Etudiant 3 Nom', 'Prénom', 'Etudiant 4 Nom', 'Prénom'];
        $table->addRow();
        foreach ($headers as $idx => $h) {
            $table->addCell($colWidths[$idx], ['bgColor' => $subBg])->addText($h, ['bold' => true, 'size' => 8, 'color' => '1F2D6B'], $centerPara);
        }

        $grouped = collect($rows)->sortBy('enc_nom')->groupBy('encadrant');

        foreach ($grouped as $encadrant => $students) {
            $bgCounts = $students->countBy('bg');
            $bgHex = $bgCounts->sortDesc()->keys()->first() ?? '#ffffff';
            $bgWord = ltrim($bgHex, '#');
            $bgWord = strtoupper($bgWord);

            $firstRow = $students->first();
            $encNom = $firstRow['enc_nom'] ?? '';
            $encPrenom = $firstRow['enc_prenom'] ?? '';
            if (empty($encNom) && $encadrant !== 'Non assigné') {
                $parts = explode(' ', $encadrant, 2);
                $encNom = $parts[0] ?? '';
                $encPrenom = $parts[1] ?? '';
            }

            $chunks = $students->chunk(4);
            foreach ($chunks as $chunk) {
                $names = $chunk->values();
                $table->addRow();
                $table->addCell(2000, ['bgColor' => 'FFFFFF'])->addText(strtoupper($encNom), ['bold' => true, 'size' => 8]);
                $table->addCell(2000, ['bgColor' => 'FFFFFF'])->addText($encPrenom, ['size' => 8]);
                for ($k = 0; $k < 4; $k++) {
                    $student = $names[$k] ?? null;
                    $eNom = strtoupper($student['etu_nom'] ?? '');
                    $ePrenom = $student['etu_prenom'] ?? '';
                    $e2Nom = strtoupper($student['etu2_nom'] ?? '');
                    $e2Prenom = $student['etu2_prenom'] ?? '';
                    $eBgHex = $student['bg'] ?? '#ffffff';
                    $eBgWord = strtoupper(ltrim($eBgHex, '#'));
                    $nomCell = $table->addCell(2250, ['bgColor' => $eBgWord]);
                    $nomCell->addText($eNom, ['size' => 8]);
                    if ($e2Nom !== '') {
                        $nomCell->addText($e2Nom, ['size' => 8]);
                    }

                    $prenomCell = $table->addCell(2250, ['bgColor' => $eBgWord]);
                    $prenomCell->addText($ePrenom, ['size' => 8]);
                    if ($e2Prenom !== '') {
                        $prenomCell->addText($e2Prenom, ['size' => 8]);
                    }
                }
            }
        }
    }

    private function cleanProfessorPrefix(string $name): string
    {
        return trim(preg_replace('/^(?:D|P)r\.\s*/i', '', $name));
    }
}
