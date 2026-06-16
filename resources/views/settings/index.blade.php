@extends('layouts.app')

@section('title', 'Paramètres')

@push('styles')
<style>
    .settings-grid { display: grid; grid-template-columns: 1fr; gap: 22px; }
    .filiere-row td { vertical-align: middle; }
    .color-dot { display: inline-block; width: 22px; height: 22px; border-radius: 6px; border: 1px solid rgba(0,0,0,.12); vertical-align: middle; }
    .color-swatch-input { width: 46px; height: 34px; padding: 2px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; }
    .settings-card-title { font-weight: 600; color: var(--heading); display: flex; align-items: center; gap: 8px; }
    .settings-hint { color: var(--muted, #6b7280); font-size: .82rem; }
    .filiere-count-badge { background: #eef2f7; color: #334155; border-radius: 20px; padding: 2px 10px; font-size: .78rem; font-weight: 600; }
</style>
@endpush

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="settings-grid">

    {{-- ─── En-tête des documents ─────────────────────────────────────── --}}
    <div class="card">
        <div class="settings-card-title mb-1"><i class="bi bi-building text-primary"></i> En-tête des documents</div>
        <div class="settings-hint mb-3">Ces informations apparaissent en haut des exports PDF, Word et des PV générés.</div>
        <form action="{{ route('settings.config') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Établissement</label>
                    <input type="text" name="etablissement" class="form-control" value="{{ old('etablissement', $config['etablissement']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Département</label>
                    <input type="text" name="departement" class="form-control" value="{{ old('departement', $config['departement']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Session</label>
                    <input type="text" name="session" class="form-control" value="{{ old('session', $config['session']) }}" placeholder="ex: Première Session">
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>

    {{-- ─── Filières & couleurs ───────────────────────────────────────── --}}
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="settings-card-title"><i class="bi bi-palette text-primary"></i> Filières &amp; couleurs</div>
            <button class="btn btn-primary btn-sm" onclick="openFiliereModal()"><i class="bi bi-plus-lg me-1"></i> Ajouter une filière</button>
        </div>
        <div class="settings-hint mb-3">Les couleurs sont utilisées dans les tableaux, graphiques et exports. Les filières sont aussi créées automatiquement lors de l'import Excel.</div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px;">Couleur</th>
                        <th>Filière</th>
                        <th>Nom complet</th>
                        <th class="text-center" style="width:120px;">Étudiants</th>
                        <th class="text-end" style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filieres as $filiere)
                        <tr class="filiere-row">
                            <td><input type="color" name="couleur" value="{{ $filiere->couleur }}" class="color-swatch-input" form="filiere-form-{{ $filiere->id }}"></td>
                            <td><input type="text" name="nom" value="{{ $filiere->nom }}" class="form-control form-control-sm" style="min-width:120px;" form="filiere-form-{{ $filiere->id }}"></td>
                            <td><input type="text" name="nom_complet" value="{{ $filiere->nom_complet }}" class="form-control form-control-sm" placeholder="—" form="filiere-form-{{ $filiere->id }}"></td>
                            <td class="text-center"><span class="filiere-count-badge">{{ $filiere->etudiants_count }}</span></td>
                            <td class="text-end">
                                <button type="submit" class="btn btn-outline-primary btn-sm" form="filiere-form-{{ $filiere->id }}" title="Enregistrer"><i class="bi bi-check-lg"></i></button>
                                <button type="submit" class="btn btn-outline-danger btn-sm" form="filiere-delete-{{ $filiere->id }}" title="Supprimer" {{ $filiere->etudiants_count > 0 ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune filière enregistrée. Elles seront créées automatiquement lors d'un import Excel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Standalone forms referenced by the row inputs/buttons via the HTML5 form attribute --}}
        @foreach($filieres as $filiere)
            <form action="{{ route('settings.filieres.update', $filiere->id) }}" method="POST" id="filiere-form-{{ $filiere->id }}">@csrf @method('PUT')</form>
            <form action="{{ route('settings.filieres.destroy', $filiere->id) }}" method="POST" id="filiere-delete-{{ $filiere->id }}" onsubmit="return confirm('Supprimer la filière « {{ $filiere->nom }} » ?')">@csrf @method('DELETE')</form>
        @endforeach
    </div>
</div>

{{-- ─── Modal ajout filière ───────────────────────────────────────── --}}
<div class="app-modal-overlay" id="filiereModal" aria-hidden="true">
    <div class="app-modal-box">
        <h3 class="h5 fw-bold mb-3">Ajouter une filière</h3>
        <form action="{{ route('settings.filieres.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Nom (code) de la filière</label>
                <input type="text" name="nom" class="form-control" placeholder="ex: GI, ID, TDIA…" value="{{ old('nom') }}">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Nom complet <span class="settings-hint">(optionnel)</span></label>
                <input type="text" name="nom_complet" class="form-control" placeholder="ex: Génie Informatique" value="{{ old('nom_complet') }}">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Couleur <span class="settings-hint">(laisser vide pour une couleur auto)</span></label><br>
                <input type="color" name="couleur" class="color-swatch-input" value="{{ old('couleur', '#E0E0E0') }}">
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="closeFiliereModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openFiliereModal() { document.getElementById('filiereModal').classList.add('is-open'); }
    function closeFiliereModal() { document.getElementById('filiereModal').classList.remove('is-open'); }
</script>
@endpush
