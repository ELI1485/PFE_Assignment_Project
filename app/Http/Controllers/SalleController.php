<?php

namespace App\Http\Controllers;

use App\Models\Salle;
use App\Repositories\SalleRepository;
use Illuminate\Http\Request;

class SalleController extends Controller
{
    public function __construct(
        private SalleRepository $salleRepository
    ) {}

    public function index()
    {
        $salles = $this->salleRepository->findAll();

        return view('salles.index', compact('salles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:50',
        ]);

        $normalized = Salle::normalizeNom($request->input('nom'));
        $duplicate = Salle::all()
            ->contains(fn (Salle $salle) => Salle::normalizeNom($salle->nom) === $normalized);

        if ($duplicate) {
            return redirect()->back()
                ->withErrors(['nom' => 'Cette salle existe déjà sous une forme équivalente.'])
                ->withInput();
        }

        $salle = new Salle;
        $salle->nom = preg_replace('/\s+/', ' ', trim($request->nom));
        $this->salleRepository->save($salle);

        return redirect()->back()->with('success', "Salle {$salle->nom} ajoutée.");
    }

    public function destroy(int $id)
    {
        $this->salleRepository->delete($id);

        return redirect()->back()->with('success', 'Salle supprimée.');
    }
}
