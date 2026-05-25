@extends('layouts.app')

@section('title', 'Gestion des Salles')

@push('styles')
<style>
    .salle-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .salle-card { background: #fff; border: 1px solid var(--border); padding: 18px 20px; border-radius: var(--radius); display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-soft); }
    .salle-name { font-weight: 600; color: var(--heading); }
    @media (max-width: 900px) { .salle-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 576px) { .salle-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="page-actions">
    <div class="section-subtitle">{{ $salles->count() }} salle(s) enregistrée(s)</div>
    <button class="btn btn-primary" onclick="openSalleModal()">
        <i class="bi bi-plus-lg"></i>
        Ajouter une Salle
    </button>
</div>

<div class="salle-grid">
    @forelse($salles as $salle)
        <div class="salle-card">
            <div class="salle-name"><i class="bi bi-building me-2 text-primary"></i>{{ $salle->nom }}</div>
            <form action="{{ route('salles.destroy', $salle->id) }}" method="POST" onsubmit="return confirm('Supprimer {{ $salle->nom }} ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Supprimer">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    @empty
        <div class="card text-center text-muted py-5" style="grid-column: 1 / -1;">
            Aucune salle enregistrée pour le moment.
        </div>
    @endforelse
</div>

<div class="app-modal-overlay" id="salleModal" aria-hidden="true">
    <div class="app-modal-box">
        <h3 class="h5 fw-bold mb-3">Ajouter une Salle</h3>
        <form action="{{ route('salles.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold">Nom de la salle</label>
                <input type="text" name="nom" class="form-control" placeholder="ex: Salle 6 NB" value="{{ old('nom') }}">
                @error('nom')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="closeSalleModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openSalleModal() {
        document.getElementById('salleModal').classList.add('is-open');
    }

    function closeSalleModal() {
        document.getElementById('salleModal').classList.remove('is-open');
    }

    @if($errors->any())
        openSalleModal();
    @endif
</script>
@endpush
