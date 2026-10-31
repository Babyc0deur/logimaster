<?php

namespace App\Http\Controllers;

use App\Models\District;
use Throwable;

/** Page d'accueil publique : présentation et accès à l'administration. */
class LandingController extends Controller
{
    public function __invoke(\Illuminate\Http\Request $request)
    {
        try {
            $districts = District::count();
        } catch (Throwable) {
            $districts = 0;   // base indisponible : la page de présentation s'affiche quand même
        }

        return view('landing', [
            'stats' => ['districts' => $districts ?: 113],
            'adminOpen' => true,
        ]);
    }
}
