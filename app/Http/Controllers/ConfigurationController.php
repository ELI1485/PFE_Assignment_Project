<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Filiere;
use App\Services\ColorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfigurationController extends Controller
{
    /** Show the settings page (document header config + logo + filières). */
    public function index()
    {
        $filieres = Filiere::withCount('etudiants')->orderBy('nom')->get();

        $config = [
            'school_name'     => Configuration::get('school_name'),
            'department_name' => Configuration::get('department_name'),
            'site_name'       => Configuration::get('site_name'),
            'site_subtitle'   => Configuration::get('site_subtitle'),
            'logo_url'        => Configuration::logoUrl(),
        ];

        return view('settings.index', compact('filieres', 'config'));
    }

    /** Save the school name, department name and (optional) logo upload. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name'     => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'site_name'       => 'nullable|string|max:255',
            'site_subtitle'   => 'nullable|string|max:255',
            'school_logo'     => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:4096',
            'remove_logo'     => 'nullable|boolean',
        ]);

        Configuration::set('school_name', $data['school_name'] ?? null);
        Configuration::set('department_name', $data['department_name'] ?? null);
        Configuration::set('site_name', $data['site_name'] ?? null);
        Configuration::set('site_subtitle', $data['site_subtitle'] ?? null);

        // Remove existing logo if requested.
        if ($request->boolean('remove_logo')) {
            $old = Configuration::get('school_logo_path');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            Configuration::set('school_logo_path', null);
        }

        // Store a newly uploaded logo (replacing any previous one).
        if ($request->hasFile('school_logo')) {
            $old = Configuration::get('school_logo_path');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('school_logo')->store('logos', 'public');
            Configuration::set('school_logo_path', $path);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Paramètres généraux mis à jour.');
    }

    /** Create a new filière (with auto-assigned distinct color if none given). */
    public function storeFiliere(Request $request)
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:255|unique:filieres,nom',
            'couleur'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        Filiere::create([
            'nom'         => trim($data['nom']),
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
            'couleur'     => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $filiere->update([
            'nom'         => trim($data['nom']),
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
