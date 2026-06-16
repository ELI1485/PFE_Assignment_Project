@extends('layouts.app')

@section('title', 'Audit des Contraintes')

@push('styles')
<style>
    .audit-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .summary-tile {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 20px;
        box-shadow: var(--shadow-soft);
    }
    .summary-tile .label {
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .summary-tile .value {
        color: var(--heading);
        font-size: 1.5rem;
        font-weight: 700;
    }
    .rules-list {
        margin: 0;
        padding-left: 18px;
        color: var(--text);
        font-size: 0.86rem;
    }
    .rules-list li { margin-bottom: 6px; }

    .audit-context {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-soft);
    }
    .audit-context-title {
        color: var(--heading);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 12px;
    }
    .audit-context-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px 22px;
        font-size: 0.86rem;
    }
    .audit-context-grid > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .audit-context-label {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .audit-context-chip {
        display: inline-block;
        padding: 2px 8px;
        margin: 2px 4px 2px 0;
        border-radius: 999px;
        background: var(--ensah-blue-soft);
        color: var(--ensah-blue);
        font-size: 0.76rem;
        font-weight: 600;
    }
    .audit-context-chip.warn { background: #fef3c7; color: #b45309; }
</style>
@endpush

@section('content')
<div class="audit-summary">
    <div class="summary-tile">
        <div class="label">Soutenances analysées</div>
        <div class="value">{{ $soutenancesCount }}</div>
    </div>
    <div class="summary-tile">
        <div class="label">Taille de jury appliquée</div>
        <div class="value">{{ $expectedJurySize }} membre{{ $expectedJurySize > 1 ? 's' : '' }}</div>
    </div>
    <div class="summary-tile">
        <div class="label">Norme d'encadrement</div>
        <div class="value">{{ $encadrementMin }} - {{ $encadrementMax }} étudiants</div>
    </div>
    <div class="summary-tile">
        <div class="label">Anomalies totales</div>
        <div class="value {{ count($planningAnomalies ?? []) === 0 ? 'text-success' : 'text-danger' }}">
            {{ count($planningAnomalies ?? []) }}
        </div>
    </div>
</div>

@if($planningConfig)
<div class="audit-context">
    <div class="audit-context-title">
        <i class="bi bi-sliders me-1"></i>
        Paramètres du planning courant
    </div>
    <div class="audit-context-grid">
        @if(!empty($planningConfig['date_debut']))
            <div>
                <span class="audit-context-label">Début</span>
                <span>{{ \Illuminate\Support\Carbon::parse($planningConfig['date_debut'])->format('d/m/Y') }}</span>
            </div>
        @endif
        @if(!empty($planningConfig['nb_jours']))
            <div>
                <span class="audit-context-label">Jours demandés</span>
                <span>{{ $planningConfig['nb_jours'] }}</span>
            </div>
        @endif
        @if(!empty($planningConfig['creneau_duree']))
            <div>
                <span class="audit-context-label">Durée créneau</span>
                <span>{{ $planningConfig['creneau_duree'] }} min</span>
            </div>
        @endif
        @if(!empty($planningConfig['slot_ranges']))
            <div>
                <span class="audit-context-label">Plages horaires</span>
                <span>
                    @foreach($planningConfig['slot_ranges'] as $start => $end)
                        <span class="audit-context-chip">{{ $start }}–{{ $end }}</span>
                    @endforeach
                </span>
            </div>
        @endif
        @if(!empty($planningConfig['dates_exclues']))
            <div>
                <span class="audit-context-label">Dates exclues</span>
                <span>
                    @foreach($planningConfig['dates_exclues'] as $iso)
                        <span class="audit-context-chip warn">{{ \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') }}</span>
                    @endforeach
                </span>
            </div>
        @endif
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-person-lines-fill text-primary me-2"></i>Audit des Affectations</div>
            </div>

            <div class="mb-4">
                <p class="mb-1 text-muted small">Moyenne d'étudiants par professeur (norme : {{ $encadrementMin }} à {{ $encadrementMax }})</p>
                <h3 class="mb-0 {{ ($moyenneEncadrement >= $encadrementMin && $moyenneEncadrement <= $encadrementMax) ? 'text-success' : 'text-warning' }}">
                    {{ $moyenneEncadrement }} étudiants/prof
                </h3>
            </div>

            <div class="constraint-box mb-3">
                Contraintes vérifiées :
                <ul class="rules-list mt-2">
                    @foreach($affectationRules as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </div>

            @if(count($affectationRecommendations ?? []) > 0)
                <div class="alert alert-info d-flex align-items-center mb-0">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong>{{ count($affectationRecommendations) }} recommandation(s) :</strong>
                        <ul class="mb-0 mt-1">
                            @foreach($affectationRecommendations as $ano)
                                <li><strong>{{ $ano['type'] }} :</strong> {{ $ano['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @else
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    La norme d'encadrement est respectée par tous les professeurs.
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-calendar-x text-primary me-2"></i>Audit du Planning</div>
            </div>

            <div class="constraint-box mb-3">
                Contraintes vérifiées (jury de {{ $expectedJurySize }} membre{{ $expectedJurySize > 1 ? 's' : '' }}) :
                <ul class="rules-list mt-2">
                    @foreach($planningRules as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </div>

            @if(count($planningAnomalies ?? []) > 0)
                <div class="alert alert-danger mb-0">
                    <h6 class="alert-heading fw-bold">Anomalies détectées ({{ count($planningAnomalies ?? []) }})</h6>
                    <hr class="mt-2 mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach($planningAnomalies as $anomalie)
                            <li class="mb-2">
                                <strong>{{ $anomalie['type'] }} :</strong><br>
                                <span class="small">{{ $anomalie['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif($soutenancesCount === 0)
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Aucun planning de soutenance n'a encore été généré.
                </div>
            @else
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Aucune anomalie de planning détectée. Le calendrier est conforme.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
