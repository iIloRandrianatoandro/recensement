<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;
//use Maatwebsite\Excel\Excel;

use App\Imports\RecensementsImport;
use App\Exports\RecensementsExport;
use App\Exports\rec3Export;
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
  /*  public function export()
    {
    $valeurTotaleExcedent = DB::table('recensements')->select(DB::raw('sum(excedentParArticle * prixUnite) as total'))->first(); //tokony anaty tableau aloha
    $valeurTotaleDeficit = DB::table('recensements')->select(DB::raw('sum(deficitParArticle * prixUnite) as total'))->first();
    $valeurTotaleExistant = DB::table('recensements')->select(DB::raw('sum((existantApresEcriture + excedentParArticle - deficitParArticle) * prixUnite) as total'))->first();
    $nbMaterielsParNomenclature = DB::table('recensements')->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')->select('materiels.nomenclature', DB::raw('count(recensements.idRecensement) as total'))->groupBy('materiels.nomenclature')->get();
    $nbArticle = DB::table('recensements')->count();
    $listeRecensementsTab = DB::table('recensements')
    ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
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
    
}*/
public function export()
{
    $listeRecensementsTab = DB::table('recensements')
    ->join('materiels', 'recensements.materiel_idMateriel', '=', 'materiels.idMateriel')
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
    // Créez un objet Excel
    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    // Créez une feuille de calcul (Feuille 1)
    $feuille1 = $excel->getActiveSheet();  // Obtenez la feuille active
    $feuille1->setTitle('rec3'); // Définissez le titre de la feuille

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
    $feuille1->setCellValue("J". 2,"des excédents");
    $feuille1->setCellValue("L". 2,"des existants");
    
    $feuille1->setCellValue("H". 3,"par article");
    $feuille1->setCellValue("I". 3,"par numéro de la nomenclature sommaire");
    $feuille1->setCellValue("J". 3,"par article");
    $feuille1->setCellValue("K". 3,"par numéro de la nomenclature sommaire");

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
    
    /*$row = 2; // Ligne de départ
    $col = 'A'; // Colonne de départ
    foreach ($titles2 as $title) {
        $feuille1->setCellValue($col . $row, $title);
        $col++;
    }*/
    // Fusionnez les cellules pour le titre
   // $feuille1->mergeCells('A1:A2'); // Fusionnez de A1 à K1

    // Utilisez les données directement depuis la requête
   /* $row = 3; // Commencez à la ligne suivante
    foreach ($listeRecensementsTab as $data) {
        $col = 'A'; // Colonne de départ
        foreach ($data as $value) {
            $feuille1->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }*/
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
    ];

    $feuille1->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray($styleArray);

    $feuille2=$excel->createSheet();
    $feuille2->setTitle('recap');

    $feuille2->setCellValue("A". 1,"NOMENCLATURE");
    $feuille2->setCellValue("B". 1,"EXCEDENTS");
    $feuille2->setCellValue("E". 1,"DEFICITS");
    $feuille2->setCellValue("I". 1,"EXISTANTS");
    $feuille2->setCellValue("L". 1,"ARTICLES");

    $feuille2->setCellValue("A". 2,"03");
    $feuille2->setCellValue("A". 3,"05");
    $feuille2->setCellValue("A". 4,"10");
    $feuille2->setCellValue("A". 5,"TOTAL");
    
    $feuille2->mergeCells('B1:D1');
    $feuille2->mergeCells('E1:H1');
    $feuille2->mergeCells('I1:K1');
    $feuille2->mergeCells('L1:M1');
    
    $feuille2->mergeCells('B2:D2');
    $feuille2->mergeCells('E2:H2');
    $feuille2->mergeCells('I2:K2');
    $feuille2->mergeCells('L2:M2');
    
    $feuille2->mergeCells('B3:D3');
    $feuille2->mergeCells('E3:H3');
    $feuille2->mergeCells('I3:K3');
    $feuille2->mergeCells('L3:M3');
    
    $feuille2->mergeCells('B4:D4');
    $feuille2->mergeCells('E4:H4');
    $feuille2->mergeCells('I4:K4');
    $feuille2->mergeCells('L4:M4');
    
    $feuille2->mergeCells('B5:D5');
    $feuille2->mergeCells('E5:H5');
    $feuille2->mergeCells('I5:K5');
    $feuille2->mergeCells('L5:M5');
    
    $feuille2->getStyle('A1:M1')->applyFromArray($styleArray);
    $feuille2->getStyle('A2:M2')->applyFromArray($styleArray);
    $feuille2->getStyle('A3:M3')->applyFromArray($styleArray);
    $feuille2->getStyle('A4:M4')->applyFromArray($styleArray);
    $feuille2->getStyle('A5:M5')->applyFromArray($styleArray);

    $feuille2->setCellValue("A". 7,"ARRETE le présent procès-verbal de recensement au nombre de : Sept cent soixante-treize (773) articles et à la somme de :");
    $feuille2->setCellValue("A". 8,"QUATRE MILLIARDS NEUF CENT SOIXANTE-DOUZE MILLIONS DEUX CENT TRENTE-SEPT MILLE");
    $feuille2->setCellValue("A". 9,"SEPT CENT TREIZE ARIARY SOIXANTE");
    $feuille2->setCellValue("A". 9,"(4 792 237 713,60 Ar)");
    $feuille2->mergeCells('A7:M7');
    $feuille2->mergeCells('A8:M8');
    $feuille2->mergeCells('A9:M9');
    $feuille2->mergeCells('A10:M10');

    // Insérez du texte dans une cellule (émulant une zone de texte)
    $text1 = "ARRETE Le présent procès-verbal à  (1) Zéro article comportant des excédents représentant une valeur de (1) NEANT; et (1) Zéro articles comportant des déficits représentant ";
    $feuille2->setCellValue('A12', $text1);
    $text2 = "une valeur de (1) NEANT; et Spet cent soixante-treize (773) articles des existants représentant une valeur de (1) Quatre milliards neuf cent soixante-douze millions ";
    $feuille2->setCellValue('A13', $text2);
    $text3 = "deux cent trente-sept mille sept cent treize ariary soixante (Ar 4 792 237 713,60) ";
    $feuille2->setCellValue('A14', $text3);
    $text4 = "Le Comptable ............................................................ Antananarivo, le 31 décembre 2020";
    $feuille2->setCellValue('A16', $text4);
    $text5 = "Le (2) Dépositaire Comptable";
    $feuille2->setCellValue('B17', $text5);
    $text6 = "OBSERVATIONS ET PROPOSITIONS DU FONCTIONNAIRE RECENSEUR";
    $feuille2->setCellValue('A22', $text6);
    $text7 = "Commissions :";
    $feuille2->setCellValue('C23', $text7);
    $text8 = "Antananarivo, le le 31 décembre 2020";
    $feuille2->setCellValue('C28', $text8);
    $text9 = "OBSERVATIONS DU COMPTABLE";
    $feuille2->setCellValue('B30', $text9);
    $text10 = "Antananarivo, le  31 décembre 2020";
    $feuille2->setCellValue('C34', $text10);
    
    $feuille2->setCellValue('A38', 'Avis du délégué du chef de service');
    $feuille2->setCellValue('A39', "(s'il y a lieu)");
    $feuille2->setCellValue('F38', 'Décision ou conclusion du chef de service');
    $feuille2->setCellValue('A45', 'Antananarivo, le');
    $feuille2->setCellValue('F45', 'Antananarivo, le');
    $feuille2->setCellValue('C47', 'DECISION DU');
    $feuille2->setCellValue('H49', 'Antananarivo, le');
    //$feuille2->getStyle('A38:M45')->applyFromArray($styleArray);
    $feuille2->mergeCells('A38:E38');
    $feuille2->mergeCells('A39:E39');
    $feuille2->mergeCells('F38:M39');
    $feuille2->mergeCells('A45:E45');
    $feuille2->mergeCells('F45:M45');

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
        /*'borders' => [
            'outline' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],*/
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



    // Ajustez la largeur de la colonne pour s'adapter au contenu
    $feuille1->getColumnDimension('A')->setAutoSize(true);
    

    // Générez le fichier Excel
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
    $temp_file = tempnam(sys_get_temp_dir(), 'export');
    $writer->save($temp_file);

    return response()->download($temp_file, 'recensement1.xlsx')->deleteFileAfterSend(true);
}
}










