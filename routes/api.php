<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RecensementController;
use App\Http\Controllers\MaterielController;

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
    Route::post('import','import'); 
    Route::get('listeMaterielARecense/{annee}','listeMaterielARecense'); 
    Route::get('rechercherRecensement/{designation}','rechercherRecensement'); 
    Route::get('voirRecensement/{id}','voirRecensement'); 
    Route::post('recenserMateriel/{idRecensement}','recenserMateriel'); 
    Route::get('suivreFluxRecensement/{annee}','suivreFluxRecensement'); 
});
Route::controller(MaterielController::class)->group(function(){
    Route::get('listeMateriel','listeMateriel'); 
    Route::get('rechercherMateriel/{designation}','rechercherMateriel'); 
    Route::get('voirMateriel/{id}','voirMateriel'); 
    Route::post('modifierMateriel/{idMateriel}','modifierMateriel'); 
});
