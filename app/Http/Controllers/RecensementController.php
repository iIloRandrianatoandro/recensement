<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;


use App\Imports\RecensementsImport;
use App\Http\Controllers\Controller;
use App\Models\recensement;
use App\Models\materiel;
use DB;


class RecensementController extends Controller
{
    public function import(Request $req) 
    {
        //Excel::import(new RecensementsImport, $req->file);
        $excel = Excel::toArray(new RecensementsImport, $req->file);
        $array=array_slice($excel[0], 3);
        foreach($array as $array){
            // s'il y a une nouvelle entree de materiel, l'ajouter à la base de donnees 
            if($array[3]!=null){
                $materiel=new materiel;
                $materiel->designation=$array[0];
                $materiel->nomenclature='3';
                $materiel->especeUnite=$array[7];
                $designation=$array[0];

                $nbDoublon = DB::select("SELECT COUNT(idMateriel) FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                
                $materiel->doublon=$nbDoublon[0]->{'COUNT(idMateriel)'}+1;
                $materiel->save();
            //crer le recensement
                $recensement=new recensement;
            $recensement->prixUnite=doubleval($array[1]);
            $recensement->existantApresEcriture=intval($array[5]);
            // chercher l'id du materiel
                $doublon=$nbDoublon[0]->{'COUNT(idMateriel)'};
            $id = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation and doublon= :doublon", ['designation' => $designation,'doublon' => $doublon]);
             $idMateriel=$id[0]->{'idMateriel'};
            $recensement->materiel_id=$idMateriel;
            $recensement->save();
            return $recensement;
            }
            //sinon creer le recensement en recuperant l'id
            else{

            }
            
        }
        //return $array;
    }
}
