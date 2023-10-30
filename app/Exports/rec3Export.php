<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class rec3Export implements FromCollection
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
{
    $formattedData = [
        ["Désignation des matières denrées et objets", "Espèce des unités","Prix de l'unité","Existants d'après les écriture","Constatées par recensement","Excédent par article","Déficit par article","Valeurs des excédents par article","Valeurs des déficits par article","Valeurs des existants","Observation"]
        // Ajoutez d'autres données ici
    ];
    $listeRecensementsTab=[];
    foreach ($this->data['listeRecensementsTab'][0] as $a) {
        $listeRecensementsTab[] = [$a->designation, $a->especeUnite, $a->prixUnite,$a->existantApresEcriture,$a->constateesParRecensement,$a->excedentParArticle,$a->deficitParArticle,$a->valeurExcedent,$a->valeurDeficit,$a->valeurExistant,$a->observation];
    }

    // Fusionnez les deux tableaux
    //$formattedData = array_merge($formattedData,$listeRecensementsTab);

    return collect($formattedData);
}
}
