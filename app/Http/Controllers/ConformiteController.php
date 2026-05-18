<?php

namespace App\Http\Controllers;

use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\Projet;
use App\Models\Salle;
use App\Models\Soutenance;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class ConformiteController extends Controller
{
    public function index()
    {
        // If no planning has been generated yet (no soutenances in DB),
        // there is nothing to diagnose — this also covers the case where
        // affectation was never run.
        if (Soutenance::count() === 0) {
            return view('conformite.index', ['diagnostic' => null]);
        }

        // 1. Try to load from persistent JSON file (written at last algorithm run)
        if (Storage::exists('conformite_diagnostic.json')) {
            $content = Storage::get('conformite_diagnostic.json');
            $diagnostic = $content ? json_decode($content, true) : [];
        } elseif (Session::has('conformite_diagnostic')) {
            $diagnostic = Session::get('conformite_diagnostic');
        } else {
            $diagnostic = $this->buildLiveDiagnostic();
        }

        if (! is_array($diagnostic)) {
            $diagnostic = [];
        }

        $diagnostic = $this->normalizeDiagnostic($diagnostic);

        return view('conformite.index', compact('diagnostic'));
    }

    private function normalizeDiagnostic(array $diagnostic): array
    {
        $nbSalles = Salle::count();
        $nbDates = Creneau::get()
            ->groupBy(fn ($creneau) => $creneau->date->format('Y-m-d'))
            ->count();
        $nbCreneauxParJour = Creneau::select('heure_debut')->distinct()->count() ?: 7;

        $diagnostic['nb_salles'] ??= $nbSalles;
        $diagnostic['nb_dates'] ??= $nbDates;
        $diagnostic['total_projets'] ??= $this->canonicalProjectsCount();
        $diagnostic['projets_planifies'] ??= Soutenance::distinct('projet_id')->count('projet_id');
        $diagnostic['projets_non_planifies'] ??= max(0, $diagnostic['total_projets'] - $diagnostic['projets_planifies']);
        $diagnostic['salles_recommandees'] ??= 5;
        $diagnostic['salles_manquantes'] ??= max(0, $diagnostic['salles_recommandees'] - $diagnostic['nb_salles']);
        $diagnostic['capacite_max'] ??= $diagnostic['nb_dates'] * $nbCreneauxParJour * $diagnostic['nb_salles'];
        $diagnostic['manque_capacite'] ??= max(0, $diagnostic['total_projets'] - $diagnostic['capacite_max']);

        return $diagnostic;
    }

    private function buildLiveDiagnostic(): array
    {
        $totalEtudiants = Etudiant::count();
        // A binome shares one Projet and one Soutenance, so conformity counts
        // scheduled student ids from both etudiant_id and etudiant2_id.
        $scheduledIds = $this->scheduledStudentIds();
        $affectes = count($scheduledIds);
        $nonAffectes = max(0, $totalEtudiants - $affectes);
        $pct = $totalEtudiants > 0 ? round(($affectes / $totalEtudiants) * 100) : 0;
        $nbSalles = Salle::count();
        $nbDates = Creneau::distinct('date')->count('date');
        $nbCreneauxParJour = Creneau::select('heure_debut')->distinct()->count();
        $capaciteMax = $nbDates * $nbCreneauxParJour * $nbSalles;
        $totalProjects = $this->canonicalProjectsCount();
        $scheduledProjects = Soutenance::distinct('projet_id')->count('projet_id');

        $etudiantsNonAffectes = Etudiant::whereNotIn('id', $scheduledIds)->get();

        return [
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
                        ? ($projet->encadrant->nom.' '.$projet->encadrant->prenom)
                        : 'Non assigné',
                ];
            })->toArray(),
        ];
    }

    private function canonicalProjectsCount(): int
    {
        $coveredAsEtudiant2 = Projet::whereNotNull('etudiant2_id')
            ->pluck('etudiant2_id')
            ->unique()
            ->values()
            ->toArray();

        return Projet::whereNotIn('etudiant_id', $coveredAsEtudiant2)->count();
    }

    private function scheduledStudentIds(): array
    {
        return Projet::whereHas('soutenance')
            ->get()
            ->flatMap(fn ($p) => array_filter([$p->etudiant_id, $p->etudiant2_id]))
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
