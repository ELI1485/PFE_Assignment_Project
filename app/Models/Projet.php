<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Projet extends Model
{
    protected $table = "projets";
    protected $fillable = [
        'cne',
        'etudiant_id',
        'etudiant2_id',
        'encadrant_id',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'etudiant_id');
    }

    public function etudiant2()
    {
        return $this->belongsTo(Etudiant::class, 'etudiant2_id');
    }

    public function encadrant()
    {
        return $this->belongsTo(Enseignant::class, 'encadrant_id');
    }

    public function soutenance()
    {
        return $this->hasOne(Soutenance::class);
    }
}
