<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;


use App\Imports\RecensementsImport;
use App\Http\Controllers\Controller;
use App\Models\recensement;

class RecensementController extends Controller
{
    public function import(Request $req) 
    {
        //Excel::import(new RecensementsImport, $req->file);
        $array = Excel::toArray(new RecensementsImport, $req->file);
        $recensement=array_slice($array[0], 4);
        foreach($recensement as $a){
            $rec=new recensement;
            
            /*'designation'=>$row[0],
            'prixUnite'=>doubleval($row[2]),
            'existantApresEcriture'=>intval($row[3]),*/
            $rec->designation=$a[0];
            $rec->prixUnite=doubleval($a[2]);
            $rec->existantApresEcriture=intval($a[3]);
            $rec->save();
        }
        return $recensement;
    }
}
