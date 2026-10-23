<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Générer le rapport d'audit — Fleet Energy Analytics</title>
<style>
  :root{
    --navy:#1C2833; --navy-soft:#25313D; --card:#FFFFFF; --bg:#F4F6F7;
    --red:#C0392B; --red-soft:#FDEDEC; --grey:#5D6D7E; --line:#E1E4E6; --text:#212B33;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:-apple-system,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    -webkit-font-smoothing:antialiased;}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.06);
    padding:36px 40px;max-width:440px;width:100%;}
  h1{font-size:18px;margin:0 0 8px;letter-spacing:-.01em;}
  p.sub{color:var(--grey);font-size:12.5px;line-height:1.6;margin:0 0 24px;}
  .erreur{background:var(--red-soft);color:var(--red);border-radius:6px;padding:10px 14px;font-size:12.5px;
    margin-bottom:20px;}
  .field{margin-bottom:16px;}
  .field label{display:block;font-size:11px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;
    margin-bottom:6px;font-weight:600;}
  .field input[type=date]{width:100%;background:#fff;border:1px solid var(--line);border-radius:6px;
    padding:9px 10px;font-size:13.5px;color:var(--text);}
  .field input[type=date]:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(28,40,51,.08);}
  .btn{width:100%;background:var(--navy);color:#fff;border:none;border-radius:7px;padding:11px 18px;
    font-size:13.5px;font-weight:600;cursor:pointer;transition:background .15s ease;margin-top:6px;}
  .btn:hover{background:var(--navy-soft);}
  .lien-retour{display:block;text-align:center;margin-top:18px;font-size:12px;color:var(--grey);
    text-decoration:none;}
  .lien-retour:hover{color:var(--navy);}
</style>
</head>
<body>
  <div class="card">
    <h1>Générer le rapport d'audit ISO 50001</h1>
    <p class="sub">
      Choisis la période couverte par ce rapport. Le document généré reste figé sur cette période — pour
      auditer une autre période, reviens ici et génère un nouveau rapport.
    </p>

    @if($erreur)
      <div class="erreur">{{ $erreur }}</div>
    @endif

    <form method="GET" action="/audit-iso50001">
      <input type="hidden" name="confirmer" value="1">
      <div class="field">
        <label for="depuis">Du</label>
        <input type="date" id="depuis" name="depuis" value="{{ $depuisDefaut }}" required>
      </div>
      <div class="field">
        <label for="jusqu_a">Au</label>
        <input type="date" id="jusqu_a" name="jusqu_a" value="{{ $jusquADefaut }}" required>
      </div>
      <button type="submit" class="btn">Générer le rapport →</button>
    </form>

    <a href="/direction" class="lien-retour">← Retour à la Vue Direction</a>
  </div>
</body>
</html>
