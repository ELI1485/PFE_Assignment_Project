@extends('layouts.app')

@section('title', 'Historique Planning')

@push('styles')
<style>
    .history-config {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }
    .history-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--ensah-blue-soft);
        color: var(--ensah-blue);
        font-size: 0.74rem;
        font-weight: 600;
    }
    .history-chip.warn { background: #fef3c7; color: #b45309; }
    .history-chip.muted { background: #eef2f7; color: var(--muted); }
</style>
@endpush

@section('content')
<div class="page-actions">
    <div class="section-subtitle">Snapshots de planning générés.</div>
    <a href="{{ route('planning.results') }}" class="btn btn-outline-secondary btn-sm">
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
                    <th>Date Génération</th>
                    <th>Soutenances</th>
                    <th>Configuration</th>
                    <th>Télécharger</th>
                </tr>
            </thead>
            <tbody>
                @forelse($snapshots as $snap)
                    @php $cfg = is_array($snap->config ?? null) ? $snap->config : null; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $snap->label }}</td>
                        <td class="text-muted">{{ $snap->created_at->format('d/m/Y à H:i') }}</td>
                        <td><span class="badge badge-gi">{{ $snap->soutenances_count }} soutenances</span></td>
                        <td>
                            @if($cfg)
                                <div class="history-config">
                                    @if(!empty($cfg['date_debut']))
                                        <span class="history-chip">
                                            <i class="bi bi-calendar3"></i>
                                            {{ \Illuminate\Support\Carbon::parse($cfg['date_debut'])->format('d/m/Y') }}
                                        </span>
                                    @endif
                                    @if(!empty($cfg['nb_jours']))
                                        <span class="history-chip muted">{{ $cfg['nb_jours'] }} jours</span>
                                    @endif
                                    @if(!empty($cfg['creneau_duree']))
                                        <span class="history-chip muted">{{ $cfg['creneau_duree'] }} min</span>
                                    @endif
                                    @if(!empty($cfg['nb_jurys']))
                                        <span class="history-chip muted">jury de {{ $cfg['nb_jurys'] }}</span>
                                    @endif
                                    @if(!empty($cfg['dates_exclues']))
                                        <span class="history-chip warn" title="{{ implode(', ', array_map(fn($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'), $cfg['dates_exclues'])) }}">
                                            {{ count($cfg['dates_exclues']) }} date(s) exclue(s)
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('snapshot.download', ['type'=>'planning','id'=>$snap->id,'format'=>'pdf']) }}" class="btn btn-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    PDF
                                </a>
                                <a href="{{ route('snapshot.download', ['type'=>'planning','id'=>$snap->id,'format'=>'word']) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-file-earmark-word"></i>
                                    Word
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">Aucun historique disponible.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
