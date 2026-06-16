<?php

namespace App\Services;

use App\Models\Filiere;

class PdfExportService
{
    public const COLOR_NONE = '#ffffff';

    public function __construct() {}

    /**
     * Resolve the background color for a filière.
     *
     * Accepts either:
     *   - a filière id (int or numeric string), or
     *   - a filière name string ("GI", "Médecine", "Génie Informatique", …)
     *
     * The color is looked up from the `filieres` table. Unknown / empty
     * values fall back to white. No filière name is hardcoded anywhere.
     */
    public static function applyFiliereColor($filiere): string
    {
        if ($filiere === null || $filiere === '') {
            return self::COLOR_NONE;
        }

        $model = null;

        if (is_int($filiere) || (is_string($filiere) && ctype_digit($filiere))) {
            $model = self::filiereCache()->get((int) $filiere);
        } else {
            $name = mb_strtolower(trim((string) $filiere));
            $model = self::filiereCache()->first(
                fn (Filiere $f) => mb_strtolower($f->nom) === $name
            );
        }

        return $model?->couleur ?: self::COLOR_NONE;
    }

    /**
     * Cached collection of all filières (keyed by id) for the request lifetime.
     */
    protected static ?\Illuminate\Support\Collection $filiereCache = null;

    protected static function filiereCache(): \Illuminate\Support\Collection
    {
        if (self::$filiereCache === null) {
            self::$filiereCache = Filiere::all()->keyBy('id');
        }

        return self::$filiereCache;
    }

    /**
     * Deterministic, unique professor color (delegated to ColorService).
     * Same professor always resolves to the same color regardless of ordering,
     * and never collides with a filière color.
     */
    public static function getProfessorColor(string $name): string
    {
        return ColorService::professorColor($name);
    }
}
