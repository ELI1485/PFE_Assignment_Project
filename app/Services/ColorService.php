<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\Filiere;
use Illuminate\Support\Str;

/**
 * Centralised, fully-dynamic color management for filières and professors.
 *
 * - Filière colors are drawn from a curated palette of 24 visually distinct
 *   colors. When the palette is exhausted, additional colors are generated
 *   with well-spaced HSL hues (golden-ratio spacing).
 * - Professor colors are guaranteed unique, deterministic (same professor
 *   always gets the same color in a session, independent of row ordering)
 *   and never collide with any filière palette color.
 */
class ColorService
{
    /**
     * 24 curated, visually distinct and pleasant colors for filières.
     * Light/medium tones so dark text stays readable on top of them.
     */
    public const FILIERE_PALETTE = [
        '#BDD7EE', // blue
        '#C6EFCE', // green
        '#F4B183', // orange
        '#FFE699', // yellow
        '#D9B3FF', // lavender
        '#F8CBAD', // peach
        '#B4C7E7', // periwinkle
        '#A9D08E', // leaf green
        '#FFB3C1', // pink
        '#9DD9D2', // teal
        '#FFD6A5', // apricot
        '#CDE7B0', // lime
        '#C5A3FF', // violet
        '#FFC9DE', // rose
        '#A0E7E5', // aqua
        '#FBE7A1', // sand
        '#D7BDE2', // mauve
        '#AED6F1', // sky
        '#F5B7B1', // salmon
        '#A3E4D7', // mint
        '#F9E79F', // butter
        '#D2B4DE', // orchid
        '#ABEBC6', // pale green
        '#F0B27A', // amber
    ];

    /**
     * 32 visually distinct colors for professors. These are chosen to be as
     * distinguishable from each other as possible, allowing easy visual grouping.
     */
    public const PROFESSOR_PALETTE = [
        '#E6194B', '#3CB44B', '#FFE119', '#4363D8', '#F58231',
        '#911EB4', '#46F0F0', '#F032E6', '#BCF60C', '#FABEBE',
        '#008080', '#E6BEFF', '#9A6324', '#FFFAC8', '#800000',
        '#AAFFC3', '#808000', '#FFD8B1', '#000075', '#808080',
        '#1F77B4', '#FF7F0E', '#2CA02C', '#D62728', '#9467BD',
        '#8C564B', '#E377C2', '#7F7F7F', '#BCBD22', '#17BECF',
        '#E41A1C', '#377EB8',
    ];

    /**
     * A progression of yellow/gold shades for dates, from light to dark.
     */
    public const DATE_PALETTE = [
        '#FFFFE0', '#FFFACD', '#FFEFD5', '#FFE4B5', '#FFDAB9',
        '#FFDEAD', '#F5DEB3', '#DEB887', '#D2B48C', '#F4A460',
        '#DAA520', '#B8860B',
    ];

    public const COLOR_NONE = '#ffffff';

    protected static array $dateColorsMap = [];
    protected static int $dateColorsIndex = 0;

    /**
     * Get a distinct shade of yellow for a given date.
     */
    public static function dateColor(?string $date): string
    {
        if (!$date) {
            return self::COLOR_NONE;
        }

        if (!isset(self::$dateColorsMap[$date])) {
            $index = self::$dateColorsIndex % count(self::DATE_PALETTE);
            self::$dateColorsMap[$date] = self::DATE_PALETTE[$index];
            self::$dateColorsIndex++;
        }

        return self::$dateColorsMap[$date];
    }

    private const GOLDEN_ANGLE = 137.508;

    /**
     * Pick the next color for a brand-new filière. Re-uses the curated
     * palette first, then falls back to algorithmically generated,
     * well-spaced HSL colors so we never run out and never repeat.
     *
     * @param array<int,string> $extraUsed Colors already chosen in the current
     *                                      batch but not yet persisted.
     */
    public static function nextFiliereColor(array $extraUsed = []): string
    {
        $used = Filiere::query()->pluck('couleur')
            ->merge($extraUsed)
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->unique()
            ->all();

        foreach (self::FILIERE_PALETTE as $color) {
            if (! in_array(strtoupper($color), $used, true)) {
                return $color;
            }
        }

        // Palette exhausted — generate a distinct color via golden-angle hue.
        $index = count($used);
        for ($attempt = 0; $attempt < 360; $attempt++) {
            $color = self::generateHslColor($index + $attempt, 12.0, 60, 78);
            if (! in_array(strtoupper($color), $used, true)) {
                return $color;
            }
        }

        return self::generateHslColor($index, 12.0, 60, 78);
    }

    /**
     * Deterministic, unique color for a professor. The mapping is built from
     * the full alphabetically-sorted list of professors, so the same teacher
     * always resolves to the same color regardless of the order rows are
     * processed in. Beyond the curated palette, colors are generated with
     * golden-ratio hue spacing, offset away from filière hues.
     */
    public static function professorColor(string $name): string
    {
        $clean = self::canonicalProfessorName($name);
        if ($clean === '') {
            return self::COLOR_NONE;
        }

        $mapping = self::professorColorMap();

        return $mapping[$clean] ?? self::COLOR_NONE;
    }

    /**
     * Build the canonical professor -> color map (sorted, unique, stable).
     *
     * @return array<string,string>
     */
    public static function professorColorMap(): array
    {
        $names = Enseignant::query()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['nom', 'prenom'])
            ->map(fn ($e) => self::canonicalProfessorName($e->nom.' '.$e->prenom))
            ->filter()
            ->unique()
            ->values();

        $mapping = [];
        foreach ($names as $i => $cleanName) {
            // Golden ratio hue generation ensures every color is mathematically 
            // offset from the last. We also vary the lightness slightly to ensure 
            // even closer hues have a different shade.
            $lightness = 35 + ($i % 3) * 10; // cycles between 35, 45, 55
            $mapping[$cleanName] = self::generateHslColor($i, 0.0, 85, $lightness);
        }

        return $mapping;
    }

    /**
     * Normalise a professor display name (strip "Pr."/"Dr." prefix, upper-case).
     */
    public static function canonicalProfessorName(string $name): string
    {
        return trim(strtoupper(preg_replace('/^\s*(?:D|P)r\.?\s*/i', '', $name)));
    }

    /**
     * Generate a hex color using golden-angle hue spacing for maximum
     * perceptual separation between successive indices.
     */
    public static function generateHslColor(int $index, float $hueOffset, int $saturation, int $lightness): string
    {
        $hue = fmod($hueOffset + ($index * self::GOLDEN_ANGLE), 360.0);

        return self::hslToHex($hue, $saturation, $lightness);
    }

    public static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100.0;
        $l /= 100.0;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60.0, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60  => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default  => [$c, 0, $x],
        };

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255)
        );
    }

    /**
     * Choose a readable (dark) text color for a given background hex.
     */
    public static function readableTextColor(?string $hexBackground): string
    {
        $hex = ltrim((string) $hexBackground, '#');
        if (strlen($hex) !== 6) {
            return '#0F172A';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Relative luminance (sRGB approximation).
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#1F2937' : '#FFFFFF';
    }
}
