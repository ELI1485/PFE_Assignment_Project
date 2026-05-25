@extends('layouts.app')

@section('title', 'Contrôle de Conformité')

@push('styles')
<style>
    .score-card { padding: 28px 32px; margin-bottom: 22px; display: flex; align-items: center; gap: 28px; }
    .score-circle { width: 110px; height: 110px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.8rem; font-weight: 800; }
    .score-ok { background: #dcfce7; color: #16a34a; }
    .score-warn { background: #fef9c3; color: #ca8a04; }
    .score-error { background: #fee2e2; color: #dc2626; }
    .anomaly-card { padding: 20px 24px; margin-bottom: 14px; border-left: 4px solid #ef4444; }
    .anomaly-card.info { border-left-color: #f59e0b; }
    .anomaly-card.ok { border-left-color: #22c55e; background: #f0fdf4; }
    .anomaly-title { font-weight: 700; color: var(--heading); font-size: 0.95rem; margin-bottom: 4px; }
    .anomaly-card.ok .anomaly-title { color: #16a34a; }
    .anomaly-desc { color: var(--muted); font-size: 0.86rem; line-height: 1.55; }
    .badge-filiere { padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }

    .conformite-config {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 14px 18px;
        box-shadow: var(--shadow-soft);
    }
    .conformite-config-title {
        color: var(--heading);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .conformite-config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px 22px;
        font-size: 0.86rem;
    }
    .conformite-config-grid > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
        color: var(--heading);
    }
    .conformite-config-label {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .conformite-config-chip {
        display: inline-block;
        padding: 2px 8px;
        margin: 2px 4px 2px 0;
        border-radius: 999px;
        background: var(--ensah-blue-soft);
        color: var(--ensah-blue);
        font-size: 0.76rem;
        font-weight: 600;
    }
    .conformite-config-chip.warn { background: #fef3c7; color: #b45309; }
</style>
@endpush

@section('content')

@include('partials.planning-start-modal')

@if(session('error'))
    <div class="alert alert-danger mb-4">{!! session('error') !!}</div>
@endif

@if(session('success'))
    <div class="alert alert-success mb-4">{!! session('success') !!}</div>
@endif

<div class="page-actions">
    <div class="section-subtitle">Analyse des anomalies et contraintes non satisfaites lors de la génération.</div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('planning.results') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
            Retour au Planning
        </a>
        <a href="{{ route('verification.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-shield-check"></i>
            Audit des Contraintes
        </a>
        <button type="button" class="btn btn-success" onclick="openPlanningModal()">
            <i class="bi bi-arrow-clockwise"></i>
            Relancer l'Algorithme
        </button>
    </div>
</div>

@if(session('planning_recommendation'))
    <div class="anomaly-card info mb-4" style="border-left-color: #3b82f6;">
        <div class="anomaly-title" style="color: #1e40af;">
            <i class="bi bi-lightbulb-fill me-1"></i> Recommandation du planificateur
        </div>
        <div class="anomaly-desc" style="white-space: pre-line;">{{ session('planning_recommendation') }}</div>
    </div>
@endif

@if(is_array($diagnostic['config'] ?? null))
@php $cfg = $diagnostic['config']; @endphp
<div class="conformite-config mb-4">
    <div class="conformite-config-title">
        <i class="bi bi-sliders me-1"></i>
        Configuration testée
    </div>
    <div class="conformite-config-grid">
        @if(!empty($cfg['date_debut']))
            <div><span class="conformite-config-label">Début</span><span>{{ \Illuminate\Support\Carbon::parse($cfg['date_debut'])->format('d/m/Y') }}</span></div>
        @endif
        @if(!empty($cfg['nb_jours']))
            <div><span class="conformite-config-label">Jours demandés</span><span>{{ $cfg['nb_jours'] }}</span></div>
        @endif
        @if(!empty($cfg['creneau_duree']))
            <div><span class="conformite-config-label">Durée créneau</span><span>{{ $cfg['creneau_duree'] }} min</span></div>
        @endif
        @if(!empty($cfg['nb_jurys']))
            <div><span class="conformite-config-label">Membres jury</span><span>{{ $cfg['nb_jurys'] }}</span></div>
        @endif
        @if(!empty($cfg['slot_ranges']))
            <div><span class="conformite-config-label">Plages</span><span>
                @foreach($cfg['slot_ranges'] as $s => $e)
                    <span class="conformite-config-chip">{{ $s }}–{{ $e }}</span>
                @endforeach
            </span></div>
        @endif
        @if(!empty($cfg['dates_exclues']))
            <div><span class="conformite-config-label">Dates exclues</span><span>
                @foreach($cfg['dates_exclues'] as $iso)
                    <span class="conformite-config-chip warn">{{ \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') }}</span>
                @endforeach
            </span></div>
        @endif
    </div>
</div>
@endif

@if($diagnostic === null)
    <div class="anomaly-card info">
        <div class="anomaly-title">Aucune affectation ou planning généré</div>
        <div class="anomaly-desc">
            Aucun planning de soutenance n'existe encore. Pour accéder au contrôle de conformité, vous devez d'abord :
            <ol class="mt-2 mb-0">
                <li>Lancer l'<a href="{{ route('affectation.index') }}">Affectation des encadrants</a></li>
                <li>Puis générer le <a href="{{ route('planning.results') }}">Planning des soutenances</a></li>
            </ol>
        </div>
    </div>
@else
@php
    $diagnostic['nb_salles'] = $diagnostic['nb_salles'] ?? 0;
    $diagnostic['nb_dates'] = $diagnostic['nb_dates'] ?? 0;
    $diagnostic['capacite_max'] = $diagnostic['capacite_max'] ?? 0;
    $diagnostic['manque_capacite'] = $diagnostic['manque_capacite'] ?? 0;
    $diagnostic['salles_recommandees'] = $diagnostic['salles_recommandees'] ?? 5;
    $diagnostic['salles_manquantes'] = $diagnostic['salles_manquantes']
        ?? max(0, $diagnostic['salles_recommandees'] - $diagnostic['nb_salles']);

    $extraDays = $diagnostic['nb_salles'] > 0
        ? (int) ceil($diagnostic['manque_capacite'] / (7 * $diagnostic['nb_salles']))
        : 0;
    $extraRooms = $diagnostic['nb_dates'] > 0
        ? (int) ceil($diagnostic['manque_capacite'] / (7 * $diagnostic['nb_dates']))
        : 0;
@endphp

<div class="score-card">
    <div class="score-circle {{ $diagnostic['pct'] >= 90 ? 'score-ok' : ($diagnostic['pct'] >= 60 ? 'score-warn' : 'score-error') }}">
        {{ $diagnostic['pct'] }}%
    </div>
    <div>
        <div class="fs-5 fw-bold text-dark">{{ $diagnostic['affectes'] }} / {{ $diagnostic['total'] }} étudiants planifiés</div>
        <div class="text-muted small mt-1">{{ $diagnostic['non_affectes'] }} étudiant(s) n'ont pas pu être affectés à un créneau.</div>
        <div class="mt-2">
            @if($diagnostic['pct'] == 100)
                <span class="badge text-bg-success">Planning complet - aucune anomalie</span>
            @elseif($diagnostic['pct'] >= 75)
                <span class="badge text-bg-warning">Planning partiel - corrections recommandées</span>
            @else
                <span class="badge text-bg-danger">Planning incomplet - action requise</span>
            @endif
        </div>
    </div>
</div>

@if($diagnostic['non_affectes'] > 0)
    <h2 class="h5 fw-bold mb-3 text-dark">Anomalies Détectées</h2>

    @if($diagnostic['nb_salles'] == 0)
        <div class="anomaly-card">
            <div class="anomaly-title">Aucune salle configurée</div>
            <div class="anomaly-desc">
                Aucune salle de soutenance n'est enregistrée dans le système. Sans salle, aucun étudiant ne peut être planifié.
                <br><strong>Solution :</strong> ajoutez des salles via le panneau Salles sur la page Planning.
            </div>
        </div>
    @else
        @if(($diagnostic['salles_manquantes'] ?? 0) > 0)
            <div class="anomaly-card">
                <div class="anomaly-title">Salle(s) insuffisante(s)</div>
                <div class="anomaly-desc">
                    Le planning de référence utilise <strong>{{ $diagnostic['salles_recommandees'] }}</strong> salles.
                    Vous avez actuellement <strong>{{ $diagnostic['nb_salles'] }}</strong> salle(s), donc il manque
                    <strong>{{ $diagnostic['salles_manquantes'] }}</strong> salle(s).
                    <br><br>
                    <strong>Solution :</strong> ajoutez au moins <strong>{{ $diagnostic['salles_manquantes'] }}</strong>
                    salle(s), ou ajoutez des jours supplémentaires.
                </div>
            </div>
        @endif

        @if($diagnostic['manque_capacite'] > 0)
            <div class="anomaly-card">
                <div class="anomaly-title">Nombre de salles ou de jours insuffisant</div>
                <div class="anomaly-desc">
                    Avec <strong>{{ $diagnostic['nb_salles'] }} salle(s)</strong> et <strong>{{ $diagnostic['nb_dates'] }} jour(s)</strong>,
                    la capacité maximale théorique est de <strong>{{ $diagnostic['capacite_max'] }} soutenances</strong>.
                    Il manque <strong>{{ $diagnostic['manque_capacite'] }} créneau(x)</strong>.
                    <br><br>
                    <strong>Solutions possibles :</strong>
                    <ul class="mt-1 mb-0">
                        <li>Ajouter <strong>{{ $extraDays }}</strong> jour(s) de soutenance supplémentaire(s)</li>
                        <li>Ou ajouter <strong>{{ $extraRooms }}</strong> salle(s) supplémentaire(s)</li>
                    </ul>
                </div>
            </div>
        @else
            <div class="anomaly-card info">
                <div class="anomaly-title">Contraintes de salles et de repos</div>
                <div class="anomaly-desc">
                    La capacité totale est théoriquement suffisante, mais les salles disponibles et les contraintes de repos des professeurs
                    ont empêché certains créneaux d'être utilisés.
                    <br><br>
                    <strong>Solutions possibles :</strong>
                    <ul class="mt-1 mb-0">
                        <li>Ajouter des salles si vous êtes sous {{ $diagnostic['salles_recommandees'] }} salles</li>
                        <li>Ajouter des jours de soutenance supplémentaires</li>
                        <li>Redistribuer les étudiants entre encadrants avant de relancer</li>
                    </ul>
                </div>
            </div>
        @endif
    @endif

    @if(!empty($diagnostic['etudiants_manquants']))
        <div class="table-card p-4 mt-4">
            <div class="card-header">
                <div class="card-title text-danger">Étudiants non planifiés ({{ count($diagnostic['etudiants_manquants']) }})</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Étudiant</th>
                            <th>Filière</th>
                            <th>Encadrant</th>
                            <th>Raison probable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($diagnostic['etudiants_manquants'] as $i => $etudiant)
                            @php
                                $f = mb_strtoupper($etudiant['filiere'] ?? '', 'UTF-8');
                                $fShort = '-';
                                $fClass = 'f-other';
                                if (str_contains($f, 'TDIA') || str_contains($f, 'ARTIFIC')) { $fShort = 'TDIA'; $fClass = 'f-tdia'; }
                                elseif (str_contains($f, 'GI') || str_contains($f, 'GENIE')) { $fShort = 'GI'; $fClass = 'f-gi'; }
                                elseif (str_contains($f, 'ID') || str_contains($f, 'INGENIER')) { $fShort = 'ID'; $fClass = 'f-id'; }
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $etudiant['nom'] }} {{ $etudiant['prenom'] }}</td>
                                <td><span class="badge-filiere {{ $fClass }}">{{ $fShort }}</span></td>
                                <td>{{ $etudiant['encadrant'] }}</td>
                                <td class="text-danger">
                                    @if(($diagnostic['salles_manquantes'] ?? 0) > 0)
                                        Salle(s) insuffisante(s)
                                    @elseif($diagnostic['manque_capacite'] > 0)
                                        Capacité insuffisante (salles/jours)
                                    @else
                                        Paquet salle + créneau + jury indisponible
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@else
    <div class="anomaly-card ok">
        <div class="anomaly-title">Aucune anomalie détectée</div>
        <div class="anomaly-desc">Tous les {{ $diagnostic['total'] }} étudiants ont été planifiés avec succès.</div>
    </div>
@endif
@endif
@endsection
