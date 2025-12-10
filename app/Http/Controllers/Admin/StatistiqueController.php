<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Cour;
use App\Models\EmploiDuTemps;
use App\Models\Groupe;
use App\Models\Departement;
use App\Helpers\NotificationHelper;

class StatistiqueController extends Controller
{
    public function index()
    {
        return view('admin.statistiques.index');
    }

    public function reservations()
    {
        $reservations = Reservation::with(['user', 'salle'])->latest()->get();
        return view('admin.reservations.index', compact('reservations'));
    }

    public function approveReservation(Request $request, $id)
    {
        try {
            $reservation = Reservation::findOrFail($id);
            
            // Validation pour créer le cours
            $validated = $request->validate([
                'groupes' => 'required|array|min:1',
                'groupes.*' => 'exists:groupes,id',
                'departement_id' => 'required|exists:departements,id',
            ], [
                'groupes.required' => 'Au moins un groupe doit être sélectionné.',
                'departement_id.required' => 'Le département est obligatoire.',
            ]);

            // Vérifier les conflits
            $jourSemaine = strtolower(date('l', strtotime($reservation->date)));
            $joursMap = [
                'monday' => 'lundi',
                'tuesday' => 'mardi',
                'wednesday' => 'mercredi',
                'thursday' => 'jeudi',
                'friday' => 'vendredi',
                'saturday' => 'samedi',
                'sunday' => 'dimanche'
            ];
            $jour = $joursMap[$jourSemaine] ?? null;

            // Extraire les heures correctement (format time HH:MM:SS ou HH:MM)
            $heureDebut = is_string($reservation->heure_debut) ? $reservation->heure_debut : $reservation->heure_debut->format('H:i:s');
            $heureFin = is_string($reservation->heure_fin) ? $reservation->heure_fin : $reservation->heure_fin->format('H:i:s');
            
            // Normaliser au format HH:MM:SS
            $heureDebut = strlen($heureDebut) == 5 ? $heureDebut . ':00' : substr($heureDebut, 0, 8);
            $heureFin = strlen($heureFin) == 5 ? $heureFin . ':00' : substr($heureFin, 0, 8);
            
            // Format pour l'emploi du temps (HH:MM)
            $heureDebutShort = substr($heureDebut, 0, 5);
            $heureFinShort = substr($heureFin, 0, 5);

            if ($jour) {
                foreach ($validated['groupes'] as $groupeId) {
                    $hasConflict = EmploiDuTemps::where('salle_id', $reservation->salle_id)
                        ->where('jour', $jour)
                        ->where(function($q) use ($heureDebut, $heureFin) {
                            $q->where(function($q1) use ($heureDebut, $heureFin) {
                                $q1->whereRaw('TIME(heure_debut) <= ?', [$heureDebut])
                                   ->whereRaw('TIME(heure_fin) > ?', [$heureDebut]);
                            })
                            ->orWhere(function($q2) use ($heureDebut, $heureFin) {
                                $q2->whereRaw('TIME(heure_debut) < ?', [$heureFin])
                                   ->whereRaw('TIME(heure_fin) >= ?', [$heureFin]);
                            })
                            ->orWhere(function($q3) use ($heureDebut, $heureFin) {
                                $q3->whereRaw('TIME(heure_debut) >= ?', [$heureDebut])
                                   ->whereRaw('TIME(heure_fin) <= ?', [$heureFin]);
                            });
                        })
                        ->exists();
                    
                    if ($hasConflict) {
                        return redirect()->back()
                            ->with('error', 'Conflit détecté : La salle est déjà réservée à cette date et heure pour un autre cours.');
                    }
                }
            }

            // Créer le cours
            $cour = Cour::create([
                'nom' => $reservation->motif,
                'code' => 'RES-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $reservation->motif), 0, 3)) . '-' . $reservation->id,
                'description' => $reservation->commentaire ?? 'Réservation approuvée par l\'administrateur',
                'enseignant_id' => $reservation->user_id,
                'departement_id' => $validated['departement_id'],
                'volume_horaire' => 1, // Volume horaire par défaut pour une réservation
            ]);

            // Attacher les groupes
            $cour->groupes()->attach($validated['groupes']);

            // Créer l'emploi du temps pour chaque groupe
            if ($jour) {
                foreach ($validated['groupes'] as $groupeId) {
                    EmploiDuTemps::create([
                        'cours_id' => $cour->id,
                        'salle_id' => $reservation->salle_id,
                        'groupe_id' => $groupeId,
                        'jour' => $jour,
                        'heure_debut' => $heureDebutShort,
                        'heure_fin' => $heureFinShort,
                        'type_seance' => 'cours',
                        'date_debut' => $reservation->date,
                        'date_fin' => $reservation->date,
                    ]);
                }
            }

            // Mettre à jour le statut de la réservation
            $reservation->update(['statut' => 'approuvee']);

            // Notifier l'enseignant que sa réservation a été approuvée
            $salle = \App\Models\Salle::find($reservation->salle_id);
            NotificationHelper::notifyReservationApproved(
                $reservation->user_id,
                $salle->nom,
                $reservation->date->format('d/m/Y'),
                $reservation->id
            );

            // Notifier l'enseignant du nouveau cours
            NotificationHelper::notifyCoursAssigned(
                $reservation->user_id,
                $cour->nom,
                $cour->id
            );

            // Notifier les étudiants des groupes
            NotificationHelper::notifyCoursCreatedToStudents(
                $validated['groupes'],
                $cour->nom,
                $cour->id
            );

            return redirect()->back()->with('success', 'Réservation approuvée et cours créé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Une erreur est survenue : ' . $e->getMessage());
        }
    }

    public function rejectReservation($id)
    {
        try {
            $reservation = Reservation::findOrFail($id);
            $reservation->update(['statut' => 'refusee']);
            
            // Notifier l'enseignant que sa réservation a été refusée
            $salle = \App\Models\Salle::find($reservation->salle_id);
            NotificationHelper::notifyReservationRejected(
                $reservation->user_id,
                $salle->nom,
                $reservation->date->format('d/m/Y'),
                $reservation->id
            );
            
            return redirect()->back()->with('success', 'Réservation refusée');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Une erreur est survenue');
        }
    }
}
