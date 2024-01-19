<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Imports\RecensementsImport;
use App\Exports\RecensementsExport;
use App\Exports\rec3Export;
use App\Http\Controllers\Controller;
use App\Models\recensement;
use App\Models\materiel;
use DB;
use Carbon\Carbon;
use NumberFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\WorksheetDimension;




class RecensementController extends Controller
{
    public function listerAnneeAvecRecensement(){
        $listeAnnee=DB::select("select annee from recensements group by annee");
        foreach($listeAnnee as $listeAnnee){
            $annee[]=$listeAnnee->{"annee"};
        }
        return $annee;
    }
    public function importer(Request $req)
    {   
        $file = $req->file('file');
        $spreadsheet = IOFactory::load($file);//file : nom champ du fichier

        $annee=$req->annee;
        $premiereUtilisation=false;
        $nbMateriel=DB::select("select count(idMateriel) from materiels;");
        if (($nbMateriel[0]->{"count(idMateriel)"})===0){$premiereUtilisation=true;}
            // récupérer les feuilles excel par nomenclature
            $nomenclature3 = $spreadsheet->getSheetByName('3');
            $nomenclature5 = $spreadsheet->getSheetByName('5');
            $nomenclature10 = $spreadsheet->getSheetByName('10');
        
            // mettre les données des feuilles excel dans des tableau
            $nomenclature3Feuille = $nomenclature3->toArray();
            $nomenclature5Feuille = $nomenclature5->toArray();
            $nomenclature10Feuille = $nomenclature10->toArray();

            // ne prend pas en compte les 3 premiere lignes du fichier excel cad titres
            $nomenclature3Tab=array_slice($nomenclature3Feuille, 3);
            $nomenclature5Tab=array_slice($nomenclature5Feuille, 3);
            $nomenclature10Tab=array_slice($nomenclature10Feuille, 3);

            function importerDonnees($array,$nomenclature,$annee,$premiereUtilisation){
                foreach($array as $array){
                    if($array[1]==null){ //s'il n'y a pas de designation
                        //ne rien faire
                    }
                    else{
                        $designation=$array[1];
                        $prixUnite=$array[3];
                        $especeUnite=$array[2];
                        $existantApresEcriture=$array[7];
                        // s'il y a une nouvelle entree de materiel ou premiere utilisation, ajouter le materiel à la base de donnees 
                        if(($array[5]!=null) || ($premiereUtilisation===true)){// $array[5]=case entree pendant l'annee
                        //creer materiel
                        $materiel=new materiel;
                        $materiel->designation=$designation;
                        $materiel->nomenclature=$nomenclature;
                        $materiel->especeUnite=$especeUnite;
    
    
                        //nombre de doublon du materiel
                        $nbDoublonTableau = DB::select("SELECT COUNT(idMateriel) FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                        $nbDoublon=$nbDoublonTableau[0]->{'COUNT(idMateriel)'};
                        $materiel->doublon=$nbDoublon+1;
                        $materiel->save();
                        //creer le recensement
                        $recensement=new recensement;
                        $recensement->prixUnite=doubleval($prixUnite);
                        $recensement->existantApresEcriture=intval($existantApresEcriture);
                        // chercher l'id du materiel
                        $doublon=$nbDoublon+1;
                        $id = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation and doublon= :doublon", ['designation' => $designation,'doublon' => $doublon]);
                        $idMateriel=$id[0]->{'idMateriel'};
                        $recensement->materiel_idMateriel=$idMateriel;
                        $recensement->annee=$annee;
                        $recensement->save();
                        }
                        else{ //ajout recensement sans nouvelle entree et non premiere utilisation
                        $listeDoublonTableau = DB::select("SELECT idMateriel FROM materiels WHERE designation = :designation", ['designation' => $designation]);
                        foreach($listeDoublonTableau as $materiel){
                            $materiel_idMateriel=$materiel->{'idMateriel'};
                            $nbRecensementTableau=DB::select("SELECT count(idRecensement) FROM recensements WHERE materiel_idMateriel = :materiel_idMateriel and annee= :annee", ['materiel_idMateriel' => $materiel_idMateriel,'annee'=>$annee]);
                            $nbRecensement= $nbRecensementTableau[0]->{"count(idRecensement)"};
                            if($nbRecensement==0){//recensement non existant sur le materiel
                                //creer le recensement
                                $recensement=new recensement;
                                $recensement->prixUnite=doubleval($prixUnite);
                                $recensement->existantApresEcriture=intval($existantApresEcriture);
                                $recensement->materiel_idMateriel=$materiel_idMateriel;
                                $recensement->annee=$annee;
                                $recensement->save();
                                break;
                            }
                        }
                        }
                    }
                }

            }   
        
            importerDonnees($nomenclature3Tab,'3',$annee,$premiereUtilisation);
            importerDonnees($nomenclature5Tab,'5',$annee,$premiereUtilisation);
            importerDonnees($nomenclature10Tab,'10',$annee,$premiereUtilisation);
    }
    public function listeMaterielARecense($annee){
        //liste materiels a recenser   
        $listeMaterielARecense=DB::select("select recensements.idRecensement,recensements.existantApresEcriture,recensements.materiel_idMateriel,materiels.designation,materiels.nomenclature,recensements.prixUnite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        // Liste des nomenclatures
        $nomenclatures = DB::table('materiels')
            ->select('nomenclature')
            ->groupBy('nomenclature')
            ->get();
        //liste materiel par nomenclature
        $recensementParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $recensement = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->where('materiels.nomenclature', $nomenclature->nomenclature)
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', false)
            ->select(
                'recensements.idRecensement as idRecensement',
                'materiels.designation as designation' ,
                'materiels.especeUnite as especeUnite',
                'materiels.nomenclature as nomenclature',
                'recensements.prixUnite as prixUnite',
                'recensements.existantApresEcriture as existantApresEcriture',
                DB::raw('(recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) as constateesParRecensement'),
                'recensements.excedentParArticle as excedentParArticle',
                'recensements.deficitParArticle as deficitParArticle',
                DB::raw('(recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent'),
                DB::raw('(recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit'),
                DB::raw('((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant'),
                'recensements.observation as observation'
            )
            ->get();
    
            $recensementParNomenclature[$nomenclature->nomenclature] = $recensement;
        }
        //nombre materiel par nomenclature
        /*$nbRecensementParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $nbRecensement = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->where('materiels.nomenclature', $nomenclature->nomenclature)
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', false)
            ->select(
                DB::raw('count(idRecensement)'),
            )
            ->get();
    
            $nbRecensementParNomenclature[$nomenclature->nomenclature] = $nbRecensement;
        }*/
        $a = [
            'nomenclatures' => $nomenclatures,
            //'nbRecensementParNomenclature' => $nbRecensementParNomenclature,
            'listeMaterielARecense' => $listeMaterielARecense,
            'recensementParNomenclature' => $recensementParNomenclature,
        ];
        return $a;
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
    public function suivreFluxRecensement(){
        $annee=Carbon::now()->year;
        //liste Materiels a recenser durant l'année
        $listeMateriel=DB::select("select materiels.designation,materiels.nomenclature,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee'");
        //nombre de materiels recensés
        $nbMaterielsRecenses=DB::select("select count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=true");
        //nombre de materiels qui restent a recenser
        $nbMaterielsARecenser=DB::select("select count(recensements.idRecensement) from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        //liste de materiels deja recensé
        $listeMaterielRecense=DB::select("select materiels.designation,materiels.nomenclature,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=true");
        //liste de materiels qui reste a recenser
        $listeMaterielARecense=DB::select("select materiels.designation,materiels.nomenclature,recensements.*  from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and annee='$annee' and recense=false");
        //nombre total de materiels 
        $nbTotalMateriels=DB::select("select count(idRecensement) from recensements where annee='$annee'");
        return ['nbTotalMateriels'=>$nbTotalMateriels,'nbMaterielsRecenses'=>$nbMaterielsRecenses,'nbMaterielsRecenses'=>$nbMaterielsRecenses,'nbMaterielsARecenser'=>$nbMaterielsARecenser,'listeMateriel'=>$listeMateriel,'listeMaterielRecense'=>$listeMaterielRecense,'listeMaterielARecense'=>$listeMaterielARecense];
    }
    public function rechercherRecensement($designation,$annee){//administrateur
        $listeMaterielCorrespondant=DB::select("select materiels.designation,recensements.* from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.designation like '%$designation%' and annee=$annee; ");
        return $listeMaterielCorrespondant;
    }
    public function modifierRecensement(Request $req , $idRecensement)
    { 
        $recensement=recensement::find($idRecensement);
        $recensement->deficitParArticle =$req->deficitParArticle ;
        $recensement->excedentParArticle =$req->excedentParArticle ;
        $recensement->observation =$req->observation ;
        $recensement->recense=true;
        $recensement->save();
        return $recensement;
    } 
    public function genererRecapitulatif(Request $req){
        $annee=$req->annee;
        //$annee=2023;
        // Liste des nomenclatures
        $nomenclatures = DB::table('materiels')
            ->select('nomenclature')
            ->groupBy('nomenclature')
            ->get();
        //recap par nomenclature
        $recapParNomenclature=DB::select(" select materiels.nomenclature,sum(recensements.excedentParArticle * recensements.prixUnite)as valeurExcedent,sum(recensements.deficitParArticle * recensements.prixUnite)as valeurDeficit,SUM((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant,COUNT(recensements.idRecensement) as nbArticle  from materiels,recensements where materiels.idMateriel=recensements.materiel_idMateriel and recensements.annee='$annee' and recensements.recense=true group by materiels.nomenclature;");
        //excedent par nomenclature
        $excedentParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $valeurExcedent = DB::table('recensements')
                ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
                ->where('materiels.nomenclature', $nomenclature->nomenclature)
                ->where('recensements.annee', $annee)
                ->where('recensements.recense', true)
                ->sum(DB::raw('recensements.excedentParArticle * recensements.prixUnite'));
    
            $excedentParNomenclature[$nomenclature->nomenclature] = $valeurExcedent;
        }
        //valeur totale des excedents
        $valeurTotaleExcedent = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->select(DB::raw('COALESCE(SUM(recensements.excedentParArticle * recensements.prixUnite), 0) as totalExcedent'))
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', true)
            ->first();

        //deficit par nomenclature
        $deficitParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $valeurDeficit = DB::table('recensements')
                ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
                ->where('materiels.nomenclature', $nomenclature->nomenclature)
                ->where('recensements.annee', $annee)
                ->where('recensements.recense', true)
                ->sum(DB::raw('recensements.deficitParArticle * recensements.prixUnite'));
    
            $deficitParNomenclature[$nomenclature->nomenclature] = $valeurDeficit;
        }
        // Valeur totale des déficits
        $valeurTotaleDeficit = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->select(DB::raw('COALESCE(SUM(recensements.deficitParArticle * recensements.prixUnite), 0) as totalDeficit'))
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', true)
            ->first();

        // Existant par nomenclature
        $existantParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
        $valeurExistant = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->select(DB::raw('COALESCE(SUM((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite), 0) as totalExistant'))
            ->where('materiels.nomenclature', $nomenclature->nomenclature)
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', true)
            ->first();

        $existantParNomenclature[$nomenclature->nomenclature] = $valeurExistant->totalExistant;
        }

        // Valeur totale des existants avec coalesce
        $valeurTotaleExistant = DB::table('recensements')
        ->select(DB::raw('COALESCE(SUM((existantApresEcriture + excedentParArticle - deficitParArticle) * prixUnite), 0) as totalExistant'))
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();

        // Nombre d'articles par nomenclature
        $nbArticleParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $nbArticle = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->select(DB::raw('COUNT(recensements.idRecensement) as totalArticles'))
            ->where('materiels.nomenclature', $nomenclature->nomenclature)
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', true)
            ->first();

            $nbArticleParNomenclature[$nomenclature->nomenclature] = $nbArticle->totalArticles;
        }

