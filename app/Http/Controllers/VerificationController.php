<?php

namespace App\Http\Controllers;

use App\Services\HistoryService;
use App\Services\VerificationService;

class VerificationController extends Controller
{
    public function __construct(
        protected VerificationService $verificationService,
        protected HistoryService $historyService,
    ) {}

    public function index()
    {
        $affectationData = $this->verificationService->checkAffectations();
        $planningData = $this->verificationService->checkPlannings();

        // Pull the active planning's persisted config so the audit page
        // can show concrete details (start date, days, excluded dates)
        // alongside the abstract rules being checked.
        $latestPlanning = $this->historyService->latest('planning');
        $planningConfig = $latestPlanning && is_array($latestPlanning->config ?? null)
            ? $latestPlanning->config
            : null;

        return view('verification.index', [
            'moyenneEncadrement' => $affectationData['moyenne'],
            'affectationRecommendations' => $affectationData['recommendations'],
            'affectationRules' => $affectationData['rules'],
            'planningAnomalies' => $planningData['anomalies'],
            'planningRules' => $planningData['rules'],
            'expectedJurySize' => $planningData['expected_jury_size'],
            'soutenancesCount' => $planningData['soutenances_count'],
            'encadrementMin' => $affectationData['encadrementMin'],
            'encadrementMax' => $affectationData['encadrementMax'],
            'planningConfig' => $planningConfig,
        ]);
    }
}
