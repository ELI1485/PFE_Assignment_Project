<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;

class VerificationController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function index()
    {
        $affectationData = $this->verificationService->checkAffectations();
        $planningData = $this->verificationService->checkPlannings();

        return view('verification.index', [
            'moyenneEncadrement' => $affectationData['moyenne'],
            'affectationAnomalies' => $affectationData['anomalies'],
            'affectationRules' => $affectationData['rules'],
            'planningAnomalies' => $planningData['anomalies'],
            'planningRules' => $planningData['rules'],
            'expectedJurySize' => $planningData['expected_jury_size'],
            'soutenancesCount' => $planningData['soutenances_count'],
            'encadrementMin' => VerificationService::ENCADREMENT_MIN,
            'encadrementMax' => VerificationService::ENCADREMENT_MAX,
        ]);
    }
}
