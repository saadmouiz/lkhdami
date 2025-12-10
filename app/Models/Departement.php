<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function groupes()
    {
        return $this->hasMany(Groupe::class);
    }

    public function cours()
    {
        return $this->hasMany(Cour::class);
    }
}
