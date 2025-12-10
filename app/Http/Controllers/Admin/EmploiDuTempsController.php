<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmploiDuTemps;

class EmploiDuTempsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Vérifier les conflits d'emploi du temps
     */
    public function checkConflict(Request $request)
    {
        $request->validate([
            'salle_id' => 'required|exists:salles,id',
            'jour' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i',
        ]);

        $hasConflict = EmploiDuTemps::hasConflict(
            $request->salle_id,
            $request->jour,
            $request->heure_debut,
            $request->heure_fin
        );

        return response()->json([
            'has_conflict' => $hasConflict
        ]);
    }
}
