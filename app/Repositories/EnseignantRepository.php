<?php

namespace App\Repositories;

use App\Models\Enseignant;

class EnseignantRepository
{
    public function findAll()
    {
        return Enseignant::all();
    }

    public function create(array $data): Enseignant
    {
        return Enseignant::create($data);
    }

    public function findByNomPrenom(string $nom, string $prenom): ?Enseignant
    {
        return Enseignant::where('nom', $nom)->where('prenom', $prenom)->first();
    }
}
