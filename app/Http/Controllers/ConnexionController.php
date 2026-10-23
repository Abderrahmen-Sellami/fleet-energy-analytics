<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Champs de formulaire identifiant/mot_de_passe et mécanique (limitation de tentatives, whitelist
 * de retour) inchangés pour ne rien casser côté écran.
 */
class ConnexionController extends Controller
{
    private const MAX_TENTATIVES = 5;
    private const FENETRE_SECONDES = 60;

    public function afficher(Request $request)
    {
        if (Auth::check()) {
            return redirect($this->retourValide($request->query('retour')));
        }

        return view('connexion', ['retour' => $this->retourValide($request->query('retour'), null)]);
    }

    public function connecter(Request $request)
    {
        $retour = $this->retourValide($request->input('retour'), null);
        $identifiantSaisi = (string) $request->input('identifiant', '');

        $cle = 'connexion:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES)) {
            $secondes = RateLimiter::availableIn($cle);

            return $this->refuser($identifiantSaisi, $retour,
                "Trop de tentatives. Réessaie dans {$secondes} s.");
        }

        // 'actif' => true dans les credentials : Laravel l'ajoute comme condition WHERE (seul 'password'
        // est traité spécialement par EloquentUserProvider) — un compte désactivé échoue donc au login
        // exactement comme un mauvais mot de passe, même message générique, jamais de fuite d'info sur
        // la raison précise de l'échec (identifiant inconnu / mauvais mot de passe / compte désactivé).
        $ok = Auth::attempt([
            'identifiant' => $identifiantSaisi,
            'password' => (string) $request->input('mot_de_passe', ''),
            'actif' => true,
        ]);

        if (!$ok) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            return $this->refuser($identifiantSaisi, $retour, 'Identifiant ou mot de passe incorrect.');
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        return redirect($this->retourValide($retour));
    }

    public function deconnecter(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }

    private function refuser(string $identifiant, ?string $retour, string $message)
    {
        return redirect('/connexion'.($retour ? '?retour='.urlencode($retour) : ''))
            ->withInput(['identifiant' => $identifiant])
            ->withErrors(['connexion' => $message]);
    }

    /**
     * Whitelist stricte de la page de retour (jamais une redirection vers un chemin arbitraire ou
     * un domaine externe) — /utilisateurs ajoutée : 4e page protégée de l'app.
     */
    private function retourValide(mixed $retour, ?string $defaut = '/dashboard'): ?string
    {
        if (is_string($retour) && preg_match('#^/(dashboard|direction|audit-iso50001|utilisateurs)(\?[^\s\\\\]*)?$#', $retour)) {
            return $retour;
        }

        return $defaut;
    }
}
