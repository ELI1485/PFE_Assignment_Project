@extends('layouts.app')

@section('title', 'Planning Soutenances')

@push('styles')
<style>
    .side-card { padding: 18px; height: 100%; }
    .side-title { font-size: 0.85rem; font-weight: 700; color: var(--heading); margin-bottom: 14px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
    .prof-list { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; }
    .prof-item { padding: 10px 0; border-bottom: 1px solid #edf1f6; font-size: 0.86rem; color: var(--text); }
    .salle-mini-card { background: #f8fafc; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
    .filiere-badge { padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
    .planning-incomplete td:first-child { border-left: 3px solid #ef4444; }

    .config-summary {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 14px 18px;
        box-shadow: var(--shadow-soft);
    }
    .config-summary-title {
        color: var(--heading);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .config-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px 22px;
    }
    .config-summary-grid > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.86rem;
    }
    .config-label {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .config-value {
        color: var(--heading);
        font-weight: 600;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .config-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--ensah-blue-soft);
        color: var(--ensah-blue);
        font-size: 0.76rem;
        font-weight: 600;
    }
    .config-chip-warn {
        background: #fef3c7;
        color: #b45309;
    }
</style>
@endpush

@section('content')
@php
    $persistedDiag = null;
    if (\Illuminate\Support\Facades\Storage::exists('conformite_diagnostic.json')) {
        $persistedDiag = json_decode(\Illuminate\Support\Facades\Storage::get('conformite_diagnostic.json'), true);
    }
@endphp

@include('partials.planning-start-modal')

@if ($persistedDiag && ($persistedDiag['non_affectes'] ?? 0) > 0)
    <div class="alert alert-warning mb-4">
        Seulement <strong>{{ $persistedDiag['pct'] }}%</strong> des étudiants ont pu être planifiés
        (<strong>{{ $persistedDiag['affectes'] }}/{{ $persistedDiag['total'] }}</strong>).
        Consultez le <a href="{{ route('conformite.index') }}" class="alert-link fw-bold">Contrôle de Conformité</a> pour plus de détails.
    </div>
@endif

<div class="page-actions">
    <div>
        <div class="section-subtitle">Planning généré depuis le dernier snapshot.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('planning.history') }}" class="btn btn-outline-secondary btn-sm">Historique</a>
        <a href="{{ route('export.affectation') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i>
            PDF Supervision
        </a>
        <a href="{{ route('export.planning') }}" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf"></i>
            PDF Planning Général
        </a>
        <a href="{{ route('export.planning.word') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-word"></i>
            Word
        </a>
        <a href="{{ route('conformite.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-shield-check"></i>
            Contrôle de Conformité
        </a>
        <button type="button" class="btn btn-success" onclick="openPlanningModal()">
            <i class="bi bi-arrow-clockwise"></i>
            Relancer l'Algorithme
        </button>
    </div>
</div>

@php
    $usedConfig = $snapshot && is_array($snapshot->config ?? null) ? $snapshot->config : null;
@endphp
@if ($usedConfig)
    <div class="config-summary mb-4">
        <div class="config-summary-title">
            <i class="bi bi-sliders me-1"></i>
            Configuration utilisée pour cette génération
        </div>
        <div class="config-summary-grid">
            @if (!empty($usedConfig['date_debut']))
                <div><span class="config-label">Début</span><span class="config-value">{{ \Illuminate\Support\Carbon::parse($usedConfig['date_debut'])->format('d/m/Y') }}</span></div>
            @endif
            @if (!empty($usedConfig['nb_jours']))
                <div><span class="config-label">Jours</span><span class="config-value">{{ $usedConfig['nb_jours'] }}</span></div>
            @endif
            @if (!empty($usedConfig['creneau_duree']))
                <div><span class="config-label">Durée créneau</span><span class="config-value">{{ $usedConfig['creneau_duree'] }} min</span></div>
            @endif
            @if (!empty($usedConfig['nb_jurys']))
                <div><span class="config-label">Membres jury</span><span class="config-value">{{ $usedConfig['nb_jurys'] }}</span></div>
            @endif
            @if (!empty($usedConfig['slot_ranges']))
                <div><span class="config-label">Plages</span><span class="config-value">
                    @foreach ($usedConfig['slot_ranges'] as $start => $end)
                        <span class="config-chip">{{ $start }}–{{ $end }}</span>
                    @endforeach
                </span></div>
            @endif
            @if (!empty($usedConfig['dates_exclues']))
                <div><span class="config-label">Dates exclues</span><span class="config-value">
                    @foreach ($usedConfig['dates_exclues'] as $iso)
                        <span class="config-chip config-chip-warn">{{ \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') }}</span>
                    @endforeach
                </span></div>
            @endif
        </div>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="side-card">
            <div class="side-title"><i class="bi bi-people"></i> Professeurs</div>
            <ul class="prof-list">
                @forelse ($enseignants as $prof)
                    <li class="prof-item">{{ $prof->nom }} {{ $prof->prenom }}</li>
                @empty
                    <li class="prof-item text-muted">Aucun enseignant importé.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="side-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="side-title mb-0"><i class="bi bi-building"></i> Salles</div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openSalleModal()">
                    <i class="bi bi-plus-lg"></i>
                    Ajouter
                </button>
            </div>
            <div style="max-height:250px; overflow-y:auto;">
                @forelse ($salles as $salle)
                    <div class="salle-mini-card">
                        <span class="fw-semibold text-dark">{{ $salle->nom }}</span>
                        <form action="{{ route('salles.destroy', $salle->id) }}" method="POST" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted small">Aucune salle enregistrée.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Étudiant(s)</th>
                    <th>Filière</th>
                    <th>Encadrant (Président)</th>
                    <th>Examinateurs (Rapporteurs)</th>
                    <th>Date & Heure</th>
                    <th>Salle</th>
                </tr>
            </thead>
            <tbody>
                @forelse($soutenances as $index => $row)
                    @php
                        $filiere = mb_strtoupper($row['filiere'] ?? '', 'UTF-8');
                        $fShort = '-';
                        $fClass = 'f-other';
                        if (str_contains($filiere, 'TDIA') || str_contains($filiere, 'TRANSFORM') || str_contains($filiere, 'ARTIF') || str_contains($filiere, 'INTELLIGENCE')) {
                            $fShort = 'TDIA';
                            $fClass = 'f-tdia';
                        } elseif (str_contains($filiere, 'DONN') || ($filiere === 'ID') || (str_contains($filiere, 'ING') && str_contains($filiere, 'DONN'))) {
                            $fShort = 'ID';
                            $fClass = 'f-id';
                        } elseif (str_contains($filiere, 'GENIE') || str_contains($filiere, 'GÉNIE') || ($filiere === 'GI') || str_contains($filiere, 'INFORMATIQUE')) {
                            $fShort = 'GI';
                            $fClass = 'f-gi';
                        }
                        $nbRapporteurs = count($row['examinateurs'] ?? []);
                        $juryOk = $nbRapporteurs >= 1 && !empty($row['president']) && $row['president'] !== 'N/A';
                    @endphp
                    <tr class="{{ !$juryOk ? 'planning-incomplete table-warning' : '' }}">
                        <td class="fw-semibold text-muted">#P{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $row['etudiant_nom'] ?? '' }} {{ $row['etudiant_prenom'] ?? '' }}</div>
                            @if(!empty($row['etudiant2_nom']))
                                <div class="fw-semibold text-dark border-top mt-2 pt-2">{{ $row['etudiant2_nom'] }} {{ $row['etudiant2_prenom'] ?? '' }}</div>
                            @endif
                            <div class="text-muted small mt-1">Sujet: {{ $row['titre'] ?? '-' }}</div>
                        </td>
                        <td>
                            <span class="filiere-badge {{ $fClass }}">{{ $fShort !== '-' ? $fShort : ($filiere ?: '-') }}</span>
                        </td>
                        <td class="fw-semibold text-dark">
                            {{ $row['encadrant'] ?? '-' }}
                            @if(!empty($row['president']) && $row['president'] !== 'N/A' && $row['president'] !== ($row['encadrant'] ?? ''))
                                <div class="text-muted small fw-normal">Président jury: {{ $row['president'] }}</div>
                            @endif
                        </td>
                        <td class="text-muted">
                            @if ($nbRapporteurs >= 2)
                                @foreach ($row['examinateurs'] as $ex)
                                    <div>{{ $ex }}</div>
                                @endforeach
                            @elseif ($nbRapporteurs === 1)
                                @foreach ($row['examinateurs'] as $ex)
                                    <div>{{ $ex }}</div>
                                @endforeach
                                <span class="badge text-bg-warning">1 rapporteur manquant</span>
                            @else
                                <span class="badge text-bg-danger">Jury incomplet - 0 rapporteurs</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $row['date'] ?? '-' }}</div>
                            <div class="text-muted small">{{ $row['heure_debut'] ?? '' }} - {{ $row['heure_fin'] ?? '' }}</div>
                        </td>
                        <td class="text-muted">{{ $row['salle'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Aucune donnée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="app-modal-overlay" id="salleModal" aria-hidden="true">
    <div class="app-modal-box">
        <h3 class="h5 fw-bold mb-3">Ajouter une Salle</h3>
        <form action="{{ route('salles.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold">Nom de la salle</label>
                <input type="text" name="nom" class="form-control" value="{{ old('nom') }}" required>
                @error('nom')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="closeSalleModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

@if (session('planning_recommendation'))
    <div class="app-modal-overlay" id="recommendationModal" aria-hidden="true">
        <div class="app-modal-box" style="max-width: 560px;">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div style="width:48px;height:48px;border-radius:50%;background:#fef3c7;color:#b45309;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-lightbulb-fill" style="font-size:1.4rem;"></i>
                </div>
                <div>
                    <h3 class="h5 fw-bold mb-1">Recommandation du planificateur</h3>
                    <p class="text-muted small mb-0">
                        Le générateur n'a pas pu placer 100% des étudiants avec votre configuration.
                    </p>
                </div>
            </div>
            <div class="alert alert-warning mb-4" style="font-size:0.92rem; line-height:1.6;">
                {{ session('planning_recommendation') }}
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="closeRecommendationModal()">Fermer</button>
                <button type="button" class="btn btn-success" onclick="closeRecommendationModal(); openPlanningModal();">
                    <i class="bi bi-arrow-clockwise"></i>
                    Relancer avec plus de jours
                </button>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    function openSalleModal() {
        document.getElementById('salleModal').classList.add('is-open');
    }

    function closeSalleModal() {
        document.getElementById('salleModal').classList.remove('is-open');
    }

    function openRecommendationModal() {
        const modal = document.getElementById('recommendationModal');
        if (modal) modal.classList.add('is-open');
    }

    function closeRecommendationModal() {
        const modal = document.getElementById('recommendationModal');
        if (modal) modal.classList.remove('is-open');
    }

    @if($errors->has('nom'))
        openSalleModal();
    @endif

    @if(session('planning_recommendation'))
        document.addEventListener('DOMContentLoaded', function () {
            openRecommendationModal();
        });
    @endif
</script>
@endpush
