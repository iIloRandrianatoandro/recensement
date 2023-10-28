<?php

namespace App\Exports;

use App\Models\Recensement;
use Maatwebsite\Excel\Concerns\FromCollection;
use DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;


class RecensementsExport implements FromCollection
{
    /*protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Formatez les données pour correspondre aux besoins de votre exportation
        $formattedData = [
            ['Description', 'Valeur'],
            ['Valeur Totale Excedent', $this->data[0]->total],
        ];

        return collect($formattedData);
    }*/
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
{
    $formattedData = [
        ['Description', 'Valeur'],
        ['Valeur Totale Excedent', $this->data['totalExcedent']],
        ['Valeur Totale Deficit', $this->data['totalDeficit']],
        ['Valeur Totale Existant', $this->data['valeurTotaleExistant']->total],
        ['Nombre Total Article', $this->data['nbArticle']],
        // Ajoutez d'autres données ici
    ];
    // Créez un tableau pour les données de nbMaterielsParNomenclature
    $nomenclatureData = [];
    foreach ($this->data['nbMaterielsParNomenclature'] as $a) {
        $nomenclatureData[] = [$a->nomenclature, $a->total];
    }
    $listeRecensementsTab=[];
    foreach ($this->data['listeRecensementsTab'] as $a) {
        $listeRecensementsTab[] = [$a->designation, $a->especeUnite];
    }

    // Fusionnez les deux tableaux
    $formattedData = array_merge($formattedData, $nomenclatureData,$listeRecensementsTab);

    return collect($formattedData);
}
   /* use Exportable;
    /**
    * @return \Illuminate\Support\Collection
    */
    /*public function collection()
    {
        return Recensement::all();
    }*/
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

        return ['valeurTotaleExcedent'=>$valeurTotaleExcedent[0]->{'sum(excedentParArticle * prixUnite)'},'valeurTotaleDeficit'=>$valeurTotaleDeficit[0]->{'sum(deficitParArticle * prixUnite)'},'valeurTotaleExistant'=>$valeurTotaleExistant[0]->{'sum((existantApresEcriture+excedentParArticle-deficitParArticle) * prixUnite)'},'nbMaterielsParNomenclature'=>$nbMaterielsParNomenclature,'nbArticle'=>$nbArticle,'listeRecensementsTab'=>$listeRecensementsTab];
        return $listeRecensementsTab;
    }*/
    /*public function query(){
        return DB::table('recensements')->select('idRecensement', 'existantApresEcriture')->where('annee','=',2022)->orderBy('idRecensement', 'asc');
    }*/
    

    
}
