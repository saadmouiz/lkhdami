<?php

namespace App\Http\Controllers\Enseignant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\EmploiDuTemps;
use App\Helpers\NotificationHelper;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reservations = Reservation::where('user_id', auth()->id())
            ->with('salle')
            ->latest()
            ->get();
        
        $salles = Salle::where('disponible', true)->get();
        
        return view('enseignant.reservations', compact('reservations', 'salles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->route('enseignant.reservations.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'salle_id' => 'required|exists:salles,id',
                'date' => 'required|date|after_or_equal:today',
                'heure_debut' => 'required|date_format:H:i',
                'heure_fin' => 'required|date_format:H:i|after:heure_debut',
                'motif' => 'required|string|max:255',
                'commentaire' => 'nullable|string',
            ], [
                'salle_id.required' => 'La salle est obligatoire.',
                'date.required' => 'La date est obligatoire.',
                'date.after_or_equal' => 'La date doit être aujourd\'hui ou dans le futur.',
                'heure_debut.required' => 'L\'heure de début est obligatoire.',
                'heure_fin.required' => 'L\'heure de fin est obligatoire.',
                'heure_fin.after' => 'L\'heure de fin doit être après l\'heure de début.',
                'motif.required' => 'Le motif est obligatoire.',
            ]);

            // Vérifier les conflits avec l'emploi du temps
            $jourSemaine = strtolower(date('l', strtotime($validated['date'])));
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

            if ($jour) {
                $debut = strlen($validated['heure_debut']) == 5 ? $validated['heure_debut'] . ':00' : $validated['heure_debut'];
                $fin = strlen($validated['heure_fin']) == 5 ? $validated['heure_fin'] . ':00' : $validated['heure_fin'];
                
                $hasConflict = EmploiDuTemps::where('salle_id', $validated['salle_id'])
                    ->where('jour', $jour)
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
                    return redirect()->route('enseignant.reservations.index')
                        ->withErrors(['conflict' => 'Conflit détecté : La salle est déjà réservée à cette date et heure dans l\'emploi du temps.'])
                        ->withInput();
                }
            }

            // Vérifier les conflits avec les autres réservations
            $existingReservation = Reservation::where('salle_id', $validated['salle_id'])
                ->where('date', $validated['date'])
                ->where('statut', '!=', 'refusee')
                ->where('statut', '!=', 'annulee')
                ->where(function($q) use ($validated) {
                    $debut = strlen($validated['heure_debut']) == 5 ? $validated['heure_debut'] . ':00' : $validated['heure_debut'];
                    $fin = strlen($validated['heure_fin']) == 5 ? $validated['heure_fin'] . ':00' : $validated['heure_fin'];
                    
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
            
            if ($existingReservation) {
                return redirect()->route('enseignant.reservations.index')
                    ->withErrors(['conflict' => 'Conflit détecté : Une autre réservation existe déjà pour cette salle à cette date et heure.'])
                    ->withInput();
            }

            $reservation = Reservation::create([
                'user_id' => auth()->id(),
                'salle_id' => $validated['salle_id'],
                'date' => $validated['date'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'],
                'motif' => $validated['motif'],
                'commentaire' => $validated['commentaire'] ?? null,
                'statut' => 'en_attente',
            ]);

            // Notifier les admins
            try {
                $salle = Salle::find($validated['salle_id']);
                if ($salle) {
                    NotificationHelper::notifyNewReservation(
                        auth()->user()->name,
                        $salle->nom,
                        date('d/m/Y', strtotime($validated['date'])),
                        $reservation->id
                    );
                }
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas la création de la réservation
                \Log::error('Erreur lors de la création de la notification: ' . $e->getMessage());
            }

            return redirect()->route('enseignant.reservations.index')
                ->with('success', 'Demande de réservation envoyée avec succès. En attente d\'approbation.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('enseignant.reservations.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('enseignant.reservations.index')
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
            $reservation = Reservation::where('user_id', auth()->id())
                ->findOrFail($id);
            
            if ($reservation->statut === 'approuvee') {
                return redirect()->route('enseignant.reservations.index')
                    ->with('error', 'Impossible de supprimer une réservation approuvée.');
            }
            
            $reservation->update(['statut' => 'annulee']);
            
            return redirect()->route('enseignant.reservations.index')
                ->with('success', 'Réservation annulée avec succès');
        } catch (\Exception $e) {
            return redirect()->route('enseignant.reservations.index')
                ->with('error', 'Une erreur est survenue lors de l\'annulation');
        }
    }
}
