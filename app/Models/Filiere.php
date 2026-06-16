<?php

namespace App\Models;

use App\Services\ColorService;
use Illuminate\Database\Eloquent\Model;

class Filiere extends Model
{
    protected $table = 'filieres';

    protected $fillable = ['nom', 'couleur'];

    public function etudiants()
    {
        return $this->hasMany(Etudiant::class);
    }

    /**
     * Find an existing filière by name (case-insensitive) or create a new one,
     * auto-assigning a distinct color from the palette.
     */
    public static function findOrCreateByName(string $nom, array $extraUsedColors = []): self
    {
        $nom = trim($nom);
        if ($nom === '') {
            $nom = 'Inconnue';
        }

        $existing = static::query()
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'nom'     => $nom,
            'couleur' => ColorService::nextFiliereColor($extraUsedColors),
        ]);
    }
}
