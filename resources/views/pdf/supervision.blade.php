<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste d'Encadrement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <h2>Liste d'Encadrement par Enseignant</h2>
    <table>
        <thead>
            <tr>
                <th>Enseignant</th>
                <th>Étudiant(s) Encadré(s)</th>
                <th>Filière</th>
            </tr>
        </thead>
        <tbody>
            @foreach($enseignants as $enseignant)
                @if($enseignant->projets->isNotEmpty())
                    @foreach($enseignant->projets as $index => $projet)
                        <tr>
                            @if($index === 0)
                                <td rowspan="{{ $enseignant->projets->count() }}">
                                    Pr. {{ $enseignant->nom }} {{ $enseignant->prenom }}
                                </td>
                            @endif
                            <td>
                                @if($projet->etudiant)
                                    {{ $projet->etudiant->nom }} {{ $projet->etudiant->prenom }}
                                    @if($projet->etudiant2)
                                        <br>{{ $projet->etudiant2->nom }} {{ $projet->etudiant2->prenom }}
                                    @endif
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $projet->etudiant?->filiere?->nom ?? '-' }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
