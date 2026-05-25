<?php

namespace App\Services;

use App\Models\Enseignant;
use Illuminate\Support\Str;

class PdfExportService
{
    /**
     * Filiere color codes used consistently in PDF, Word and the PV
     * dashboard. Keep these in sync with the values produced by
     * applyFiliereColor() so all three surfaces match.
     */
    public const COLOR_TDIA = '#C6EFCE'; // green
    public const COLOR_ID   = '#F4B183'; // orange
    public const COLOR_GI   = '#BDD7EE'; // blue
    public const COLOR_NONE = '#ffffff';

    public function __construct() {}

    /**
     * Map a filiere label (short code, accented full name, or anything
     * the unified import produced) to its background color.
     *
     * The function is robust against:
     *   - Short codes "GI", "ID", "TDIA" produced by the unified import
     *   - Accented full names ("Génie Informatique", "Ingénierie des
     *     Données", "Transformation Digitale & Intelligence Artificielle")
     *   - Mixed casing and stray whitespace
     */
    public static function applyFiliereColor(string $filiere): string
    {
        $normalized = strtoupper(Str::ascii(trim($filiere)));

        if ($normalized === '') {
            return self::COLOR_NONE;
        }

        // TDIA — green (check first so 'ID' inside 'TDIA' does not steal it)
        if ($normalized === 'TDIA'
            || str_contains($normalized, 'TRANSFORM')
            || str_contains($normalized, 'ARTIFIC')
            || str_contains($normalized, 'INTELLIGENCE')) {
            return self::COLOR_TDIA;
        }

        // ID — orange (Ingénierie des Données)
        if ($normalized === 'ID'
            || str_contains($normalized, 'INGENIERIE')
            || str_contains($normalized, 'DONNEES')
            || str_contains($normalized, 'DONN')) {
            return self::COLOR_ID;
        }

        // GI — blue (Génie Informatique). Checked AFTER ID so "Ingénierie
        // Informatique" (if it ever appears) lands on ID, not GI.
        if ($normalized === 'GI'
            || str_contains($normalized, 'GENIE INFORMATIQUE')
            || str_contains($normalized, 'INFORMATIQUE')
            || $normalized === 'GENIE') {
            return self::COLOR_GI;
        }

        return self::COLOR_NONE;
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
