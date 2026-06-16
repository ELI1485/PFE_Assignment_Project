<?php

namespace App\Repositories;

use App\Models\Salle;
use Illuminate\Database\Eloquent\Collection;

class SalleRepository
{
    public function findAll(): Collection
    {
        return Salle::all();
    }


    public function save(Salle $salle): void
    {
        $salle->save();
    }

    public function delete(int $id): void
    {
        $salle = Salle::findOrFail($id);
        $salle->delete();
    }
}
