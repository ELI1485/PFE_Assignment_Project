<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ConformiteController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PvController;
use App\Http\Controllers\SalleController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// ─── Dashboard ────────────────────────────────────────────────────────────────
Route::get('/', [AssignmentController::class, 'dashboard'])->name('dashboard');

// ─── Import ───────────────────────────────────────────────────────────────────
Route::get('/import', [ImportController::class, 'showForm'])->name('import.form');
Route::post('/import/unified', [ImportController::class, 'importUnified'])->name('import.unified');
Route::get('/import/template', [ImportController::class, 'downloadTemplate'])->name('import.template');
Route::post('/import/reset-database', [ImportController::class, 'resetDatabase'])->name('import.reset-db');

// ─── Affectation des Encadrants ───────────────────────────────────────────────
Route::get('/affectation', [AssignmentController::class, 'showAffectation'])->name('affectation.index');
Route::post('/affectation/run', [AssignmentController::class, 'runAffectation'])->name('affectation.run');
Route::get('/affectation/history', [AssignmentController::class, 'affectationHistory'])->name('affectation.history');

// ─── Planning des Soutenances ─────────────────────────────────────────────────
Route::post('/planning/run', [AssignmentController::class, 'runAlgorithm'])->name('planning.run');
Route::get('/planning/results', [AssignmentController::class, 'showResults'])->name('planning.results');
Route::get('/planning/history', [AssignmentController::class, 'planningHistory'])->name('planning.history');

Route::get('/planning', fn () => redirect()->route('planning.results'))->name('planning.index');

// ─── Snapshot Downloads ───────────────────────────────────────────────────────
Route::get('/snapshot/{type}/{id}/{format}', [AssignmentController::class, 'downloadSnapshot'])
    ->name('snapshot.download')
    ->where(['type' => 'affectation|planning', 'format' => 'pdf|word']);

// ─── Exports (current live data) ──────────────────────────────────────────────
Route::get('/export/planning/pdf', [ExportController::class, 'downloadPlanning'])->name('export.planning');
Route::get('/export/planning/word', [ExportController::class, 'downloadPlanningWord'])->name('export.planning.word');
Route::get('/export/affectation/pdf', [ExportController::class, 'downloadAffectation'])->name('export.affectation');
Route::get('/export/affectation/word', [ExportController::class, 'downloadAffectationWord'])->name('export.affectation.word');
Route::get('/export/supervision', [ExportController::class, 'downloadSupervision'])->name('export.supervision');

// ─── PV Generation ────────────────────────────────────────────────────────────
Route::get('/generation-pvs', fn () => view('pvs.intro'))->name('pv.intro');
Route::get('/pv', [PvController::class, 'index'])->name('pv.index');
Route::get('/pv/download/{id}', [PvController::class, 'downloadSinglePv'])->name('pv.download');
Route::get('/pv/archive/download-all', [PvController::class, 'downloadPvsArchive'])->name('pv.downloadAll');

// ─── Salles ───────────────────────────────────────────────────────────────────
Route::get('/salles', [SalleController::class, 'index'])->name('salles.index');
Route::post('/salles', [SalleController::class, 'store'])->name('salles.store');
Route::delete('/salles/{id}', [SalleController::class, 'destroy'])->name('salles.destroy');

// Vérification des contraintes
Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');

// Contrôle de Conformité
Route::get('/conformite', [ConformiteController::class, 'index'])->name('conformite.index');
