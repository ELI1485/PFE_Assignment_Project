<?php

namespace App\Services;

use App\Models\Enseignant;

class PdfExportService
{
    public function __construct() {}

    public static function applyFiliereColor(string $filiere): string
    {
        $exact = [
            'Transformation Digitale & Intelligence Artificielle' => '#C6EFCE',
            'Ingénierie des Données' => '#F4B183',
            'Génie Informatique' => '#BDD7EE',
        ];
        if (isset($exact[$filiere])) {
            return $exact[$filiere];
        }

        if (strpos($filiere, 'Transformation') !== false || strpos($filiere, 'TDIA') !== false) {
            return '#C6EFCE';
        }
        if (strpos($filiere, 'Ing') !== false || strpos($filiere, 'Donn') !== false) {
            return '#F4B183';
        }
        if (strpos($filiere, 'nie') !== false || strpos($filiere, 'Informatique') !== false) {
            return '#BDD7EE';
        }

        return '#ffffff';
    }


    public static function getProfessorColor(string $name): string
    {
        $palette = [
            '#0000FF', '#FF0000', '#008000', '#FFD700',
            '#D0006F', '#00FFFF', '#FFA500', '#7F00FF',
            '#4169E1', '#FF69B4', '#BFFF00', '#8B4513',
            '#7FFFD4', '#E0115F', '#EAA221', '#00A86B',
            '#007FFF', '#FA8072', '#808000', '#C8A2C8',
            '#FF7518', '#40E0D0', '#A83C09', '#E6E6FA',
            '#808080', '#FF7F50', '#8F9779', '#FF00FF',
            '#C19A6B', '#E0B0FF', '#CD7F32', '#BDB76B',
        ];

        $profs = Enseignant::select('nom', 'prenom')->get()
            ->map(fn ($e) => trim(strtoupper($e->nom.' '.$e->prenom)))
            ->unique()
            ->values();

        $mapping = [];
        foreach ($profs as $i => $p) {
            $mapping[$p] = $palette[$i % count($palette)];
        }

        $cleanName = trim(strtoupper(preg_replace('/^(?:D|P)r\.\s*/i', '', $name)));

        return $mapping[$cleanName] ?? '#ffffff';
    }
}
