<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Groupe;
use App\Models\Departement;

class GroupeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groupes = Groupe::with('departement')->latest()->get();
        return view('admin.groupes.index', compact('groupes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->route('admin.groupes.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255|unique:groupes',
                'departement_id' => 'required|exists:departements,id',
                'effectif' => 'nullable|integer|min:0',
            ], [
                'nom.required' => 'Le nom du groupe est obligatoire.',
                'nom.unique' => 'Ce nom de groupe existe déjà.',
                'departement_id.required' => 'Le département est obligatoire.',
                'departement_id.exists' => 'Le département sélectionné n\'existe pas.',
            ]);

            Groupe::create([
                'nom' => $validated['nom'],
                'departement_id' => $validated['departement_id'],
                'effectif' => $validated['effectif'] ?? 0,
            ]);

            return redirect()->route('admin.groupes.index')
                ->with('success', 'Groupe créé avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.groupes.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.groupes.index')
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
            $groupe = Groupe::findOrFail($id);
            $groupe->delete();
            
            return redirect()->route('admin.groupes.index')
                ->with('success', 'Groupe supprimé avec succès');
        } catch (\Exception $e) {
            return redirect()->route('admin.groupes.index')
                ->with('error', 'Une erreur est survenue lors de la suppression');
        }
    }
}

