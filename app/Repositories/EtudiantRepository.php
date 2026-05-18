<?php

namespace App\Repositories;

use App\Models\Etudiant;

class EtudiantRepository
{
    public function create(array $data): Etudiant
    {
        return Etudiant::create($data);
    }

    public function findByCne(string $cne): ?Etudiant
    {
        return Etudiant::where('cne', $cne)->first();
    }
}
