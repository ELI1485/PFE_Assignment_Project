@extends('layouts.app')

@section('title', 'Audit des Contraintes')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-person-lines-fill text-primary me-2"></i>Audit des Affectations</div>
            </div>
            <div class="mb-4">
                <p class="mb-1 text-muted small">Moyenne d'étudiants par Professeur (norme : 3 à 4)</p>
                <h3 class="mb-0 {{ ($moyenneEncadrement >= 3 && $moyenneEncadrement <= 4) ? 'text-success' : 'text-warning' }}">
                    {{ $moyenneEncadrement }} étudiants/prof
                </h3>
            </div>

            @if(count($affectationAnomalies) > 0)
                <div class="alert alert-warning mb-0">
                    <h6 class="alert-heading fw-bold">Anomalies Détectées ({{ count($affectationAnomalies) }})</h6>
                    <hr class="mt-2 mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach($affectationAnomalies as $anomalie)
                            <li class="mb-2">
                                <strong>{{ $anomalie['type'] }} :</strong><br>
                                <span class="small">{{ $anomalie['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Aucune anomalie d'affectation détectée. La répartition est équitable.
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-calendar-x text-primary me-2"></i>Audit du Planning</div>
            </div>
            <p class="text-muted small mb-4">
                Contrôles effectués : chevauchements de salles, doubles assignations horaires, respect de l'heure de repos.
            </p>

            @if(count($planningAnomalies) > 0)
                <div class="alert alert-danger mb-0">
                    <h6 class="alert-heading fw-bold">Anomalies Détectées ({{ count($planningAnomalies) }})</h6>
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
