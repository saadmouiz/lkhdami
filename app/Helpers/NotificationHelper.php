<?php

namespace App\Helpers;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Créer une notification pour un utilisateur
     */
    public static function create($userId, $titre, $message, $type = 'info', $lien = null)
    {
        return Notification::create([
            'user_id' => $userId,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'lien' => $lien,
            'lu' => false,
        ]);
    }

    /**
     * Notifier un enseignant qu'un cours lui a été assigné
     */
    public static function notifyCoursAssigned($enseignantId, $coursNom, $coursId = null)
    {
        return self::create(
            $enseignantId,
            'Nouveau cours assigné',
            "Un nouveau cours \"{$coursNom}\" vous a été assigné.",
            'success',
            $coursId ? route('enseignant.cours') : null
        );
    }

    /**
     * Notifier un étudiant qu'un cours a été ajouté à son groupe
     */
    public static function notifyCoursAdded($etudiantId, $coursNom, $coursId = null)
    {
        return self::create(
            $etudiantId,
            'Nouveau cours disponible',
            "Un nouveau cours \"{$coursNom}\" a été ajouté à votre emploi du temps.",
            'info',
            $coursId ? route('etudiant.cours') : null
        );
    }

    /**
     * Notifier qu'une réservation a été approuvée
     */
    public static function notifyReservationApproved($userId, $salleNom, $date, $reservationId = null)
    {
        return self::create(
            $userId,
            'Réservation approuvée',
            "Votre demande de réservation pour la salle \"{$salleNom}\" le {$date} a été approuvée. Le cours a été créé automatiquement.",
            'success',
            $reservationId ? route('enseignant.reservations.index') : null
        );
    }

    /**
     * Notifier qu'une réservation a été refusée
     */
    public static function notifyReservationRejected($userId, $salleNom, $date, $reservationId = null)
    {
        return self::create(
            $userId,
            'Réservation refusée',
            "Votre demande de réservation pour la salle \"{$salleNom}\" le {$date} a été refusée.",
            'error',
            $reservationId ? route('enseignant.reservations.index') : null
        );
    }

    /**
     * Notifier qu'un utilisateur a été créé
     */
    public static function notifyUserCreated($userId, $userName, $role)
    {
        return self::create(
            $userId,
            'Bienvenue sur la plateforme HESTIM',
            "Votre compte {$role} a été créé avec succès. Bienvenue {$userName}!",
            'success',
            route('dashboard')
        );
    }

    /**
     * Notifier plusieurs utilisateurs
     */
    public static function notifyMultiple($userIds, $titre, $message, $type = 'info', $lien = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = self::create($userId, $titre, $message, $type, $lien);
        }
        return $notifications;
    }

    /**
     * Notifier les admins qu'une nouvelle demande de réservation a été faite
     */
    public static function notifyNewReservation($enseignantName, $salleNom, $date, $reservationId = null)
    {
        try {
            $admins = \App\Models\User::where('role', 'admin')->get();
            $notifications = [];
            
            if ($admins->isEmpty()) {
                Log::warning('Aucun admin trouvé pour envoyer la notification de réservation');
                return [];
            }
            
            foreach ($admins as $admin) {
                try {
                    $notifications[] = self::create(
                        $admin->id,
                        'Nouvelle demande de réservation',
                        "{$enseignantName} a fait une demande de réservation pour la salle \"{$salleNom}\" le {$date}.",
                        'info',
                        $reservationId ? route('admin.reservations.index') : null
                    );
                } catch (\Exception $e) {
                    Log::error("Erreur lors de la création de la notification pour l'admin {$admin->id}: " . $e->getMessage());
                }
            }
            
            return $notifications;
        } catch (\Exception $e) {
            Log::error('Erreur dans notifyNewReservation: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Notifier les étudiants d'un groupe qu'un nouveau cours a été créé
     */
    public static function notifyCoursCreatedToStudents($groupeIds, $coursNom, $coursId = null)
    {
        $etudiants = \App\Models\User::where('role', 'etudiant')
            ->whereHas('groupes', function($q) use ($groupeIds) {
                $q->whereIn('groupes.id', $groupeIds);
            })
            ->get();
        
        $notifications = [];
        foreach ($etudiants as $etudiant) {
            $notifications[] = self::create(
                $etudiant->id,
                'Nouveau cours disponible',
                "Un nouveau cours \"{$coursNom}\" a été ajouté à votre emploi du temps.",
                'success',
                $coursId ? route('etudiant.cours') : null
            );
        }
        
        return $notifications;
    }
}

