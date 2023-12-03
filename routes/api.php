<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RecensementController;
use App\Http\Controllers\MaterielController;
use App\Http\Controllers\authentification;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
    
});
Route::controller(RecensementController::class)->group(function(){
    Route::post('importer','importer'); 
    Route::get('listerAnneeAvecRecensement','listerAnneeAvecRecensement'); 
    Route::get('listeMaterielARecense/{annee}','listeMaterielARecense');  
    Route::get('voirRecensement/{id}','voirRecensement'); 
    Route::post('recenserMateriel/{idRecensement}','recenserMateriel'); 
    Route::get('suivreFluxRecensement/','suivreFluxRecensement'); 
    Route::get('rechercherRecensement/{designation}/{annee}','rechercherRecensement');
    Route::post('modifierRecensement/{idRecensement}','modifierRecensement'); 
    Route::post('genererRecapitulatif','genererRecapitulatif'); 
    Route::get('consulterEvolution5Ans','consulterEvolution5Ans'); 
    Route::get('consulterEvolutionMateriel/{materielID}','consulterEvolutionMateriel');
    Route::get('export/{annee}','genererExcel');  
});
Route::controller(MaterielController::class)->group(function(){
    Route::get('listeMateriel','listeMateriel'); 
    Route::get('rechercherMateriel/{designation}','rechercherMateriel'); 
    Route::get('voirMateriel/{id}','voirMateriel'); 
    Route::post('modifierMateriel/{idMateriel}','modifierMateriel'); 
});
//authentification
Route::controller(authentification::class)->group(function(){
    Route::post('seConnecter','seConnecter');
    Route::get('seDeconnecter','seDeconnecter');
    Route::post('creerUtilisateur','creerUtilisateur');
    Route::post('modifierUtilisateur/{id}','modifierUtilisateur');
    Route::post('supprimerUtilisateur/{id}','supprimerUtilisateur');
    Route::get('/listerUtilisateur', 'listerUtilisateur');
    Route::get('voirUtilisateur/{id}','voirUtilisateur');
});
