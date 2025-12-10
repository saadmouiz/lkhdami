<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    use HasFactory;

    protected $fillable = [
        'cours_id',
        'salle_id',
        'groupe_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'date_debut',
        'date_fin',
        'type_seance',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function cours()
    {
        return $this->belongsTo(Cour::class, 'cours_id');
    }

    public function salle()
    {
        return $this->belongsTo(Salle::class);
    }

    public function groupe()
    {
        return $this->belongsTo(Groupe::class);
    }

    // Vérifier les conflits
    public static function hasConflict($salleId, $jour, $heureDebut, $heureFin, $excludeId = null)
    {
        // Convertir les heures en format Time pour la comparaison
        $debut = is_string($heureDebut) ? $heureDebut : (is_object($heureDebut) ? $heureDebut->format('H:i:s') : $heureDebut);
        $fin = is_string($heureFin) ? $heureFin : (is_object($heureFin) ? $heureFin->format('H:i:s') : $heureFin);
        
        // S'assurer que les heures sont au format H:i:s
        if (strlen($debut) == 5) $debut .= ':00';
        if (strlen($fin) == 5) $fin .= ':00';
        
        $query = self::where('salle_id', $salleId)
            ->where('jour', $jour)
            ->where(function($q) use ($debut, $fin) {
                // Conflit si le nouveau cours commence pendant un cours existant
                $q->where(function($q1) use ($debut, $fin) {
                    $q1->whereRaw('TIME(heure_debut) <= ?', [$debut])
                       ->whereRaw('TIME(heure_fin) > ?', [$debut]);
                })
                // Conflit si le nouveau cours se termine pendant un cours existant
                ->orWhere(function($q2) use ($debut, $fin) {
                    $q2->whereRaw('TIME(heure_debut) < ?', [$fin])
                       ->whereRaw('TIME(heure_fin) >= ?', [$fin]);
                })
                // Conflit si le nouveau cours englobe un cours existant
                ->orWhere(function($q3) use ($debut, $fin) {
                    $q3->whereRaw('TIME(heure_debut) >= ?', [$debut])
                       ->whereRaw('TIME(heure_fin) <= ?', [$fin]);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
