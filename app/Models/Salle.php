<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Salle extends Model
{
    protected $fillable = [
        'nom',
    ];

    public function soutenances()
    {
        return $this->hasMany(Soutenance::class);
    }



    public static function normalizeNom(string $nom): string
    {
        $normalized = Str::ascii($nom);
        $normalized = strtoupper($normalized);

        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized);

        $normalized = preg_replace('/\bSALLE\b/', ' ', $normalized);

        $normalized = preg_replace('/\bS\s*(\d+)/', '$1', $normalized);

        $normalized = preg_replace('/\bNOUVEAU\s+BLOC\b/', 'NOUVEAU BLOC', $normalized);
        $normalized = preg_replace('/\bNB\b/', 'NOUVEAU BLOC', $normalized);

        $normalized = preg_replace('/\bANCIEN\s+BLOC\b/', 'ANCIEN BLOC', $normalized);
        $normalized = preg_replace('/\bAB\b/', 'ANCIEN BLOC', $normalized);

        $normalized = preg_replace('/\b(\d+)A\b/', '$1 ANCIEN BLOC', $normalized);
        $normalized = preg_replace('/\b(\d+)N(B)?\b/', '$1 NOUVEAU BLOC', $normalized);

        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized;
    }
}
