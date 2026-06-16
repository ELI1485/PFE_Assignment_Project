<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Creneau extends Model
{
    use HasFactory;

    protected $table = 'creneaux';

    protected $fillable = [
        'date',
        'heure_debut',
        'heure_fin',
        'capacite',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'heure_debut' => 'datetime:H:i',
            'heure_fin'   => 'datetime:H:i',
        ];
    }

    public function soutenances()
    {
        return $this->hasMany(Soutenance::class);
    }
}
