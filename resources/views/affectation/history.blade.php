@extends('layouts.app')

@section('title', 'Historique Affectation')

@section('content')
<div class="page-actions">
    <div class="section-subtitle">Snapshots d'affectation enregistrés.</div>
    <a href="{{ route('affectation.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
        Retour
    </a>
</div>

<div class="table-card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Date</th>
                    <th>Étudiants</th>
                    <th>Télécharger</th>
                </tr>
            </thead>
            <tbody>
                @forelse($snapshots as $snap)
                    <tr>
                        <td>{{ $snap->label }}</td>
                        <td class="text-muted">{{ $snap->created_at->format('d/m/Y à H:i') }}</td>
                        <td><span class="badge badge-ok">{{ $snap->etudiants_count }} étudiants</span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('snapshot.download', ['type'=>'affectation','id'=>$snap->id,'format'=>'pdf']) }}" class="btn btn-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    PDF
                                </a>
                                <a href="{{ route('snapshot.download', ['type'=>'affectation','id'=>$snap->id,'format'=>'word']) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-file-earmark-word"></i>
                                    Word
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">Aucun historique disponible.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
