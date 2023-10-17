<?php

namespace App\Imports;

use App\Models\Recensement;
use Maatwebsite\Excel\Concerns\ToModel;

class RecensementsImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Recensement([
            //'nomattribut'=>$row['champs excel],
            'designation'=>$row[0],
            'prixUnite'=>decimalval($row[2]),
            'existantApresEcriture'=>intval($row[3]),
        ]);
    }
}
