<?php

namespace App\Http\Controllers;

use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Jury;
use App\Models\Projet;
use App\Models\Salle;
use App\Models\Soutenance;
use App\Services\AssignmentService;
use App\Services\HistoryService;
use App\Services\PdfExportService;
use App\Services\WordExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function __construct(
        protected AssignmentService $assignmentService,
        protected PdfExportService $pdfExportService,
        protected HistoryService $historyService,
    ) {}

    // DASHBOARD ────────────────────────────────────────────────────────

    public function dashboard()
    {
        $latestPlanning = $this->historyService->latest('planning');

        // Invalidate the snapshot if the DB was wiped (no students or no professors).
        $dbIsEmpty = Etudiant::count() === 0 || Enseignant::count() === 0;
        if ($dbIsEmpty) {
            $latestPlanning = null;
        }

        $stats = [
            'total_etudiants'   => Etudiant::count(),
            'total_enseignants' => Enseignant::count(),
            'total_soutenances' => $latestPlanning?->soutenances_count ?? 0,
        ];

        $rawFiliere = Etudiant::selectRaw('filiere, COUNT(*) as total')->groupBy('filiere')->get();
        $parFiliereData = [];
        foreach ($rawFiliere as $item) {
            $f = mb_strtoupper($item->filiere ?? '', 'UTF-8');
            $fShort = 'Autre';
            if (str_contains($f, 'TDIA') || str_contains($f, 'TRANSFORM') || str_contains($f, 'ARTIFIC')) {
                $fShort = 'TDIA';
            } elseif (str_contains($f, 'ID') || str_contains($f, 'INGENIERIE') || str_contains($f, 'DONNÉES') || str_contains($f, 'DONNEES')) {
                $fShort = 'ID';
            } elseif (str_contains($f, 'GI') || str_contains($f, 'GENIE') || str_contains($f, 'GÉNIE')) {
                $fShort = 'GI';
            }
            $parFiliereData[$fShort] = ($parFiliereData[$fShort] ?? 0) + $item->total;
        }
        $parFiliere = collect($parFiliereData);

        $parEncadrant = Enseignant::withCount('projets')
            ->having('projets_count', '>', 0)
            ->get();

        $parJury = Enseignant::withCount('jurys')
            ->having('jurys_count', '>', 0)
            ->get();

        return view('dashboard.index', compact('stats', 'parFiliere', 'parEncadrant', 'parJury', 'latestPlanning'));
    }

    // AFFECTATION ──────────────────────────────────────────────────────

    public function showAffectation()
    {
        $projets = $this->canonicalProjects();
        $enseignants = Enseignant::all();
        $etudiants = Etudiant::all();
        $snapshots = $this->historyService->all('affectation');

        $lastEtudiant = Etudiant::latest()->first();
        $lastEnseignant = Enseignant::latest()->first();
        $latestSnapshot = $this->historyService->latest('affectation');
        $hasSnapshot = $latestSnapshot
            && ($lastEtudiant || $lastEnseignant)
            && $latestSnapshot->created_at > ($lastEtudiant?->created_at ?? now()->subCentury())
            && $latestSnapshot->created_at > ($lastEnseignant?->created_at ?? now()->subCentury());

        return view('affectation.index', compact('projets', 'enseignants', 'etudiants', 'snapshots', 'hasSnapshot'));
    }

    public function runAffectation()
    {
        Projet::query()->update(['encadrant_id' => null]);

        $this->assignmentService->assignStudentsToEncadrants();

        $projets = $this->canonicalProjects();
        $data = $projets->map(function ($p) {
            $e1 = $p->etudiant;
            $e2 = $p->etudiant2;
            $bg = PdfExportService::applyFiliereColor($e1->filiere ?? '');

            return [
                'etu_nom' => $e1?->nom,
                'etu_prenom' => $e1?->prenom,
                'etudiant' => $e1 ? ($e1->nom . ' ' . $e1->prenom) : '',
                'etu2_nom' => $e2?->nom,
                'etu2_prenom' => $e2?->prenom,
                'etudiant2' => $e2 ? ($e2->nom . ' ' . $e2->prenom) : '',
                'filiere' => $e1?->filiere,
                'bg' => $bg,
                'encadrant' => $p->encadrant
                    ? ($p->encadrant->nom . ' ' . $p->encadrant->prenom)
                    : 'Non assigné',
                'enc_nom' => $p->encadrant?->nom ?? '',
                'enc_prenom' => $p->encadrant?->prenom ?? '',
            ];
        })->values()->toArray();

        $etudiantsCount = $projets->sum(fn($p) => 1 + ($p->etudiant2_id ? 1 : 0));

        $this->historyService->save('affectation', [
            'label' => 'Affectation du ' . now()->format('d/m/Y à H:i'),
            'data' => $data,
            'count' => $etudiantsCount,
        ]);

        return redirect()->route('affectation.index')
            ->with('success', $etudiantsCount . ' étudiants affectés avec succès.');
    }

    public function affectationHistory()
    {
        $snapshots = $this->historyService->all('affectation');

        return view('affectation.history', compact('snapshots'));
    }

    // PLANNING ─────────────────────────────────────────────────────────

    public function runAlgorithm(Request $request)
    {
        try {
            $validated = $request->validate([
                'date_debut'    => 'required|date',
                'nb_jours'      => 'required|integer|min:1|max:30',
                'creneau_duree' => 'nullable|integer|in:30,45,60,90,120',
                'matin_actif'   => 'nullable|boolean',
                'matin_debut'   => 'nullable|date_format:H:i|required_if:matin_actif,1',
                'matin_fin'     => 'nullable|date_format:H:i|required_if:matin_actif,1|after:matin_debut',
                'aprem_actif'   => 'nullable|boolean',
                'aprem_debut'   => 'nullable|date_format:H:i|required_if:aprem_actif,1',
                'aprem_fin'     => 'nullable|date_format:H:i|required_if:aprem_actif,1|after:aprem_debut',
            ]);

            $dateDebut  = $validated['date_debut'];
            $nbJours    = (int) $validated['nb_jours'];
            $duree      = (int) ($validated['creneau_duree'] ?? 60);
            $slotRanges = $this->buildSlotRanges($request, $duree);

            if (empty($slotRanges)) {
                return redirect()->route('affectation.index')
                    ->with('error', 'Veuillez activer au moins une plage horaire (matinée ou après-midi) avec des heures valides.');
            }

            DB::transaction(function () use ($dateDebut, $nbJours, $slotRanges) {
                DB::table('jury_enseignant')->delete();
                Soutenance::query()->delete();
                Jury::query()->delete();
                Creneau::query()->delete();

                // Encadrant assignment is done separately via the Affectation workflow.
                // We trust the existing encadrant_id values on Projet rows.
                $this->assignmentService->planifierCreneaux($dateDebut, $nbJours, $slotRanges);
                $this->assignmentService->runAssignment();
                $this->assignmentService->buildJuries();
            });

            // Construire le snapshot
            $soutenances = Soutenance::with([
                'projet.etudiant',
                'projet.etudiant2',
                'projet.encadrant',
                'jury.enseignants',
                'creneau',
                'salleRelation',
            ])->get();

            $data = $soutenances->map(function ($s) {
                $jury = $s->jury?->enseignants ?? collect();
                $president = $jury->where('pivot.role', 'President')->first();
                $rapporteurs = $jury->where('pivot.role', 'Rapporteur');

                return [
                    'id' => $s->id,
                    'etudiant_nom' => $s->projet?->etudiant?->nom,
                    'etudiant_prenom' => $s->projet?->etudiant?->prenom,
                    'etudiant2_nom' => $s->projet?->etudiant2?->nom,
                    'etudiant2_prenom' => $s->projet?->etudiant2?->prenom,
                    'titre' => $s->projet?->sujet ?? $s->projet?->titre,
                    'filiere' => $s->projet?->etudiant?->filiere,
                    'encadrant' => $s->projet?->encadrant
                        ? ('Pr. ' . $s->projet->encadrant->nom . ' ' . $s->projet->encadrant->prenom)
                        : 'N/A',
                    'president' => $president
                        ? ('Pr. ' . $president->nom . ' ' . $president->prenom)
                        : 'N/A',
                    'examinateurs' => $rapporteurs->map(fn($r) => 'Pr. ' . $r->nom . ' ' . $r->prenom)->values()->toArray(),
                    'date' => $s->creneau?->date?->format('d/m/Y'),
                    'date_sort' => $s->creneau?->date?->format('Y-m-d'),
                    'heure_debut' => $s->creneau?->heure_debut?->format('H:i'),
                    'heure_fin' => $s->creneau?->heure_fin?->format('H:i'),
                    'salle' => $s->salleRelation?->nom ?? 'N/A',
                ];
            })->sortBy([
                ['date_sort', 'asc'],
                ['heure_debut', 'asc'],
            ])->values()->toArray();

            $totalEtudiants = Etudiant::count();
            $scheduledIds = $this->scheduledStudentIds();
            $affectes = count($scheduledIds);
            $nonAffectes = max(0, $totalEtudiants - $affectes);
            $pct = $totalEtudiants > 0 ? round(($affectes / $totalEtudiants) * 100) : 0;
            $totalProjects = $this->canonicalProjects()->count();
            $scheduledProjects = Soutenance::distinct('projet_id')->count('projet_id');

            $this->historyService->save('planning', [
                'label' => 'Planning du ' . now()->format('d/m/Y à H:i'),
                'data' => $data,
                'count' => $soutenances->count(),
            ]);

            if ($pct < 100) {
                $nbDates = Creneau::get()
                    ->groupBy(fn($c) => $c->date->format('Y-m-d'))
                    ->count();

                $nbSalles = Salle::count();
                $nbCreneauxParJour = Creneau::select('heure_debut')
                    ->distinct()
                    ->count();
                $capaciteMax =
                    $nbDates *
                    $nbCreneauxParJour *
                    app(AssignmentService::class)
                    ->maxSoutenancesPerSlot();

                $etudiantsNonAffectes = Etudiant::whereNotIn('id', $scheduledIds)->get();

                $diagnostic = [
                    'pct' => $pct,
                    'total' => $totalEtudiants,
                    'affectes' => $affectes,
                    'non_affectes' => $nonAffectes,
                    'total_projets' => $totalProjects,
                    'projets_planifies' => $scheduledProjects,
                    'projets_non_planifies' => max(0, $totalProjects - $scheduledProjects),
                    'nb_salles' => $nbSalles,
                    'salles_recommandees' => 5,
                    'salles_manquantes' => max(0, 5 - $nbSalles),
                    'nb_dates' => $nbDates,
                    'capacite_max' => $capaciteMax,
                    'manque_capacite' => max(0, $totalProjects - $capaciteMax),
                    'etudiants_manquants' => $etudiantsNonAffectes->map(function ($e) {
                        $projet = $this->projectForStudent($e);

                        return [
                            'nom' => $e->nom,
                            'prenom' => $e->prenom,
                            'filiere' => $e->filiere,
                            'encadrant' => $projet?->encadrant
                                ? ($projet->encadrant->nom . ' ' . $projet->encadrant->prenom)
                                : 'Non assigné',
                        ];
                    })->toArray(),
                ];

                session(['conformite_diagnostic' => $diagnostic]);
                Storage::put('conformite_diagnostic.json', json_encode($diagnostic));

                // Build a recommendation based on the actually-observed scheduling
                // throughput. Falls back to a theoretical capacity estimate if no
                // project at all could be scheduled.
                $recommendedDays = $this->recommendDays(
                    $totalProjects,
                    $scheduledProjects,
                    $nbJours,
                    $nbCreneauxParJour ?: count($slotRanges),
                    max(1, $nbSalles)
                );

                $recommendation = sprintf(
                    "Avec %d projet(s) à planifier et la configuration actuelle (%d jours, %d créneaux/jour, %d salle(s)), seuls %d%% des étudiants ont pu être placés. " .
                        "D'après la cadence observée, il est recommandé d'allouer environ %d jour(s) pour respecter pleinement toutes les contraintes.",
                    $totalProjects,
                    $nbJours,
                    $nbCreneauxParJour ?: count($slotRanges),
                    max(1, $nbSalles),
                    $pct,
                    $recommendedDays
                );

                session()->flash('planning_recommendation', $recommendation);

                return redirect()->route('planning.results')
                    ->with('warning', "⚠️ Seulement {$pct}% des étudiants ont pu être planifiés ({$affectes}/{$totalEtudiants}). Consultez le <a href=\"" . route('conformite.index') . '" class="alert-link fw-bold">Contrôle de Conformité</a> pour plus de détails.');
            }

            Storage::delete('conformite_diagnostic.json');
            Session::forget('conformite_diagnostic');

            return redirect()->route('planning.results')
                ->with('success', "✓ {$pct}% des étudiants affectés. Aucun conflit d'horaire détecté.");
        } catch (\Exception $e) {
            return redirect()->route('affectation.index')
                ->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    /**
     * Build the array of "HH:MM" => "HH:MM" slot ranges from the request,
     * splitting each enabled period (matinée / après-midi) into chunks
     * of `$duree` minutes. Returns an empty array when no period is enabled.
     *
     * @return array<string,string>
     */
    private function buildSlotRanges(Request $request, int $duree): array
    {
        $ranges = [];

        if ($request->boolean('matin_actif') && $request->filled('matin_debut') && $request->filled('matin_fin')) {
            foreach ($this->splitInterval($request->input('matin_debut'), $request->input('matin_fin'), $duree) as $start => $end) {
                $ranges[$start] = $end;
            }
        }

        if ($request->boolean('aprem_actif') && $request->filled('aprem_debut') && $request->filled('aprem_fin')) {
            foreach ($this->splitInterval($request->input('aprem_debut'), $request->input('aprem_fin'), $duree) as $start => $end) {
                $ranges[$start] = $end;
            }
        }

        ksort($ranges);

        return $ranges;
    }

    /**
     * Split a [start, end] window into consecutive sub-intervals of $duree minutes.
     *
     * @return array<string,string>
     */
    private function splitInterval(string $startTime, string $endTime, int $duree): array
    {
        $ranges = [];

        try {
            $start = \DateTimeImmutable::createFromFormat('H:i', $startTime);
            $end   = \DateTimeImmutable::createFromFormat('H:i', $endTime);
        } catch (\Throwable) {
            return [];
        }

        if (!$start || !$end || $start >= $end || $duree <= 0) {
            return [];
        }

        $cursor = $start;
        while (true) {
            $next = $cursor->modify("+{$duree} minutes");
            if ($next > $end) {
                break;
            }
            $ranges[$cursor->format('H:i')] = $next->format('H:i');
            $cursor = $next;
        }

        return $ranges;
    }

    /**
     * Recommend a number of days given the achieved scheduling throughput.
     * Extrapolates from the observed (scheduled / requested) ratio when at
     * least one project landed; otherwise falls back to a theoretical
     * capacity-based estimate.
     */
    private function recommendDays(
        int $totalProjects,
        int $scheduledProjects,
        int $nbJoursDemandes,
        int $slotsParJour,
        int $nbSalles
    ): int {
        $nbJoursDemandes = max(1, $nbJoursDemandes);
        $slotsParJour    = max(1, $slotsParJour);
        $nbSalles        = max(1, $nbSalles);

        if ($scheduledProjects > 0) {
            $cadenceParJour = $scheduledProjects / $nbJoursDemandes;
            $estimation = (int) ceil($totalProjects / max(0.1, $cadenceParJour));
        } else {
            // No project landed at all — fall back to theoretical capacity,
            // discounted by 30% to account for the rest-rule and jury constraints.
            $capaciteRealistePerDay = max(1, (int) floor($slotsParJour * $nbSalles * 0.7));
            $estimation = (int) ceil($totalProjects / $capaciteRealistePerDay);
        }

        return max($nbJoursDemandes + 1, $estimation);
    }

    public function showResults()
    {
        $snapshot = $this->historyService->latest('planning');

        $lastEtudiant = Etudiant::latest()->first();
        $lastEnseignant = Enseignant::latest()->first();

        if ($snapshot && $lastEtudiant === null) {
            $snapshot = null;
        }

        if ($snapshot) {
            $isStale = ($lastEtudiant && $snapshot->created_at < $lastEtudiant->created_at)
                || ($lastEnseignant && $snapshot->created_at < $lastEnseignant->created_at);

            if ($isStale) {
                $snapshot = null;
            }
        }

        $soutenances = $snapshot ? collect($snapshot->data) : collect();
        $salles = Salle::all();
        $enseignants = Enseignant::all();

        return view('planning.results', compact('soutenances', 'snapshot', 'salles', 'enseignants'));
    }

    public function planningHistory()
    {
        $snapshots = $this->historyService->all('planning');

        return view('planning.history', compact('snapshots'));
    }

    public function downloadSnapshot(string $type, string $id, string $format)
    {
        $snapshot = $this->historyService->find($type, $id);

        if (!$snapshot) {
            abort(404);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView("pdf.{$type}_snapshot", [
                'snapshot' => $snapshot,
                'rows' => collect($snapshot->data),
            ]);

            return $pdf->download("{$type}_{$id}.pdf");
        }

        if ($format === 'word') {
            return app(WordExportService::class)
                ->downloadSnapshot($snapshot, $type);
        }

        abort(404);
    }

    // Helpers ───────────────────────────────────────────────────

    private function canonicalProjects()
    {
        $coveredAsEtudiant2 = Projet::whereNotNull('etudiant2_id')
            ->pluck('etudiant2_id')
            ->unique()
            ->values()
            ->toArray();

        return Projet::with(['etudiant', 'etudiant2', 'encadrant'])
            ->whereNotIn('etudiant_id', $coveredAsEtudiant2)
            ->get();
    }

    private function scheduledStudentIds(): array
    {
        return Projet::whereHas('soutenance')
            ->get()
            ->flatMap(fn($p) => array_filter([$p->etudiant_id, $p->etudiant2_id]))
            ->unique()
            ->values()
            ->toArray();
    }

    private function projectForStudent(Etudiant $etudiant): ?Projet
    {
        return Projet::with('encadrant')->where('etudiant2_id', $etudiant->id)->first()
            ?? Projet::with('encadrant')->where('etudiant_id', $etudiant->id)->first();
    }
}
