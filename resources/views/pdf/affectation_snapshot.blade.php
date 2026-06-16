<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Affectation des Encadrants — PFE</title>
    <style>
        @page { margin: 40px 50px; } /* Explicit DomPDF page margins */
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 9px; 
            color: #1a1a1a; 
            margin: 0; 
            padding: 0; 
        }
        table, div, p { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Header ── */
        .header-box {
            border: 1.5px solid #333;
            width: 55%;
            margin: 0 auto 10px;
            padding: 8px 12px;
            text-align: center;
        }
        .header-box .school   { font-weight: bold; font-size: 10.5px; }
        .header-box .dept     { font-size: 9.5px; margin-top: 2px; }
        .header-box .doc-type { font-size: 9px;   margin-top: 2px; }
        .header-box .annee    { font-size: 9px;   margin-top: 2px; }

        /* ── Legend ── */
        .legend-table { border-collapse: collapse; margin-bottom: 25px; }
        .legend-table td { padding: 2px 6px 2px 2px; font-size: 8.5px; vertical-align: middle; }

        /* ── Main table ── */
        table.main {
            width: 100%;
            border-collapse: separate;
            border-spacing: 1px;
            background-color: #000000;
        }
        .th-main {
            background-color: #2F5496;
            color: white;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            padding: 5px 6px;
        }
        .th-sub {
            background-color: #D9E1F2;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            padding: 4px 4px;
            color: #1F2D6B;
        }
        td.data-cell {
            font-size: 8px;
            padding: 4px 5px;
            vertical-align: middle;
            border: none;
        }
        td.data-cell-bold {
            font-size: 8.5px;
            font-weight: bold;
            padding: 4px 5px;
            vertical-align: middle;
            border: none;
        }
    </style>
</head>
<body>

{{-- ─── Institution Header ─── --}}
@php
    $schoolName     = $schoolName     ?? \App\Models\Configuration::get('school_name');
    $departmentName = $departmentName ?? \App\Models\Configuration::get('department_name');
    $logoSrc        = $logoSrc        ?? \App\Models\Configuration::logoDataUri();
@endphp
<div class="header-box">
    @if($logoSrc)
        <img src="{{ $logoSrc }}" alt="logo" style="max-height:60px; margin-bottom:6px;">
    @endif
    <div class="school">{{ $schoolName }}</div>
    <div class="dept">{{ $departmentName }}</div>
    <div class="doc-type">Affectation des encadrants de Projet de Fin d'Etude</div>
    <div class="annee">Année Universitaire {{ date('n') < 9 ? (date('Y') - 1) . '/' . date('Y') : date('Y') . '/' . (date('Y') + 1) }}</div>
</div>

{{-- ─── Color Legend (dynamic — built from the filières present) ─── --}}
@php
    $legend = $rows
        ->filter(fn ($r) => !empty($r['filiere']))
        ->groupBy('filiere')
        ->map(fn ($items) => $items->first()['filiere_color'] ?? ($items->first()['bg'] ?? '#ffffff'));
@endphp
@if($legend->isNotEmpty())
<table class="legend-table">
    @foreach($legend as $filiereName => $color)
    <tr>
        <td><div style="width:26px;height:11px;background-color:{{ $color }};">&nbsp;</div></td>
        <td>Filière {{ $filiereName }}</td>
    </tr>
    @endforeach
</table>
@endif

{{-- ─── Table (bg pre-computed in controller, sorted alphabetically) ─── --}}
@php
    // Sort rows by enc_nom for alphabetical display, then group by professor
    $grouped = $rows->sortBy('enc_nom')->groupBy('encadrant');
@endphp

<table class="main">
    <thead>
        <tr>
            <th class="th-main" colspan="2">Encadrant</th>
            <th class="th-main" colspan="8">Etudiants encadrés</th>
        </tr>
        <tr>
            <th class="th-sub" style="width:9%">Nom</th>
            <th class="th-sub" style="width:9%">Prénom</th>
            <th class="th-sub" style="width:10%">Etudiant 1 — Nom</th>
            <th class="th-sub" style="width:10%">Prénom</th>
            <th class="th-sub" style="width:10%">Etudiant 2 — Nom</th>
            <th class="th-sub" style="width:10%">Prénom</th>
            <th class="th-sub" style="width:10%">Etudiant 3 — Nom</th>
            <th class="th-sub" style="width:10%">Prénom</th>
            <th class="th-sub" style="width:10%">Etudiant 4 — Nom</th>
            <th class="th-sub" style="width:10%">Prénom</th>
        </tr>
    </thead>
    <tbody>
        @foreach($grouped as $encadrant => $students)
            @php
                // Majority-vote bg color from all students in this professor's group
                $bgCounts = $students->countBy('bg');
                $bg       = $bgCounts->sortDesc()->keys()->first() ?? '#ffffff';

                $encNom    = $students->first()['enc_nom']    ?? '';
                $encPrenom = $students->first()['enc_prenom'] ?? '';
                if (empty($encNom) && $encadrant !== 'Non assigné') {
                    $p = explode(' ', $encadrant, 2);
                    $encNom    = $p[0] ?? $encadrant;
                    $encPrenom = $p[1] ?? '';
                }
                $chunks = $students->chunk(4);
            @endphp

            @foreach($chunks as $ci => $chunk)
                @php
                    $names = $chunk->values();
                    $etu = [];
                    for ($k = 0; $k < 4; $k++) {
                        $etu[] = [
                            'nom'    => $names[$k]['etu_nom']    ?? '',
                            'prenom' => $names[$k]['etu_prenom'] ?? '',
                            'nom2'    => $names[$k]['etu2_nom']    ?? '',
                            'prenom2' => $names[$k]['etu2_prenom'] ?? '',
                            'bg'     => $names[$k]['bg']         ?? '#ffffff',
                        ];
                    }
                @endphp
                <tr>
                    @if($ci === 0)
                        <td class="data-cell-bold" rowspan="{{ count($chunks) }}" style="background-color:#ffffff;">{{ strtoupper($encNom) }}</td>
                        <td class="data-cell" rowspan="{{ count($chunks) }}" style="background-color:#ffffff;">{{ $encPrenom }}</td>
                    @endif
                    @for($k = 0; $k < 4; $k++)
                        <td class="data-cell" style="background-color:{{ $etu[$k]['bg'] }};">
                            {{ strtoupper($etu[$k]['nom']) }}
                            @if(!empty($etu[$k]['nom2']))
                                <br>{{ strtoupper($etu[$k]['nom2']) }}
                            @endif
                        </td>
                        <td class="data-cell" style="background-color:{{ $etu[$k]['bg'] }};">
                            {{ $etu[$k]['prenom'] }}
                            @if(!empty($etu[$k]['prenom2']))
                                <br>{{ $etu[$k]['prenom2'] }}
                            @endif
                        </td>
                    @endfor
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

</body>
</html>
