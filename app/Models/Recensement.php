<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recensement extends Model
{
    use HasFactory;
    protected $primaryKey = 'idRecensement';
    protected $fillable = ['annee','deficitParArticle','excedentParArticle','prixUnite','observation','existantApresEcriture','designation'];
    protected $guarded=[];
}
