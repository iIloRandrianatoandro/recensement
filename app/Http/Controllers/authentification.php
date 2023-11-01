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
        $User->password=Hash::make($req->password);
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
       return response(['message' => 'Échec de l\'authentification'], 401);
    }
    public function seDeconnecter(){ //logout
        Auth::logout();
        return 'deconnecte';
    }
    public function modifierUtilisateur(Request $request, $id)
    {
        $user = User::find($id);
    
        if (!$user) {
            return "Utilisateur non trouvé";
        }
    
        // Validez les données d'entrée pour la mise à jour (ajoutez des règles de validation selon vos besoins)
        $userData = $request->validate([
            'nom' => 'required',
            'mail' => 'required|email|unique:users,email,' . $id,
            'mdp' => 'required',
        ]);
    
        // Mettez à jour les champs de l'utilisateur
        $user->name = $userData['nom'];
        $user->email = $userData['mail'];
        $userData['password'] = Hash::make($userData['mdp']);
    
        $user->save();
    
        return response(['message' => 'Utilisateur modifié avec succès', 'user' => $user]);
    }
    public function supprimerUtilisateur($id)
    {
        $user = User::find($id);
    
        if (!$user) {
            return "Utilisateur non trouvé";
        }
    
        $user->delete();
    
        return "Utilisateur supprimé avec succès";
    }
    public function listerUtilisateur()
    {
        $users = User::all();
        return $users;
    }
    public function voirUtilisateur($id)
    {
        $user = User::find($id);
    
        return $user;
    }
    
}
