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
        $excel = Excel::toArray(new RecensementsImport, $req->file);
        $annee=$req->annee;
        $premiereUtilisation=$req->premiereUtilisation;
        $nomenclature=$req->nomenclature;
        $array=array_slice($excel[0], 3); // ne prend pas en compte les 3 premiere lignes du fichier excel cad titres
        foreach($array as $array){
            //$premiereUtilisation=false;
            /*if($premiereUtilisation === "true"){ //si premiere utilisation
               // creer le materiel
                $materiel=new materiel;
                $materiel->designation=$array[0];
                $materiel->nomenclature=$nomenclature;
                $materiel->especeUnite=$array[7]; 

                //trouver le nombre de doublon
                $nbMaterielTableau=DB::select("SELECT COUNT(idMateriel) FROM materiels WHERE designation = :designation", ['designation' => $array[0]]);
                $nbMateriel=$nbMaterielTableau[0]->{'COUNT(idMateriel)'};
                $materiel->doublon=$nbMateriel+1;

                $materiel->save();
            
                //creer le recensement
                $recensement=new recensement;
                $recensement->prixUnite=doubleval($array[1]);
                $recensement->existantApresEcriture=intval($array[5]);
                // chercher l'id du materiel
                $designation=$array[0];
                $id = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation and doublon= :doublon", ['designation' => $designation,'doublon' => $nbMateriel+1]);
                $idMateriel=$id[0]->{'idMateriel'};
                $recensement->materiel_id=$idMateriel;
                $recensement->annee=$annee;
                $recensement->save();
            }*/
            // s'il y a une nouvelle entree de materiel, ajouter le materiel à la base de donnees 
            if(($array[3]!=null) || ($premiereUtilisation==="true")){ //s'il y a une nouvelle entree 
                //creer materiel
                $materiel=new materiel;
                $materiel->designation=$array[0];
                $materiel->nomenclature=$nomenclature;
                $materiel->especeUnite=$array[7];

                $designation=$array[0];

                //nombre de doublon du materiel
                $nbDoublonTableau = DB::select("SELECT COUNT(idMateriel) FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                $nbDoublon=$nbDoublonTableau[0]->{'COUNT(idMateriel)'};
                $materiel->doublon=$nbDoublon+1;
                $materiel->save();
                //creer le recensement
                $recensement=new recensement;
                $recensement->prixUnite=doubleval($array[1]);
                $recensement->existantApresEcriture=intval($array[5]);
                // chercher l'id du materiel
                $doublon=$nbDoublon+1;
                $id = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation and doublon= :doublon", ['designation' => $designation,'doublon' => $doublon]);
                $idMateriel=$id[0]->{'idMateriel'};
                $recensement->materiel_id=$idMateriel;
                $recensement->annee=$annee;
                $recensement->save();
            }
            else{ //ajout recensement sans nouvelle entree et non premiere utilisation
                $designation=$array[0];
                $listeDoublonTableau = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                foreach($listeDoublonTableau as $materiel){
                    $materiel_id=$materiel->{'idMateriel'};
                    $nbRecensementTableau=DB::select("SELECT count(idRecensement) FROM recensements WHERE materiel_id = :materiel_id and annee= :annee", ['materiel_id' => $materiel_id,'annee'=>$annee]);
                    $nbRecensement= $nbRecensementTableau[0]->{"count(idRecensement)"};
                    if($nbRecensement==0){//recensement non existant sur le materiel
                        //creer le recensement
                        $recensement=new recensement;
                        $recensement->prixUnite=doubleval($array[1]);
                        $recensement->existantApresEcriture=intval($array[5]);
                        $recensement->materiel_id=$materiel_id;
                        $recensement->annee=$annee;
                        $recensement->save();
                        break;
                    }
                }
            }
        }
    }
}
