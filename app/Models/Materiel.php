<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materiel extends Model
{
    use HasFactory;
    protected $fillable = ['designation','nomenclature','especeUnite'];
    public function recensements(){
        return $this->hasMany(recensements::class);
    }
}
