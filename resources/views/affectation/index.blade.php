@extends('layouts.app')

@section('title', 'Affectation Encadrants')

@push('styles')
<style>
    .affectation-layout {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 24px;
        align-items: start;
    }

    .action-topbar {
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .action-topbar-title { color: var(--heading); font-size: 1.05rem; font-weight: 600; }
    .action-topbar-sub { color: var(--muted); font-size: 0.84rem; margin-top: 4px; }
    .action-topbar-btns { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .prof-panel { padding: 20px; }
    .prof-panel h3 { color: var(--heading); font-size: 0.95rem; font-weight: 600; margin-bottom: 14px; }
    .prof-list { display: flex; flex-direction: column; gap: 8px; max-height: 520px; overflow-y: auto; }
    .prof-item { display: flex; align-items: center; gap: 10px; padding: 9px 8px; border-bottom: 1px solid #edf1f6; color: var(--text); font-size: 0.86rem; }
    .prof-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--ensah-blue-soft); color: var(--ensah-blue); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
    .student-name { font-weight: 600; color: var(--heading); }
    .student-sub { color: var(--muted); font-size: 0.78rem; margin-top: 3px; }

    @media (max-width: 900px) {
        .affectation-layout { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
@include('partials.planning-start-modal')

<div class="action-topbar">
    <div>
        <div class="action-topbar-title">Affectation Encadrants</div>
        <div class="action-topbar-sub">Répartition équilibrée des encadrants sur les projets importés.</div>
    </div>
    <div class="action-topbar-btns">
        <a href="{{ route('affectation.history') }}" class="btn btn-outline-secondary btn-sm">Historique</a>
        <form action="{{ route('affectation.run') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                {{ $hasSnapshot ? "Relancer l'affectation" : "Lancer l'affectation" }}
            </button>
        </form>
        <button type="button" class="btn btn-success" onclick="openPlanningModal()">
            <i class="bi bi-calendar-week"></i>
            Générer le planning
        </button>
        @if ($hasSnapshot)
            <a href="{{ route('export.affectation') }}" class="btn btn-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i>
                PDF
            </a>
            <a href="{{ route('export.affectation.word') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-word"></i>
                Word
            </a>
        @endif
    </div>
</div>

<div class="affectation-layout">
    <aside class="prof-panel">
        <h3>Enseignants</h3>
        <div class="prof-list">
            @forelse($enseignants as $ens)
                <div class="prof-item">
                    <span class="prof-avatar">{{ strtoupper(substr($ens->nom, 0, 1)) }}</span>
                    <span>{{ $ens->nom }} {{ $ens->prenom }}</span>
                </div>
            @empty
                <p class="text-muted small mb-0">Aucun enseignant importé.</p>
            @endforelse
        </div>
    </aside>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Liste des étudiants - Affectation Encadrants</div>
            <span class="text-muted small">{{ $etudiants->count() }} étudiant(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Étudiant(s)</th>
                        <th>Filière</th>
                        <th>Encadrant assigné</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projets as $i => $projet)
                        @php
                            $f = mb_strtoupper($projet->etudiant->filiere ?? '', 'UTF-8');
                            $filiereCode = 'other';
                            if (str_contains($f, 'TDIA') || str_contains($f, 'TRANSFORM') || str_contains($f, 'ARTIFIC')) {
                                $filiereCode = 'tdia';
                            } elseif (str_contains($f, 'INGÉNIERIE') || str_contains($f, 'INGENIERIE') || str_contains($f, 'DONNÉES') || str_contains($f, 'DONNEES') || str_contains($f, 'ID')) {
                                $filiereCode = 'id';
                            } elseif (str_contains($f, 'GÉNIE') || str_contains($f, 'GENIE') || str_contains($f, 'GI')) {
                                $filiereCode = 'gi';
                            }
                        @endphp
                        <tr>
                            <td class="text-muted small">#{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                @if ($projet->etudiant)
                                    <div class="student-name">{{ $projet->etudiant->nom }} {{ $projet->etudiant->prenom }}</div>
                                    <div class="student-sub">{{ $projet->etudiant->cne }}</div>
                                @endif
                                @if ($projet->etudiant2)
                                    <div class="student-name mt-2 pt-1 border-top">{{ $projet->etudiant2->nom }} {{ $projet->etudiant2->prenom }}</div>
                                    <div class="student-sub">{{ $projet->etudiant2->cne }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($filiereCode === 'tdia')
                                    <span class="badge badge-tdia">TDIA</span>
                                @elseif($filiereCode === 'gi')
                                    <span class="badge badge-gi">GI</span>
                                @elseif($filiereCode === 'id')
                                    <span class="badge badge-id">ID</span>
                                @else
                                    <span class="badge badge-other">{{ $projet->etudiant->filiere ?? '-' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($projet->encadrant)
                                    Pr. {{ $projet->encadrant->nom }} {{ $projet->encadrant->prenom }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($projet->encadrant)
                                    <span class="badge badge-ok">Affecté</span>
                                @else
                                    <span class="badge badge-none">Non affecté</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                Aucun projet importé. <a href="{{ route('import.form') }}" class="text-primary">Importer un fichier Excel</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
