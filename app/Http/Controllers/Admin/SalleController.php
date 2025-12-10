<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Salle;

class SalleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $salles = \App\Models\Salle::latest()->get();
        return view('admin.salles.index', compact('salles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Rediriger vers l'index car nous utilisons un modal
        return redirect()->route('admin.salles.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'numero' => 'required|string|max:50|unique:salles',
                'capacite' => 'required|integer|min:1',
                'type' => 'required|in:amphitheatre,salle_cours,laboratoire,salle_td',
                'equipements' => 'nullable|string',
                'disponible' => 'boolean',
            ], [
                'nom.required' => 'Le nom de la salle est obligatoire.',
                'numero.required' => 'Le numéro de la salle est obligatoire.',
                'numero.unique' => 'Ce numéro de salle est déjà utilisé.',
                'capacite.required' => 'La capacité est obligatoire.',
                'capacite.integer' => 'La capacité doit être un nombre.',
                'capacite.min' => 'La capacité doit être au moins 1.',
                'type.required' => 'Le type de salle est obligatoire.',
            ]);

            Salle::create([
                'nom' => $validated['nom'],
                'numero' => $validated['numero'],
                'capacite' => $validated['capacite'],
                'type' => $validated['type'],
                'equipements' => $validated['equipements'] ?? null,
                'disponible' => $request->has('disponible') ? true : false,
            ]);

            return redirect()->route('admin.salles.index')
                ->with('success', 'Salle créée avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.salles.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.salles.index')
                ->with('error', 'Une erreur est survenue : ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $salle = Salle::findOrFail($id);
            $salle->delete();
            
            return redirect()->route('admin.salles.index')
                ->with('success', 'Salle supprimée avec succès');
        } catch (\Exception $e) {
            return redirect()->route('admin.salles.index')
                ->with('error', 'Une erreur est survenue lors de la suppression');
        }
    }
}
