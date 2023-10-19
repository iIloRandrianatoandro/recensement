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
        $array=array_slice($excel[0], 3);
        foreach($array as $array){
            $premiereUtilisation=false;
            if($premiereUtilisation){ //si premiere utilisation
               // creer le materiel
                $materiel=new materiel;
                $materiel->designation=$array[0];
                $materiel->nomenclature='3';
                $materiel->especeUnite=$array[7]; 

                //verifier le materiel existe deja
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
                $recensement->annee=2024;
                $recensement->save();
            }
            // s'il y a une nouvelle entree de materiel, ajouter le materiel à la base de donnees 
            elseif($array[3]!=null){ //s'il y a une nouvelle entree
                //creer materiel
                $materiel=new materiel;
                $materiel->designation=$array[0];
                $materiel->nomenclature='3';
                $materiel->especeUnite=$array[7];
                $designation=$array[0];

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
                $recensement->annee=2024;
                $recensement->save();
            }
            else{ //ajout recensement sans nouvelle entree et non premiere utilisation
                $designation=$array[0];
                $listeDoublonTableau = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                foreach($listeDoublonTableau as $a){
                    $materiel_id=$a->{'idMateriel'};
                    $nbRecensementTableau=DB::select("SELECT count(idRecensement) FROM recensements WHERE materiel_id = :materiel_id and annee= :annee", ['materiel_id' => $materiel_id,'annee'=>2024]);
                    $nbRecensement= $nbRecensementTableau[0]->{"count(idRecensement)"};
                    if($nbRecensement==0){//recensement non existant sur le materiel
                        //creer le recensement
                        $recensement=new recensement;
                        $recensement->prixUnite=doubleval($array[1]);
                        $recensement->existantApresEcriture=intval($array[5]);
                        $recensement->materiel_id=$materiel_id;
                        $recensement->annee=2024;
                        $recensement->save();
                        break;
                    }
                }
            }
        }
    }
}
