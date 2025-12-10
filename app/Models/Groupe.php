<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Groupe extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'effectif',
        'departement_id',
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function etudiants()
    {
        return $this->belongsToMany(User::class);
    }

    public function cours()
    {
        return $this->belongsToMany(Cour::class, 'cour_groupe', 'groupe_id', 'cour_id');
    }

    public function emploiDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }
}
