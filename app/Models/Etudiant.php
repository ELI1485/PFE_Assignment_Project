<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    protected $table = "etudiants";
    protected $fillable = ['cne', 'nom', 'prenom', 'filiere_id'];

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }
}
