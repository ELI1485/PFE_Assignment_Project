<?php

namespace App\Http\Controllers;



use App\Services\PvService;
use App\Repositories\SoutenanceRepository;

class PvController extends Controller
{
    protected PvService $pvService;
    protected SoutenanceRepository $soutenanceRepository;

    public function __construct(PvService $pvService, SoutenanceRepository $soutenanceRepository)
    {
        $this->pvService = $pvService;
        $this->soutenanceRepository = $soutenanceRepository;
    }

    public function index()
    {
        // 1. Fetch all soutenances (Eager loaded for performance!)
        $soutenances = $this->soutenanceRepository
            ->findAll()
            ->sortBy([
                ['creneau.date', 'asc'],
                ['creneau.heure_debut', 'asc'],
            ])->values();

        // 2. Return the HTML Blade View
        return view('pvs.index', compact('soutenances'));
    }

    public function downloadPvsArchive()
    {
        $zipFilePath = $this->pvService->organizePvsByTeacher();
        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    public function downloadSinglePv(int $id)
    {
        $soutenance = $this->soutenanceRepository->findById($id);
        $savePath = $this->pvService->generatePvForStudent($soutenance);
        return response()->download($savePath)->deleteFileAfterSend(true);
    }
}
