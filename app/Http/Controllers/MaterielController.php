<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\materiel;
use DB;

class MaterielController extends Controller
{
    public function listeMateriel(){  
        $listeMateriel=DB::select("select * from materiels;");
        return $listeMateriel;
    }
    public function rechercherMateriel($designation){
        $listeMaterielCorrespondant=DB::select("select * from materiels where designation like '%$designation%'; ");
        return $listeMaterielCorrespondant;
    }
    public function voirMateriel($id)
    { 
        $materiel=DB::select("select * from materiels where idMateriel='$id'");
        return $materiel;
    }
    public function modifierMateriel(Request $req , $idMateriel)
    { 
        $materiel=materiel::find($idMateriel);
        $materiel->designation =$req->designation ;
        $materiel->nomenclature =$req->nomenclature ;
        $materiel->especeUnite =$req->especeUnite ;
        $materiel->save();
        return $materiel;
    } 
}
