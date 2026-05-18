<?php

namespace App\Http\Controllers;

use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Projet;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;


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

    public function importMaster(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5000',
        ]);

        $results = $this->importService->importMaster($request->file('file'));

        return redirect()->route('affectation.index')->with(
            'success',
            "Importation terminée : {$results['etudiants']} étudiants, {$results['enseignants']} enseignants, {$results['salles']} salles."
        );
    }

    public function importEtudiants(Request $request)
    {
        $request->validate([
            'file_etudiants' => 'required',
            'file_etudiants.*' => 'file|mimes:xlsx,xls|max:5000',
        ]);

        // Validate each file's structure before touching the DB
        foreach ($request->file('file_etudiants') as $file) {
            $error = $this->detectWrongFileType($file, 'etudiants');
            if ($error) {
                return redirect()->route('import.form')
                    ->withErrors(['file_etudiants' => $error])
                    ->withInput();
            }
        }

        Etudiant::query()->delete();
        Projet::query()->delete();

        \Illuminate\Support\Facades\DB::table('jury_enseignant')->delete();
        \App\Models\Soutenance::query()->delete();
        \App\Models\Jury::query()->delete();
        \App\Models\Creneau::query()->delete();

        $totalCount = 0;
        $details = [];

        foreach ($request->file('file_etudiants') as $file) {
            $filename = strtolower($file->getClientOriginalName());

            $filiere = $this->detectFiliere($filename, $file);

            $count = $this->importService->import($file, $filiere);
            $totalCount += $count;
            $details[] = "$count étudiants ($filiere)";
        }

        $detail = implode(', ', $details);

        return redirect()->route('import.form')
            ->with('success', "Fichiers importés avec succès — $totalCount étudiants au total. ($detail)");
    }

    public function importProfs(Request $request)
    {
        $request->validate([
            'file_profs' => 'required|file|mimes:xlsx,xls|max:2048',
        ]);

        $file = $request->file('file_profs');

        // Validate file structure before touching the DB
        $error = $this->detectWrongFileType($file, 'enseignants');
        if ($error) {
            return redirect()->route('import.form')
                ->withErrors(['file_profs' => $error])
                ->withInput();
        }

        // Clear planning data and reset encadrant assignments.
        // We keep Etudiants and Projets intact so the student list survives a professor re-import.
        // encadrant_id is set to null here explicitly so the user knows to re-run Affectation.
        \Illuminate\Support\Facades\DB::table('jury_enseignant')->delete();
        \App\Models\Soutenance::query()->delete();
        \App\Models\Jury::query()->delete();
        \App\Models\Creneau::query()->delete();
        Projet::query()->update(['encadrant_id' => null]);

        Enseignant::query()->delete();

        $count = $this->importService->importEncadrants($file);

        return redirect()->route('import.form')->with('success', "$count enseignants importés avec succès. Vous pouvez maintenant importer les fichiers étudiants.");
    }

    public function downloadEtudiantsTemplate()
    {
        return response()->download(
            public_path('templates/template_etudiants.xlsx')
        );
    }

    public function downloadEtudiantsEmailsTemplate()
    {
        return response()->download(
            public_path('templates/template_etudiants_email.xlsx')
        );
    }

    public function downloadProfesseursTemplate()
    {
        return response()->download(
            public_path('templates/template_enseignant.xlsx')
        );
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

    // Vérifie que le fichier uploadé correspond bien au type attendu (étudiants ou enseignants)
    private function detectWrongFileType(UploadedFile $file, string $expectedType): ?string
    {
        try {
            $rows = Excel::toArray([], $file)[0] ?? [];

            // Find the first non-empty row to use as the header
            $headerRow = null;
            foreach ($rows as $row) {
                $flat = array_filter(array_map('strval', $row));
                if (count($flat) >= 2) {
                    $headerRow = array_map(fn($v) => mb_strtolower(trim((string) $v)), $row);
                    break;
                }
            }

            if ($headerRow === null) {
                return "Le fichier est vide ou son format n'est pas reconnu.";
            }

            $headerStr = implode(' ', $headerRow);

            // Signatures that strongly indicate an étudiants file
            $etudiantSignatures = ['cne', 'etudiant', 'étudiant', 'binome', 'binôme', 'sujet', 'langue'];
            // Signatures that strongly indicate an enseignants file
            $enseignantSignatures = ['enseignant', 'discipline', 'specialite', 'spécialité', 'encadrant', 'grade'];

            $looksLikeEtudiant   = $this->matchesSignatures($headerStr, $etudiantSignatures);
            $looksLikeEnseignant = $this->matchesSignatures($headerStr, $enseignantSignatures);

            if ($expectedType === 'enseignants' && $looksLikeEtudiant && ! $looksLikeEnseignant) {
                return "Format de document incorrect : vous avez importé une liste d'étudiants dans la section enseignants. Veuillez sélectionner le bon fichier.";
            }

            if ($expectedType === 'etudiants' && $looksLikeEnseignant && ! $looksLikeEtudiant) {
                return "Format de document incorrect : vous avez importé une liste d'enseignants dans la section étudiants. Veuillez sélectionner le bon fichier.";
            }
        } catch (\Throwable) {
            // If we cannot read the file at all, let the import attempt fail naturally
        }

        return null;
    }

    private function matchesSignatures(string $haystack, array $signatures): bool
    {
        foreach ($signatures as $sig) {
            if (str_contains($haystack, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function detectFiliere(string $filename, UploadedFile $file): string
    {
        $map = [
            'ingenierie' => 'Ingénierie des Données',
            'ingénierie' => 'Ingénierie des Données',
            'donnees' => 'Ingénierie des Données',
            'données' => 'Ingénierie des Données',
            'tdia' => 'Transformation Digitale & Intelligence Artificielle',
            'transformation' => 'Transformation Digitale & Intelligence Artificielle',
            'digitale' => 'Transformation Digitale & Intelligence Artificielle',
            'artificielle' => 'Transformation Digitale & Intelligence Artificielle',
            'genie' => 'Génie Informatique',
            'génie' => 'Génie Informatique',
            'info'  => 'Génie Informatique',
        ];

        foreach ($map as $keyword => $filiere) {
            if (str_contains($filename, $keyword)) {
                return $filiere;
            }
        }

        if (preg_match('/\btdia\b/i', $filename)) {
            return 'Transformation Digitale & Intelligence Artificielle';
        }
        if (preg_match('/\bid\b/i', $filename)) {
            return 'Ingénierie des Données';
        }
        if (preg_match('/\bgi\b/i', $filename)) {
            return 'Génie Informatique';
        }

        try {
            $rows = Excel::toArray([], $file)[0];
            $haystack = strtolower(implode(' ', array_map('strval', array_merge(...array_slice($rows, 0, 5)))));

            if (str_contains($haystack, 'ingénierie') || str_contains($haystack, 'ingenierie') || str_contains($haystack, 'données')) {
                return 'Ingénierie des Données';
            }
            if (str_contains($haystack, 'tdia') || str_contains($haystack, 'transformation')) {
                return 'Transformation Digitale & Intelligence Artificielle';
            }
            if (str_contains($haystack, 'génie') || str_contains($haystack, 'genie')) {
                return 'Génie Informatique';
            }
        } catch (\Throwable $e) {
        }

        return 'Inconnue';
    }
}
