<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RecensementController;

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
