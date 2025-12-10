<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cour;
use App\Models\Groupe;
use App\Models\Salle;
use App\Models\EmploiDuTemps;
use App\Helpers\NotificationHelper;

class CourController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cours = Cour::with(['enseignant', 'groupes', 'departement', 'emploisDuTemps.salle'])->latest()->get();
        return view('admin.cours.index', compact('cours'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->route('admin.cours.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:cours',
                'description' => 'nullable|string',
                'enseignant_id' => 'required|exists:users,id',
                'departement_id' => 'required|exists:departements,id',
                'groupes' => 'required|array|min:1',
                'groupes.*' => 'exists:groupes,id',
                'salle_id' => 'required|exists:salles,id',
                'volume_horaire' => 'required|integer|min:1',
                'jour' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
                'heure_debut' => 'required|date_format:H:i',
                'heure_fin' => 'required|date_format:H:i|after:heure_debut',
                'type_seance' => 'required|in:cours,td,tp',
                'date_debut' => 'nullable|date',
                'date_fin' => 'nullable|date|after_or_equal:date_debut',
            ], [
                'nom.required' => 'Le nom du cours est obligatoire.',
                'code.required' => 'Le code du cours est obligatoire.',
                'code.unique' => 'Ce code de cours est déjà utilisé.',
                'enseignant_id.required' => 'L\'enseignant est obligatoire.',
                'departement_id.required' => 'Le département est obligatoire.',
                'groupes.required' => 'Au moins un groupe doit être sélectionné.',
                'salle_id.required' => 'La salle est obligatoire.',
                'volume_horaire.required' => 'Le volume horaire est obligatoire.',
                'jour.required' => 'Le jour de la semaine est obligatoire.',
                'heure_debut.required' => 'L\'heure de début est obligatoire.',
                'heure_fin.required' => 'L\'heure de fin est obligatoire.',
                'heure_fin.after' => 'L\'heure de fin doit être après l\'heure de début.',
                'type_seance.required' => 'Le type de séance est obligatoire.',
            ]);

            // Vérifier les conflits avant de créer
            foreach ($validated['groupes'] as $groupeId) {
                $hasConflict = EmploiDuTemps::hasConflict(
                    $validated['salle_id'],
                    $validated['jour'],
                    $validated['heure_debut'],
                    $validated['heure_fin']
                );
                
                if ($hasConflict) {
                    return redirect()->route('admin.cours.index')
                        ->withErrors(['conflict' => 'Conflit détecté : La salle est déjà réservée à cette date et heure pour un autre cours.'])
                        ->withInput();
                }
            }

            $cour = Cour::create([
                'nom' => $validated['nom'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'enseignant_id' => $validated['enseignant_id'],
                'departement_id' => $validated['departement_id'],
                'volume_horaire' => $validated['volume_horaire'],
            ]);

            // Attacher les groupes
            $cour->groupes()->attach($validated['groupes']);

            // Créer l'emploi du temps pour chaque groupe
            foreach ($validated['groupes'] as $groupeId) {
                EmploiDuTemps::create([
                    'cours_id' => $cour->id,
                    'salle_id' => $validated['salle_id'],
                    'groupe_id' => $groupeId,
                    'jour' => $validated['jour'],
                    'heure_debut' => $validated['heure_debut'],
                    'heure_fin' => $validated['heure_fin'],
                    'type_seance' => $validated['type_seance'],
                    'date_debut' => $validated['date_debut'] ?? null,
                    'date_fin' => $validated['date_fin'] ?? null,
                ]);
            }

            // Notifier l'enseignant
            NotificationHelper::notifyCoursAssigned(
                $validated['enseignant_id'],
                $validated['nom'],
                $cour->id
            );

            // Notifier les étudiants des groupes
            NotificationHelper::notifyCoursCreatedToStudents(
                $validated['groupes'],
                $validated['nom'],
                $cour->id
            );

            return redirect()->route('admin.cours.index')
                ->with('success', 'Cours créé avec succès et ajouté à l\'emploi du temps');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.cours.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.cours.index')
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
        $cour = Cour::with(['groupes', 'emploisDuTemps'])->findOrFail($id);
        $emploi = $cour->emploisDuTemps->first(); // Prendre le premier emploi du temps comme référence
        
        return view('admin.cours.edit', compact('cour', 'emploi'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $cour = Cour::findOrFail($id);
            
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:cours,code,' . $cour->id,
                'description' => 'nullable|string',
                'enseignant_id' => 'required|exists:users,id',
                'departement_id' => 'required|exists:departements,id',
                'groupes' => 'required|array|min:1',
                'groupes.*' => 'exists:groupes,id',
                'salle_id' => 'required|exists:salles,id',
                'volume_horaire' => 'required|integer|min:1',
                'jour' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
                'heure_debut' => 'required|date_format:H:i',
                'heure_fin' => 'required|date_format:H:i|after:heure_debut',
                'type_seance' => 'required|in:cours,td,tp',
                'date_debut' => 'nullable|date',
                'date_fin' => 'nullable|date|after_or_equal:date_debut',
            ], [
                'nom.required' => 'Le nom du cours est obligatoire.',
                'code.required' => 'Le code du cours est obligatoire.',
                'code.unique' => 'Ce code de cours est déjà utilisé.',
                'enseignant_id.required' => 'L\'enseignant est obligatoire.',
                'departement_id.required' => 'Le département est obligatoire.',
                'groupes.required' => 'Au moins un groupe doit être sélectionné.',
                'salle_id.required' => 'La salle est obligatoire.',
                'volume_horaire.required' => 'Le volume horaire est obligatoire.',
                'jour.required' => 'Le jour de la semaine est obligatoire.',
                'heure_debut.required' => 'L\'heure de début est obligatoire.',
                'heure_fin.required' => 'L\'heure de fin est obligatoire.',
                'heure_fin.after' => 'L\'heure de fin doit être après l\'heure de début.',
                'type_seance.required' => 'Le type de séance est obligatoire.',
            ]);

            // Vérifier les conflits avant de mettre à jour (exclure les emplois du temps actuels)
            foreach ($validated['groupes'] as $groupeId) {
                $debut = strlen($validated['heure_debut']) == 5 ? $validated['heure_debut'] . ':00' : $validated['heure_debut'];
                $fin = strlen($validated['heure_fin']) == 5 ? $validated['heure_fin'] . ':00' : $validated['heure_fin'];
                
                $hasConflict = EmploiDuTemps::where('salle_id', $validated['salle_id'])
                    ->where('jour', $validated['jour'])
                    ->where('groupe_id', $groupeId)
                    ->where('cours_id', '!=', $cour->id)
                    ->where(function($q) use ($debut, $fin) {
                        $q->where(function($q1) use ($debut, $fin) {
                            $q1->whereRaw('TIME(heure_debut) <= ?', [$debut])
                               ->whereRaw('TIME(heure_fin) > ?', [$debut]);
                        })
                        ->orWhere(function($q2) use ($debut, $fin) {
                            $q2->whereRaw('TIME(heure_debut) < ?', [$fin])
                               ->whereRaw('TIME(heure_fin) >= ?', [$fin]);
                        })
                        ->orWhere(function($q3) use ($debut, $fin) {
                            $q3->whereRaw('TIME(heure_debut) >= ?', [$debut])
                               ->whereRaw('TIME(heure_fin) <= ?', [$fin]);
                        });
                    })
                    ->exists();
                
                if ($hasConflict) {
                    return redirect()->route('admin.cours.edit', $cour)
                        ->withErrors(['conflict' => 'Conflit détecté : La salle est déjà réservée à cette date et heure pour un autre cours.'])
                        ->withInput();
                }
            }

            // Mettre à jour le cours
            $cour->update([
                'nom' => $validated['nom'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'enseignant_id' => $validated['enseignant_id'],
                'departement_id' => $validated['departement_id'],
                'volume_horaire' => $validated['volume_horaire'],
            ]);

            // Synchroniser les groupes
            $cour->groupes()->sync($validated['groupes']);

            // Supprimer les anciens emplois du temps
            $cour->emploisDuTemps()->delete();

            // Créer les nouveaux emplois du temps pour chaque groupe
            foreach ($validated['groupes'] as $groupeId) {
                EmploiDuTemps::create([
                    'cours_id' => $cour->id,
                    'salle_id' => $validated['salle_id'],
                    'groupe_id' => $groupeId,
                    'jour' => $validated['jour'],
                    'heure_debut' => $validated['heure_debut'],
                    'heure_fin' => $validated['heure_fin'],
                    'type_seance' => $validated['type_seance'],
                    'date_debut' => $validated['date_debut'] ?? null,
                    'date_fin' => $validated['date_fin'] ?? null,
                ]);
            }

            return redirect()->route('admin.cours.index')
                ->with('success', 'Cours modifié avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.cours.edit', $id)
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.cours.index')
                ->with('error', 'Une erreur est survenue : ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $cour = Cour::findOrFail($id);
            $cour->groupes()->detach();
            $cour->delete();
            
            return redirect()->route('admin.cours.index')
                ->with('success', 'Cours supprimé avec succès');
        } catch (\Exception $e) {
            return redirect()->route('admin.cours.index')
                ->with('error', 'Une erreur est survenue lors de la suppression');
        }
    }
}
