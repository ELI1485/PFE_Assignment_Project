@extends('layouts.app')

@section('title', 'Tableau de bord')

@push('styles')
<style>
    .stat-card {
        height: 100%;
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .stat-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        flex: 0 0 auto;
    }

    .stat-icon.students { background: #edf4ff; color: #3159c5; }
    .stat-icon.teachers { background: #eef8f7; color: #0f8d82; }
    .stat-icon.planning { background: #f0f8ef; color: #238356; }
    .stat-value { color: var(--heading); font-size: 2rem; font-weight: 700; line-height: 1; }
    .stat-label { color: var(--muted); font-size: 0.84rem; margin-top: 5px; }
    .chart-wrap { position: relative; height: 260px; }
</style>
@endpush

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon students"><i class="bi bi-mortarboard-fill"></i></div>
            <div>
                <div class="stat-value">{{ $stats['total_etudiants'] }}</div>
                <div class="stat-label">Étudiants importés</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon teachers"><i class="bi bi-person-badge-fill"></i></div>
            <div>
                <div class="stat-value">{{ $stats['total_enseignants'] }}</div>
                <div class="stat-label">Enseignants / Encadrants</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon planning"><i class="bi bi-calendar2-check-fill"></i></div>
            <div>
                <div class="stat-value">{{ $stats['total_soutenances'] }}</div>
                <div class="stat-label">Soutenances planifiées</div>


            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title">Répartition par Filière</div>
            </div>
            <div class="chart-wrap">
                <canvas id="filiereChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title">Étudiants par Encadrant</div>
            </div>
            <div class="table-responsive" style="max-height: 260px;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Encadrant</th>
                            <th>Étudiants</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parEncadrant as $prof)
                            <tr>
                                <td>{{ $prof->nom }} {{ $prof->prenom }}</td>
                                <td><span class="badge badge-other">{{ $prof->projets_count }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">Aucune affectation</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Participations Jury par Enseignant</div>
    </div>
    <div class="chart-wrap" style="height: 320px;">
        @if($parJury->isEmpty())
            <div class="text-center text-muted py-5">Aucun jury généré</div>
        @else
            <canvas id="juryChart"></canvas>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('filiereChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($parFiliere->keys()) !!},
        datasets: [{
            label: 'Étudiants',
            data: {!! json_encode($parFiliere->values()) !!},
            backgroundColor: ['#3f67d5', '#24b57a', '#f4b23d', '#3cb7c7', '#d94b55'],
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#edf1f6' } },
            x: { grid: { display: false } }
        }
    }
});

@if($parJury->isNotEmpty())
const juryCtx = document.getElementById('juryChart');
const profLabels = {!! json_encode($parJury->map(fn($p) => $p->nom . ' ' . substr($p->prenom, 0, 1) . '.')->values()) !!};
const profData = {!! json_encode($parJury->pluck('jurys_count')->values()) !!};

new Chart(juryCtx, {
    type: 'bar',
    data: {
        labels: profLabels,
        datasets: [{
            label: 'Participations Jury',
            data: profData,
            backgroundColor: '#24b57a',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#edf1f6' } },
            x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 45, font: { size: 10 } }, grid: { display: false } }
        }
    }
});
@endif
</script>
@endpush