        // Nombre total d'articles
        $nbArticleTotal = DB::table('recensements')
        ->select(DB::raw('COUNT(idRecensement) as totalArticles'))
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();
        //liste recensement
        $listeRecensementsTab=DB::select("select recensements.idRecensement,materiels.nomenclature,materiels.designation,materiels.especeUnite,recensements.prixUnite,recensements.existantApresEcriture,(recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as constateesParRecensement, recensements.excedentParArticle, recensements.deficitParArticle, (recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent, (recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit, ((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant, recensements.observation from recensements, materiels where recensements.materiel_idMateriel=materiels.idMateriel and recensements.annee=$annee and recense=true");
        //recensement par nomenclature
        $recensementParNomenclature = [];
        foreach ($nomenclatures as $nomenclature) {
            $recensement = DB::table('recensements')
            ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
            ->where('materiels.nomenclature', $nomenclature->nomenclature)
            ->where('recensements.annee', $annee)
            ->where('recensements.recense', true)
            ->select(
                'recensements.idRecensement as idRecensement',
                'materiels.designation as designation' ,
                'materiels.especeUnite as especeUnite',
                'recensements.prixUnite as prixUnite',
                'recensements.existantApresEcriture as existantApresEcriture',
                DB::raw('(recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) as constateesParRecensement'),
                'recensements.excedentParArticle as excedentParArticle',
                'recensements.deficitParArticle as deficitParArticle',
                DB::raw('(recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent'),
                DB::raw('(recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit'),
                DB::raw('((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant'),
                'recensements.observation as observation'
            )
            ->get();
    
            $recensementParNomenclature[$nomenclature->nomenclature] = $recensement;
        }
        //nombre d'article ayant des excedents
        $nbArticleAvecExcedent = DB::table('recensements')
        ->where('excedentParArticle', '!=', 0)
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->count();
        //nombre d'article ayant des deficits
        $nbArticleAvecDeficit = DB::table('recensements')
        ->where('deficitParArticle', '!=', 0)
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->count();
        $a = [
            'nomenclatures' => $nomenclatures,
            'recapParNomenclature' => $recapParNomenclature,
            'excedentParNomenclature' => $excedentParNomenclature,
            'valeurTotaleExcedent' => $valeurTotaleExcedent,
            'deficitParNomenclature' => $deficitParNomenclature,
            'valeurTotaleDeficit' => $valeurTotaleDeficit,
            'existantParNomenclature' => $existantParNomenclature,
            'valeurTotaleExistant' => $valeurTotaleExistant,
            'nbArticleParNomenclature' => $nbArticleParNomenclature,
            'nbArticleTotal' => $nbArticleTotal,
            'nbArticleAvecExcedent'=>$nbArticleAvecExcedent,
            'nbArticleAvecDeficit'=>$nbArticleAvecDeficit,
            'recensementParNomenclature' => $recensementParNomenclature,
            'listeRecensementsTab' => $listeRecensementsTab,
        ];
        return $a;
    }
    public function consulterEvolution5Ans(){
        $annee5=Carbon::now()->year;
        $annee1=$annee5-4;
        $annee2=$annee5-3;
        $annee3=$annee5-2;
        $annee4=$annee5-1;
        $annee1Valeur=Db::select("select COALESCE(sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite),0) as valeur from recensements where annee='$annee1' and recense=true");
        $annee1Quantite=DB::select("SELECT COALESCE(sum(existantApresEcriture + excedentParArticle - deficitParArticle),0) as quantite FROM recensements WHERE annee = '$annee1' AND recense = true;");
        $annee2Valeur=Db::select("select COALESCE(sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite),0) as valeur from recensements where annee='$annee2' and recense=true");
        $annee2Quantite=DB::select("SELECT COALESCE(sum(existantApresEcriture + excedentParArticle - deficitParArticle),0) as quantite FROM recensements WHERE annee = '$annee2' AND recense = true;");
        //$annee2Quantite=DB::select("select sum(existantApresEcriture+excedentParArticle-deficitParArticle) as quantite from recensements where annee='$annee2' and recense=true");
        $annee3Valeur=Db::select("select COALESCE(sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite),0) as valeur from recensements where annee='$annee3' and recense=true");
        //$annee3Quantite=DB::select("select sum(existantApresEcriture+excedentParArticle-deficitParArticle) as quantite  from recensements where annee='$annee3' and recense=true");
        $annee3Quantite=DB::select("SELECT COALESCE(sum(existantApresEcriture + excedentParArticle - deficitParArticle),0) as quantite FROM recensements WHERE annee = '$annee3' AND recense = true;");
        $annee4Valeur=Db::select("select COALESCE(sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite),0) as valeur from recensements where annee='$annee4' and recense=true");
        //$annee4Quantite=DB::select("select sum(existantApresEcriture+excedentParArticle-deficitParArticle) as quantite  from recensements where annee='$annee4' and recense=true");
        $annee4Quantite=DB::select("SELECT COALESCE(sum(existantApresEcriture + excedentParArticle - deficitParArticle),0) as quantite FROM recensements WHERE annee = '$annee4' AND recense = true;");
        $annee5Valeur=Db::select("select COALESCE(sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite),0) as valeur from recensements where annee='$annee5' and recense=true");
        //$annee5Quantite=DB::select("select sum(existantApresEcriture+excedentParArticle-deficitParArticle) as quantite s from recensements where annee='$annee5' and recense=true");
        $annee5Quantite=DB::select("SELECT COALESCE(sum(existantApresEcriture + excedentParArticle - deficitParArticle),0) as quantite FROM recensements WHERE annee = '$annee5' AND recense = true;");
        return ['annee1Valeur'=>$annee1Valeur,'annee1Quantite'=>$annee1Quantite,'annee2Valeur'=>$annee2Valeur,'annee2Quantite'=>$annee2Quantite,'annee2Quantite'=>$annee2Quantite,'annee3Valeur'=>$annee3Valeur,'annee3Quantite'=>$annee3Quantite,'annee4Valeur'=>$annee4Valeur,'annee4Quantite'=>$annee4Quantite,'annee5Valeur'=>$annee5Valeur,'annee5Quantite'=>$annee5Quantite];
    }
    public function consulterEvolutionMateriel($materielID){
        $annee5=Carbon::now()->year;
        $annee1=$annee5-4;
        $annee2=$annee5-3;
        $annee3=$annee5-2;
        $annee4=$annee5-1;
        $annee1Valeur=Db::select("select COALESCE(sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite),0) as valeur from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee1' and recense=true");
        $annee1Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as quantite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee1' and recense=true");
        $annee2Valeur=Db::select("select COALESCE(sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite),0) as valeur from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee2' and recense=true");
        $annee2Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as quantite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee2' and recense=true");
        $annee3Valeur=Db::select("select COALESCE(sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite),0) as valeur from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee3' and recense=true");
        $annee3Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as quantite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee3' and recense=true");
        $annee4Valeur=Db::select("select COALESCE(sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite),0) as valeur from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee4' and recense=true");
        $annee4Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as quantite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee4' and recense=true");
        $annee5Valeur=Db::select("select COALESCE(sum((recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) * recensements.prixUnite),0) as valeur from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee5' and recense=true");
        $annee5Quantite=DB::select("select (recensements.existantApresEcriture+recensements.excedentParArticle-recensements.deficitParArticle) as quantite from recensements,materiels where recensements.materiel_idMateriel=materiels.idMateriel and materiels.idMateriel='$materielID' and annee='$annee5' and recense=true");
        return ['annee1Valeur'=>$annee1Valeur,'annee1Quantite'=>$annee1Quantite,'annee2Valeur'=>$annee2Valeur,'annee2Quantite'=>$annee2Quantite,'annee2Quantite'=>$annee2Quantite,'annee3Valeur'=>$annee3Valeur,'annee3Quantite'=>$annee3Quantite,'annee4Valeur'=>$annee4Valeur,'annee4Quantite'=>$annee4Quantite,'annee5Valeur'=>$annee5Valeur,'annee5Quantite'=>$annee5Quantite];
    }
