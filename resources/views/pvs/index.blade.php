@extends('layouts.app')

@section('title', 'Génération des PVs')

@push('styles')
    <style>
        .pv-badge {
            font-size: 0.78rem;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 700;
            display: inline-block;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    <div class="page-actions">
        <div class="section-subtitle">Documents de PV disponibles pour les soutenances planifiées.</div>
        <a href="{{ route('pv.downloadAll') }}" class="btn btn-primary">
            <i class="bi bi-file-zip"></i>
            Télécharger Tous (Archive ZIP)
        </a>
    </div>

    <div class="table-card p-4">
        <div class="table-responsive">
            <table id="pvTable" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 5%">#</th>
                        <th>Étudiant</th>
                        <th>Filière</th>
                        <th>Président (Encadrant)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($soutenances as $soutenance)
                        @php
                            $etudiant = $soutenance->projet?->etudiant;
                            $encadrant = $soutenance->projet?->encadrant;
                            $filiere = $etudiant?->filiere ?? '';
                            $bgColor = \App\Services\PdfExportService::applyFiliereColor($filiere);
                            $textColor = '#000000';

                            if (in_array($bgColor, ['#BDD7EE'])) {
                                $textColor = '#0F172A';
                            }

                            if (in_array($bgColor, ['#C6EFCE'])) {
                                $textColor = '#065F46';
                            }

                            if (in_array($bgColor, ['#F4B183'])) {
                                $textColor = '#7C2D12';
                            }
                        @endphp

                        <tr>
                            <td class="text-center text-secondary fw-semibold" data-order="{{ $loop->iteration }}">
                                #P{{ sprintf('%02d', $loop->iteration) }}
                            </td>
                            <td>
                                @if ($etudiant)
                                    <div class="fw-bold text-dark">{{ strtoupper($etudiant->nom) }} {{ $etudiant->prenom }}</div>
                                    <div class="text-secondary" style="font-size: 0.85rem;">{{ $etudiant->cne ?? $etudiant->id }}</div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if ($etudiant)
                                    <span class="pv-badge"
                                        style="background-color: {{ $bgColor }}; color: {{ $textColor }};">
                                        {{ $filiere }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($encadrant)
                                    <span class="fw-semibold">Pr. {{ strtoupper($encadrant->nom) }}</span>
                                @else
                                    <span class="text-danger fw-semibold">Non assigné</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('pv.download', $soutenance->id) }}" class="btn btn-success btn-sm">
                                    <i class="bi bi-download"></i>
                                    Télécharger PV
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#pvTable').DataTable({
                order: [
                    [0, 'asc']
                ],
                columnDefs: [{
                    targets: 0,
                    render: function(data, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseInt(data.replace('#', ''));
                        }
                        return data;
                    }
                }],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                pageLength: 10,
                responsive: true
            });
        });
    </script>
@endpush
