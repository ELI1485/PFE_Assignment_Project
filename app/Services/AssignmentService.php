<?php

namespace App\Services;

use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Jury;
use App\Models\Projet;
use App\Models\Salle;
use App\Models\Soutenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentService
{
    private const DEFAULT_ROOMS = [
        'S4A',
        'S5A',
        'S16A',
        'S17A',
        'AMPHI A',
    ];

    public function __construct() {}

    public function assignStudentsToEncadrants(): void
    {
        $enseignants = Enseignant::inRandomOrder()->get();
        if ($enseignants->isEmpty()) {
            return;
        }

        $loads = $enseignants
            ->mapWithKeys(fn(Enseignant $enseignant) => [
                $enseignant->id => Projet::where('encadrant_id', $enseignant->id)->count(),
            ])
            ->toArray();

        $projets = Projet::all();
        $assignedEtudiantIds = $projets
            ->flatMap(fn(Projet $projet) => [$projet->etudiant_id, $projet->etudiant2_id])
            ->filter()
            ->unique()
            ->values();

        foreach (Etudiant::orderBy('id')->get() as $etudiant) {
            if ($assignedEtudiantIds->contains($etudiant->id)) {
                continue;
            }

            $encadrant = $this->leastLoadedProfessor($enseignants, $loads);
            Projet::create([
                'titre' => 'Projet PFE - ' . trim($etudiant->nom . ' ' . $etudiant->prenom),
                'etudiant_id' => $etudiant->id,
                'encadrant_id' => $encadrant->id,
            ]);

            $loads[$encadrant->id]++;
        }

        $coveredAsEtudiant2 = $projets->pluck('etudiant2_id')->filter()->unique()->values()->toArray();
        $projetsSansEncadrant = Projet::whereNull('encadrant_id')
            ->whereNotIn('etudiant_id', $coveredAsEtudiant2)
            ->orderBy('id')
            ->get();

        foreach ($projetsSansEncadrant as $projet) {
            $encadrant = $this->leastLoadedProfessor($enseignants, $loads);
            $projet->update(['encadrant_id' => $encadrant->id]);
            $loads[$encadrant->id]++;
        }
    }

    /**
     * Generate Creneau records dynamically based on the user-provided
     * start date, number of working days and slot ranges (HH:MM => HH:MM).
     *
     * @param string                $dateDebut  Start date (YYYY-MM-DD)
     * @param int                   $nbJours    Number of working days (weekends skipped)
     * @param array<string,string>  $slotRanges Map of "HH:MM" start => "HH:MM" end
     */
    public function planifierCreneaux(string $dateDebut, int $nbJours = 4, array $slotRanges = []): void
    {
        $this->ensureSallesExist();

        if (empty($slotRanges)) {
            $slotRanges = $this->defaultSlotRanges();
        }

        // Sort slot ranges chronologically by their start time so the
        // dynamic slotOrder() helper stays consistent across days.
        ksort($slotRanges);

        $coveredAsEtudiant2 = Projet::whereNotNull('etudiant2_id')
            ->pluck('etudiant2_id')
            ->unique()
            ->values()
            ->toArray();

        $hasProjets = Projet::whereNotNull('encadrant_id')
            ->whereNotIn('etudiant_id', $coveredAsEtudiant2)
            ->exists();

        if (!$hasProjets) {
            return;
        }

        $capacite = $this->maxSoutenancesPerSlot();
        $nbJours = max(1, $nbJours);

        foreach ($this->workingDates($dateDebut, $nbJours) as $date) {
            foreach ($slotRanges as $debut => $fin) {
                Creneau::updateOrCreate(
                    [
                        'date'        => $date,
                        'heure_debut' => $debut,
                    ],
                    [
                        'heure_fin' => $fin,
                        'capacite' => $capacite,
                    ]
                );
            }
        }
    }

    public function runAssignment(int $nbJurys = 3): void
    {
        $this->ensureSallesExist();

        $slotOrder = $this->slotOrder();
        $creneaux = $this->officialCreneaux($slotOrder);
        $allProfessors = Enseignant::orderBy('id')->get();
        $salles = Salle::orderBy('id')->get();

        if ($allProfessors->count() < 3) {
            Log::warning('Planning impossible: at least 3 professors are required.');
            return;
        }

        if ($creneaux->isEmpty() || $salles->isEmpty()) {
            Log::warning('Planning impossible: official slots or rooms are missing.', [
                'creneaux' => $creneaux->count(),
                'salles' => $salles->count(),
            ]);
            return;
        }

        $projects = $this->professorDrivenProjectOrder($this->projectsToSchedule());
        if (empty($projects)) {
            return;
        }

        $state = $this->buildPlanningState($allProfessors, $salles, $slotOrder);

        foreach ($projects as $projet) {
            $package = $this->findBestPackage(
                $projet,
                $creneaux,
                $salles,
                $allProfessors,
                $state,
                $slotOrder,
                $nbJurys
            );

            if (!$package) {
                Log::warning('Project skipped because no complete Java-style planning package is feasible.', [
                    'projet_id' => $projet->id,
                    'encadrant_id' => $projet->encadrant_id,
                ]);
                continue;
            }

            $this->persistPackage($projet, $package);
            $this->markPackageInState($projet, $package, $state, $slotOrder);

            Log::info('Soutenance created atomically with balanced professor-driven scheduling.', [
                'projet_id' => $projet->id,
                'creneau_id' => $package['creneau']->id,
                'salle_id' => $package['salle']->id,
                'president_id' => $projet->encadrant_id,
                'rapporteur_ids' => array_map(
                    fn(Enseignant $rapporteur) => $rapporteur->id,
                    $package['rapporteurs']
                ),
            ]);
        }
    }

    public function buildJuries(): void
    {
        $invalidCount = Soutenance::whereNull('jury_id')->count();
        if ($invalidCount > 0) {
            Log::warning('Deleting invalid soutenances without jury_id after atomic scheduling refactor.', [
                'count' => $invalidCount,
            ]);
            Soutenance::whereNull('jury_id')->delete();
        }

        Jury::doesntHave('enseignants')->delete();
    }

    public function maxSoutenancesPerSlot(): int
    {
        $this->ensureSallesExist();

        return max(1, Salle::count());
    }

    private function leastLoadedProfessor(Collection $enseignants, array $loads): Enseignant
    {
        $minLoad = min(array_map(
            fn(Enseignant $enseignant) => $loads[$enseignant->id] ?? 0,
            $enseignants->all()
        ));

        $candidates = $enseignants
            ->filter(fn(Enseignant $enseignant) => ($loads[$enseignant->id] ?? 0) === $minLoad)
            ->shuffle()
            ->values();

        return $candidates->first();
    }

    private function projectsToSchedule(): Collection
    {
        $coveredAsEtudiant2 = Projet::whereNotNull('etudiant2_id')
            ->pluck('etudiant2_id')
            ->unique()
            ->values()
            ->toArray();

        return Projet::whereNotNull('encadrant_id')
            ->whereDoesntHave('soutenance')
            ->whereNotIn('etudiant_id', $coveredAsEtudiant2)
            ->with(['etudiant', 'etudiant2', 'encadrant'])
            ->get();
    }

    private function professorDrivenProjectOrder(Collection $projects): array
    {
        $groups = [];

        foreach ($projects as $projet) {
            $groups[$projet->encadrant_id][] = $projet;
        }

        foreach ($groups as &$group) {
            usort($group, fn(Projet $a, Projet $b) => $this->compareProjects($a, $b));
        }
        unset($group);

        uasort($groups, function (array $a, array $b) {
            $encadrantA = $a[0]->encadrant_id ?? 0;
            $encadrantB = $b[0]->encadrant_id ?? 0;

            return [
                -count($a),
                $encadrantA,
            ] <=> [
                -count($b),
                $encadrantB,
            ];
        });

        $max = empty($groups) ? 0 : max(array_map('count', $groups));
        $ordered = [];

        for ($round = 0; $round < $max; $round++) {
            foreach ($groups as $group) {
                if (isset($group[$round])) {
                    $ordered[] = $group[$round];
                }
            }
        }

        return $ordered;
    }

    private function compareProjects(Projet $a, Projet $b): int
    {
        return [
            $this->normalizeFiliere($a->etudiant?->filiere ?? ''),
            $a->id,
        ] <=> [
            $this->normalizeFiliere($b->etudiant?->filiere ?? ''),
            $b->id,
        ];
    }

    private function findBestPackage(
        Projet $projet,
        Collection $creneaux,
        Collection $salles,
        Collection $allProfessors,
        array $state,
        array $slotOrder,
        int $nbJurys = 3
    ): ?array {
        $encadrant = $projet->encadrant;
        if (!$encadrant) {
            return null;
        }

        $nbRapporteurs = max(1, $nbJurys - 1);
        $minDailyLoad = PHP_INT_MAX;
        $minSlotLoad = PHP_INT_MAX;
        $best = null;

        foreach ($creneaux as $creneau) {
            // Cheap pruning first: drop creneaux whose encadrant daily load
            // is already worse than the current best, before doing any
            // schedule scan.
            $date = $creneau->date->format('Y-m-d');
            $encadrantDailyLoad = $state['profDailyCount'][$encadrant->id][$date] ?? 0;
            if ($encadrantDailyLoad > $minDailyLoad) {
                continue;
            }

            $slotIndex = $this->getSlotIndex($creneau, $slotOrder);
            if ($slotIndex === null) {
                continue;
            }

            if (!$this->isProfessorAvailable($encadrant->id, $date, $slotIndex, $state)) {
                continue;
            }

            $slotLoad = $this->slotLoad($date, $slotIndex, $state);
            $isBetter = $encadrantDailyLoad < $minDailyLoad
                || ($encadrantDailyLoad === $minDailyLoad && $slotLoad < $minSlotLoad);

            if (!$isBetter) {
                continue;
            }

            $salle = $this->getFreeRoom($date, $slotIndex, $salles, $state);
            if (!$salle) {
                continue;
            }

            $available = $allProfessors
                ->filter(fn(Enseignant $professor) => $professor->id !== $encadrant->id
                    && $this->isProfessorAvailable($professor->id, $date, $slotIndex, $state))
                ->values();

            if ($available->count() < $nbRapporteurs) {
                continue;
            }

            $rapporteurs = $this->pickBestJury($encadrant, $available, $state['profJuryCount'], $nbRapporteurs);
            if ($rapporteurs === null) {
                continue;
            }

            $best = [
                'creneau' => $creneau,
                'salle' => $salle,
                'rapporteurs' => $rapporteurs,
            ];
            $minDailyLoad = $encadrantDailyLoad;
            $minSlotLoad = $slotLoad;

            // (0,0) is the absolute optimum — nothing can beat it, so we can
            // exit early. This dramatically speeds up generation when the
            // encadrant has free days available.
            if ($minDailyLoad === 0 && $minSlotLoad === 0) {
                break;
            }
        }

        return $best;
    }

    /**
     * Pick the best set of $nbRapporteurs professors for the jury,
     * ensuring at least 2 informatique professors across the full jury
     * (encadrant + picked members).
     *
     * @return Enseignant[]|null
     */
    private function pickBestJury(Enseignant $encadrant, Collection $available, array $juryCount, int $nbRapporteurs = 2): ?array
    {
        if ($nbRapporteurs <= 0) {
            return [];
        }

        $sorted = $available
            ->sort(function (Enseignant $a, Enseignant $b) use ($juryCount) {
                return [
                    $juryCount[$a->id] ?? 0,
                    $a->nom,
                    $a->prenom,
                    $a->id,
                ] <=> [
                    $juryCount[$b->id] ?? 0,
                    $b->nom,
                    $b->prenom,
                    $b->id,
                ];
            })
            ->values()
            ->all();

        // Separate professors into "info" and "other" categories, keeping sorted order
        $infoProfs = [];
        $otherProfs = [];
        
        foreach ($sorted as $index => $prof) {
            if ($this->isInfoProfessor($prof)) {
                $infoProfs[] = ['prof' => $prof, 'index' => $index];
            } else {
                $otherProfs[] = ['prof' => $prof, 'index' => $index];
            }
        }

        $encadrantIsInfo = $this->isInfoProfessor($encadrant);
        // We need at least 2 info professors total (including encadrant)
        $minInfoNeeded = $encadrantIsInfo ? 1 : 2;
        $minInfoNeeded = max(0, $minInfoNeeded);

        $bestTuple = null;

        // Try picking $c info professors and ($nbRapporteurs - $c) other professors.
        // Since both groups are already sorted by load, picking the first items from each group 
        // guarantees the lexicographically smallest combination for that specific split.
        for ($c = $minInfoNeeded; $c <= $nbRapporteurs; $c++) {
            $otherNeeded = $nbRapporteurs - $c;

            if ($c > count($infoProfs) || $otherNeeded > count($otherProfs)) {
                continue; // Not enough professors in this category
            }

            $comboIndices = [];
            
            for ($i = 0; $i < $c; $i++) {
                $comboIndices[] = $infoProfs[$i]['index'];
            }
            for ($i = 0; $i < $otherNeeded; $i++) {
                $comboIndices[] = $otherProfs[$i]['index'];
            }

            sort($comboIndices);

            if ($bestTuple === null || $this->isLexicographicallySmaller($comboIndices, $bestTuple)) {
                $bestTuple = $comboIndices;
            }
        }

        if ($bestTuple !== null) {
            return array_map(fn($idx) => $sorted[$idx], $bestTuple);
        }

        return null;
    }

    private function isLexicographicallySmaller(array $a, array $b): bool
    {
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            if ($a[$i] < $b[$i]) {
                return true;
            }
            if ($a[$i] > $b[$i]) {
                return false;
            }
        }
        return count($a) < count($b);
    }

    private function persistPackage(Projet $projet, array $package): void
    {
        DB::transaction(function () use ($projet, $package) {
            $jury = Jury::create([]);
            $jury->enseignants()->attach($projet->encadrant_id, ['role' => 'President']);

            foreach ($package['rapporteurs'] as $rapporteur) {
                $jury->enseignants()->attach($rapporteur->id, ['role' => 'Rapporteur']);
            }

            Soutenance::create([
                'cne' => $projet->cne ?? $projet->etudiant?->cne,
                'projet_id' => $projet->id,
                'encadrant_id' => $projet->encadrant_id,
                'jury_id' => $jury->id,
                'creneau_id' => $package['creneau']->id,
                'salle_id' => $package['salle']->id,
                'langue' => $projet->langue_soutenance ?? 'Francais',
            ]);
        });
    }

    private function markPackageInState(Projet $projet, array $package, array &$state, array $slotOrder): void
    {
        $creneau = $package['creneau'];
        $slotIndex = $this->getSlotIndex($creneau, $slotOrder);
        if ($slotIndex === null) {
            return;
        }

        $date = $creneau->date->format('Y-m-d');
        $this->markProfessorBusy($projet->encadrant_id, $date, $slotIndex, $state);

        foreach ($package['rapporteurs'] as $rapporteur) {
            $this->markProfessorBusy($rapporteur->id, $date, $slotIndex, $state);
            $state['profJuryCount'][$rapporteur->id] = ($state['profJuryCount'][$rapporteur->id] ?? 0) + 1;
        }

        $state['roomBusy'][$date][$slotIndex][$package['salle']->id] = true;
    }

    private function buildPlanningState(Collection $professors, Collection $salles, array $slotOrder): array
    {
        $state = [
            'profBusyAtSlot' => [],
            'profSchedule' => [],
            'profJuryCount' => [],
            'profDailyCount' => [],
            'roomBusy' => [],
        ];

        foreach ($professors as $professor) {
            $state['profSchedule'][$professor->id] = [];
            $state['profJuryCount'][$professor->id] = 0;
            $state['profDailyCount'][$professor->id] = [];
        }

        $salleIdsByName = $salles->mapWithKeys(fn(Salle $salle) => [$salle->nom => $salle->id])->toArray();

        Soutenance::with(['creneau', 'jury.enseignants'])->get()->each(function (Soutenance $soutenance) use (&$state, $slotOrder, $salleIdsByName) {
            $slotIndex = $this->getSlotIndex($soutenance->creneau, $slotOrder);
            if (!$soutenance->creneau || $slotIndex === null) {
                return;
            }

            $date = $soutenance->creneau->date->format('Y-m-d');
            $participants = [];

            if ($soutenance->encadrant_id) {
                $participants[$soutenance->encadrant_id] = true;
            }

            if ($soutenance->jury) {
                foreach ($soutenance->jury->enseignants as $membre) {
                    $participants[$membre->id] = true;
                    if (($membre->pivot->role ?? 'Rapporteur') !== 'President') {
                        $state['profJuryCount'][$membre->id] = ($state['profJuryCount'][$membre->id] ?? 0) + 1;
                    }
                }
            }

            foreach (array_keys($participants) as $profId) {
                $this->markProfessorBusy((int) $profId, $date, $slotIndex, $state);
            }

            $salleId = $soutenance->salle_id ?: ($salleIdsByName[$soutenance->salle] ?? null);
            if ($salleId) {
                $state['roomBusy'][$date][$slotIndex][$salleId] = true;
            }
        });

        return $state;
    }

    private function markProfessorBusy(int $profId, string $date, int $slotIndex, array &$state): void
    {
        $key = $date . '|' . $slotIndex;
        $alreadyPlanned = isset($state['profSchedule'][$profId][$key]);

        $state['profBusyAtSlot'][$date][$slotIndex][$profId] = true;
        $state['profSchedule'][$profId][$key] = true;

        if (!$alreadyPlanned) {
            $state['profDailyCount'][$profId][$date] = ($state['profDailyCount'][$profId][$date] ?? 0) + 1;
        }
    }

    private function isProfessorAvailable(int $profId, string $date, int $slotIndex, array $state): bool
    {
        if (isset($state['profBusyAtSlot'][$date][$slotIndex][$profId])) {
            return false;
        }

        foreach (array_keys($state['profSchedule'][$profId] ?? []) as $key) {
            [$plannedDate, $plannedSlot] = explode('|', $key);
            if ($plannedDate !== $date) {
                continue;
            }

            if (abs(((int) $plannedSlot) - $slotIndex) <= 1) {
                return false;
            }
        }

        return true;
    }

    private function slotLoad(string $date, int $slotIndex, array $state): int
    {
        return count($state['roomBusy'][$date][$slotIndex] ?? []);
    }

    private function getFreeRoom(string $date, int $slotIndex, Collection $salles, array $state): ?Salle
    {
        foreach ($salles->shuffle() as $salle) {
            if (!isset($state['roomBusy'][$date][$slotIndex][$salle->id])) {
                return $salle;
            }
        }

        return null;
    }

    private function ensureSallesExist(): void
    {
        if (Salle::count() > 0) {
            return;
        }

        foreach (self::DEFAULT_ROOMS as $roomName) {
            Salle::create([
                'nom' => $roomName,
            ]);
        }
    }

    private function officialCreneaux(array $slotOrder): Collection
    {
        return Creneau::orderBy('date')
            ->orderBy('heure_debut')
            ->get()
            ->filter(fn(Creneau $creneau) => $this->getSlotIndex($creneau, $slotOrder) !== null)
            ->sort(function (Creneau $a, Creneau $b) use ($slotOrder) {
                return [
                    $a->date->format('Y-m-d'),
                    $this->getSlotIndex($a, $slotOrder),
                ] <=> [
                    $b->date->format('Y-m-d'),
                    $this->getSlotIndex($b, $slotOrder),
                ];
            })
            ->values();
    }

    private function workingDates(string $dateDebut, int $days): array
    {
        $dates = [];
        $current = new \DateTimeImmutable($dateDebut);

        while (count($dates) < $days) {
            $dayOfWeek = (int) $current->format('N');
            if ($dayOfWeek < 6) {
                $dates[] = $current->format('Y-m-d');
            }

            $current = $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Build a slot ordering map ("HH:MM" => index) from the Creneau records
     * currently in the DB. Replaces the previously hardcoded 7-slot layout
     * so the algorithm honors whatever the user chose in the modal.
     */
    private function slotOrder(): array
    {
        $times = Creneau::query()
            ->select('heure_debut')
            ->distinct()
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Creneau $creneau) {
                return is_object($creneau->heure_debut)
                    ? $creneau->heure_debut->format('H:i')
                    : substr((string) $creneau->heure_debut, 0, 5);
            })
            ->unique()
            ->values()
            ->toArray();

        $order = [];
        foreach ($times as $i => $time) {
            $order[$time] = $i;
        }

        return $order;
    }

    /**
     * Default slot layout used when the caller does not supply custom ranges.
     * Reproduces the historical 7-slot/day behaviour (09-12 + 14-18).
     */
    private function defaultSlotRanges(): array
    {
        return [
            '09:00' => '10:00',
            '10:00' => '11:00',
            '11:00' => '12:00',
            '14:00' => '15:00',
            '15:00' => '16:00',
            '16:00' => '17:00',
            '17:00' => '18:00',
        ];
    }

    private function getSlotIndex(?Creneau $creneau, array $slotOrder): ?int
    {
        if (!$creneau) {
            return null;
        }

        $slotKey = is_object($creneau->heure_debut)
            ? $creneau->heure_debut->format('H:i')
            : substr((string) $creneau->heure_debut, 0, 5);

        return $slotOrder[$slotKey] ?? null;
    }

    private function normalizeFiliere(string $filiere): string
    {
        $filiere = strtoupper($filiere);

        if (str_contains($filiere, 'TDIA') || str_contains($filiere, 'TRANSFORM')) {
            return 'TDIA';
        }

        if (str_contains($filiere, 'ING') && str_contains($filiere, 'DONN')) {
            return 'ID';
        }

        if (str_contains($filiere, 'INFORMATIQUE') || $filiere === 'GI') {
            return 'GI';
        }

        return 'AUTRE';
    }

    private function isInfoProfessor(Enseignant $professor): bool
    {
        return str_contains(strtolower($professor->discipline ?? ''), 'info');
    }
}
