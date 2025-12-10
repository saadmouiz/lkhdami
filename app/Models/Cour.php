<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cour extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code',
        'description',
        'volume_horaire',
        'enseignant_id',
        'departement_id',
    ];

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function groupes()
    {
        return $this->belongsToMany(Groupe::class, 'cour_groupe', 'cour_id', 'groupe_id');
    }

    public function emploiDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class, 'cours_id');
    }
    
    public function emploisDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class, 'cours_id');
    }
}
