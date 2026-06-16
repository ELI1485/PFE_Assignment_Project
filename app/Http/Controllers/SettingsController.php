<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Filiere;
use App\Services\ColorService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $filieres = Filiere::withCount('etudiants')->orderBy('nom')->get();

        $config = [
            'etablissement' => Configuration::get('etablissement'),
            'departement'   => Configuration::get('departement'),
            'session'       => Configuration::get('session'),
        ];

        return view('settings.index', compact('filieres', 'config'));
    }

    /** Update the document / export header configuration. */
    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'etablissement' => 'nullable|string|max:255',
            'departement'   => 'nullable|string|max:255',
            'session'       => 'nullable|string|max:255',
        ]);

        foreach ($data as $key => $value) {
            Configuration::set($key, $value);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Paramètres généraux mis à jour.');
    }

    /** Create a new filière (with auto-assigned distinct color if none given). */
    public function storeFiliere(Request $request)
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:255|unique:filieres,nom',
            'nom_complet' => 'nullable|string|max:255',
            'couleur'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        Filiere::create([
            'nom'         => trim($data['nom']),
            'nom_complet' => $data['nom_complet'] ?? null,
            'couleur'     => $data['couleur'] ?? ColorService::nextFiliereColor(),
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Filière ajoutée.');
    }

    /** Update an existing filière's full name and color. */
    public function updateFiliere(Request $request, int $id)
    {
        $filiere = Filiere::findOrFail($id);

        $data = $request->validate([
            'nom'         => 'required|string|max:255|unique:filieres,nom,'.$id,
            'nom_complet' => 'nullable|string|max:255',
            'couleur'     => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $filiere->update([
            'nom'         => trim($data['nom']),
            'nom_complet' => $data['nom_complet'] ?? null,
            'couleur'     => $data['couleur'],
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Filière mise à jour.');
    }

    /** Delete a filière (only if no student is attached). */
    public function destroyFiliere(int $id)
    {
        $filiere = Filiere::withCount('etudiants')->findOrFail($id);

        if ($filiere->etudiants_count > 0) {
            return redirect()->route('settings.index')
                ->withErrors(['filiere' => "Impossible de supprimer « {$filiere->nom} » : des étudiants y sont rattachés."]);
        }

        $filiere->delete();

        return redirect()->route('settings.index')
            ->with('success', 'Filière supprimée.');
    }
}
