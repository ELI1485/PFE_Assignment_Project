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
    <div class="header">
        <h1>Ecole Nationale des Sciences Appliquées - Al Hoceima</h1>
        <h2>Département Mathématiques et Informatique</h2>
        <h3>Planning des soutenances des Projets de Fin d'Etude</h3>
        <div class="session">(Première Session)</div>
        <div class="annee">Année Universitaire {{ $anneeUniversitaire ?? (date('n') < 9 ? (date('Y') - 1) . '/' . date('Y') : date('Y') . '/' . (date('Y') + 1)) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Encadrant</th>
                <th>Membre de jury 1</th>
                <th>Membre de jury 2</th>
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
                $f = strtoupper($fRaw); 
                $filiereColor = \App\Services\PdfExportService::applyFiliereColor($fRaw);
                
                $encadrant = $row['encadrant'] ?? '';
                $encColor = \App\Services\PdfExportService::getProfessorColor($encadrant);
                
                $jury1 = $row['examinateurs'][0] ?? '';
                $j1Color = \App\Services\PdfExportService::getProfessorColor($jury1);
                
                $jury2 = $row['examinateurs'][1] ?? '';
                $j2Color = \App\Services\PdfExportService::getProfessorColor($jury2);

                $bgBase = ($i % 2 === 0) ? '#ffffff' : '#DDEBF7';
                
                $hasBinome = !empty($row['etudiant2_nom']);
                $rowspan = $hasBinome ? 2 : 1;
                
                $filiereText = '';
                if(str_contains($f,'TDIA') || str_contains($f,'TRANSFORM') || str_contains($f,'ARTIFIC')) $filiereText = 'TDIA';
                elseif(str_contains($f,'ING') && str_contains($f,'DONN') || $f === 'ID') $filiereText = 'ID';
                elseif(str_contains($f,'G') && str_contains($f,'NIE') || str_contains($f,'INFORMATIQUE') || $f === 'GI') $filiereText = 'GI';
                else $filiereText = $fRaw;
            @endphp
            <tr>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $encColor }}; text-align: center; font-weight:bold;">{{ $i+1 }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $encColor }}; font-weight: bold;">{{ preg_replace('/^(?:D|P)r\.\s*/i', '', $encadrant) }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $j1Color }}; font-weight: bold;">{{ preg_replace('/^(?:D|P)r\.\s*/i', '', $jury1) }}</td>
                <td rowspan="{{ $rowspan }}" style="background-color: {{ $j2Color }}; font-weight: bold;">{{ preg_replace('/^(?:D|P)r\.\s*/i', '', $jury2) }}</td>
                <td rowspan="{{ $rowspan }}" class="date-cell">{{ $row['date'] ?? '' }}</td>
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
