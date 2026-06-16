<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planning des Soutenances</title>
    <style>
        @page { margin: 30px 40px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }
        
        .header { text-align: center; margin-bottom: 15px; }
        .header h1 { font-size: 14px; margin: 0 0 3px 0; }
        .header h2 { font-size: 12px; font-weight: normal; margin: 0 0 3px 0; }
        .header h3 { font-size: 11px; font-weight: normal; margin: 0 0 3px 0; }
        .header .session { font-size: 10px; font-style: italic; margin: 0 0 3px 0; }
        .header .annee { font-size: 10px; margin: 0; }

        table { width: 100%; border-collapse: separate; border-spacing: 1px; background-color: #000; margin-top: 10px; }
        th {
            background-color: #000; color: #fff; padding: 6px 4px;
            text-align: left; font-size: 8px; font-weight: bold;
        }
        td { background-color: #fff; padding: 5px 4px; vertical-align: middle; }
        
        .date-cell { background-color: #FFFF00 !important; font-weight: bold; }
        .alternating-cell { background-color: #DDEBF7; }
        .white-cell { background-color: #ffffff; }
    </style>
</head>
<body>
    @php
        $schoolName     = $schoolName     ?? \App\Models\Configuration::get('school_name');
        $departmentName = $departmentName ?? \App\Models\Configuration::get('department_name');
        $sessionName    = $sessionName    ?? 'Première Session';
        $logoSrc        = $logoSrc        ?? \App\Models\Configuration::logoDataUri();
    @endphp
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="logo" style="max-height:60px; margin-bottom:6px;">
        @endif
        <h1>{{ $schoolName }}</h1>
        <h2>{{ $departmentName }}</h2>
        <h3>Planning des soutenances des Projets de Fin d'Etude</h3>
        <div class="session">({{ $sessionName }})</div>
        <div class="annee">Année Universitaire {{ $anneeUniversitaire ?? (date('n') < 9 ? (date('Y') - 1) . '/' . date('Y') : date('Y') . '/' . (date('Y') + 1)) }}</div>
    </div>

    @php
        // Determine the max number of jury members (examinateurs) across all rows
        $maxJuryMembers = $rows->max(fn($row) => count($row['examinateurs'] ?? []));
        $maxJuryMembers = max(2, $maxJuryMembers); // At least 2 columns
    @endphp

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Encadrant</th>
                @for($j = 1; $j <= $maxJuryMembers; $j++)
                    <th>Membre de jury {{ $j }}</th>
                @endfor
                <th>Date</th>
                <th>Heure</th>
                <th>Salle</th>
                <th>Nom d'étudiant</th>
                <th>Prénom d'étudiant</th>
                <th>Filière</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
            @php 
                $fRaw = $row['filiere'] ?? '';
                $filiereColor = $row['filiere_color'] ?? \App\Services\PdfExportService::applyFiliereColor($fRaw);
                
                $encadrant = $row['encadrant'] ?? '';
                $encColor = \App\Services\PdfExportService::getProfessorColor($encadrant);

                $examinateurs = $row['examinateurs'] ?? [];

                $bgBase = ($i % 2 === 0) ? '#ffffff' : '#DDEBF7';
                
                $hasBinome = !empty($row['etudiant2_nom']);
                $rowspan = $hasBinome ? 2 : 1;
                
                $filiereText = $fRaw;
                $dateColor = \App\Services\ColorService::dateColor($row['date'] ?? null);
            @endphp
            <tr>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $encColor }}; text-align: center; font-weight:bold;">{{ $i+1 }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $encColor }}; font-weight: bold;">{{ preg_replace('/^(?:D|P)r\.\s*/i', '', $encadrant) }}</td>
                @for($j = 0; $j < $maxJuryMembers; $j++)
                    @php
                        $juryMember = $examinateurs[$j] ?? '';
                        $jColor = \App\Services\PdfExportService::getProfessorColor($juryMember);
                    @endphp
                    <td rowspan="{{ $rowspan }}" style="background-color: {{ $jColor }}; font-weight: bold;">{{ preg_replace('/^(?:D|P)r\.\s*/i', '', $juryMember) }}</td>
                @endfor
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $dateColor }}; font-weight: bold;">{{ $row['date'] ?? '' }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $bgBase }};">{{ $row['heure_debut'] ?? '' }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $bgBase }}; font-weight: bold;">{{ $row['salle'] ?? '' }}</td>
                <td style="background-color: {{ $filiereColor }};">{{ strtoupper($row['etudiant_nom'] ?? '') }}</td>
                <td style="background-color: {{ $filiereColor }};">{{ $row['etudiant_prenom'] ?? '' }}</td>
                <td style="background-color: {{ $filiereColor }}; font-weight: bold; text-align: center;">{{ $filiereText }}</td>
            </tr>
            @if($hasBinome)
            <tr>
                <td style="background-color: {{ $filiereColor }};">{{ strtoupper($row['etudiant2_nom'] ?? '') }}</td>
                <td style="background-color: {{ $filiereColor }};">{{ $row['etudiant2_prenom'] ?? '' }}</td>
                <td style="background-color: {{ $filiereColor }}; font-weight: bold; text-align: center;">{{ $filiereText }}</td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
