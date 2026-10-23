<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet Energy Analytics · Vue Direction — Suivi énergétique flotte</title>
<script src="/js/session-garde.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
  :root{
    --navy:#1C2833; --navy-soft:#25313D; --card:#FFFFFF; --bg:#F4F6F7;
    --amber:#B9770E; --amber-soft:#FEF5E7; --green:#1E8449; --green-soft:#EAF7EE;
    --red:#C0392B; --red-soft:#FDEDEC; --grey:#5D6D7E; --line:#E1E4E6; --text:#212B33;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:-apple-system,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);
    -webkit-font-smoothing:antialiased;}
  ::selection{background:var(--amber-soft);color:var(--amber);}
  ::-webkit-scrollbar{width:9px;height:9px;}
  ::-webkit-scrollbar-track{background:transparent;}
  ::-webkit-scrollbar-thumb{background:#C7CDD1;border-radius:20px;}
  ::-webkit-scrollbar-thumb:hover{background:#AEB6BB;}

  .app{display:flex;min-height:100vh;}

  /* ---------- Sidebar ---------- */
  .sidebar{width:220px;flex-shrink:0;background:linear-gradient(180deg,#1C2833 0%,#182028 100%);
    color:#fff;padding:22px 14px;display:flex;flex-direction:column;gap:4px;
    box-shadow:2px 0 12px rgba(0,0,0,.08);}
  .brand{padding:2px 8px 22px;}
  .brand b{font-size:19px;letter-spacing:.02em;}
  .brand span{display:block;font-size:10.5px;color:#8FA0AD;letter-spacing:.08em;margin-top:3px;}
  .nav-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border:none;border-left:3px solid transparent;
    background:transparent;color:#AEB9C2;font-size:13.5px;font-weight:600;border-radius:6px;cursor:pointer;
    text-align:left;width:100%;transition:background .15s ease,color .15s ease,border-color .15s ease;}
  .nav-btn:hover{background:rgba(255,255,255,.06);color:#fff;}
  .nav-btn.active{background:var(--amber);color:#1C1300;border-left-color:#fff;}
  .nav-sep{height:1px;background:rgba(255,255,255,.08);margin:14px 4px;}
  .sidebar-footer{margin-top:auto;padding:12px 10px 2px;font-size:10.5px;color:#6E7C87;letter-spacing:.02em;}

  /* ---------- Barre supérieure ---------- */
  /* flex-wrap ici (pas seulement dans le @media mobile plus bas) : à une largeur intermédiaire
     .topbar-info + .topbar-actions (3 boutons) ne tiennent plus sur une seule ligne mais aucune
     règle mobile (@media max-width:768px) ne s'applique à cette largeur.topbar-actions débordait
     silencieusement du viewport. */
  .topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;row-gap:10px;
    padding:14px 32px;background:#fff;border-bottom:1px solid var(--line);
    box-shadow:0 1px 3px rgba(0,0,0,.03);}
  .topbar-info{display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--grey);font-weight:600;}
  .topbar-dot{width:8px;height:8px;border-radius:50%;background:var(--green);
    box-shadow:0 0 0 3px var(--green-soft);}
  .topbar-sep{color:var(--line);}
  .topbar-meteo{display:flex;align-items:center;gap:10px;}
  .topbar-meteo .site{display:flex;align-items:center;gap:3px;}
  .topbar-meteo .site img{width:20px;height:20px;display:block;}
  .topbar-meteo .site b{font-weight:700;color:#2c3e50;}
  .topbar-meteo .temp-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:20px;
    font-size:10px;font-weight:700;margin-left:2px;white-space:nowrap;}
  .topbar-meteo .temp-badge.canicule{background:var(--red-soft);color:var(--red);}
  .topbar-meteo .temp-badge.chaleur{background:var(--amber-soft);color:var(--amber);}
  .topbar-meteo .temp-badge.froid{background:#EAF2F8;color:#1F618D;}
  .topbar-datetime{white-space:nowrap;text-transform:capitalize;}
  .btn-print-top{display:flex;align-items:center;gap:7px;background:var(--navy);color:#fff;border:none;
    border-radius:7px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;
    transition:background .15s ease,transform .1s ease;}
  .btn-print-top:hover{background:var(--navy-soft);}
  .btn-print-top:active{transform:scale(.97);}
  .topbar-actions{display:flex;align-items:center;gap:10px;}
  .btn-tech-link{display:flex;align-items:center;gap:6px;background:#fff;color:var(--navy);
    border:1px solid var(--line);border-radius:7px;padding:9px 16px;font-size:13px;font-weight:600;
    text-decoration:none;transition:background .15s ease,border-color .15s ease;}
  .btn-tech-link:hover{background:#F0F2F3;border-color:#C7CDD1;}
  /* Bouton "Se déconnecter" : <form> POST (CSRF) transparent pour la mise en page
     (display:contents) — le <button> reste un enfant flex direct de .topbar-actions. */
  .form-deconnexion{display:contents;}
  .form-deconnexion button{font-family:inherit;cursor:pointer;}

  .main{flex:1;min-width:0;}
  .main-inner{padding:26px 32px 60px;}
  h1{font-size:22px;margin:0 0 3px;letter-spacing:-.01em;}
  .sub{color:var(--grey);font-size:12.5px;margin:0 0 20px;}

  /* ---------- Filtres ---------- */
  .filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center;}
  .filters select, .filters input{
    background:#fff;border:1px solid var(--line);border-radius:6px;padding:8px 10px;font-size:13px;color:var(--text);
    transition:border-color .15s ease,box-shadow .15s ease;
  }
  .filters select:focus, .filters input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(28,40,51,.08);}
  .filters label{font-size:11px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;margin-right:4px;}

  .btn{background:var(--navy);color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;
    cursor:pointer;transition:background .15s ease,transform .1s ease;}
  .btn:hover{background:var(--navy-soft);}
  .btn:active{transform:scale(.97);}
  .btn.outline{background:#fff;color:var(--navy);border:1px solid var(--line);}
  .btn.outline:hover{background:#F0F2F3;border-color:#C7CDD1;}
  .btn.outline.active{background:var(--navy);color:#fff;border-color:var(--navy);}

  /* ---------- KPI ---------- */
  .kpi-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;}
  .kpi-strip.kpi-strip-6{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:9px;padding:14px 16px;position:relative;
    box-shadow:0 1px 2px rgba(0,0,0,.03);transition:box-shadow .15s ease,transform .12s ease;}
  .kpi:hover{box-shadow:0 4px 14px rgba(0,0,0,.07);transform:translateY(-1px);}
  .kpi::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;border-radius:9px 9px 0 0;background:var(--amber);opacity:.55;}
  .kpi .lbl{font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
  .kpi .val{font-size:23px;font-weight:700;letter-spacing:-.01em;}
  .kpi .val small{font-size:12px;color:var(--grey);font-weight:500;margin-left:3px;}

  .grid-2{display:grid;grid-template-columns:1.3fr 1fr;gap:16px;}
  .grid-2-eq{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  /* minmax(0,1fr), pas juste 1fr : même correctif que .grid-3-eq sur dashboard.blade.php — sans
     ça, les pistes refusent de rétrécir sous la largeur intrinsèque de leur contenu et le
     conteneur déborde. */
  .grid-4-eq{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;}
  .grid-3-eq{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}

  .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:18px 20px;margin-bottom:16px;
    box-shadow:0 1px 2px rgba(0,0,0,.03);transition:box-shadow .18s ease;}
  .card:hover{box-shadow:0 3px 16px rgba(0,0,0,.06);}
  .card h3{font-size:13.5px;margin:0 0 14px;display:flex;align-items:center;justify-content:space-between;
    letter-spacing:.01em;}

  /* ---------- Tableaux ---------- */
  table{width:100%;border-collapse:collapse;font-size:12.8px;}
  th{text-align:left;padding:9px 10px;color:var(--grey);font-weight:600;border-bottom:2px solid var(--line);
    cursor:pointer;user-select:none;transition:color .15s ease;}
  th:hover{color:var(--navy);}
  td{padding:9px 10px;border-bottom:1px solid var(--line);}
  tbody tr{cursor:pointer;transition:background .12s ease;}
  tbody tr:hover{background:#F7F9FA;}
  tbody tr:nth-child(even){background:#FBFCFC;}
  tbody tr:nth-child(even):hover{background:#F2F5F6;}

  /* ---------- Badges / chips ---------- */
  .badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:10.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.03em;}
  .badge.site-a{background:#EAF2F8;color:#1F618D;}
  .badge.site-b{background:var(--amber-soft);color:var(--amber);}
  .badge.good{background:var(--green-soft);color:var(--green);}
  .badge.mid{background:var(--amber-soft);color:var(--amber);}
  .badge.bad{background:var(--red-soft);color:var(--red);}
  .badge.rouge{background:var(--red-soft);color:var(--red);}
  .badge.orange{background:var(--amber-soft);color:var(--amber);}
  .badge.vert-clair{background:var(--green-soft);color:var(--green);}
  .badge.vert-fonce{background:var(--green);color:#fff;}
  .badge.gris{background:#EAECEE;color:var(--grey);}
  .badge.warn{background:var(--red-soft);color:var(--red);}

  .info-i{display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;
    background:#EAECEE;color:var(--grey);font-size:9.5px;font-weight:700;margin-left:5px;cursor:help;flex-shrink:0;
    position:relative;}
  .info-i:hover{background:var(--navy);color:#fff;}
  .info-i::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 9px);left:50%;
    transform:translateX(-50%) translateY(4px);width:max-content;max-width:230px;background:var(--navy);color:#fff;
    font-size:11px;font-weight:400;text-transform:none;letter-spacing:normal;line-height:1.5;padding:8px 10px;
    border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.18);opacity:0;visibility:hidden;pointer-events:none;
    transition:opacity .15s ease,transform .15s ease;z-index:60;}
  .info-i::before{content:"";position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);
    border:5px solid transparent;border-top-color:var(--navy);opacity:0;visibility:hidden;
    transition:opacity .15s ease;z-index:60;}
  .info-i:hover::after, .info-i:hover::before{opacity:1;visibility:visible;}
  .info-i:hover::after{transform:translateX(-50%) translateY(0);}

  .cell-edit{cursor:pointer;border-bottom:1px dashed transparent;transition:border-color .15s ease;}
  .cell-edit:hover{border-bottom-color:var(--grey);}
  .cell-edit input{width:64px;padding:3px 6px;font-size:12.5px;border:1px solid var(--line);border-radius:4px;}
  .cell-edit input[type=text]{width:170px;}
  .cell-edit .btn-valider{padding:3px 8px;font-size:11px;margin-left:4px;background:var(--navy);color:#fff;
    border:none;border-radius:4px;cursor:pointer;}

  .bar-track{width:100%;height:7px;background:#EAECEE;border-radius:4px;overflow:hidden;}
  .bar-fill{height:100%;border-radius:4px;transition:width .3s ease;}

  .chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;}
  .chip{padding:6px 13px;border-radius:20px;border:1px solid var(--line);background:#fff;font-size:12px;
    cursor:pointer;font-weight:600;color:var(--grey);transition:all .15s ease;}
  .chip:hover{border-color:#AEB6BB;color:var(--text);}
  .chip.active{background:var(--navy);color:#fff;border-color:var(--navy);}
  .chip.multi.active{background:var(--amber);border-color:var(--amber);color:#1C1300;}
  .chip.disabled{opacity:.35;cursor:not-allowed;}

  .info-box{background:var(--amber-soft);border-left:4px solid var(--amber);border-radius:6px;padding:12px 16px;
    font-size:12.5px;color:#6E4B0A;margin-bottom:16px;}
  .info-box.green{background:var(--green-soft);border-left-color:var(--green);color:#14532A;}

  .field{background:#fff;border:1px solid var(--line);border-radius:8px;padding:10px 14px;min-width:200px;
    transition:border-color .15s ease;}
  .field:focus-within{border-color:var(--navy);}
  .field label{display:block;font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;
    margin-bottom:6px;}
  .field input[type=number]{border:none;font-size:18px;font-weight:700;width:100px;padding:0;}
  .field input[type=range]{width:100%;accent-color:var(--amber);}

  .ser-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0;}
  .ser-out{background:#fff;border:1px solid var(--line);border-radius:9px;padding:16px;
    box-shadow:0 1px 2px rgba(0,0,0,.03);}
  .ser-out .lbl{font-size:10.5px;color:var(--grey);text-transform:uppercase;margin-bottom:8px;}
  .ser-out .val{font-size:21px;font-weight:700;}

  .hidden{display:none !important;}
  .note{font-size:11.5px;color:var(--grey);line-height:1.65;margin-top:14px;border-top:1px solid var(--line);
    padding-top:12px;}
  .toggle-link{font-size:12px;color:var(--navy);text-decoration:underline;cursor:pointer;background:none;
    border:none;padding:0;transition:color .15s ease;}
  .toggle-link:hover{color:var(--amber);}
  .print-header{display:none;}
  .date-range{display:flex;gap:8px;align-items:center;}

  /* ---------- Ajouts propres à la vue Direction ---------- */
  /* Redondant avec .kpi-strip de base (déjà repeat(5,1fr)) mais gardé explicite, même convention
     que .kpi-strip-6 ailleurs dans l'app — passé de 4 à 5 colonnes avec l'ajout du KPI
     Consommation/heure (conso_horaire_moyenne) */
  .kpi-strip-5{grid-template-columns:repeat(5,1fr);}
  .kpi-grand .val{font-size:36px;}
  .kpi-grand .lbl{font-size:11.5px;}

  /* ---------- Mobile (375px) ---------- */
  @media (max-width:768px){
    /* .date-range : même débordement horizontal que dashboard.blade.php à 390px (427px de large
       pour ~350px disponibles, display:flex sans wrap) — même correctif, cf. son commentaire pour
       le détail complet. */
    .date-range{flex-wrap:wrap;}

    /* .kpi-strip-5 : même correctif que .kpi-strip sur dashboard.blade.php — 5 colonnes (avant 4)
       sur 375px laissent trop peu de largeur par carte, 2 colonnes avec minmax(0,1fr) (pas juste
       1fr, pour que les pistes se partagent réellement la largeur du conteneur au lieu de
       déborder) reste lisible. */
    .kpi-strip-5{grid-template-columns:repeat(2,minmax(0,1fr));}

    /* topbar-info (météo + date/heure) : règles identiques à dashboard.blade.php. */
    .topbar-info{flex-wrap:wrap;}
    .topbar-meteo .site-label{display:none;}
    .topbar-meteo .temp-badge{display:none;}

    /* .grid-2-eq (Éco-conduite / Consommation SITE A vs SITE B) : même correctif que
       dashboard.blade.php à l'impression (grid-2-eq{grid-template-columns:1fr !important;}),
       appliqué ici en écran mobile. */
    .grid-2-eq{grid-template-columns:1fr;}

    /* .grid-4-eq : même traitement 1 colonne que .grid-2-eq juste au-dessus — minmax(0,1fr) suffit
       à éliminer le débordement du conteneur. */
    .grid-4-eq{grid-template-columns:1fr;}
    /* .grid-3-eq : même raisonnement et même correctif que .grid-4-eq juste au-dessus. */
    .grid-3-eq{grid-template-columns:1fr;}

    /* .topbar-actions : la rangée de 3 boutons (Vue technique complète / Générer rapport d'audit /
       Imprimer) déborde à 375px sur une seule ligne à côté de .topbar-info. */
    .topbar-actions{flex-direction:column;align-items:stretch;width:100%;}
    .btn-tech-link, .btn-print-top{justify-content:center;}
  }

  /* ---------- Impression / Export PDF ---------- */
  @media print{
    body{background:#fff;}
    .sidebar, .topbar, .filters, .btn, .chip, #toggleQualite{display:none !important;}
    .app{display:block;}
    /* Pas d'onglet à historique long ici (contrairement à Courbes complètes/registre manuel vs
       Télématique A sur /dashboard) donc pas de bascule paysage nécessaire, juste le portrait
       180mm. */
    .main-inner{padding:0;max-width:180mm;}
    [id^="tab-"]:not(.print-active){display:none !important;}
    .card{border:1px solid #ccc;box-shadow:none;break-inside:auto;}
    .kpi{page-break-inside:avoid;}
    /* .grid-2-eq (Éco-conduite / Consommation SITE A vs SITE B) déborde de la largeur imprimable
       en grille figée à 2 colonnes. */
    .grid-2-eq{grid-template-columns:1fr !important;}
    .print-header{display:block;margin-bottom:16px;border-bottom:2px solid var(--navy);padding-bottom:10px;}
    .print-header b{font-size:18px;}
    .print-header span{display:block;font-size:11px;color:#555;}
    canvas{max-height:260px !important;}
  }
</style>
</head>
<body>
<div class="app">

  <div class="main">

    <div class="topbar">
      <div class="topbar-info">
        <span class="topbar-dot"></span> <span id="lastUpdateLabel">Chargement…</span>
        <span class="topbar-sep">·</span>
        <span id="topbarMeteo" class="topbar-meteo"><span class="sub">Météo…</span></span>
        <span class="topbar-sep">·</span>
        <span id="topbarDateHeure" class="topbar-datetime"></span>
      </div>
      <div class="topbar-actions">
        <a href="/dashboard" class="btn-tech-link">Vue technique complète →</a>
        <a href="/audit-iso50001" id="lienGenererAudit" class="btn-tech-link" target="_blank" rel="noopener">📋 Générer rapport d'audit</a>
        <button class="btn-print-top" id="btnPrint">🖨️ Imprimer / Export PDF</button>
        {{-- Lien Admin uniquement — même garde serveur que dans dashboard.blade.php, ce masquage
           n'est qu'un confort d'affichage. --}}
        @if (auth()->user()?->estAdmin())
          <a href="/utilisateurs" class="btn-tech-link">👤 Utilisateurs</a>
        @endif
        <form method="POST" action="/deconnexion" class="form-deconnexion">@csrf<button type="submit" class="btn-tech-link">🔒 Se déconnecter</button></form>
      </div>
    </div>

    <div class="main-inner">

      <div class="print-header">
        <b>Fleet Energy Analytics · Vue Direction — Suivi énergétique flotte</b>
        <span id="printDate"></span>
      </div>

      <h1>Fleet Energy Analytics · Vue Direction — Suivi énergétique flotte</h1>
      <p class="sub" id="sousTitreDirection">Synthèse pour le pilotage — chargement de l'effectif…</p>
      <div class="sub" id="sousTitreDirectionNote" style="margin-top:-8px;font-size:11px;color:var(--grey);" hidden></div>

      <div class="filters">
        <label>Période</label>
        <select id="filtrePeriodeDirection">
          <option value="3">3 derniers mois</option>
          <option value="6">6 derniers mois</option>
          <option value="12">12 derniers mois</option>
          <option value="999" selected>Tout l'historique</option>
          <option value="custom">Plage personnalisée...</option>
        </select>
        <span id="dateRangeDirection" class="date-range hidden">
          <label>Du</label><input type="date" id="dateDebutDirection">
          <label>au</label><input type="date" id="dateFinDirection">
          <button class="btn outline" id="btnAppliquerDatesDirection">Appliquer</button>
        </span>
      </div>

      <div class="kpi-strip kpi-strip-5" id="kpiDirection"></div>

      <div class="grid-2-eq">
        <div class="card">
          <h3>Éco-conduite — SITE A vs SITE B</h3>
          <canvas id="chartSitesEco" height="110"></canvas>
        </div>
        <div class="card">
          <h3>Consommation — SITE A vs SITE B (L/100km)</h3>
          <canvas id="chartSitesConso" height="110"></canvas>
        </div>
      </div>

      <div class="card">
        <h3>Points d'attention</h3>
        <p class="sub" style="margin:-8px 0 14px;">Statut norme interne actuel de chaque véhicule (dernier mois exploité) — indépendant de la période choisie ci-dessus, comme "Aujourd'hui" n'a pas de sens recalculé à une date passée.</p>
        <div id="pointsAttention"></div>
      </div>

      <div class="card">
        <h3>Suggestions</h3>
        <div id="suggestionsDirection"></div>
      </div>

    </div>
  </div>
</div>

<script src="/js/periode-selecteur.js"></script>
<script>
const API = {
  resume: '/api/flotte/resume',
  meteo: '/api/meteo',
  alertes: '/api/alertes',
  suggestions: '/api/suggestions',
  comparateurSites: '/api/comparateur-sites',
  serDynamique: '/api/ser-dynamique',
};
const COULEURS_SITE = { 'SITE A': '#1F618D', 'SITE B': '#B9770E' };
let chartSitesEcoInstance = null, chartSitesConsoInstance = null;
// Caches pour le sélecteur de période : Alertes actives (KPI) reste sur l'état courant (pas de
// sens de "recompter les alertes actives à une date passée", même limite que Points
// d'attention/SER dynamique) donc ALERTES_DIRECTION_CACHE n'est jamais refiltré, juste réutilisé
// tel quel à chaque nouveau rendu des KPI.
let ALERTES_DIRECTION_CACHE = null;
let COMPARATEUR_SITES_DIRECTION_TOUTES = [];

function fmt(n, d=1){ return (n ?? 0).toLocaleString('fr-FR', {minimumFractionDigits:d, maximumFractionDigits:d}); }
function moisLisible(d){ const dt = new Date(d); return dt.toLocaleDateString('fr-FR', {month:'short', year:'numeric'}); }
function formatKpiPeriode(valeur, nbMesuresPeriode, unite){
  if (!nbMesuresPeriode) return '<span style="color:var(--grey);">—</span><small>aucune donnée</small>';
  return `${fmt(valeur)}<small>${unite}</small>`;
}

function badgeTemperature(temp){
  if (temp == null) return '';
  if (temp >= 35) return `<span class="temp-badge canicule" title="Canicule : risque accru de surconsommation, vigilance chauffeurs">Canicule</span>`;
  if (temp >= 30) return `<span class="temp-badge chaleur" title="Chaleur">Chaleur</span>`;
  if (temp < 10) return `<span class="temp-badge froid" title="Froid : démarrage moteur à froid, ralenti plus long">Froid</span>`;
  return '';
}

async function renderMeteo(){
  const el = document.getElementById('topbarMeteo');
  try {
    const res = await fetch(API.meteo);
    if (!res.ok) throw new Error('meteo indisponible');
    const data = await res.json();
    el.innerHTML = Object.entries(data).map(([site, m]) => {
      if (!m || m.erreur || m.temperature == null) {
        return `<span class="site" title="Donnée météo indisponible${m?.status ? ' (erreur ' + m.status + ')' : ''}">${site} <span style="color:var(--red);">indisponible</span></span>`;
      }
      const titre = `${m.description ?? ''} · Vent ${m.vent_kmh != null ? fmt(m.vent_kmh, 1) + ' km/h' : '—'} · Ressenti ${m.ressenti != null ? fmt(m.ressenti, 1) + '°C' : '—'} · MAJ ${m.maj ?? '—'}`;
      return `<span class="site" title="${titre}"><span class="site-label">${site}</span>${m.icone ? ` <img src="https://openweathermap.org/img/wn/${m.icone}.png" alt="${m.description ?? ''}">` : ''} <b>${fmt(m.temperature, 1)}°C</b>${badgeTemperature(m.temperature)}</span>`;
    }).join('');
  } catch(e) {
    el.innerHTML = `<span style="color:var(--red);">Météo indisponible</span>`;
  }
}

function majHorlogeTopbar(){
  const el = document.getElementById('topbarDateHeure');
  const maintenant = new Date();
  // Format court sous le même seuil que dashboard.blade.php — la date longue avec jour de la
  // semaine ("vendredi 07 août 2026") contribuait au débordement de .topbar-info.
  const compact = window.matchMedia('(max-width:768px)').matches;
  const date = compact
    ? maintenant.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit'})
    : maintenant.toLocaleDateString('fr-FR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
  const heure = maintenant.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
  el.textContent = `${date} · ${heure}`;
}

// KPI
// periodeActive : true si une période autre que "Tout l'historique" est sélectionnée — sert
// uniquement à afficher la note ci-dessous sur le coût carburant, qui n'est PAS scopé par
// depuis/jusqu_a côté backend (CamionController::resumeFlotte.
function renderKpis(data, alertes, coutFlotte, periodeActive){
  const nbAlertesActives = alertes ? alertes.filter(a => !a.resolue).length : null;
  const couleurAlertes = nbAlertesActives === null ? 'var(--grey)' : (nbAlertesActives > 0 ? 'var(--red)' : 'var(--green)');
  const valAlertes = nbAlertesActives === null
    ? '<span style="color:var(--grey);">—</span>'
    : `<span style="color:${couleurAlertes};">${nbAlertesActives}</span>`;

  document.getElementById('kpiDirection').innerHTML = `
    <div class="kpi kpi-grand"><div class="lbl">Score éco-conduite</div><div class="val">${formatKpiPeriode(data.eco_score_moyen, data.nb_mesures_periode, '/10')}</div></div>
    <div class="kpi kpi-grand"><div class="lbl">Consommation moyenne</div><div class="val">${formatKpiPeriode(data.conso_moyenne, data.nb_mesures_periode, 'L/100km')}</div></div>
    <div class="kpi kpi-grand"><div class="lbl">Consommation / heure</div><div class="val">${formatKpiPeriode(data.conso_horaire_moyenne, data.nb_mesures_conso_horaire, 'L/h')}</div></div>
    <div class="kpi kpi-grand">
      <div class="lbl">Coût carburant moyen</div>
      <div class="val">${coutFlotte.moyenne !== null ? fmt(coutFlotte.moyenne, 3) + '<small>DT/km</small>' : '<span style="color:var(--grey);">—</span><small>aucune donnée</small>'}</div>
      ${(() => {
        // Fusionnées en une seule ligne (pas deux notes empilées) : les deux faits peuvent être
        // vrais en même temps (prix estimé ET valeur non scopée par la période), et un lecteur qui
        // change la période doit toujours voir la 2e raison, même quand la 1re est déjà affichée.
        const notes = [];
        if (coutFlotte.estime) notes.push("Prix estimé (historique de prix incomplet pour certaines périodes)");
        if (periodeActive) notes.push("toujours calculé sur tout l'historique, indépendamment de la période choisie");
        return notes.length
          ? `<div class="sub" style="margin:4px 0 0;color:${coutFlotte.estime ? 'var(--amber)' : 'var(--grey)'};font-size:10.5px;line-height:1.3;">${notes.join(' · ')}</div>`
          : '';
      })()}
    </div>
    <div class="kpi kpi-grand">
      <div class="lbl">Alertes actives</div>
      <div class="val">${valAlertes}</div>
      ${nbAlertesActives === null ? `<div class="sub" style="margin:4px 0 0;color:var(--red);font-size:10.5px;">Indisponible pour le moment</div>` : ''}
    </div>
  `;
}

// Points d'attention
// Source volontairement différente de "Alertes actives" dans le KPI strip ci-dessus (/api/alertes,
// historique d'événements) : ceci reflète le statut norme interne du DERNIER mois exploité par
// véhicule (/api/ser-dynamique, cf. NormeRefStatutService)
const STATUTS_CONNUS_NORME_REF = ['ROUGE', 'ORANGE', 'VERT_CLAIR', 'VERT_FONCE'];
function renderPointsAttention(statuts){
  const el = document.getElementById('pointsAttention');
  if (statuts === null) {
    el.innerHTML = `<p class="sub" style="color:var(--red);">Points d'attention indisponibles pour le moment.</p>`;
    return;
  }

  const compte = { ROUGE: 0, ORANGE: 0, VERT_CLAIR: 0, VERT_FONCE: 0, DONNEES_INSUFFISANTES: 0 };
  statuts.forEach(s => {
    // Tout statut hors des 4 valeurs "réelles" connues (y compris DONNEES_INSUFFISANTES lui-même, ou
    // une valeur imprévue) rejoint "donnée manquante" — jamais ignoré silencieusement, pour que le
    // total des 3 cartes + la barre corresponde toujours au nombre réel de camions (règle de fiabilité
    // du projet : ne jamais faire disparaître un véhicule plutôt que d'admettre une donnée absente).
    const cle = STATUTS_CONNUS_NORME_REF.includes(s.statut) ? s.statut : 'DONNEES_INSUFFISANTES';
    compte[cle]++;
  });
  const total = statuts.length;

  // 4e carte "Donnée insuffisante" ajoutée, au même niveau visuel (même classe kpi-grand) que
  // Critique/À surveiller/Normal.
  const cartes = [
    { label: 'Critique', n: compte.ROUGE, couleur: 'var(--red)' },
    { label: 'À surveiller', n: compte.ORANGE, couleur: 'var(--amber)' },
    { label: 'Normal', n: compte.VERT_CLAIR + compte.VERT_FONCE, couleur: 'var(--green)' },
    { label: 'Donnée insuffisante', n: compte.DONNEES_INSUFFISANTES, couleur: 'var(--grey)' },
  ].map(c => `
    <div class="kpi kpi-grand">
      <div class="lbl">${c.label}</div>
      <div class="val" style="color:${c.couleur};">${c.n}</div>
    </div>`).join('');

  // Barre empilée : réutilise .bar-track/.bar-fill (déjà utilisée pour "Répartition par site") plutôt
  // qu'un nouveau composant — display:flex posé en style inline uniquement sur CET élément, pour ne
  // pas changer le comportement du .bar-track partagé ailleurs (segment unique, sans flex). Garde le
  // détail plus fin que les 4 cartes (rouge/orange/vert clair/vert foncé/donnée manquante séparés,
  // pas juste vert clair+foncé fusionnés dans "Normal") — les 4 cartes donnent le coup d'œil global,
  // la barre le détail pour qui veut creuser. Un segment à 0 véhicule ne produit simplement aucune
  // largeur visible.
  const segments = [
    { n: compte.ROUGE, couleur: 'var(--red)', label: 'rouge' },
    { n: compte.ORANGE, couleur: 'var(--amber)', label: 'orange' },
    { n: compte.VERT_CLAIR, couleur: 'var(--green-soft)', label: 'vert clair' },
    { n: compte.VERT_FONCE, couleur: 'var(--green)', label: 'vert foncé' },
    { n: compte.DONNEES_INSUFFISANTES, couleur: '#AEB6BB', label: 'donnée manquante' },
  ];
  const barre = total
    ? segments.filter(s => s.n).map(s =>
        `<div class="bar-fill" style="flex:0 0 ${(s.n / total * 100)}%;background:${s.couleur};" title="${s.n} ${s.label}"></div>`
      ).join('')
    : '';
  const legende = total
    ? segments.filter(s => s.n).map(s => `${s.n} ${s.label}`).join(' · ')
    : 'Aucun véhicule';

  el.innerHTML = `
    <div class="grid-4-eq" style="margin-bottom:16px;">${cartes}</div>
    <div class="bar-track" style="display:flex;">${barre}</div>
    <div class="sub" style="margin:8px 0 0;font-size:11.5px;">${legende}</div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px;">
      <a href="/dashboard" class="btn-tech-link">Voir le détail →</a>
    </div>
  `;
}

// Suggestions
// Réutilise le même pattern (cartes kpi-grand + lien "Voir le détail →" vers /dashboard) plutôt
// qu'un nouveau composant.grid-3-eq (3 cartes ici) au lieu de .grid-4-eq (4 cartes pour Points
// d'attention), seule différence.
function renderSuggestionsDirection(suggestions){
  const el = document.getElementById('suggestionsDirection');
  if (suggestions === null) {
    el.innerHTML = '<p class="sub" style="color:var(--red);">Suggestions indisponibles pour le moment.</p>';
    return;
  }

  const cartes = [
    { label: 'Urgent', n: suggestions.filter(s => s.gravite === 'urgent').length, couleur: 'var(--red)' },
    { label: 'Attention', n: suggestions.filter(s => s.gravite === 'attention').length, couleur: 'var(--amber)' },
    { label: 'Info', n: suggestions.filter(s => s.gravite === 'info').length, couleur: 'var(--grey)' },
  ].map(c => `
    <div class="kpi kpi-grand">
      <div class="lbl">${c.label}</div>
      <div class="val" style="color:${c.couleur};">${c.n}</div>
    </div>`).join('');

  el.innerHTML = `
    <div class="grid-3-eq">${cartes}</div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px;">
      <a href="/dashboard" class="btn-tech-link">Voir le détail →</a>
    </div>
  `;
}

// ---------- Comparaison SITE A vs SITE B (mêmes graphiques que l'onglet Comparateur de /dashboard) ----------
function renderComparateurSites(lignes){
  const sites = ['SITE A', 'SITE B'];
  const mois = [...new Set(lignes.map(l => l.mois))].sort();
  const parSite = {};
  sites.forEach(site => {
    parSite[site] = {};
    lignes.filter(l => l.site === site).forEach(l => { parSite[site][l.mois] = l; });
  });

  const labels = mois.map(m => moisLisible(m));

  const ctxEco = document.getElementById('chartSitesEco');
  if (chartSitesEcoInstance) chartSitesEcoInstance.destroy();
  chartSitesEcoInstance = new Chart(ctxEco, {
    type: 'line',
    data: {
      labels,
      datasets: sites.map(site => ({
        label: site,
        data: mois.map(m => parSite[site][m]?.eco_score_moyen ?? null),
        borderColor: COULEURS_SITE[site],
        tension: 0,
        spanGaps: true,
      })),
    },
    options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { min: 0, max: 10, title: { display: true, text: 'Eco Score' } } } },
  });

  const ctxConso = document.getElementById('chartSitesConso');
  if (chartSitesConsoInstance) chartSitesConsoInstance.destroy();
  chartSitesConsoInstance = new Chart(ctxConso, {
    type: 'line',
    data: {
      labels,
      datasets: sites.map(site => ({
        label: site,
        data: mois.map(m => parSite[site][m]?.conso_moyenne ?? null),
        borderColor: COULEURS_SITE[site],
        tension: 0,
        spanGaps: true,
      })),
    },
    options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { title: { display: true, text: 'L/100km' } } } },
  });
}

// Sélecteur de période
// Recharge les KPI depuis /api/flotte/resume avec les bornes choisies — endpoint déjà period-aware
// (mêmes paramètres depuis/jusqu_a que Vue d'ensemble sur /dashboard), donc aucun filtrage côté
// client nécessaire ici.
async function chargerKpisPeriode(){
  const bornes = bornesPeriodeGenerique('Direction');
  if (bornes.incomplet) return;
  const params = new URLSearchParams();
  if (bornes.depuis) params.set('depuis', bornes.depuis);
  if (bornes.jusquA) params.set('jusqu_a', bornes.jusquA);
  // source_principale toujours explicite — sans lui, le repli backend par défaut.
  params.set('source_principale', 'SOURCE_A,SOURCE_B');
  try {
    const res = await fetch(API.resume + `?${params.toString()}`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    const coutFlotte = { moyenne: data?.cout_carburant_moyen ?? null, estime: data?.cout_carburant_estime ?? false };
    renderKpis(data, ALERTES_DIRECTION_CACHE, coutFlotte, !!(bornes.depuis || bornes.jusquA));
  } catch (e) {
    console.error('Erreur /api/flotte/resume (période Direction) :', e);
  }
}

// Refiltre COMPARATEUR_SITES_DIRECTION_TOUTES (déjà en cache, jamais re-fetché) sur la période
// choisie — /api/comparateur-sites ne prend pas depuis/jusqu_a côté serveur, donc le filtrage se fait
// ici, même pattern que rafraichirComparateurSites() sur l'onglet Comparateur de /dashboard.
function rafraichirComparateurSitesDirection(){
  const bornes = bornesPeriodeGenerique('Direction');
  if (bornes.incomplet) return;
  const lignes = (bornes.depuis || bornes.jusquA)
    ? COMPARATEUR_SITES_DIRECTION_TOUTES.filter(l => dansPeriode(l.mois + '-01', bornes))
    : COMPARATEUR_SITES_DIRECTION_TOUTES;
  renderComparateurSites(lignes);
}

function appliquerPeriodeDirection(){
  chargerKpisPeriode();
  rafraichirComparateurSitesDirection();
  majLienAudit('Direction', 'lienGenererAudit');
}
initSelecteurPeriode('Direction', appliquerPeriodeDirection);

document.getElementById('btnPrint').addEventListener('click', () => window.print());

// Même correctif que dashboard.blade.php : forcer un resize des graphiques Chart.js une fois la
// mise en page print appliquée (grid-2-eq empilé en 1 colonne), sinon ils gardent leur taille écran.
window.addEventListener('beforeprint', () => {
  Object.values(Chart.instances).forEach(c => c.resize());
});

// ---------- Démarrage ----------
async function init(){
  document.getElementById('printDate').textContent = 'Généré le ' + new Date().toLocaleDateString('fr-FR', {day:'2-digit',month:'long',year:'numeric'});
  majHorlogeTopbar();
  setInterval(majHorlogeTopbar, 60000);
  renderMeteo();

  // Les 5 appels sont indépendants (aucun n'a besoin du résultat d'un autre) — lancés en parallèle
  // plutôt qu'en séquence pour que le temps de chargement soit celui du plus lent des 5, pas leur
  // somme.
  const [data, alertes, suggestions, serDynamique, comparateurSites] = await Promise.all([
    (async () => {
      try {
        // Sans ce paramètre, le repli backend par défaut ('SOURCE_A' seul, cf. CamionController)
        // ramènerait silencieusement cette page à 12.
        const res = await fetch(API.resume + '?source_principale=SOURCE_A,SOURCE_B');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
      } catch (e) {
        document.getElementById('kpiDirection').innerHTML = '<p class="sub" style="color:var(--red);">Données flotte indisponibles pour le moment.</p>';
        return null;
      }
    })(),
    (async () => {
      try {
        const res = await fetch(API.alertes);
        if (res.ok) return await res.json();
      } catch (e) {}
      return null;
    })(),
    (async () => {
      try {
        const res = await fetch(API.suggestions);
        if (res.ok) return await res.json();
      } catch (e) {}
      return null;
    })(),
    (async () => {
      try {
        const res = await fetch(API.serDynamique);
        if (res.ok) return await res.json();
      } catch (e) {}
      return null;
    })(),
    (async () => {
      try {
        const res = await fetch(API.comparateurSites);
        if (res.ok) return await res.json();
      } catch (e) {}
      return [];
    })(),
  ]);

  const coutFlotte = { moyenne: data?.cout_carburant_moyen ?? null, estime: data?.cout_carburant_estime ?? false };

  if (data) {
    // Plus jamais "12" en dur : effectif réel et sites réels, tirés de par_camion/par_groupe
    // (mêmes clés que le tableau de bord principal, cf. texteEffectifFlotte() dans
    // dashboard.blade.php)
    const nb = data.par_camion?.length ?? 0;
    const sites = Object.keys(data.par_groupe ?? {});
    const sitesTxt = sites.length === 0 ? 'Aucun site'
      : sites.length === 1 ? `Site ${sites[0]}`
      : `Sites ${sites.slice(0, -1).join(', ')} & ${sites[sites.length - 1]}`;
    document.getElementById('sousTitreDirection').textContent =
      `Synthèse pour le pilotage — ${nb} véhicule${nb > 1 ? 's' : ''} · ${sitesTxt}`;
    // Clarification : les KPI de score éco-conduite/consommation restent calculés UNIQUEMENT
    // depuis les mesures Télématique A réelles (structurellement nulles pour un véhicule
    // Télématique B-only) — leur valeur ne change donc jamais avec l'élargissement à 27.
    const nbAvecSourceA = (data.par_camion || []).filter(c => c.source_principale === 'SOURCE_A').length;
    const noteEl = document.getElementById('sousTitreDirectionNote');
    if (noteEl) {
      noteEl.textContent = nb !== nbAvecSourceA ? `dont ${nbAvecSourceA} avec données Télématique A` : '';
      noteEl.hidden = nb === nbAvecSourceA;
    }
  }

  // Mis en cache pour le sélecteur de période : ALERTES_DIRECTION_CACHE est réutilisé tel quel par
  // chargerKpisPeriode() (KPI "Alertes actives" non scopé, cf. commentaire plus haut),
  // COMPARATEUR_SITES_DIRECTION_TOUTES est l'historique complet jamais re-fetché.
  ALERTES_DIRECTION_CACHE = alertes;
  COMPARATEUR_SITES_DIRECTION_TOUTES = comparateurSites;

  if (data) renderKpis(data, alertes, coutFlotte, false);
  renderPointsAttention(serDynamique);
  renderSuggestionsDirection(suggestions);
  rafraichirComparateurSitesDirection();

  document.getElementById('lastUpdateLabel').textContent = 'Données à jour · ' + new Date().toLocaleString('fr-FR');
  majLienAudit('Direction', 'lienGenererAudit');
}

init();
</script>
</body>
</html>
