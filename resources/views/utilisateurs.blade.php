<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Gestion des utilisateurs — Fleet Energy Analytics</title>
<style>
  :root{
    --navy:#1C2833; --navy-soft:#25313D; --card:#FFFFFF; --bg:#F4F6F7; --amber:#B9770E;
    --red:#C0392B; --red-soft:#FDEDEC; --green:#1E8449; --green-soft:#EAFAF1; --grey:#5D6D7E;
    --line:#E1E4E6; --text:#212B33;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:-apple-system,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);
    min-height:100vh;-webkit-font-smoothing:antialiased;}
  .topbar{background:linear-gradient(180deg,#1C2833 0%,#182028 100%);color:#fff;padding:16px 24px;
    display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .topbar b{font-size:16px;letter-spacing:.02em;}
  .topbar span{display:block;font-size:10px;color:#8FA0AD;letter-spacing:.08em;margin-top:2px;}
  .topbar-actions{display:flex;align-items:center;gap:14px;font-size:12.5px;}
  .topbar-actions a{color:#D5DBDE;text-decoration:none;}
  .topbar-actions a:hover{color:#fff;}
  .form-deco{display:contents;}
  .form-deco button{background:none;border:none;color:#D5DBDE;font-family:inherit;font-size:12.5px;
    cursor:pointer;padding:0;}
  .form-deco button:hover{color:#fff;}
  .main{max-width:960px;margin:0 auto;padding:28px 20px 60px;}
  h1{font-size:19px;margin:0 0 4px;letter-spacing:-.01em;}
  p.sub{color:var(--grey);font-size:12.5px;line-height:1.6;margin:0 0 22px;}
  .card{background:var(--card);border:1px solid var(--line);border-radius:10px;
    box-shadow:0 2px 12px rgba(0,0,0,.04);padding:22px 24px;margin-bottom:22px;}
  .card h2{font-size:14px;margin:0 0 14px;}
  .erreur{background:var(--red-soft);color:var(--red);border-radius:6px;padding:9px 13px;font-size:12.5px;
    margin-bottom:14px;display:none;}
  .succes{background:var(--green-soft);color:var(--green);border-radius:6px;padding:9px 13px;font-size:12.5px;
    margin-bottom:14px;display:none;}
  .form-creer{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
  .champ{flex:1;min-width:160px;}
  .champ label{display:block;font-size:10.5px;color:var(--grey);text-transform:uppercase;
    letter-spacing:.05em;margin-bottom:6px;font-weight:600;}
  .champ input, .champ select{width:100%;background:#fff;border:1px solid var(--line);border-radius:6px;
    padding:9px 10px;font-size:13.5px;color:var(--text);font-family:inherit;}
  .champ input:focus, .champ select:focus{outline:none;border-color:var(--navy);
    box-shadow:0 0 0 3px rgba(28,40,51,.08);}
  .btn{background:var(--navy);color:#fff;border:none;border-radius:7px;padding:10px 20px;
    font-size:13.5px;font-weight:600;cursor:pointer;transition:background .15s ease;white-space:nowrap;}
  .btn:hover{background:var(--navy-soft);}
  .table-scroll{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;font-size:13px;min-width:520px;}
  th{text-align:left;font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;
    padding:8px 10px;border-bottom:2px solid var(--line);white-space:nowrap;}
  td{padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600;
    white-space:nowrap;}
  .badge.actif{background:var(--green-soft);color:var(--green);}
  .badge.inactif{background:#EAECEE;color:var(--grey);}
  select.role-select{border:1px solid var(--line);border-radius:5px;padding:5px 7px;font-size:12.5px;
    font-family:inherit;background:#fff;}
  .btn-toggle{background:none;border:1px solid var(--line);border-radius:6px;padding:5px 11px;
    font-size:12px;cursor:pointer;font-family:inherit;color:var(--text);}
  .btn-toggle:hover{background:var(--bg);}
  .lien-retour{display:inline-block;margin-top:4px;font-size:12px;color:var(--grey);text-decoration:none;}
  .lien-retour:hover{color:var(--navy);}
  @media (max-width:600px){
    .form-creer{flex-direction:column;align-items:stretch;}
    .champ{min-width:0;}
    .btn{width:100%;}
  }
</style>
</head>
<body>
  <div class="topbar">
    <div><b>FLEET ENERGY</b><span>GESTION DES UTILISATEURS</span></div>
    <div class="topbar-actions">
      <a href="/dashboard">← Retour au dashboard</a>
      <form method="POST" action="/deconnexion" class="form-deco">@csrf<button type="submit">🔒 Se déconnecter</button></form>
    </div>
  </div>

  <div class="main">
    <h1>Gestion des utilisateurs</h1>
    <p class="sub">Réservé aux Admins. Un Consultant a un accès en lecture seule à toute la plateforme, sans aucun bouton d'action visible.</p>

    <div class="card">
      <h2>Créer un utilisateur</h2>
      <div class="erreur" id="erreurCreation"></div>
      <div class="succes" id="succesCreation"></div>
      <form class="form-creer" id="formCreerUtilisateur">
        <div class="champ">
          <label for="nouvelIdentifiant">Identifiant</label>
          <input type="text" id="nouvelIdentifiant" name="identifiant" autocomplete="off" required>
        </div>
        <div class="champ">
          <label for="nouveauMotDePasse">Mot de passe</label>
          <input type="password" id="nouveauMotDePasse" name="mot_de_passe" autocomplete="new-password" minlength="8" required>
        </div>
        <div class="champ" style="flex:0 0 160px;">
          <label for="nouveauRole">Rôle</label>
          <select id="nouveauRole" name="role">
            <option value="consultant">Consultant</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <button type="submit" class="btn">Créer</button>
      </form>
    </div>

    <div class="card">
      <h2>Comptes existants</h2>
      <div class="erreur" id="erreurListe"></div>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Identifiant</th><th>Rôle</th><th>Statut</th><th>Créé le</th><th>Action</th></tr></thead>
          <tbody id="corpsTableUtilisateurs"></tbody>
        </table>
      </div>
    </div>
  </div>

<script src="/js/session-garde.js"></script>
<script>
const idUtilisateurActuel = {{ auth()->id() }};

function formatDate(iso){
  return new Date(iso).toLocaleDateString('fr-FR', { year:'numeric', month:'2-digit', day:'2-digit' });
}

function afficherMessage(el, texte){
  el.textContent = texte;
  el.style.display = texte ? 'block' : 'none';
}

async function chargerUtilisateurs(){
  const erreurEl = document.getElementById('erreurListe');
  afficherMessage(erreurEl, '');
  try {
    const res = await fetch('/api/utilisateurs');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const utilisateurs = await res.json();
    const corps = document.getElementById('corpsTableUtilisateurs');
    corps.innerHTML = utilisateurs.map(u => `
      <tr data-id="${u.id}">
        <td>${u.identifiant}${u.id === idUtilisateurActuel ? ' <span style="color:var(--grey);">(toi)</span>' : ''}</td>
        <td>
          <select class="role-select" data-id="${u.id}" data-champ="role">
            <option value="consultant" ${u.role === 'consultant' ? 'selected' : ''}>Consultant</option>
            <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
          </select>
        </td>
        <td><span class="badge ${u.actif ? 'actif' : 'inactif'}">${u.actif ? 'Actif' : 'Inactif'}</span></td>
        <td>${formatDate(u.created_at)}</td>
        <td><button type="button" class="btn-toggle" data-id="${u.id}" data-actif="${u.actif ? '1' : '0'}">${u.actif ? 'Désactiver' : 'Activer'}</button></td>
      </tr>`).join('');

    corps.querySelectorAll('.role-select').forEach(sel => {
      sel.addEventListener('change', () => modifierUtilisateur(parseInt(sel.dataset.id), { role: sel.value }));
    });
    corps.querySelectorAll('.btn-toggle').forEach(btn => {
      btn.addEventListener('click', () => modifierUtilisateur(parseInt(btn.dataset.id), { actif: btn.dataset.actif !== '1' }));
    });
  } catch (e) {
    afficherMessage(erreurEl, 'Impossible de charger la liste des utilisateurs.');
  }
}

async function modifierUtilisateur(id, donnees){
  const erreurEl = document.getElementById('erreurListe');
  afficherMessage(erreurEl, '');
  try {
    const res = await fetch(`/api/utilisateurs/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(donnees),
    });
    const data = await res.json();
    if (!res.ok) {
      afficherMessage(erreurEl, data.message || 'Modification refusée.');
    }
    await chargerUtilisateurs();
  } catch (e) {
    afficherMessage(erreurEl, 'Erreur réseau — modification non appliquée.');
    await chargerUtilisateurs();
  }
}

document.getElementById('formCreerUtilisateur').addEventListener('submit', async (e) => {
  e.preventDefault();
  const erreurEl = document.getElementById('erreurCreation');
  const succesEl = document.getElementById('succesCreation');
  afficherMessage(erreurEl, '');
  afficherMessage(succesEl, '');

  const form = e.target;
  const donnees = {
    identifiant: form.identifiant.value,
    mot_de_passe: form.mot_de_passe.value,
    role: form.role.value,
  };

  try {
    const res = await fetch('/api/utilisateurs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(donnees),
    });
    const data = await res.json();
    if (!res.ok) {
      const premiereErreur = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Création refusée.');
      afficherMessage(erreurEl, premiereErreur);
      return;
    }
    form.reset();
    afficherMessage(succesEl, `Utilisateur "${data.identifiant}" créé.`);
    await chargerUtilisateurs();
  } catch (e) {
    afficherMessage(erreurEl, 'Erreur réseau — utilisateur non créé.');
  }
});

chargerUtilisateurs();
</script>
</body>
</html>
