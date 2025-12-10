<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'emploi_du_temps_id',
        'cours_id',
        'numero_place',
        'statut',
    ];

    public function etudiant()
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function emploiDuTemps()
    {
        return $this->belongsTo(EmploiDuTemps::class, 'emploi_du_temps_id');
    }

    public function cours()
    {
        return $this->belongsTo(Cour::class, 'cours_id');
    }
}

