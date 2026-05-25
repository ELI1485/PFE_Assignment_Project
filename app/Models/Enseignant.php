<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $table = 'enseignants';
    protected $fillable = [
        'nom',
        'prenom',
        'discipline',        
    ];

    public function projets()
    {
        return $this->hasMany(Projet::class, 'encadrant_id');
    }

    public function jurys()
    {
        return $this->belongsToMany(Jury::class, 'jury_enseignant')
                    ->withPivot('role');
    }

    public function soutenances()
    {
        return $this->hasManyThrough(Soutenance::class, Projet::class, 'encadrant_id', 'projet_id');
    }
}