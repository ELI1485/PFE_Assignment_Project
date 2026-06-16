<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Enseignant;
use App\Services\HistoryService;
use App\Services\WordExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        protected WordExportService $wordService,
        protected HistoryService $historyService
    ) {}

    /**
     * Resolve the filière-ID filter from the request.
     *
     * @return array<int>
     */
    private function filiereFilter(Request $request): array
    {
        return collect($request->input('filieres', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /** Resolve the session label from the request (defaults to "Première Session"). */
    private function sessionName(Request $request): string
    {
        $session = trim((string) $request->input('session_name', ''));

        return $session !== '' ? $session : 'Première Session';
    }

    /** Download the current live planning as PDF (optionally filtered by filière). */
    public function downloadPlanning(Request $request)
    {
        $snapshot = $this->historyService->latest('planning');
        if (! $snapshot) {
            return back()->with('error', 'Aucun planning généré.');
        }

        // Dynamic academic year: September+ = current/next, else previous/current.
        $year = now()->month >= 9 ? now()->year : now()->year - 1;
        $anneeUniversitaire = $year.'/'.($year + 1);

        $filiereIds = $this->filiereFilter($request);
        $rows = collect($snapshot->data);
        if (! empty($filiereIds)) {
            $rows = $rows->filter(fn ($r) => in_array((int) ($r['filiere_id'] ?? 0), $filiereIds, true))->values();
        }

        $pdf = Pdf::loadView('pdf.planning_snapshot', [
            'snapshot' => $snapshot,
            'rows' => $rows,
            'anneeUniversitaire' => $anneeUniversitaire,
            'schoolName' => Configuration::get('school_name'),
            'departmentName' => Configuration::get('department_name'),
            'sessionName' => $this->sessionName($request),
            'logoSrc' => Configuration::logoDataUri(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('planning_'.now()->format('d-m-Y').'.pdf');
    }

    /** Download the current live planning as Word (optionally filtered by filière). */
    public function downloadPlanningWord(Request $request)
    {
        return $this->wordService->downloadLivePlanning(
            $this->filiereFilter($request),
            $this->sessionName($request)
        );
    }

    /** Download the current live affectation as PDF */
    public function downloadAffectation()
    {
        $snapshot = $this->historyService->latest('affectation');
        if (! $snapshot) {
            return back()->with('error', 'Aucune affectation générée.');
        }

        $pdf = Pdf::loadView('pdf.affectation_snapshot', [
            'snapshot' => $snapshot,
            'rows' => collect($snapshot->data),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('affectation_'.now()->format('d-m-Y').'.pdf');
    }

    /** Download the current live affectation as Word */
    public function downloadAffectationWord()
    {
        return $this->wordService->downloadLiveAffectation();
    }

    /** Download supervision report as PDF (legacy route) */
    public function downloadSupervision()
    {
        $enseignants = Enseignant::with(['projets.etudiant.filiere', 'projets.etudiant2'])->get();
        $pdf = Pdf::loadView('pdf.supervision', compact('enseignants'));

        return $pdf->download('supervision_'.now()->format('d-m-Y').'.pdf');
    }
}
