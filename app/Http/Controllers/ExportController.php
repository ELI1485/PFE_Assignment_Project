<?php

namespace App\Http\Controllers;

use App\Models\Enseignant;
use App\Services\HistoryService;
use App\Services\WordExportService;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    public function __construct(
        protected WordExportService $wordService,
        protected HistoryService $historyService
    ) {}

    /** Download the current live planning as PDF */
    public function downloadPlanning()
    {
        $snapshot = $this->historyService->latest('planning');
        if (! $snapshot) {
            return back()->with('error', 'Aucun planning généré.');
        }

        // Dynamic academic year: September+ = current/next, else previous/current.
        $year = now()->month >= 9 ? now()->year : now()->year - 1;
        $anneeUniversitaire = $year.'/'.($year + 1);

        $pdf = Pdf::loadView('pdf.planning_snapshot', [
            'snapshot' => $snapshot,
            'rows' => collect($snapshot->data),
            'anneeUniversitaire' => $anneeUniversitaire,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('planning_'.now()->format('d-m-Y').'.pdf');
    }

    /** Download the current live planning as Word */
    public function downloadPlanningWord()
    {
        return $this->wordService->downloadLivePlanning();
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
