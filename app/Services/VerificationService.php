<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\Salle;
use App\Models\Soutenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    public function checkAffectations(): array
    {
        $enseignants = Enseignant::with('projets')->get();
        $totalEtudiantsAffectes = 0;
        $anomalies = [];

        foreach ($enseignants as $enseignant) {
            $count = $enseignant->projets->sum(fn($projet) => 1 + ($projet->etudiant2_id ? 1 : 0));
            $totalEtudiantsAffectes += $count;

            if ($count > 0 && ($count < 3 || $count > 4)) {
                $anomalies[] = [
                    'type' => "Ecart d'encadrement",
                    'message' => "Le professeur {$enseignant->nom} {$enseignant->prenom} encadre {$count} etudiants (la norme est entre 3 et 4).",
                ];
            }
        }

        $totalEnseignants = $enseignants->count();
        $moyenne = $totalEnseignants > 0 ? round($totalEtudiantsAffectes / $totalEnseignants, 2) : 0;

        return [
            'moyenne' => $moyenne,
            'anomalies' => $anomalies,
        ];
    }

    public function checkPlannings(): array
    {
        $slotOrder = $this->slotOrder();
        $soutenances = Soutenance::with(['creneau', 'projet.encadrant', 'jury.enseignants'])->get();
        $professors = Enseignant::all()->keyBy('id');

        $anomalies = [];
        $sallesParCreneau = [];
        $profsParSlot = [];
        $profsPlanning = [];
        $juryLoads = $professors->mapWithKeys(fn(Enseignant $professor) => [$professor->id => 0])->toArray();

        foreach ($soutenances as $soutenance) {
            $creneau = $soutenance->creneau;
            if (!$creneau) {
                $anomalies[] = [
                    'type' => 'Creneau manquant',
                    'message' => "La soutenance #{$soutenance->id} n'a pas de creneau.",
                ];
                continue;
            }

            $creneauId = $creneau->id;
            $date = $creneau->date->format('Y-m-d');
            $heure = $this->formatSlotTime($creneau);
            $slotIndex = $this->getSlotIndex($creneau, $slotOrder);
            $salleKey = $soutenance->salle_id;

            if (!$salleKey) {
                $anomalies[] = [
                    'type' => 'Salle manquante',
                    'message' => "La soutenance #{$soutenance->id} n'a pas de salle.",
                ];
            } elseif (isset($sallesParCreneau[$creneauId][$salleKey])) {
                $nomSalle = $soutenance->salleRelation?->nom ?? $salleKey;
                $anomalies[] = [
                    'type' => 'Chevauchement de salle',
                    'message' => "La salle '{$nomSalle}' est utilisee plusieurs fois le {$creneau->date->format('d/m/Y')} a {$heure}.",
                ];
            }
            $sallesParCreneau[$creneauId][$salleKey] = true;

            $jury = $soutenance->jury?->enseignants ?? collect();
            $encadrantId = $soutenance->encadrant_id ?? $soutenance->projet?->encadrant_id;
            $presidents = $jury->filter(fn($membre) => ($membre->pivot->role ?? null) === 'President')->values();
            $rapporteurs = $jury->filter(fn($membre) => ($membre->pivot->role ?? 'Rapporteur') === 'Rapporteur')->values();
            $juryIds = $jury->pluck('id')->all();

            $this->detectJuryComposition(
                $soutenance,
                $encadrantId,
                $jury,
                $presidents,
                $rapporteurs,
                $professors,
                $anomalies
            );

            foreach ($rapporteurs as $rapporteur) {
                $juryLoads[$rapporteur->id] = ($juryLoads[$rapporteur->id] ?? 0) + 1;
            }

            if ($slotIndex === null) {
                $anomalies[] = [
                    'type' => 'Creneau non officiel',
                    'message' => "La soutenance #{$soutenance->id} utilise un horaire non officiel ({$heure}).",
                ];
                continue;
            }

            $participants = [];
            if ($encadrantId) {
                $participants[$encadrantId] = true;
            }
            foreach ($juryIds as $juryId) {
                $participants[$juryId] = true;
            }

            foreach (array_keys($participants) as $profId) {
                if (isset($profsParSlot[$date][$slotIndex][$profId])) {
                    $prof = $this->professorLabel((int) $profId, $professors);
                    $anomalies[] = [
                        'type' => 'Double assignation',
                        'message' => "Le professeur {$prof} est assigne a plusieurs soutenances dans le meme slot le {$creneau->date->format('d/m/Y')} a {$heure}.",
                    ];
                    Log::warning('Duplicate detected: professor assigned twice in same slot.', [
                        'professor_id' => $profId,
                        'date' => $date,
                        'slot' => $slotIndex,
                        'soutenance_id' => $soutenance->id,
                        'first_soutenance_id' => $profsParSlot[$date][$slotIndex][$profId],
                    ]);
                }

                $profsParSlot[$date][$slotIndex][$profId] = $soutenance->id;
                $profsPlanning[$profId][] = [
                    'soutenance_id' => $soutenance->id,
                    'date' => $date,
                    'date_label' => $creneau->date->format('d/m/Y'),
                    'slot' => $slotIndex,
                    'heure' => $heure,
                ];
            }
        }

        $this->detectRestViolations($profsPlanning, $professors, $anomalies);
        $this->detectRoomSaturation($soutenances, $anomalies);
        $this->detectJuryLoadImbalance($juryLoads, $professors, $anomalies);

        return $anomalies;
    }

    private function detectJuryComposition(
        Soutenance $soutenance,
        ?int $encadrantId,
        Collection $jury,
        Collection $presidents,
        Collection $rapporteurs,
        Collection $professors,
        array &$anomalies
    ): void {
        $juryIds = $jury->pluck('id')->all();
        $uniqueJuryIds = array_unique($juryIds);

        if (count($juryIds) !== 3) {
            $anomalies[] = [
                'type' => 'Jury incomplet',
                'message' => "La soutenance #{$soutenance->id} doit avoir exactement 3 membres de jury, mais en a " . count($juryIds) . ".",
            ];
            Log::warning('Jury impossible or incomplete detected during verification.', [
                'soutenance_id' => $soutenance->id,
                'jury_member_count' => count($juryIds),
            ]);
        }

        if (count($juryIds) !== count($uniqueJuryIds)) {
            $anomalies[] = [
                'type' => 'Membre de jury duplique',
                'message' => "La soutenance #{$soutenance->id} contient un professeur plusieurs fois dans le jury.",
            ];
            Log::warning('Duplicate detected: duplicate jury membership.', [
                'soutenance_id' => $soutenance->id,
                'jury_member_ids' => $juryIds,
            ]);
        }

        if ($presidents->count() !== 1) {
            $anomalies[] = [
                'type' => 'President jury invalide',
                'message' => "La soutenance #{$soutenance->id} doit avoir exactement un president de jury.",
            ];
        }

        if ($rapporteurs->count() !== 2) {
            $anomalies[] = [
                'type' => 'Rapporteurs invalides',
                'message' => "La soutenance #{$soutenance->id} doit avoir exactement 2 rapporteurs.",
            ];
        }

        $president = $presidents->first();
        if ($encadrantId && (!$president || $president->id !== $encadrantId)) {
            $prof = $this->professorLabel($encadrantId, $professors);
            $anomalies[] = [
                'type' => 'President non conforme',
                'message' => "Le professeur {$prof} doit etre le president du jury pour la soutenance #{$soutenance->id}.",
            ];
        }

        if ($encadrantId && $rapporteurs->contains('id', $encadrantId)) {
            $prof = $this->professorLabel($encadrantId, $professors);
            $anomalies[] = [
                'type' => 'Encadrant rapporteur',
                'message' => "Le professeur {$prof} est encadrant et rapporteur pour la soutenance #{$soutenance->id}.",
            ];
        }

        $infoCount = 0;
        foreach ($uniqueJuryIds as $profId) {
            $professor = $professors->get($profId);
            if ($professor && $this->isInfoProfessor($professor)) {
                $infoCount++;
            }
        }

        if ($infoCount < 2) {
            $anomalies[] = [
                'type' => 'Jury informatique insuffisant',
                'message' => "La soutenance #{$soutenance->id} doit avoir au moins 2 professeurs informatique dans le jury.",
            ];
        }
    }

    private function detectRestViolations(array $profsPlanning, Collection $professors, array &$anomalies): void
    {
        foreach ($profsPlanning as $profId => $entries) {
            usort($entries, fn($a, $b) => [$a['date'], $a['slot']] <=> [$b['date'], $b['slot']]);

            for ($i = 0; $i < count($entries) - 1; $i++) {
                $current = $entries[$i];
                $next = $entries[$i + 1];

                if ($current['date'] !== $next['date']) {
                    continue;
                }

                if (abs($current['slot'] - $next['slot']) <= 1) {
                    $prof = $this->professorLabel((int) $profId, $professors);
                    $anomalies[] = [
                        'type' => 'Heure de repos non respectee',
                        'message' => "Le professeur {$prof} viole la regle de repos le {$current['date_label']} (slots {$current['slot']} et {$next['slot']}, {$current['heure']} puis {$next['heure']}).",
                    ];
                    Log::warning('Duplicate detected: rest rule violation.', [
                        'professor_id' => $profId,
                        'date' => $current['date'],
                        'slot_a' => $current['slot'],
                        'slot_b' => $next['slot'],
                        'soutenance_a' => $current['soutenance_id'],
                        'soutenance_b' => $next['soutenance_id'],
                    ]);
                }
            }
        }
    }

    private function detectRoomSaturation(Collection $soutenances, array &$anomalies): void
    {
        $roomCount = Salle::count();
        if ($roomCount <= 0) {
            return;
        }

        $loads = [];
        foreach ($soutenances as $soutenance) {
            if (!$soutenance->creneau) {
                continue;
            }

            $loads[$soutenance->creneau_id] = ($loads[$soutenance->creneau_id] ?? 0) + 1;
        }

        foreach ($loads as $creneauId => $load) {
            if ($load <= $roomCount) {
                continue;
            }

            $anomalies[] = [
                'type' => 'Saturation de salles',
                'message' => "Le creneau #{$creneauId} contient {$load} soutenances pour {$roomCount} salles disponibles.",
            ];
        }
    }

    private function detectJuryLoadImbalance(array $juryLoads, Collection $professors, array &$anomalies): void
    {
        if (count($juryLoads) < 2) {
            return;
        }

        $min = min($juryLoads);
        $max = max($juryLoads);
        if (($max - $min) <= 2) {
            return;
        }

        $mostLoaded = array_keys($juryLoads, $max, true);
        $leastLoaded = array_keys($juryLoads, $min, true);

        $anomalies[] = [
            'type' => 'Repartition jury desequilibree',
            'message' => 'La distribution des rapporteurs est desequilibree (ecart ' . ($max - $min) . '). '
                . 'Plus charge: ' . $this->professorLabel((int) $mostLoaded[0], $professors)
                . ', moins charge: ' . $this->professorLabel((int) $leastLoaded[0], $professors) . '.',
        ];
    }

    private function slotOrder(): array
    {
        $times = \App\Models\Creneau::query()
            ->select('heure_debut')
            ->distinct()
            ->orderBy('heure_debut')
            ->get()
            ->map(function ($creneau) {
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

    private function getSlotIndex($creneau, array $slotOrder): ?int
    {
        $slotKey = $this->formatSlotTime($creneau);

        return $slotOrder[$slotKey] ?? null;
    }

    private function formatSlotTime($creneau): string
    {
        return is_object($creneau->heure_debut)
            ? $creneau->heure_debut->format('H:i')
            : substr((string) $creneau->heure_debut, 0, 5);
    }

    private function professorLabel(int $profId, Collection $professors): string
    {
        $prof = $professors->get($profId);

        if (!$prof) {
            return "#{$profId}";
        }

        return trim("{$prof->nom} {$prof->prenom}");
    }

    private function isInfoProfessor(Enseignant $professor): bool
    {
        return str_contains(strtolower($professor->discipline ?? ''), 'info');
    }
}