public function genererExcel($annee)
{    
    // Créez un objet Excel
    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    // Supprimez la première feuille de calcul inutile
    $excel->removeSheetByIndex(0);
    //liste des nomenclatures
    $nomenclatures=Db::select('select nomenclature from materiels group by nomenclature');
    $nomenclaturesTab=[];
    foreach ($nomenclatures as $a){
        $nomenclaturesTab[]=$a->nomenclature;
    }
    foreach($nomenclaturesTab as $nomenclature){
        $listeRecensementsTab = DB::table('recensements')
    ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
    ->where('materiels.nomenclature', $nomenclature) 
    ->where('recensements.annee', $annee)
    ->where('recensements.recense', true)
    ->select(
        'materiels.designation as designation' ,
        'materiels.especeUnite as especeUnite',
        'recensements.prixUnite as prixUnite',
        'recensements.existantApresEcriture as existantApresEcriture',
        DB::raw('(recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) as constateesParRecensement'),
        'recensements.excedentParArticle as excedentParArticle',
        'recensements.deficitParArticle as deficitParArticle',
        DB::raw('(recensements.excedentParArticle * recensements.prixUnite) as valeurExcedent'),
        DB::raw('(recensements.deficitParArticle * recensements.prixUnite) as valeurDeficit'),
        DB::raw('((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as valeurExistant'),
        'recensements.observation as observation'
    )
    ->get();
    // Créez une feuille de calcul (Feuille 1)
    $feuille1 = $excel->createSheet();  // Obtenez la feuille active
    $feuille1->setTitle('rec'.$nomenclature); // Définissez le titre de la feuille


    //titres
    $feuille1->setCellValue("A". 1,"Désignation des matières, denrées et objets");
    $feuille1->setCellValue("B". 1,"Espèce des unités");
    $feuille1->setCellValue("C". 1,"Prix de l'unité");
    $feuille1->setCellValue("D". 1,"Quantités");
    $feuille1->setCellValue("F". 1,"Excédent par article");
    $feuille1->setCellValue("G". 1,"Déficit par article");
    $feuille1->setCellValue("H". 1,"Valeurs");
    $feuille1->setCellValue("M". 1,"Observation");

    $feuille1->setCellValue("D". 2,"Existants d'après les écritures");
    $feuille1->setCellValue("E". 2,"Constatées par recensement");
    $feuille1->setCellValue("H". 2,"des excédents");
    $feuille1->setCellValue("J". 2,"des déficits");
    $feuille1->setCellValue("L". 2,"des existants");
    
    $feuille1->setCellValue("H". 3,"par article");
    $feuille1->setCellValue("I". 3,"par numéro de la nomenclature sommaire");
    $feuille1->setCellValue("J". 3,"par article");
    $feuille1->setCellValue("K". 3,"par numéro de la nomenclature sommaire");
    $feuille1->setCellValue("A". 4,"NOMENCLATURE ".$nomenclature);

    //fusion cellules titres
    $feuille1->mergeCells('A1:A3');
    $feuille1->mergeCells('B1:B3');
    $feuille1->mergeCells('C1:C3');
    $feuille1->mergeCells('D2:D3');
    $feuille1->mergeCells('E2:E3');
    $feuille1->mergeCells('F1:F3');
    $feuille1->mergeCells('G1:G3');
    $feuille1->mergeCells('H1:L1');
    $feuille1->mergeCells('H2:I2');
    $feuille1->mergeCells('J2:K2');
    $feuille1->mergeCells('L2:L3');
    $feuille1->mergeCells('M1:M3');
    $feuille1->mergeCells('D1:E1');
    
    //figer titres
    $feuille1->freezePane('A1');
    $feuille1->freezePane('A2');
    $feuille1->freezePane('A3');
    $feuille1->freezePane('A4');
    // Repeat headers on each printed page
    $feuille1->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 3);


    //contenu 
    // Définir la largeur de la colonne A
    $feuille1->getColumnDimension('A')->setWidth(75);
    // Insérer les données dans la feuille de calcul
    $rowIndex = 5;
    // Définir la hauteur totale de la page en mode paysage
    $nbLignes1page = 45;
    // Initialiser les variables
    $nbRecensementEcritSurPageActuelle = 0;
    $nbLignesEcriteSurPageActuelle = 1;
    $nbArticleRecense = 0;
    //index de la ligne au debut de la page
    $premiereLigne=5;
    //totalExistants
    $totalExistantEcrit=0;
    $totalExedentEcrit=0;
    $totalDeficitEcrit=0;
    $nonFinTableau=true;
    // Obtenir le pointeur sur le dernier élément
    $dernierElement = $listeRecensementsTab->last();
        foreach ($listeRecensementsTab as $recensement) {
        // Vérifier si on est sur le dernier élément
        if ($recensement === $dernierElement) {
            $nonFinTableau=false;
        }
        $columnIndex = 1;
        foreach ($recensement as $value) {
            if(($columnIndex==9)||($columnIndex==11)){
                $feuille1->setCellValueByColumnAndRow($columnIndex, $rowIndex, "");
                $columnIndex++;
                $feuille1->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);
            }
            else{
                $feuille1->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);

            }
            $cellContent = $feuille1->getCellByColumnAndRow(1, $rowIndex)->getFormattedValue();
            $designationTab = mb_str_split($cellContent, 1);
            $nbCaractere= count($designationTab);
            
        if(($columnIndex==1 & $nbCaractere>75)){
                // Appliquer le style à la cellule pour activer le "text wrapping" (retour à la ligne automatique)
                $feuille1->getCellByColumnAndRow($columnIndex, $rowIndex)->getStyle()->getAlignment()->setWrapText(true);
            }
            
            $columnIndex++;
        }
        
        // prendre le contenu de la cellule(designation)
        $cellContent = $feuille1->getCellByColumnAndRow(1, $rowIndex)->getValue();
        // diviser la designation par la largeur de la cellule pour avoir le nombre de lignes occupées par la designation
        $lines = mb_str_split($cellContent, 75); 
        $nbLigneDesignation = count($lines);
        $nbRecensementEcritSurPageActuelle+=1;
        $nbLignesEcriteSurPageActuelle+=$nbLigneDesignation;
        $nbArticleRecense+=1;

        $rowIndex++;

        if($nbLignesEcriteSurPageActuelle>$nbLignes1page & $nonFinTableau){
            $cellStyle = $feuille1->getStyleByColumnAndRow(3, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(3, $rowIndex, "A reporter");
            $cellStyle = $feuille1->getStyleByColumnAndRow(4, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(4, $rowIndex, $nbArticleRecense . " Articles");
            $feuille1->mergeCells('D' . $rowIndex . ':H' . $rowIndex);
            
            // Initialiser la sommeExistant
            $sommeExistant = 0;
            // Initialiser la sommeExedent
            $sommeExedent = 0;
            // Initialiser la sommeDeficit
            $sommeDeficit = 0;
            $finLigne=$rowIndex;
            // Parcourir les lignes jusqu'à finLigne
            for ($i = $premiereLigne; $i < $finLigne; $i++) {
                // Obtenir la valeur de la cellule existant
                $valeurExistantActuel = $feuille1->getCellByColumnAndRow(12, $i)->getValue();
                // Obtenir la valeur de la cellule exedent
                $valeurExedentActuel = $feuille1->getCellByColumnAndRow(8, $i)->getValue();
                // Obtenir la valeur de la cellule deficit
                $valeurDeficitActuel = $feuille1->getCellByColumnAndRow(10, $i)->getValue();

                // Ajouter la valeur à la somme existant
                $sommeExistant += $valeurExistantActuel;
                // Ajouter la valeur à la somme exedent
                $sommeExedent += $valeurExedentActuel;
                // Ajouter la valeur à la somme deficit
                $sommeDeficit += $valeurDeficitActuel;
            }
            $totalExistantEcrit+=$sommeExistant;
            $totalExedentEcrit+=$sommeExedent;
            $totalDeficitEcrit+=$sommeDeficit;


            // Mettre à jour la valeur de la cellule
            $cellStyle = $feuille1->getStyleByColumnAndRow(12, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(12, $rowIndex, $totalExistantEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(9, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(9, $rowIndex, $totalExedentEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(11, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(11, $rowIndex, $totalDeficitEcrit);

            $rowIndex++;
            $cellStyle = $feuille1->getStyleByColumnAndRow(3, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(3, $rowIndex, "Report");

            $cellStyle = $feuille1->getStyleByColumnAndRow(4, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(4, $rowIndex, $nbArticleRecense . " Articles");

            $feuille1->mergeCells('D' . $rowIndex . ':H' . $rowIndex);
            $cellStyle = $feuille1->getStyleByColumnAndRow(12, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(12, $rowIndex, $totalExistantEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(9, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(9, $rowIndex, $totalExedentEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(11, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(11, $rowIndex, $totalDeficitEcrit);

            $nbLignesEcriteSurPageActuelle=2;
            $rowIndex++;
            $premiereLigne=$rowIndex;
        }
    }

/*$sampleText = "- Armoire basse en matière mélaminée à 2 portes battantes de dim (150x80x40) cm"; // Remplacez cela par le texte réel que vous prévoyez d'utiliser
$length = strlen($sampleText);
return $length;

    $cellContent = $feuille1->getCellByColumnAndRow(1, 8)->getFormattedValue();
    $designationTab = mb_str_split($cellContent, 1);
    $nbCaractere= count($designationTab);
    return $cellContent;*/

    //fin tableau
    $cellStyle = $feuille1->getStyleByColumnAndRow(1, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(1, $rowIndex, "TOTAL NOMENCLATURE".$nomenclature);
            $feuille1->mergeCells('A' . $rowIndex . ':C' . $rowIndex);

            $cellStyle = $feuille1->getStyleByColumnAndRow(4, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(4, $rowIndex, $nbArticleRecense . " Articles");
            $feuille1->mergeCells('D' . $rowIndex . ':G' . $rowIndex);

            $cellStyle = $feuille1->getStyleByColumnAndRow(12, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(12, $rowIndex, $totalExistantEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(9, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(9, $rowIndex, $totalExedentEcrit);

            $cellStyle = $feuille1->getStyleByColumnAndRow(11, $rowIndex);
            $cellStyle->getFont()->setBold(true);
            $feuille1->setCellValueByColumnAndRow(11, $rowIndex, $totalDeficitEcrit);

    // Obtenez la lettre de la dernière colonne et le numéro de la dernière ligne
    $lastColumn = $feuille1->getHighestDataColumn();
    $lastRow = $feuille1->getHighestDataRow();

    // Appliquez des bordures aux cellules de A1 à la dernière cellule avec des données
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
        
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
        ],
    ];

    $feuille1->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray($styleArray);
   // $feuille1->getStyle('A1:' . $lastColumn . $lastRow)->getFont()->setSize(10); // Définir la taille de la police à 10 points
   
    }
    

    //derniere page
    $feuille2=$excel->createSheet();
    $feuille2->setTitle('recap');

    //tableau recapitulatif nomenclature
    //titre tableau
    $feuille2->setCellValue("A". 1,"NOMENCLATURE");
    $feuille2->setCellValue("B". 1,"EXCEDENTS");
    $feuille2->setCellValue("E". 1,"DEFICITS");
    $feuille2->setCellValue("I". 1,"EXISTANTS");
    $feuille2->setCellValue("L". 1,"ARTICLES");

    //mettre les nomenclatures dans le tableau
    $ligne="A"; $colonne=2;
    foreach ($nomenclaturesTab as $a){
        $feuille2->setCellValue($ligne. $colonne,$a);
        $colonne++;
    }
    $feuille2->setCellValue("A". $colonne,"TOTAL");
    //mettre la valeur des excedents par nomenclature
    $ligne="B"; $colonne=2;
    foreach ($nomenclaturesTab as $a){
        $valeurTotaleExcedent = DB::table('recensements')
        ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
        ->select(DB::raw('SUM(recensements.excedentParArticle * recensements.prixUnite) as totalExcedent'))
        ->where('materiels.nomenclature', $a)
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();

        // valeur des excédents
        $totalExcedent = $valeurTotaleExcedent->totalExcedent;
        
        $feuille2->setCellValue($ligne. $colonne,$totalExcedent);
        $colonne++;
    }
    $valeurTotaleExcedent = DB::table('recensements')
        ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
        ->select(DB::raw('SUM(recensements.excedentParArticle * recensements.prixUnite) as totalExcedent'))
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();

    // valeur totale des excédents
    $totalExcedent = $valeurTotaleExcedent->totalExcedent;
    $feuille2->setCellValue("B". $colonne,$totalExcedent);

    //mettre la valeur des deficits
     $ligne="E"; $colonne=2;
     foreach ($nomenclaturesTab as $a){
         $valeurTotaleDeficit = DB::table('recensements')
         ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
         ->select(DB::raw('SUM(recensements.deficitParArticle * recensements.prixUnite) as totalDeficit'))
         ->where('materiels.nomenclature', $a)
         ->where('recensements.annee', $annee)
         ->where('recensements.recense', true)
         ->first();
 
         // valeur des deficits par nomenclature
         $totalDeficit = $valeurTotaleDeficit->totalDeficit;
         
         $feuille2->setCellValue($ligne. $colonne,$totalDeficit);
         $colonne++;
     }
     $valeurTotaleDeficit = DB::table('recensements')
         ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
         ->select(DB::raw('SUM(recensements.deficitParArticle * recensements.prixUnite) as totalDeficit'))
         ->where('recensements.annee', $annee)
         ->where('recensements.recense', true)
         ->first();
 
     // valeur totale des deficits
     $totalDeficit = $valeurTotaleDeficit->totalDeficit;
     $feuille2->setCellValue("E". $colonne,$totalDeficit);

     //mettre la valeur des existants
     $ligne="I"; $colonne=2;
     foreach ($nomenclaturesTab as $a){
        $valeurTotaleExistant = DB::table('recensements')
        ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
       ->select(DB::raw('SUM((recensements.existantApresEcriture + recensements.excedentParArticle - recensements.deficitParArticle) * recensements.prixUnite) as totalExistant'))
       ->where('materiels.nomenclature', $a)
       ->where('recensements.annee', $annee)
       ->where('recensements.recense', true)
       ->first();
   
       //valeur des existants par nomenclature
       $totalExistant = $valeurTotaleExistant->totalExistant;
         
         $feuille2->setCellValue($ligne. $colonne,$totalExistant);
         $colonne++;
     }
     $valeurTotaleExistant = DB::table('recensements')
    ->select(DB::raw('SUM((existantApresEcriture + excedentParArticle - deficitParArticle) * prixUnite) as totalExistant'))
    ->where('recensements.annee', $annee)
    ->where('recensements.recense', true)
    ->first();

    //valeur totale des existants
    $totalExistant = $valeurTotaleExistant->totalExistant;
    $feuille2->setCellValue("I". $colonne,$totalExistant);
    
     //mettre le nombre d'articles
     $ligne="L"; $colonne=2;
     foreach ($nomenclaturesTab as $a){
       $nbArticle = DB::table('recensements')
       ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
        ->select(DB::raw('COUNT(recensements.idRecensement) as totalArticles'))
        ->where('materiels.nomenclature', $a)
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();

        //nombre d'articles par nomenclature
        $totalArticles = $nbArticle->totalArticles;
         
        $feuille2->setCellValue($ligne. $colonne,$totalArticles);
        $colonne++;
     }
     $nbArticle = DB::table('recensements')
        ->select(DB::raw('COUNT(idRecensement) as totalArticles'))
        ->where('recensements.annee', $annee)
        ->where('recensements.recense', true)
        ->first();

    //nombre total d'articles
    $totalArticles = $nbArticle->totalArticles;
    $feuille2->setCellValue("L". $colonne,$totalArticles);


    //fusion cellules
    //premiere ligne
    $feuille2->mergeCells('B1:D1');
    $feuille2->mergeCells('E1:H1');
    $feuille2->mergeCells('I1:K1');
    $feuille2->mergeCells('L1:M1');
    //nombre de ligne 
    $nbLigne = count($nomenclaturesTab);

    for ($i = 0; $i < $nbLigne+1; $i++) {
     $a = $i + 2;
        $feuille2->mergeCells('B' . $a . ':' . 'D' . $a);
        $feuille2->mergeCells('E' . $a . ':' . 'H' . $a);
        $feuille2->mergeCells('I' . $a . ':' . 'K' . $a);
        $feuille2->mergeCells('L' . $a . ':' . 'M' . $a);
    }

    // Appliquez des bordures aux cellules de A1 à la dernière cellule avec des données
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
        
    ];

    $feuille2->getStyle('A1:M5')->applyFromArray($styleArray);

    $locale = 'fr_FR';
    $fmt = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
    $totalExistantTexteMaj=strtoupper($fmt->format($totalExistant));
    $totalArticlesTexteMaj=strtoupper($fmt->format($totalArticles));
    $totalExistantTexte=$fmt->format($totalExistant);
    $totalArticlesTexte=$fmt->format($totalArticles);

    $feuille2->setCellValue("A". 7,"ARRETE le présent procès-verbal de recensement au nombre de : $totalArticlesTexteMaj ($totalArticles) articles et à la somme de :");
    $feuille2->setCellValue("A". 8,$totalExistantTexteMaj);
    $feuille2->setCellValue("A". 9,"($totalExistant)");
    $feuille2->mergeCells('A7:M7');
    $feuille2->mergeCells('A8:M8');
    $feuille2->mergeCells('A9:M9');
    $feuille2->mergeCells('A10:M10');

    //nombre d'article ayant des excedents
    $nbArticleAvecExcedent = DB::table('recensements')
    ->where('excedentParArticle', '!=', 0)
    ->where('recensements.annee', $annee)
    ->where('recensements.recense', true)
    ->count();
    $nbArticleAvecExcedentTexte=$fmt->format($nbArticleAvecExcedent);
    $totalExcedentTexte=$fmt->format($totalExcedent);

    //nombre d'article ayant des deficits
    $nbArticleAvecDeficit = DB::table('recensements')
    ->where('deficitParArticle', '!=', 0)
    ->where('recensements.annee', $annee)
    ->where('recensements.recense', true)
    ->count();
    $nbArticleAvecDeficitTexte=$fmt->format($nbArticleAvecDeficit);
    $totalDeficitTexte=$fmt->format($totalDeficit);


    // Insérez du texte dans une cellule (émulant une zone de texte)
    $text1 = "ARRETE Le présent procès-verbal à  (1) $nbArticleAvecExcedentTexte article comportant des excédents représentant une valeur de (1) $totalExcedentTexte;
     et (1) $nbArticleAvecDeficitTexte articles comportant des déficits représentant ";
    $feuille2->setCellValue('A12', $text1);
    $text2 = "une valeur de (1) $totalDeficitTexte; et $totalArticlesTexte ($totalArticles) articles des existants représentant une valeur de (1)  ";
    $feuille2->setCellValue('A13', $text2);
    $text3 = "$totalExistantTexte (Ar $totalExistant) ";
    $feuille2->setCellValue('A14', $text3);
    $text4 = "Le Comptable ............................................................ Antananarivo, le 31 décembre $annee";
    $feuille2->setCellValue('A16', $text4);
    $text5 = "Le (2) Dépositaire Comptable";
    $feuille2->setCellValue('B17', $text5);
    $text6 = "OBSERVATIONS ET PROPOSITIONS DU FONCTIONNAIRE RECENSEUR";
    $feuille2->setCellValue('A22', $text6);
    $text7 = "Commissions :";
    $feuille2->setCellValue('C23', $text7);
    $text8 = "Antananarivo, le le 31 décembre $annee";
    $feuille2->setCellValue('C28', $text8);
    $text9 = "OBSERVATIONS DU COMPTABLE";
    $feuille2->setCellValue('B30', $text9);
    $text10 = "Antananarivo, le  31 décembre $annee";
    $feuille2->setCellValue('C34', $text10);
    
    $feuille2->setCellValue('A38', "Avis du délégué du chef de service (s'il y a lieu)");
    $feuille2->setCellValue('F38', 'Décision ou conclusion du chef de service');
    $feuille2->setCellValue('A45', 'Antananarivo, le');
    $feuille2->setCellValue('F45', 'Antananarivo, le');
    $feuille2->setCellValue('C47', 'DECISION DU');
    $feuille2->setCellValue('H49', 'Antananarivo, le');
    //$feuille2->getStyle('A38:M45')->applyFromArray($styleArray);
    $feuille2->mergeCells('A38:E39');
    $feuille2->mergeCells('F38:I39');
    $feuille2->mergeCells('A45:E45');
    $feuille2->mergeCells('F45:I45');
    $feuille2->mergeCells('A40:E44');
    $feuille2->mergeCells('F40:I44');

    
    $feuille2->getStyle('A38:I45')->applyFromArray($styleArray);

    //$feuille2->getStyle('A38')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Appliquez des styles pour obtenir un aspect de zone de texte
    $style = [
        'font' => [
            'name' => 'Calibri (Corps)',
            'size' => 12,
            'color' => ['rgb' => '000000'], // Couleur du texte (noir)
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
        ],
    ];

    $feuille2->getStyle('A12')->applyFromArray($style);
    $feuille2->getStyle('A13')->applyFromArray($style);
    $feuille2->getStyle('A14')->applyFromArray($style);
    $feuille2->getStyle('A15')->applyFromArray($style);
    $feuille2->getStyle('B15')->applyFromArray($style);
    $feuille2->getStyle('A21')->applyFromArray($style);
    $feuille2->getStyle('C22')->applyFromArray($style);
    $feuille2->getStyle('C26')->applyFromArray($style);
    $feuille2->getStyle('B28')->applyFromArray($style);
    $feuille2->getStyle('C33')->applyFromArray($style);
   
    
    //$feuille2->setPaperSize("A4");

    // Générez le fichier Excel
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
    $temp_file = tempnam(sys_get_temp_dir(), 'export');
    $writer->save($temp_file);

    return response()->download($temp_file, 'recensement1.xlsx')->deleteFileAfterSend(true);
}
}










