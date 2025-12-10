<?php

namespace App\Http\Controllers\Enseignant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function index()
    {
        return view('enseignant.planning');
    }

    public function cours()
    {
        $enseignant = auth()->user();

        $cours = $enseignant->coursEnseignes()
            ->with(['groupes', 'emploisDuTemps.salle', 'emploisDuTemps.groupe'])
            ->get();

        $stats = [
            'cours' => $cours->count(),
            'groupes' => $cours->flatMap->groupes->unique('id')->count(),
            'seances' => $cours->flatMap->emploisDuTemps->count(),
        ];

        $orderedDays = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
        ];

        $seances = $cours->flatMap(function ($cour) {
            return $cour->emploisDuTemps->map(function ($emploi) use ($cour) {
                return [
                    'cour' => $cour,
                    'emploi' => $emploi,
                ];
            });
        })->sortBy(function ($item) use ($orderedDays) {
            return ($orderedDays[$item['emploi']->jour] ?? 99) . '_' . $item['emploi']->heure_debut;
        })->values();

        return view('enseignant.cours', compact('cours', 'stats', 'seances', 'orderedDays'));
    }
}
