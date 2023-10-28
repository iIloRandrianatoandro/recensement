<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;
//use Maatwebsite\Excel\Excel;

use App\Imports\RecensementsImport;
use App\Exports\RecensementsExport;
use App\Http\Controllers\Controller;
use App\Models\recensement;
use App\Models\materiel;
use DB;
use Carbon\Carbon;

class RecensementController extends Controller
{
    public function import(Request $req)
    {   
        $excel = Excel::toArray(new RecensementsImport, $req->file);//file nom champ du fichier
        $annee=$req->annee;
        $premiereUtilisation=$req->premiereUtilisation;
        $nomenclature=$req->nomenclature;
        $array=array_slice($excel[0], 3); // ne prend pas en compte les 3 premiere lignes du fichier excel cad titres
        foreach($array as $array){
            // s'il y a une nouvelle entree de materiel ou premiere utilisation, ajouter le materiel à la base de donnees 
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
                $recensement->materiel_idMateriel=$idMateriel;
                $recensement->annee=$annee;
                $recensement->save();
            }
            else{ //ajout recensement sans nouvelle entree et non premiere utilisation
                $designation=$array[0];
                $listeDoublonTableau = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                foreach($listeDoublonTableau as $materiel){
                    $materiel_idMateriel=$materiel->{'idMateriel'};
                    $nbRecensementTableau=DB::select("SELECT count(idRecensement) FROM recensements WHERE materiel_idMateriel = :materiel_idMateriel and annee= :annee", ['materiel_idMateriel' => $materiel_idMateriel,'annee'=>$annee]);
                    $nbRecensement= $nbRecensementTableau[0]->{"count(idRecensement)"};
                    if($nbRecensement==0){//recensement non existant sur le materiel
                        //creer le recensement
                        $recensement=new recensement;
                        $recensement->prixUnite=doubleval($array[1]);
                        $recensement->existantApresEcriture=intval($array[5]);
                        $recensement->materiel_idMateriel=$materiel_idMateriel;
                        $recensement->annee=$annee;
                        $recensement->save();
                        break;
                    }
                }
            }
        }
    }
    public function listeMaterielARecense($annee){   
        $listeMaterielARecense=DB::select("select recensements.idRecensement,recensements.existantApresEcriture,recensements.materiel_idMateriel,materiels.designation from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        return $listeMaterielARecense;
    }
    public function rechercherRecensement($designation){
        $listeMaterielCorrespondant=DB::select("select materiels.designation,recensements.* from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.designation like '%$designation%' and recense=false; ");
        return $listeMaterielCorrespondant;
    }
    public function voirRecensement($id)
    { 
        $recensement=DB::select("select recensements.idRecensement,recensements.prixUnite,recensements.existantApresEcriture,recensements.materiel_idMateriel,materiels.designation,recensements.deficitParArticle,recensements.excedentParArticle, recensements.observation from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and idRecensement='$id'");
        return $recensement;
    }

    public function recenserMateriel(Request $req , $idRecensement)
    { 
        $recensement=recensement::find($idRecensement);
        $recensement->deficitParArticle =$req->deficitParArticle ;
        $recensement->excedentParArticle =$req->excedentParArticle ;
        $recensement->observation =$req->observation ;
        $recensement->recense =true;
        $recensement->save();
        return $recensement;
    } 
    public function suivreFluxRecensement($annee){
        //liste Materiels a recenser durant l'année
        $listeMateriel=DB::select("select materiels.designation,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee'");
        //nombre de materiels a recenser durant l'annee
        $nbMateriels=DB::select("select count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee'");
        //nombre de materiels recensés
        $nbMaterielsRecenses=DB::select("select count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=true");
        //nombre de materiels qui restent a recenser
        $nbMaterielsARecenser=DB::select("select count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        //liste de materiels deja recensé
        $listeMaterielRecense=DB::select("select materiels.designation,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=true");
        //liste de materiels qui reste a recenser
        $listeMaterielARecense=DB::select("select materiels.designation,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        return ['listeMateriel'=>$listeMateriel,'nbMateriels'=>$nbMateriels,'nbMaterielsRecenses'=>$nbMaterielsRecenses,'nbMaterielsRecenses'=>$nbMaterielsRecenses,'nbMaterielsARecenser'=>$nbMaterielsARecenser,'listeMaterielFRecense'=>$listeMaterielRecense,'listeMaterielARecense'=>$listeMaterielARecense];
    }
    public function modifierRecensement(Request $req , $idRecensement)
    { 
        $recensement=recensement::find($idRecensement);
        $recensement->deficitParArticle =$req->deficitParArticle ;
        $recensement->excedentParArticle =$req->excedentParArticle ;
        $recensement->prixUnite =$req->prixUnite ;
        $recensement->observation =$req->observation ;
        $recensement->save();
        return $recensement;
    } 
    public function genererRecapitulatif($annee){
        //valeur totale excedents
        $valeurTotaleExcedent=Db::select("select sum(excedentParArticle * prixUnite) from recensements");
        //valeur totale deficits
        $valeurTotaleDeficit=Db::select("select sum(deficitParArticle * prixUnite) from recensements");
        //valeur totale existants
        $valeurTotaleExistant=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements");
        //nombre d'articles par nomenclature
        $nbMaterielsParNomenclature=DB::select("select materiels.nomenclature,count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel group by materiels.nomenclature");
        //nombre d'articles total //nbArticle=existantApresEcriture+excedent-deficit
        $nbArticle=DB::select("select count(idRecensement)from recensements");
        //liste recensement
        $listeRecensementsTab=DB::select("select materiels.designation,materiels.especeUnite,recensements.prixUnite,recensements.existantApresEcriture,(recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as constateesParRecensement, recensements.excedentParArticle, recensements.deficitParArticle, (recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent, (recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit, ((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant, recensements.observation from recensements, materiels where recensements.materiel_idMateriel=materiels.idMateriel ");

        $a=['valeurTotaleExcedent'=>$valeurTotaleExcedent[0]->{'sum(excedentParArticle * prixUnite)'},'valeurTotaleDeficit'=>$valeurTotaleDeficit[0]->{'sum(deficitParArticle * prixUnite)'},'valeurTotaleExistant'=>$valeurTotaleExistant[0]->{'sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite)'},'nbMaterielsParNomenclature'=>$nbMaterielsParNomenclature,'nbArticle'=>$nbArticle[0]->{'count(idRecensement)'},'listeRecensementsTab'=>$listeRecensementsTab];
        return $a;
    }
    public function consulterEvolution5Ans(){
        $annee5=Carbon::now()->year;
        $annee1=$annee5-4;
        $annee2=$annee5-3;
        $annee3=$annee5-2;
        $annee4=$annee5-1;
        $annee1Valeur=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements where annee='$annee1'");
        $annee1Quantite=DB::select("select count(idRecensement)from recensements where annee='$annee1'");
        $annee2Valeur=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements where annee='$annee2'");
        $annee2Quantite=DB::select("select count(idRecensement)from recensements where annee='$annee2'");
        $annee3Valeur=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements where annee='$annee3'");
        $annee3Quantite=DB::select("select count(idRecensement)from recensements where annee='$annee3'");
        $annee4Valeur=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements where annee='$annee4'");
        $annee4Quantite=DB::select("select count(idRecensement)from recensements where annee='$annee4'");
        $annee5Valeur=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements where annee='$annee5'");
        $annee5Quantite=DB::select("select count(idRecensement)from recensements where annee='$annee5'");
        return ['annee1Valeur'=>$annee1Valeur,'annee1Quantite'=>$annee1Quantite,'annee2Valeur'=>$annee2Valeur,'annee2Quantite'=>$annee2Quantite,'annee2Quantite'=>$annee2Quantite,'annee3Valeur'=>$annee3Valeur,'annee3Quantite'=>$annee3Quantite,'annee4Valeur'=>$annee4Valeur,'annee4Quantite'=>$annee4Quantite,'annee5Valeur'=>$annee5Valeur,'annee5Quantite'=>$annee5Quantite];
    }
    public function consulterEvolutionMateriel($materielID){
        $annee5=Carbon::now()->year;
        $annee1=$annee5-4;
        $annee2=$annee5-3;
        $annee3=$annee5-2;
        $annee4=$annee5-1;
        $annee1Valeur=Db::select("select sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee1'");
        $annee1Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle)from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee1'");
        $annee2Valeur=Db::select("select sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee2'");
        $annee2Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle)from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee2'");
        $annee3Valeur=Db::select("select sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee3'");
        $annee3Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle)from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee3'");
        $annee4Valeur=Db::select("select sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee4'");
        $annee4Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle)from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee4'");
        $annee5Valeur=Db::select("select sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee5'");
        $annee5Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle)from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee5'");
        return ['annee1Valeur'=>$annee1Valeur,'annee1Quantite'=>$annee1Quantite,'annee2Valeur'=>$annee2Valeur,'annee2Quantite'=>$annee2Quantite,'annee2Quantite'=>$annee2Quantite,'annee3Valeur'=>$annee3Valeur,'annee3Quantite'=>$annee3Quantite,'annee4Valeur'=>$annee4Valeur,'annee4Quantite'=>$annee4Quantite,'annee5Valeur'=>$annee5Valeur,'annee5Quantite'=>$annee5Quantite];
    }
    /*public function export()
    {
        return Excel::download(new RecensementsExport, 'j.xlsx');
    }*/
    public function export()
{
    // Effectuez vos requêtes pour obtenir les données
   // $dataFromQuery1 = DB::table('recensements')->get();
   // return $dataFromQuery1;
    $valeurTotaleExcedent = DB::table('recensements')->select(DB::raw('sum(excedentParArticle * prixUnite) as total'))->first(); //tokony anaty tableau aloha
    $valeurTotaleDeficit = DB::table('recensements')->select(DB::raw('sum(deficitParArticle * prixUnite) as total'))->first();
    $valeurTotaleExistant = DB::table('recensements')->select(DB::raw('sum((existantApresEcriture + excedentParArticle - deficitParArticle) * prixUnite) as total'))->first();
    $nbMaterielsParNomenclature = DB::table('recensements')->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')->select('materiels.nomenclature', DB::raw('count(recensements.idRecensement) as total'))->groupBy('materiels.nomenclature')->get();
    $nbArticle = DB::table('recensements')->count();
    $listeRecensementsTab = DB::table('recensements')
    ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
    ->select(
        'materiels.designation',
        'materiels.especeUnite',
        'recensements.prixUnite',
        'recensements.existantApresEcriture',
        DB::raw('(recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) as constateesParRecensement'),
        'recensements.excedentParArticle',
        'recensements.deficitParArticle',
        DB::raw('(recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent'),
        DB::raw('(recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit'),
        DB::raw('((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant'),
        'recensements.observation'
    )
    ->get();
    //return $nbMaterielsParNomenclature;

    // Fusionnez les données en un tableau unique
    //$combinedData = array_merge($valeurTotaleExcedent->toArray(), $valeurTotaleDeficit->toArray());
    $combinedData = [
        'totalExcedent' => $valeurTotaleExcedent->total,
        'totalDeficit' => $valeurTotaleDeficit->total,
        'valeurTotaleExistant'=>$valeurTotaleExistant,
        'nbArticle'=>$nbArticle,
        'nbMaterielsParNomenclature'=>$nbMaterielsParNomenclature,
        'listeRecensementsTab'=>$listeRecensementsTab,

        // Ajoutez d'autres données ici
    ];
    // Utilisez Laravel Excel pour générer un fichier Excel
    return Excel::download(new RecensementsExport($combinedData), 'jal.xlsx');
    
}
/*public function export(){
    //$dataFromQuery1 = DB::table('recensements')->get();
    //return $dataFromQuery1;
    //$dataFromQuery1 =  DB::table('recensements')->select(DB::raw('sum(excedentParArticle * prixUnite) '))->first();
    //return $dataFromQuery1;
    $valeurTotaleDeficit=Db::select("select sum(deficitParArticle * prixUnite) as b from recensements");
      /* $valeurTotaleExcedent=Db::select("select sum(excedentParArticle * prixUnite) from recensements");
        //valeur totale deficits
        $valeurTotaleDeficit=Db::select("select sum(deficitParArticle * prixUnite) from recensements");
        //valeur totale existants
        $valeurTotaleExistant=Db::select("select sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite) from recensements");
        //nombre d'articles par nomenclature
        $nbMaterielsParNomenclature=DB::select("select materiels.nomenclature,count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel group by materiels.nomenclature");
        //nombre d'articles total //nbArticle=existantApresEcriture+excedent-deficit
        $nbArticle=DB::select("select count(idRecensement)from recensements");
        //liste recensement
        $listeRecensementsTab=DB::select("select materiels.designation,materiels.especeUnite,recensements.prixUnite,recensements.existantApresEcriture,(recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as constateesParRecensement, recensements.excedentParArticle, recensements.deficitParArticle, (recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent, (recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit, ((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant, recensements.observation from recensements, materiels where recensements.materiel_idMateriel=materiels.idMateriel ");
*/
    // Fusionnez les données en un tableau unique
    //$combinedData = array_merge($dataFromQuery1->toArray(), $valeurTotaleDeficit->toArray());

  //  return Excel::download(new RecensementsExport($dataFromQuery1,$valeurTotaleDeficit), 'jal.xlsx');
//}
}










