<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Soutenance extends Model
{
    use HasFactory;

    protected $table = 'soutenances';

    protected $fillable = [
        'cne',
        'projet_id',
        'encadrant_id',
        'jury_id',
        'salle_id',
        'creneau_id',
        'langue',
    ];

    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    public function creneau()
    {
        return $this->belongsTo(Creneau::class);
    }

    public function jury()
    {
        return $this->belongsTo(Jury::class);
    }

    public function encadrant()
    {
        return $this->belongsTo(Enseignant::class, 'encadrant_id');
    }

    public function salleRelation()
    {
        return $this->belongsTo(Salle::class, 'salle_id');
    }

}
