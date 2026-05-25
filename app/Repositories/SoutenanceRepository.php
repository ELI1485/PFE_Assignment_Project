<?php

namespace App\Repositories;

use App\Models\Soutenance;
use Illuminate\Support\Collection;

class SoutenanceRepository
{
    public function findAll(): Collection
    {
        return Soutenance::with(['projet.etudiant', 'projet.etudiant2', 'projet.encadrant', 'jury.enseignants', 'creneau', 'salleRelation'])->get();
    }

    public function findById(int $id): Soutenance
    {
        return Soutenance::findOrFail($id);
    }

}
