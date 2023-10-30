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

    
}
