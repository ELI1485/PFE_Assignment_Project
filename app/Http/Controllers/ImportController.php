<?php

namespace App\Http\Controllers;

use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Projet;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    protected ExcelImportService $importService;

    public function __construct(ExcelImportService $importService)
    {
        $this->importService = $importService;
    }

    public function showForm()
    {
        return view('import', [
            'hasStudents' => Etudiant::exists(),
            'hasProfessors' => Enseignant::exists(),
        ]);
    }

    /**
     * Unified import: a single Excel file containing exactly 2 sheets:
     *  - Sheet 1: Étudiants — all filières mixed together. The "Filière"
     *    column accepts any value and the filière is auto-created on the fly.
     *  - Sheet 2: Professeurs
     */
    public function importUnified(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5000',
        ]);

        try {
            $results = $this->importService->importUnified($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->route('import.form')
                ->withErrors(['file' => "Impossible d'importer le fichier : " . $e->getMessage()])
                ->withInput();
        }

        $message = sprintf(
            'Importation terminée : %d étudiants et %d enseignants ajoutés.',
            $results['etudiants'] ?? 0,
            $results['enseignants'] ?? 0
        );

        return redirect()->route('import.form')->with('success', $message);
    }

    /**
     * Serve the single unified Excel template. The file lives in
     * public/templates/excel_template.xlsx and is generated on-demand
     * the first time it is requested if it is missing.
     */
    public function downloadTemplate()
    {
        $path = public_path('templates/excel_template.xlsx');

        if (!file_exists($path)) {
            $this->generateUnifiedTemplate($path);
        }

        return response()->download($path, 'excel_template.xlsx');
    }

    /**
     * Drop all tables (equivalent to php artisan migrate:fresh).
     */
    public function resetDatabase()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);

            // Only wipe the temporary diagnostic file — history snapshots are a
            // permanent local archive and must survive a DB reset.
            \Illuminate\Support\Facades\Storage::delete('conformite_diagnostic.json');

            return redirect()->route('import.form')
                ->with('success', 'Base de données réinitialisée avec succès. Toutes les tables ont été recréées.');
        } catch (\Throwable $e) {
            return redirect()->route('import.form')
                ->withErrors(['reset' => 'Erreur lors de la réinitialisation : ' . $e->getMessage()]);
        }
    }

    /**
     * Build a fresh excel_template.xlsx with 2 sheets:
     *  - "Etudiants"   (all filières mixed; header on row 1)
     *  - "Professeurs" (header on row 2 to match the historical layout)
     */
    private function generateUnifiedTemplate(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $spreadsheet = new Spreadsheet();

        // Sheet 1 — Étudiants (all filières together)
        $studentHeaders = ['CNE', 'Nom', 'Prenom', 'Filiere', 'CNE Binome', 'Nom Binome', 'Prenom Binome'];
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Etudiants');
        $sheet->fromArray($studentHeaders, null, 'A1');
        $this->styleHeaderRow($sheet, count($studentHeaders), 1);

        // Sheet 2 — Professeurs (header on row 2 to match the historical layout)
        $profSheet = $spreadsheet->createSheet();
        $profSheet->setTitle('Professeurs');
        $profSheet->setCellValue('A1', 'Liste des Enseignants');
        $profSheet->mergeCells('A1:C1');
        $profSheet->getStyle('A1')->getFont()->setBold(true);
        $profSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $profHeaders = ['Nom', 'Prenom', 'Discipline'];
        $profSheet->fromArray($profHeaders, null, 'A2');
        $this->styleHeaderRow($profSheet, count($profHeaders), 2);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    }

    private function styleHeaderRow($sheet, int $cols, int $row): void
    {
        $lastColumn = chr(ord('A') + $cols - 1);
        $range = "A{$row}:{$lastColumn}{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEAFB');

        for ($i = 0; $i < $cols; $i++) {
            $col = chr(ord('A') + $i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
