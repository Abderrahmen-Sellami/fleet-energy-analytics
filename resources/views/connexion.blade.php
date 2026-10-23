<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — Fleet Energy Analytics</title>
<style>
  :root{
    --navy:#1C2833; --navy-soft:#25313D; --card:#FFFFFF; --bg:#F4F6F7; --amber:#B9770E;
    --red:#C0392B; --red-soft:#FDEDEC; --grey:#5D6D7E; --line:#E1E4E6; --text:#212B33;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:-apple-system,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    -webkit-font-smoothing:antialiased;}
  .wrap{width:100%;max-width:400px;}
  .marque{background:linear-gradient(180deg,#1C2833 0%,#182028 100%);color:#fff;border-radius:12px 12px 0 0;
    padding:22px 28px;}
  .marque b{font-size:19px;letter-spacing:.02em;}
  .marque span{display:block;font-size:10.5px;color:#8FA0AD;letter-spacing:.08em;margin-top:3px;}
  .card{background:var(--card);border:1px solid var(--line);border-top:3px solid var(--amber);
    border-radius:0 0 12px 12px;box-shadow:0 4px 24px rgba(0,0,0,.06);padding:28px;}
  h1{font-size:17px;margin:0 0 6px;letter-spacing:-.01em;}
  p.sub{color:var(--grey);font-size:12.5px;line-height:1.6;margin:0 0 22px;}
  .erreur{background:var(--red-soft);color:var(--red);border-radius:6px;padding:10px 14px;font-size:12.5px;
    margin-bottom:18px;}
  .field{margin-bottom:16px;}
  .field label{display:block;font-size:11px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;
    margin-bottom:6px;font-weight:600;}
  .field input{width:100%;background:#fff;border:1px solid var(--line);border-radius:6px;
    padding:10px 11px;font-size:14px;color:var(--text);font-family:inherit;}
  .field input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(28,40,51,.08);}
  .btn{width:100%;background:var(--navy);color:#fff;border:none;border-radius:7px;padding:12px 18px;
    font-size:14px;font-weight:600;cursor:pointer;transition:background .15s ease;margin-top:4px;}
  .btn:hover{background:var(--navy-soft);}
  @media (max-width:768px){
    body{padding:16px;align-items:flex-start;padding-top:12vh;}
    .marque{padding:18px 20px;} .card{padding:22px 20px;}
    /* 16px : empêche le zoom automatique d'iOS Safari au focus d'un champ. */
    .field input{font-size:16px;}
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="marque"><b>FLEET ENERGY</b><span>SUIVI ISO 50001 — MULTI-SOURCES</span></div>
    <div class="card">
      <h1>Connexion</h1>
      <p class="sub">Accès réservé. Saisis ton identifiant et ton mot de passe.</p>

      @if ($errors->has('connexion'))
        <div class="erreur" role="alert">{{ $errors->first('connexion') }}</div>
      @endif

      <form method="POST" action="/connexion">
        @csrf
        @if ($retour)
          <input type="hidden" name="retour" value="{{ $retour }}">
        @endif
        <div class="field">
          <label for="identifiant">Identifiant</label>
          <input type="text" id="identifiant" name="identifiant" value="{{ old('identifiant') }}"
                 autocomplete="username" autocapitalize="none" autocorrect="off" required autofocus>
        </div>
        <div class="field">
          <label for="mot_de_passe">Mot de passe</label>
          <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn">Se connecter</button>
      </form>
    </div>
  </div>
</body>
</html>
