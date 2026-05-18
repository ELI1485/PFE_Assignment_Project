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
        $planningAnomalies = $this->verificationService->checkPlannings();

        return view('verification.index', [
            'moyenneEncadrement' => $affectationData['moyenne'],
            'affectationAnomalies' => $affectationData['anomalies'],
            'planningAnomalies' => $planningAnomalies,
        ]);
    }
}
