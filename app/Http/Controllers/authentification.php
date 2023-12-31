<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Hash;
use Session;

class authentification extends Controller
{
    //
    public function creerUtilisateur(Request $req){//nouvel utilisateur
        $User= new User;
        $User->name=$req->name;
        $User->email=$req->email;
        $User->password=bcrypt($req->password);
        $User->save();
        return response(['message' => 'Utilisateur créé avec succès', 'user' => $User]);
    }
    public function seConnecter(Request $req){ //login
       if(Auth()->attempt($req->only('email', 'password'))){
        if(Auth::user()->isAdmin==false){ //si utilisateur menuUtilisateur
            return ['id'=>Auth::user()->id,'isAdmin'=>false];
        }
        else if(Auth::user()->isAdmin==true){//si admin menu admin
            return ['id'=>Auth::user()->id,'isAdmin'=>true];
        }
       };
       //return response(['message' => 'Échec de l\'authentification'], 401);
       //return "nonExistant";
    }
    public function seDeconnecter(){ //logout
        Auth::logout();
        return 'deconnecte';
    }
    public function modifierUtilisateur(Request $request, $id)
    {
        $user = User::find($id);
        
        // Validez les données d'entrée pour la mise à jour (ajoutez des règles de validation selon vos besoins)
        $userData = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'required',
        ]);
    
        // Mettez à jour les champs de l'utilisateur
        $user->update([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => bcrypt($userData['password']),
        ])
        ;
    }
    public function supprimerUtilisateur($id)
    {
        $user = User::find($id);    
        $user->delete();
    
        return "Utilisateur supprimé avec succès";
    }
    public function listerUtilisateur()
    {
        $users = User::where('isAdmin', false)->get();
        return $users;
    }
    public function voirUtilisateur($id)
    {
        $user = User::find($id);
    
        return $user;
    }
    
}
