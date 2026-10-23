<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet Energy Analytics — Suivi énergétique ISO 50001</title>
<script src="/js/session-garde.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<!-- Prototype temps réel : Suivi GPS uniquement. -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@2/dist/echo.iife.js"></script>
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

  /* ---------- Barre de filtres globaux ---------- */
  /* Placée hors de .app (au-dessus de sidebar+topbar) pour rester visible sur tous les onglets,
     puisque seuls les enfants [id^="tab-"] de .main-inner sont masqués/affichés au changement d'onglet. */
  .global-filterbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;
    background:#12181F;padding:10px 24px;border-bottom:1px solid rgba(255,255,255,.08);
    position:sticky;top:0;z-index:80;}
  .global-filterbar .gfb-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    color:#8FA0AD;display:flex;align-items:center;gap:6px;margin-right:2px;white-space:nowrap;}
  .global-filterbar .gfb-group{display:flex;align-items:center;gap:6px;}
  .global-filterbar label{font-size:11px;color:#AEB9C2;text-transform:uppercase;letter-spacing:.05em;}
  .global-filterbar select{background:#1C2833;color:#fff;border:1px solid #34424D;border-radius:6px;
    padding:7px 10px;font-size:13px;transition:border-color .15s ease;}
  .global-filterbar select:focus{outline:none;border-color:var(--amber);}
  .global-filterbar .btn.outline{background:transparent;color:#AEB9C2;border-color:#34424D;
    padding:7px 14px;font-size:12.5px;}
  .global-filterbar .btn.outline:hover{background:rgba(255,255,255,.06);color:#fff;border-color:#4C5A66;}

  /* ---------- Filtres généraux multi-select (menus à cases à cocher) ---------- */
  .ms{position:relative;}
  .ms-btn{background:#1C2833;color:#fff;border:1px solid #34424D;border-radius:6px;padding:7px 10px;
    font-size:13px;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:7px;
    transition:border-color .15s ease;max-width:220px;overflow:hidden;text-overflow:ellipsis;}
  .ms-btn:hover{border-color:#4C5A66;}
  .ms-btn::after{content:"▾";font-size:9px;color:#8FA0AD;flex-shrink:0;}
  .ms-panel{position:absolute;top:calc(100% + 6px);left:0;background:#1C2833;border:1px solid #34424D;
    border-radius:8px;padding:8px;min-width:200px;max-width:min(320px,calc(100vw - 32px));
    max-height:280px;overflow-y:auto;box-shadow:0 10px 28px rgba(0,0,0,.4);z-index:90;}
  .ms-panel label{display:flex;align-items:center;gap:8px;padding:6px 7px;border-radius:5px;
    font-size:13px;color:#fff;text-transform:none;letter-spacing:normal;cursor:pointer;}
  .ms-panel label:hover{background:rgba(255,255,255,.07);}
  .ms-panel input[type=checkbox]{accent-color:var(--amber);width:15px;height:15px;flex-shrink:0;}
  .ms-panel .ms-actions{display:flex;gap:6px;margin-top:6px;padding-top:8px;border-top:1px solid #34424D;}
  .ms-panel .ms-actions button{background:none;border:none;color:#AEB9C2;font-size:11px;cursor:pointer;
    padding:3px 5px;text-transform:uppercase;letter-spacing:.04em;}
  .ms-panel .ms-actions button:hover{color:#fff;}
  .ms-panel .ms-vide{color:#8FA0AD;font-size:12.5px;padding:6px 7px;font-style:italic;}

  /* ---------- Sidebar ---------- */
  .sidebar{width:220px;flex-shrink:0;background:linear-gradient(180deg,#1C2833 0%,#182028 100%);
    color:#fff;padding:22px 14px;display:flex;flex-direction:column;gap:4px;
    box-shadow:2px 0 12px rgba(0,0,0,.08);}
  .brand{padding:2px 8px 22px;}
  .brand b{font-size:19px;letter-spacing:.02em;}
  .brand > span{display:block;font-size:10.5px;color:#8FA0AD;letter-spacing:.08em;margin-top:3px;}
  .nav-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border:none;border-left:3px solid transparent;
    background:transparent;color:#AEB9C2;font-size:13.5px;font-weight:600;border-radius:6px;cursor:pointer;
    text-align:left;width:100%;transition:background .15s ease,color .15s ease,border-color .15s ease;}
  .nav-btn:hover{background:rgba(255,255,255,.06);color:#fff;}
  .nav-btn.active{background:var(--amber);color:#1C1300;border-left-color:#fff;}
  .nav-sep{height:1px;background:rgba(255,255,255,.08);margin:14px 4px;}
  .nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-left:3px solid transparent;
    color:#AEB9C2;font-size:13.5px;font-weight:600;border-radius:6px;text-decoration:none;
    transition:background .15s ease,color .15s ease;}
  .nav-link:hover{background:rgba(255,255,255,.06);color:#fff;}
  /* Bouton "Se déconnecter" : un <button> dans un <form> POST (CSRF), habillé comme .nav-link. */
  .nav-form{margin:0;}
  .nav-form .nav-link{background:transparent;border:none;border-left:3px solid transparent;width:100%;
    cursor:pointer;font-family:inherit;text-align:left;}
  .sidebar-footer{margin-top:auto;padding:12px 10px 2px;font-size:10.5px;color:#6E7C87;letter-spacing:.02em;}

  /* Sidebar rétractable + adaptative ---------- Deux états au-dessus de 768px : complète (220px)
     et rail d'icônes (60px) */
  .sidebar-toggle{display:none;align-items:center;gap:10px;width:100%;padding:8px 12px;margin-bottom:6px;
    border:1px solid rgba(255,255,255,.12);background:transparent;color:#8FA0AD;font-size:12px;
    font-weight:600;font-family:inherit;border-radius:6px;cursor:pointer;
    transition:background .15s ease,color .15s ease;}
  .sidebar-toggle:hover{background:rgba(255,255,255,.06);color:#fff;}
  /* Sidebar FIXE pendant le défilement : au-dessus de 768px elle est sticky juste sous la barre de
     filtres globaux (elle-même sticky en haut, hauteur variable : 1 à 2 lignes + mention
     persistante — d'où --gfb-h, mesurée en JS par un ResizeObserver, cf. "Sidebar fixe"), sur
     toute la hauteur restante de l'écran. */
  .sidebar{scrollbar-width:thin;scrollbar-color:#34424D transparent;}
  .sidebar::-webkit-scrollbar{width:6px;}
  .sidebar::-webkit-scrollbar-thumb{background:#34424D;}
  @media (min-width:769px){
    .sidebar{transition:width .2s ease;overflow-x:hidden;overflow-y:auto;
      position:sticky;top:var(--gfb-h,0px);align-self:flex-start;
      height:calc(100vh - var(--gfb-h,0px));height:calc(100dvh - var(--gfb-h,0px));}
    .sidebar-toggle{display:flex;}
    .app.sidebar-rail .sidebar{width:60px;padding:22px 8px;}
    .app.sidebar-rail .rail-cache, .app.sidebar-rail .nav-label{display:none;}
    .app.sidebar-rail .brand{padding:2px 0 14px;text-align:center;}
    .app.sidebar-rail .brand b{font-size:15px;}
    .app.sidebar-rail .nav-btn, .app.sidebar-rail .nav-link, .app.sidebar-rail .sidebar-toggle{
      justify-content:center;padding:10px 0;border-left-width:0;}
    .app.sidebar-rail .nav-sep{margin:10px 6px;}
    .app.sidebar-rail .sidebar-footer{padding:12px 0 2px;text-align:center;font-size:9.5px;}
  }
  @media (prefers-reduced-motion:reduce){ .sidebar{transition:none;} }

  /* ---------- Barre supérieure ---------- */
  /* flex-wrap inconditionnel : avec la sidebar en rail (769-1199px), .topbar-info + le bouton
     Imprimer ne tiennent plus sur une ligne vers 900px (bouton coupé de 14px à droite, mesuré) —
     la barre passe sur 2 lignes dès que ça ne tient plus, à n'importe quelle largeur, plutôt que
     de dépendre d'un seuil précis. */
  .topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;row-gap:10px;
    padding:14px 32px;background:#fff;border-bottom:1px solid var(--line);
    box-shadow:0 1px 3px rgba(0,0,0,.03);}
  /* flex-wrap:wrap de base : forcer la sidebar complète vers 900px laisse ~680px de large à .main
     — la ligne (mise à jour, météo, date, badge automatisations : ~730px) débordait de la page
     (mesuré : 983px de large pour un viewport de 900px) */
  .topbar-info{display:flex;align-items:center;flex-wrap:wrap;gap:4px 9px;font-size:12.5px;color:var(--grey);font-weight:600;}
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

  /* Sidebar mobile (hamburger + overlay) ---------- Cachés par défaut, visibles uniquement sous
     @media (max-width:768px) plus bas, où .sidebar passe en position:fixed (donc hors du flux flex
     de .app).main{flex:1} récupère alors automatiquement 100% de la largeur de .app dans les deux
     états (ouvert/fermé) */
  .btn-hamburger{display:none;align-items:center;justify-content:center;width:38px;height:38px;
    border:none;background:transparent;color:var(--navy);font-size:20px;cursor:pointer;border-radius:6px;
    flex-shrink:0;transition:background .15s ease;}
  .btn-hamburger:hover{background:#F0F2F3;}
  .sidebar-backdrop{display:none;}

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
  /* Sous-scores éco-conduite (Fiche camion) : même grille 5 colonnes que .kpi-strip, déplacée d'un
     style inline vers cette classe pour pouvoir la cibler dans le media query mobile ci-dessous. */
  .souscores-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:16px;}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:9px;padding:14px 16px;position:relative;
    box-shadow:0 1px 2px rgba(0,0,0,.03);transition:box-shadow .15s ease,transform .12s ease;}
  .kpi:hover{box-shadow:0 4px 14px rgba(0,0,0,.07);transform:translateY(-1px);}
  .kpi::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;border-radius:9px 9px 0 0;background:var(--amber);opacity:.55;}
  .kpi .lbl{font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
  .kpi .val{font-size:23px;font-weight:700;letter-spacing:-.01em;}
  .kpi .val small{font-size:12px;color:var(--grey);font-weight:500;margin-left:3px;}

  /* minmax(0,...) + .table-scroll en overflow-x:auto de base : sans ça, le tableau "Classement des
     camions" (largeur minimale ~700px) poussait la colonne de droite hors du viewport entre ~800
     et ~1050px (mesuré : page à 1020px de large pour un viewport de 1000px) — les pistes `fr` ont
     un min-width:auto implicite. */
  .grid-2{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:16px;}
  /* En-têtes de colonnes FIXES : un `position:sticky` ne colle qu'au plus proche ancêtre à
     défilement — ici .table-scroll (overflow-x:auto le rend aussi défilant en Y, sans hauteur
     bornée il ne défile jamais et l'en-tête partirait avec la page) */
  .table-scroll{overflow:auto;-webkit-overflow-scrolling:touch;max-height:min(72vh,720px);}
  .table-scroll thead th{position:sticky;top:0;z-index:3;background:var(--card);}
  /* Un <canvas> Chart.js pas encore dessiné (onglet sans sélection) garde sa largeur intrinsèque de
     300px et débordait de sa colonne de .grid-3-eq à ~900px (Comparateur, mesuré 941px > 900px). */
  canvas{max-width:100%;}
  @media (max-width:1100px){ .grid-2{grid-template-columns:minmax(0,1fr);} }
  .grid-2-eq{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  /* minmax(0,1fr) plutôt que 1fr seul : par défaut une piste "1fr" de CSS Grid vaut
     minmax(auto,1fr), donc refuse de rétrécir sous la largeur minimale de son contenu (ici, la
     largeur "native" d'un canvas Chart.js) — sur mobile 375px, où .main-inner ne laisse que 91px
     disponibles à cause de la sidebar fixe. */
  .grid-3-eq{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}

  .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:18px 20px;margin-bottom:16px;
    box-shadow:0 1px 2px rgba(0,0,0,.03);transition:box-shadow .18s ease;}
  .card:hover{box-shadow:0 3px 16px rgba(0,0,0,.06);}
  .card h3{font-size:13.5px;margin:0 0 14px;display:flex;align-items:center;justify-content:space-between;
    letter-spacing:.01em;}

  /* ---------- Tableaux ---------- */
  table{width:100%;border-collapse:separate;border-spacing:0;font-size:12.8px;}
  th{text-align:left;padding:9px 10px;color:var(--grey);font-weight:600;border-bottom:2px solid var(--line);
    user-select:none;transition:color .15s ease;}
  th:hover{color:var(--navy);}
  td{padding:9px 10px;border-bottom:1px solid var(--line);}
  tbody tr{transition:background .12s ease;}
  tbody tr:hover{background:#F7F9FA;}
  tbody tr:nth-child(even){background:#FBFCFC;}
  tbody tr.sel td{background:#FEF5E7 !important;}
  .chk-flotte{width:16px;height:16px;cursor:pointer;accent-color:var(--navy);}
  tbody tr:nth-child(even):hover{background:#F2F5F6;}
  /* Curseur pointer réservé aux éléments réellement cliquables (data-sort pour le tri de colonne,
     data-id pour la navigation vers la fiche camion) — jamais posé globalement sur tbody tr / th,
     sinon des tableaux sans aucun listener (détail mensuel, registre manuel vs Télématique A)
     paraissent cliquables sans l'être. */
  th[data-sort],th[data-sort-ser],th[data-id],td[data-id],tr[data-id]{cursor:pointer;}

  /* ---------- Badges / chips ---------- */
  .badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:10.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
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
  .badge.source_b{background:#F4ECF7;color:#7D3C98;}
  /* Kilométrage source corrompu : distinct des badges de plausibilité, il juge la donnée source et non la valeur calculée. */
  .badge.corrompu{background:#1F2937;color:#FBBF24;white-space:normal;}
  /* Badges du titre de « Comparaison des sources » : leur texte peut être long (« 2 sources :
     Télématique A + Registre (Télématique B indisponible pour ce véhicule) ») — sans retour à la
     ligne (.badge est nowrap) le titre dépassait le viewport à 390px (scrollWidth 465) */
  #badgeNbSourcesComparateur, #badgeKmCorrompuComparateur .badge{white-space:normal;}
  /* Contour plutôt que fond plein (contrairement à .badge.source_b ci-dessus) — même teinte
     Télématique B pour garder l'association visuelle, mais volontairement distinct de "Source
     Télématique B" : "+Télématique B" signale une source complémentaire sur un camion en source
     Télématique A, pas sa source principale. */
  .badge.source_b-multi{background:#fff;color:#7D3C98;border:1px solid #7D3C98;}

  .info-i{display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;
    background:#EAECEE;color:var(--grey);font-size:9.5px;font-weight:700;margin-left:5px;cursor:help;flex-shrink:0;
    position:relative;}
  .info-i:hover{background:var(--navy);color:#fff;}
  /* .info-t : même pastille que .info-i mais info-bulle NATIVE (title) — sans pseudo-élément. */
  .info-t{display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;
    background:#EAECEE;color:var(--grey);font-size:9.5px;font-weight:700;margin-left:5px;cursor:help;flex-shrink:0;}
  .info-t:hover{background:var(--navy);color:#fff;}
  /* .info-i::after/::before restaient dans le flux de layout même invisibles (opacity:0;
     visibility:hidden n'est pas display:none) : sur mobile, la bulle de tooltip. */
  .info-i::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 9px);left:50%;
    transform:translateX(calc(-50% + var(--tip-dx,0px)));width:max-content;max-width:min(230px,calc(100vw - 24px));background:var(--navy);color:#fff;
    font-size:11px;font-weight:400;text-transform:none;letter-spacing:normal;line-height:1.5;padding:8px 10px;
    border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.18);display:none;pointer-events:none;z-index:60;}
  .info-i::before{content:"";position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);
    border:5px solid transparent;border-top-color:var(--navy);display:none;z-index:60;}
  .info-i:hover::after, .info-i:hover::before{display:block;}
  /* Info-bulle du ⓘ de la BARRE DE FILTRES : elle s'ouvre vers le BAS. */
  .global-filterbar .info-i::after{bottom:auto;top:calc(100% + 9px);max-width:min(230px,calc(100vw - 24px));
    transform:translateX(calc(-50% + var(--tip-dx,0px)));}
  .global-filterbar .info-i::before{bottom:auto;top:calc(100% + 4px);
    border-top-color:transparent;border-bottom-color:var(--navy);}

  /* Standards SER enrichi ---------- 3 groupes de colonnes (référence norme interne en vigueur /
     statut du dernier mois exploité / réel sur la période choisie) + 1re colonne figée au
     défilement horizontal (12 colonnes : sans elle, le nom du camion disparaît dès qu'on fait
     défiler) */
  .tbl-ser-std th.grp{text-align:center;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;
    border-bottom:1px solid var(--line);white-space:nowrap;}
  .tbl-ser-std th.grp-ref{color:var(--navy);} .tbl-ser-std th.grp-statut{color:var(--amber);}
  .tbl-ser-std th.grp-reel{color:var(--green);}
  .tbl-ser-std thead th{background:#fff;vertical-align:bottom;}
  .tbl-ser-std tbody tr:nth-child(odd){background:#fff;}
  .tbl-ser-std tbody tr:nth-child(even){background:#FBFCFC;}
  .tbl-ser-std tbody tr:hover{background:#F2F5F6;}
  /* Colonne « Camion » figée à gauche. Seulement la 1re cellule des LIGNES du corps et la 1re cellule de la
     1re ligne d'en-tête : le 1er <th> de la 2e ligne d'en-tête est « Usage norme interne » (la cellule « Camion » est en
     rowspan=2 sur la 1re ligne), qui ne doit pas se figer. Coin en haut à gauche : collé en haut ET à gauche,
     au-dessus (z-index 4) des autres en-têtes (3) et des cellules figées du corps (2). */
  .tbl-ser-std tbody td:first-child{position:sticky;left:0;z-index:2;
    background:inherit;box-shadow:1px 0 0 var(--line);}
  .tbl-ser-std thead tr:first-child th:first-child{left:0;z-index:4;background:#fff;box-shadow:1px 0 0 var(--line);}
  /* En-tête sur DEUX lignes : la 2e ligne se colle juste sous la 1re (hauteur fixée pour que ce décalage soit
     exact — 34px, vérifié par mesure). */
  .tbl-ser-std thead tr:first-child th{height:34px;box-sizing:border-box;}
  .tbl-ser-std thead tr:nth-child(2) th{top:34px;}
  /* Comparateur : un camion par LIGNE, un indicateur par colonne. 1re colonne (camion) figée à
     gauche pour les écrans étroits, même principe que Standards SER. */
  .tbl-comparateur tbody td:first-child{position:sticky;left:0;z-index:2;background:inherit;box-shadow:1px 0 0 var(--line);}
  .tbl-comparateur thead th:first-child{left:0;z-index:4;box-shadow:1px 0 0 var(--line);}
  .tbl-comparateur tbody tr:nth-child(odd){background:#fff;}
  .tbl-comparateur th{vertical-align:bottom;}
  .tbl-comparateur td:first-child{min-width:130px;}
  .tbl-ser-std td.reel{white-space:nowrap;}
  .tbl-ser-std .sub-mois{display:block;font-size:10.5px;color:var(--grey);font-weight:400;margin-top:2px;
    white-space:nowrap;}
  .tbl-ser-std .sub-mois.partiel{color:var(--amber);font-weight:600;}
  .tbl-ser-std td.cell-statut{white-space:nowrap;}

  .bar-track{width:100%;height:7px;background:#EAECEE;border-radius:4px;overflow:hidden;}
  .bar-fill{height:100%;border-radius:4px;transition:width .3s ease;}

  /* Flux unique Alertes & suggestions ---------- Distinction par la FORME, pas seulement la
     couleur (la couleur code déjà le niveau d'urgence) */
  .flux-item{display:flex;gap:12px;padding:12px 14px;margin-bottom:8px;border:1px solid var(--line);
    border-left-width:5px;border-radius:8px;background:#fff;font-size:12.5px;}
  .flux-item.niveau-rouge{--niv:var(--red);} .flux-item.niveau-orange{--niv:var(--amber);}
  .flux-item.niveau-gris{--niv:var(--grey);}
  .flux-item.flux-alerte{border-left-style:solid;border-left-color:var(--niv);}
  .flux-item.flux-suggestion{border-left-style:dashed;border-left-color:var(--niv);background:#FCFDFD;}
  .flux-item.resolue{opacity:.6;}
  .flux-puce{font-size:16px;line-height:1.2;flex-shrink:0;width:20px;text-align:center;}
  .flux-tete{display:flex;align-items:center;flex-wrap:wrap;gap:6px 8px;margin-bottom:4px;}
  .flux-type{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.06em;padding:2px 9px;
    border-radius:20px;text-transform:uppercase;border:1px solid var(--niv);white-space:nowrap;}
  .flux-type.alerte{background:var(--niv);color:#fff;}
  .flux-type.suggestion{background:transparent;color:var(--niv);}
  .flux-niveau{font-size:10.5px;color:var(--grey);font-weight:600;white-space:nowrap;}
  .flux-legende{display:flex;flex-wrap:wrap;gap:8px 22px;font-size:11.5px;color:var(--grey);margin:2px 0 14px;}
  .flux-legende .flux-type{margin-right:4px;}
  .alert-line{display:flex;gap:10px;padding:11px 2px;border-bottom:1px solid var(--line);font-size:12.5px;}
  .alert-line:last-child{border-bottom:none;}
  .dot{width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0;}

  .chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;}
  .chip{padding:6px 13px;border-radius:20px;border:1px solid var(--line);background:#fff;font-size:12px;
    cursor:pointer;font-weight:600;color:var(--grey);transition:all .15s ease;}
  .chip:hover{border-color:#AEB6BB;color:var(--text);}
  .chip.active{background:var(--navy);color:#fff;border-color:var(--navy);}
  /* Hauteur liée au viewport (pas une valeur fixe unique pour tous les écrans) : occupe la majorité
     de l'espace vertical disponible sous les chips/en-tête, plafonnée pour ne pas devenir démesurée
     sur un grand écran, avec un plancher pour rester utilisable sur un viewport court (mobile en
     paysage). La largeur (100%, déjà fluide) suit .card/.main-inner sans contrainte supplémentaire. */
  #mapGps{width:100%;height:clamp(340px, 70vh, 640px);border-radius:8px;z-index:1;}
  .leaflet-popup-content{font-size:12.5px;line-height:1.5;}
  .chip.multi.active{background:var(--amber);border-color:var(--amber);color:#1C1300;}

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

  /* Estimation de coût annuel : reprise de l'ancienne carte "Coût carburant estimé /an" de Suivi
     SER/IPÉ. */
  .cout-annuel{background:#fff;border:1px solid var(--line);border-radius:9px;padding:10px 16px;
    box-shadow:0 1px 2px rgba(0,0,0,.03);min-width:220px;}
  .cout-annuel .lbl{font-size:10.5px;color:var(--grey);text-transform:uppercase;margin-bottom:4px;}
  .cout-annuel .val{font-size:21px;font-weight:700;}
  .cout-annuel .sub{margin:4px 0 0;font-size:10.5px;color:var(--grey);line-height:1.35;}

  .hidden{display:none !important;}
  /* Carte « Qualité des données » : résumé en ligne (chiffre + barre), panneau de détail en blocs
     (définitions, puis répartition par raison). flex-wrap : sur mobile le chiffre et la barre
     passent à la ligne au lieu de se comprimer. */
  .qualite-ligne{display:flex;gap:24px;align-items:center;flex-wrap:wrap;}
  .qualite-ligne .bar-track{min-width:160px;}
  .qualite-bloc{margin-top:10px;}
  .qualite-bloc:first-child{margin-top:0;}
  .qualite-bloc ul{margin:6px 0 0;padding-left:18px;}
  .qualite-bloc li{margin-top:4px;}
  .note{font-size:11.5px;color:var(--grey);line-height:1.65;margin-top:14px;border-top:1px solid var(--line);
    padding-top:12px;}

  /* ---------- Bouton historique SER (Standards SER) ---------- */
  .btn-historique-ser{background:none;border:none;cursor:pointer;font-size:12px;padding:0 0 0 2px;
    opacity:.55;transition:opacity .15s ease;vertical-align:middle;}
  .btn-historique-ser:hover{opacity:1;}

  /* ---------- Modal générique (historique SER) ---------- */
  .modal-overlay{position:fixed;inset:0;background:rgba(28,40,51,.45);z-index:100;
    display:flex;align-items:center;justify-content:center;padding:24px;}
  .modal-box{background:var(--card);border-radius:10px;max-width:640px;width:100%;max-height:85vh;
    overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,.25);}
  .modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;
    border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--card);border-radius:10px 10px 0 0;}
  .modal-head h3{margin:0;font-size:15px;}
  .modal-close{background:none;border:none;font-size:15px;color:var(--grey);cursor:pointer;
    padding:4px 6px;border-radius:5px;transition:background .15s ease,color .15s ease;}
  .modal-close:hover{background:#F0F2F3;color:var(--text);}
  .modal-box .body{padding:16px 20px 20px;}
  .toggle-link{font-size:12px;color:var(--navy);text-decoration:underline;cursor:pointer;background:none;
    border:none;padding:0;transition:color .15s ease;}
  .toggle-link:hover{color:var(--amber);}
  /* Repli "pas de Télématique A, Télématique B disponible" — même teinte violette Télématique B
     que .badge.source_b/.badge.source_b-multi pour l'association visuelle, en lien souligné plutôt
     qu'en badge : la cellule est déjà cliquable (tr/td[data-id] existants) */
  .lien-source_b-fiche{color:#7D3C98;text-decoration:underline;font-size:12.5px;white-space:nowrap;}
  .print-header{display:none;}
  .date-range{display:flex;gap:8px;align-items:center;}

  /* ---------- Sidebar mobile ---------- */
  @media (max-width:768px){
    /* Hamburger FLOTTANT : il vivait dans .topbar, qui défile avec la page — une fois défilé, il
       fallait remonter tout en haut pour rouvrir le menu. */
    .btn-hamburger, .btn-hamburger:hover, .btn-hamburger:active{
      background:var(--amber);color:#1C1300;}
    .btn-hamburger{display:inline-flex;position:fixed;top:6px;left:8px;width:44px;height:44px;
      z-index:120;border-radius:10px;font-size:22px;box-shadow:0 2px 8px rgba(0,0,0,.4);}
    .btn-hamburger:active{transform:scale(.95);}
    .global-filterbar{padding-left:62px;}
    .sidebar{
      position:fixed;top:0;left:0;height:100vh;height:100dvh;overflow-y:auto;z-index:200;
      transform:translateX(-100%);transition:transform .2s ease;
    }
    .app.sidebar-open .sidebar{transform:translateX(0);}
    /* Fond semi-transparent cliquable pour refermer au clic en dehors — position:fixed comme la
       sidebar, donc hors du flux flex de .app lui aussi (n'affecte pas la largeur de .main). */
    .sidebar-backdrop{
      display:block;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:150;
      opacity:0;pointer-events:none;transition:opacity .2s ease;
    }
    .app.sidebar-open .sidebar-backdrop{opacity:1;pointer-events:auto;}

    /* .date-range (Du/input date/au/input date/Appliquer, tous les sélecteurs de période "Plage
       personnalisée" — Vue d'ensemble, Fiche camion, Comparateur, registre manuel vs Télématique
       A, Courbes complètes, Alertes. */
    .date-range{flex-wrap:wrap;}

    /* grid-3-eq (Comparateur : Eco Score/Conso/Conso-Horaire comparés) */
    .grid-3-eq{grid-template-columns:1fr;}

    /* .grid-2 (Vue d'ensemble : Classement des camions / Répartition par site) */
    .grid-2{grid-template-columns:minmax(0,1fr);}

    /* .kpi-strip / .souscores-grid (5 colonnes) : testé 1 colonne vs 2 à 311px/269px de large — 1
       colonne rend chaque carte inutilement longue à faire défiler pour seulement 2-3 mots de libellé
       et une valeur courte ; 2 colonnes reste lisible (~145px/~128px par carte) et réduit la hauteur
       totale de moitié. minmax(0,1fr) (pas juste 1fr) pour que les 2 pistes se partagent réellement la
       largeur du conteneur au lieu de déborder comme avant. kpi-strip-6 (Fiche camion, tableau KPI
       principal) n'est pas concerné : il utilise déjà repeat(auto-fill,minmax(150px,1fr)), qui
       s'empile déjà correctement en 1 colonne à cette largeur. */
    .kpi-strip{grid-template-columns:repeat(2,minmax(0,1fr));}
    .souscores-grid{grid-template-columns:repeat(2,minmax(0,1fr));}

    /* topbar-info (météo + date/heure) : simplifié plutôt que masqué en silence. Vent/ressenti/
       description restent dans le title (tooltip) — inchangé, déjà invisible à l'écran sur desktop
       comme sur mobile. Ce qui était VISIBLE et forçait le débordement (596,7px mesurés pour un écran
       de 375px), c'est le libellé du site ("SITE A"/"SITE B" en toutes lettres) et le badge texte
       ("Chaleur"/"Canicule") à côté de chaque température — retirés ici, l'icône + la température
       suffisent à identifier chaque site. flex-wrap en filet de sécurité si un texte long (ex: nom de
       camion très long ailleurs) fait quand même déborder après simplification — un retour à la ligne
       reste toujours préférable à un débordement horizontal silencieux de toute la page. */
    .topbar-info{flex-wrap:wrap;}
    .topbar-meteo .site-label{display:none;}
    .topbar-meteo .temp-badge{display:none;}

    /* Tableaux larges (Classement des camions, Détail mensuel, Comparaison chiffrée) : overflow-x
       posé sur un conteneur dédié autour du <table>, pas sur toute la .card, pour que le tableau seul
       scrolle horizontalement sans faire déborder la page ni le reste de la carte (titre, boutons). */
    /* Mobile : la barre de filtres collée en haut occupe ~45% de l'écran (--gfb-h, mesurée en JS) — le cadre
       du tableau est borné à ce qui reste visible dessous (plancher 240px) pour qu'il tienne entièrement à
       l'écran une fois la page défilée jusqu'à lui, au lieu de dépasser d'un demi-écran. */
    .table-scroll{overflow:auto;-webkit-overflow-scrolling:touch;max-height:max(240px,calc(100dvh - var(--gfb-h,0px) - 24px));}
  }

  /* ---------- Impression / Export PDF ---------- */
  @media print{
    body{background:#fff;}
    /* En-têtes fixes / hauteur bornée : écran seulement — un tableau imprimé doit pouvoir se répartir sur
       plusieurs pages (thead se répète alors de lui-même), pas être tronqué à 72vh. */
    .table-scroll{max-height:none !important;}
    .table-scroll thead th{position:static !important;}
    .global-filterbar, .sidebar, .btn-hamburger, .topbar, .filters, .btn, .chip, #toggleQualite, .modal-overlay{display:none !important;}
    .app{display:block;}
    .main-inner{padding:0;max-width:180mm;}
    /* Courbes complètes et registre manuel vs Télématique A peuvent couvrir 25-31 mois d'historique (SER
       officielle en L/h, registre manuel le plus à jour des deux sources) : la largeur imprimable portrait
       (180mm) resterait juste pour un historique aussi long. Bascule en paysage (@page nommée
       "paysage" ci-dessous, appliquée uniquement à ces deux onglets via leur id précis quand actifs à
       l'impression) pour ~270mm de largeur imprimable, sans toucher les autres onglets qui restent en
       portrait. :has() (Chrome 105+, largement disponible) permet à .main-inner d'élargir sa propre
       max-width seulement quand l'un de ces deux onglets est l'onglet actif imprimé. page:paysage
       est posé sur .main-inner (pas directement sur #tab-courbes) pour que .print-header, qui est un
       frère PRÉCÉDENT #tab-courbes dans le DOM (donc resterait sur la page portrait par défaut sinon),
       partage le même contexte de page — sans ça, un changement de page context entre .print-header
       et #tab-courbes insère une page 1 portrait quasi vide avant la vraie page 2 en paysage. */
    .main-inner:has(#tab-courbes.print-active),
    .main-inner:has(#tab-registre-source_a.print-active){max-width:270mm;page:paysage;}
    [id^="tab-"]:not(.print-active){display:none !important;}
    .card{border:1px solid #ccc;box-shadow:none;break-inside:auto;}
    /* .card-graphique : cartes ne contenant qu'un ou plusieurs canvas Chart.js, jamais de grand
       tableau. */
    .card-graphique{page-break-inside:avoid;break-inside:avoid;}
    .kpi{page-break-inside:avoid;}
    /* .grid-2-eq (Standards SER : 2 graphiques L/h vs L/100km) déborde de la largeur imprimable en
       grille figée à 2 colonnes — empilé en une seule colonne à l'impression pour que chaque carte
       ait la pleine largeur de page. .grid-3-eq (Comparateur) n'est volontairement PAS touché. */
    .grid-2-eq{grid-template-columns:1fr !important;}
    .print-header{display:block;margin-bottom:16px;border-bottom:2px solid var(--navy);padding-bottom:10px;}
    .print-header b{font-size:18px;}
    .print-header span{display:block;font-size:11px;color:#555;}
    canvas{max-height:260px !important;}
    /* Page nommée utilisée par #tab-courbes/#tab-registre-source_a ci-dessus (propriété page:paysage)
       — les autres onglets restent sur la page par défaut (portrait, taille navigateur/OS). */
    @page paysage{size:A4 landscape;}
  }
</style>
</head>
<body>

<div class="global-filterbar">
  <span class="gfb-title">🔎 Filtres globaux</span>
  <div class="gfb-group">
    <label>Site</label>
    <div class="ms" id="msSite">
      <button type="button" class="ms-btn" id="msSiteBtn">Tous les sites</button>
      <div class="ms-panel hidden" id="msSitePanel">
        <label><input type="checkbox" value="SITE A" checked> SITE A</label>
        <label><input type="checkbox" value="SITE B" checked> SITE B</label>
        <div class="ms-actions">
          <button type="button" class="ms-tout">Tout cocher</button>
          <button type="button" class="ms-aucun">Tout décocher</button>
        </div>
      </div>
    </div>
  </div>
  <div class="gfb-group">
    <label>Type / Usage norme interne</label>
    <div class="ms" id="msUsage">
      <button type="button" class="ms-btn" id="msUsageBtn">Tous les usages</button>
      <div class="ms-panel hidden" id="msUsagePanel"></div>
    </div>
  </div>
  <div class="gfb-group">
    <label>Matricule</label>
    <div class="ms" id="msMatricule">
      <button type="button" class="ms-btn" id="msMatriculeBtn">Tous les camions</button>
      <div class="ms-panel hidden" id="msMatriculePanel"></div>
    </div>
  </div>
  <div class="gfb-group">
    <label>Marque</label>
    <div class="ms" id="msMarque">
      <button type="button" class="ms-btn" id="msMarqueBtn">Toutes les marques</button>
      <div class="ms-panel hidden" id="msMarquePanel"></div>
    </div>
  </div>
  <div class="gfb-group">
    <label>Périmètre <span class="info-i" data-tip="Tous les véhicules du parc (tous dans le périmètre de l'audit ISO 50001) sont affichés à égalité par défaut — chaque ligne porte un badge indiquant sa source de données. Décochez « Source Télématique B » pour n'afficher que les véhicules suivis via Télématique A.">i</span></label>
    <div class="ms" id="msPerimetre">
      <button type="button" class="ms-btn" id="msPerimetreBtn">Tous les véhicules</button>
      <div class="ms-panel hidden" id="msPerimetrePanel">
        <label><input type="checkbox" value="SOURCE_A" checked> Source Télématique A</label>
        <label><input type="checkbox" value="SOURCE_B" checked> Source Télématique B</label>
        <div class="ms-actions">
          <button type="button" class="ms-tout">Tout cocher</button>
          <button type="button" class="ms-aucun">Tout décocher</button>
        </div>
      </div>
    </div>
  </div>
  <button class="btn outline" id="btnResetFiltresGlobaux">✕ Réinitialiser</button>
  <!-- Id/nom de fonction historiques (noteFlotteElargie) conservés. -->
  <div class="global-filterbar-note" id="noteFlotteElargie" style="display:block;flex-basis:100%;padding:5px 0 0;font-size:11.5px;color:#D9A9EE;">
    Cette vue combine des véhicules suivis via <b>Télématique A</b> et via <b>Télématique B</b> — le badge sur chaque ligne indique la source de données. Pour n'afficher que les véhicules suivis via Télématique A, décochez « Source Télématique B » dans le filtre Périmètre.
  </div>
</div>

<div class="app">

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="sidebar" id="sidebar-nav">
    <div class="brand"><b>FEA<span class="rail-cache"> · ÉNERGIE</span></b><span class="rail-cache">SUIVI ISO 50001 — MULTI-SOURCES</span></div>
    {{-- Bouton rétracter/étendre : volontairement PAS de classe .nav-btn (tous les .nav-btn sont
       câblés à la navigation d'onglets via data-tab, cf. "Navigation onglets" plus bas) --}}
    <button type="button" class="sidebar-toggle" id="btnSidebarToggle" aria-controls="sidebar-nav"><span class="nav-ico" id="sidebarToggleIco">«</span><span class="nav-label" id="sidebarToggleLabel">Réduire le menu</span></button>
    <button class="nav-btn active" data-tab="apercu"><span class="nav-ico">📊</span><span class="nav-label">Accueil</span></button>
    <button class="nav-btn" data-tab="alertes"><span class="nav-ico">⚠️</span><span class="nav-label">Alertes &amp; suggestions</span></button>
    <div class="nav-sep"></div>
    <button class="nav-btn" data-tab="ser-standards"><span class="nav-ico">📐</span><span class="nav-label">Norme de référence</span></button>
    <button class="nav-btn" data-tab="registre-source_a"><span class="nav-ico">🔀</span><span class="nav-label">Comparaison des sources</span></button>
    <button class="nav-btn" data-tab="courbes"><span class="nav-ico">📈</span><span class="nav-label">Courbes complètes</span></button>
    <button class="nav-btn" data-tab="gps"><span class="nav-ico">🗺️</span><span class="nav-label">Suivi GPS</span></button>
    <div class="nav-sep"></div>
    <a href="/direction" class="nav-link"><span class="nav-ico">🧭</span><span class="nav-label">Vue Direction</span></a>
    {{-- Lien Admin uniquement — la vraie protection est côté serveur (middleware role.admin sur
       /utilisateurs) : ce masquage n'est qu'un confort d'affichage, un Consultant qui force l'URL
       reçoit un 403, jamais un accès. --}}
    @if (auth()->user()?->estAdmin())
      <a href="/utilisateurs" class="nav-link"><span class="nav-ico">👤</span><span class="nav-label">Utilisateurs</span></a>
    @endif
    <form method="POST" action="/deconnexion" class="nav-form">@csrf<button type="submit" class="nav-link"><span class="nav-ico">🔒</span><span class="nav-label">Se déconnecter</span></button></form>
    <div class="sidebar-footer"><span class="rail-cache">Connecté : {{ auth()->user()?->identifiant }} ({{ auth()->user()?->estAdmin() ? 'Admin' : 'Consultant' }}) · </span>v1.0</div>
  </div>
  {{-- État initial appliqué dès le parsing (avant le premier rendu de .main) pour éviter un flash
       "sidebar complète qui se réduit en animation" à 900px. Même règle que le bloc JS "Sidebar
       rétractable + adaptative" plus bas (à garder synchronisées) : état forcé en localStorage, sinon
       complète >=1200px / rail en dessous. --}}
  <script>(function(){try{var f=localStorage.getItem('fea_sidebar_force');var e=(f==='complete'||f==='rail')?f:(matchMedia('(min-width:1200px)').matches?'complete':'rail');if(e==='rail')document.querySelector('.app').classList.add('sidebar-rail');}catch(x){}})();</script>

  <div class="main">

    <div class="topbar">
      <div class="topbar-info">
        <button class="btn-hamburger" id="btnHamburger" aria-label="Ouvrir le menu">☰</button>
        <span class="topbar-dot"></span> <span id="lastUpdateLabel">Chargement…</span>
        <span class="topbar-sep">·</span>
        <span id="topbarMeteo" class="topbar-meteo"><span class="sub">Météo…</span></span>
        <span class="topbar-sep">·</span>
        <span id="topbarDateHeure" class="topbar-datetime"></span>
        <span id="badgeSanteAutomatisations" class="badge mid hidden" style="cursor:help;" title=""></span>
      </div>
      <button class="btn-print-top" id="btnPrint">🖨️ Imprimer / Export PDF</button>
    </div>

    <div class="main-inner">

      <div class="print-header">
        <b>Fleet Energy Analytics · Suivi énergétique flotte — ISO 50001</b>
        <span id="printDate"></span>
      </div>

      <!-- ============ ACCUEIL (onglet data-tab="apercu") ============ -->
      <div id="tab-apercu">
        <h1>Accueil</h1>
        <p class="sub" id="sousTitreFlotte">Chargement de l'effectif de la flotte…</p>

        <div class="filters">
          <input type="text" id="rechercheCamion" placeholder="🔍 Rechercher une immatriculation..." style="min-width:320px;">
          <label>Statut norme interne</label>
          <select id="filtreStatutNormeRef">
            <option value="TOUS">Tous</option>
            <option value="VERT_FONCE">Vert foncé</option>
            <option value="VERT_CLAIR">Vert clair</option>
            <option value="ORANGE">Orange</option>
            <option value="ROUGE">Rouge</option>
            <option value="DONNEES_INSUFFISANTES">Donnée manquante</option>
          </select>
          <label for="filtreNbAlertes">Alertes actives</label>
          <select id="filtreNbAlertes" title="Nombre d'alertes actives du véhicule (colonne « Alertes actives »)">
            <option value="TOUS">Tous</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2+">2 et plus</option>
          </select>
          <label for="filtreTypeAlerte">Type d'alerte</label>
          <select id="filtreTypeAlerte" title="Véhicules ayant au moins une alerte active de ce type">
            <option value="TOUS">Tous les types</option>
          </select>
          <span id="noteFiltresAlertes" class="hidden" style="font-size:11.5px;color:var(--amber);flex-basis:100%;"></span>
          <label>Tri</label>
          <select id="filtreTri">
            <option value="eco_desc">Eco Score (meilleur d'abord)</option>
            <option value="eco_asc">Eco Score (à surveiller d'abord)</option>
            <option value="conso_asc">Consommation (croissant)</option>
            <option value="conso_desc">Consommation (décroissant)</option>
          </select>
          <button class="btn outline" id="btnExport">⬇ Exporter CSV (vue filtrée)</button>
          <button class="btn outline" id="btnResetFiltres">✕ Réinitialiser filtres</button>
          <button class="btn outline" id="btnRefresh">↻ Actualiser</button>
        </div>

        <div class="filters">
          <label>Période <span class="info-i" data-tip="Période unique de l'écran : elle s'applique aux KPI, au tableau, à l'évolution d'un camion et à la comparaison. Le statut norme interne, la SER, les alertes et la consommation Télématique B sont indépendants de la période.">i</span></label>
          <select id="filtrePeriodeApercu">
            <option value="3">3 derniers mois</option>
            <option value="6">6 derniers mois</option>
            <option value="12">12 derniers mois</option>
            <option value="999" selected>Tout l'historique</option>
            <option value="custom">Plage personnalisée...</option>
          </select>
          <span id="dateRangeApercu" class="date-range hidden">
            <label>Du</label><input type="date" id="dateDebutApercu">
            <label>au</label><input type="date" id="dateFinApercu">
            <button class="btn outline" id="btnAppliquerDatesApercu">Appliquer</button>
          </span>
        </div>

        <div class="kpi-strip kpi-strip-6" id="kpiApercu"></div>

        <div class="card">
          <h3>Camions <span id="nbCamionsAffiches" style="font-weight:400;color:var(--grey);font-size:11.5px;"></span>
            <span style="font-weight:400;color:var(--grey);font-size:11.5px;">— coche un camion pour son détail, deux ou plus pour les comparer</span></h3>
          <div class="table-scroll">
          <table>
            <thead><tr>
              <th style="width:28px;"></th>
              <th data-sort="immatriculation">Camion</th>
              <th data-sort="groupe">Site</th>
              <th data-sort="eco_score_moyen">Eco Score</th>
              <th data-sort="conso_moyenne">Conso (L/100km)</th>
              <th>Eco</th>
              <th title="Atteint = statut norme interne Vert foncé (IPE ≤ cible officielle du véhicule). Dérivé du même calcul que Statut norme interne, jamais de la consommation Télématique A — les deux ne peuvent donc plus se contredire.">Objectif IPÉ</th>
              <th>Statut norme interne</th>
              <th title="Alertes non résolues détectées pour ce camion (indépendant de la période)">Alertes actives</th>
            </tr></thead>
            <tbody id="tableCamions"></tbody>
          </table>
          </div>
        </div>

        <div id="zoneDetailFlotte">
          <!-- 0 camion coché : vue flotte -->
          <div id="detailVueFlotte">
            <div class="card">
              <h3>Répartition par site</h3>
              <div id="resumeGroupes"></div>
            </div>
            <div class="card card-graphique">
              <h3>Comparaison par site — Eco Score mensuel (SITE A vs SITE B)</h3>
              <canvas id="chartSitesEco" height="90"></canvas>
            </div>
            <div class="card card-graphique">
              <h3>Comparaison par site — Consommation mensuelle (SITE A vs SITE B)</h3>
              <canvas id="chartSitesConso" height="90"></canvas>
            </div>
          </div>
          <!-- 1 camion ou plus coché -->
          <div id="detailSelection" class="hidden">
            <div class="card">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                  <h3 id="detailSelectionTitre" style="margin:0 0 4px;"></h3>
                  <p class="sub" id="detailSelectionListe" style="margin:0;"></p>
                </div>
                <div class="filters" style="margin:0;">
                  <button class="btn outline" id="btnDetailEffacer">✕ Tout désélectionner</button>
                </div>
              </div>
            </div>

            <!-- 1 camion : deux sous-sections -->
            <div id="detailUnCamion" class="hidden">
              <div class="chips" id="sousOngletsDetail" style="margin:14px 0;">
                <button class="chip active" data-sous="evolution">📈 Évolution <span style="font-weight:400;opacity:.8;">(période choisie)</span></button>
                <button class="chip" data-sous="etat">📌 État actuel <span style="font-weight:400;opacity:.8;">(hors période)</span></button>
              </div>

              <div id="sousOngletEvolution">

        <p class="sub">Période : celle du sélecteur en haut de l'écran.</p>

        <div style="display:flex;gap:20px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">
          <div class="field" style="max-width:220px;margin-bottom:0;">
            <label>Prix gasoil (TND/L)</label>
            <input type="number" id="ficheCamionPrix" step="0.001" value="1.985">
          </div>
          <div class="cout-annuel" id="ficheCoutAnnuel"></div>
        </div>

        <div class="kpi-strip kpi-strip-6" id="kpiCamion"></div>

        <div class="card card-graphique">
          <h3>Évolution — Eco Score &amp; consommation</h3>
          <div id="ficheCamionMetriquesSupp" style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:12px;font-size:13px;"></div>
          <p id="ficheCamionLimiteMsg" class="sub hidden" style="color:var(--red);">Maximum 2 métriques supplémentaires simultanées (4 axes au total pour rester lisible).</p>
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;margin-bottom:8px;">
            <input type="checkbox" id="chkAfficherProjection">
            Afficher la projection (3 prochains mois)
          </label>
          <p id="projectionInfo" class="sub hidden" style="margin-bottom:8px;"></p>
          <p id="badgeMoisPartielCamion" class="sub hidden" style="margin-bottom:8px;color:var(--amber);"></p>
          <canvas id="chartCamion" height="90"></canvas>
        </div>

        <div class="card">
          <h3>Détail mensuel <button class="btn outline" id="btnExportFicheCamionCsv" style="font-size:12px;padding:6px 12px;">⬇ Exporter CSV</button></h3>
          <div class="table-scroll">
          <table>
            <thead><tr><th>Mois</th><th>Conso (L/100)</th><th>L/Heure</th><th>Km parcourus</th><th>Heures moteur</th><th>Eco Score</th></tr></thead>
            <tbody id="tableMesuresCamion"></tbody>
          </table>
          </div>
        </div>

        <div class="card card-graphique">
          <h3>Sous-scores éco-conduite <span style="font-weight:400;font-size:11.5px;color:var(--grey);">Moyenne sur la période</span></h3>
          <p id="alerteSurregime" class="hidden" style="background:var(--red-soft);color:var(--red);border-radius:6px;padding:8px 12px;font-size:12.5px;font-weight:600;margin-bottom:12px;">⚠ Sur-régime moteur détecté ce mois-ci</p>
          <div id="souscores" class="souscores-grid"></div>
          <canvas id="chartSousScores" height="80"></canvas>
          <p class="note" style="border-top:none;padding-top:10px;margin-top:10px;">
            Anticipation = freinage/arrêt + roue libre. Utilisation abusive = mode assistance puissance + surrégime.
            Utilisation optimale = Ishift zone A + zone économique. Arrêt = ralenti moteur.
          </p>
        </div>
              </div>

              <div id="sousOngletEtat" class="hidden">
                <p class="sub" style="margin-bottom:12px;">Cette section est <b>indépendante de la période</b> : statut norme interne du dernier mois exploité, SER en vigueur, alertes et suggestions actives, consommation Télématique B sur son historique complet.</p>

          <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;">
              <div>
                <h2 id="syntheseImmat" style="margin:0 0 4px;font-size:22px;"></h2>
                <div id="syntheseSousTitre" style="color:var(--grey);font-size:13px;"></div>
              </div>
              <div id="syntheseStatutBadge"></div>
            </div>
            <p id="syntheseBadgePartiel" class="sub hidden" style="color:var(--amber);margin-top:6px;"></p>
            <p id="conducteurSourceBSynthese" class="sub hidden" style="margin-top:6px;"></p>
          </div>

          <div class="grid-2-eq">
            <div class="card">
              <h3>SER norme interne actuelle</h3>
              <div id="syntheseSer"></div>
            </div>
            <div class="card">
              <h3>Dernière comparaison Registre vs Télématique A</h3>
              <div id="syntheseComparateur"></div>
            </div>
          </div>

          <div class="card">
            <h3>Alertes actives</h3>
            <div id="syntheseAlertesActives"></div>
            <details class="note"><summary><b>Historique (résolues)</b></summary>
              <div id="syntheseAlertesResolues"></div>
            </details>
          </div>

          <div class="card">
            <h3>Suggestions actives</h3>
            <div id="syntheseSuggestionsActives"></div>
            <details class="note"><summary><b>Historique (résolues)</b></summary>
              <div id="syntheseSuggestionsResolues"></div>
            </details>
          </div>

          <div class="card">
            <h3>Analyses correctives documentées</h3>
            <div id="syntheseAnalyses"></div>
          </div>

          <div class="card" id="cardConsommationSourceB" style="display:none;">
            <h3>Consommation Télématique B <span class="badge source_b">source Télématique B</span></h3>
            <div id="syntheseConsommationSourceB"></div>
          </div>

              </div>
            </div>

            <!-- 2 camions ou plus : comparaison -->
            <div id="detailComparaison" class="hidden">
              <p class="sub" style="margin:14px 0 8px;">Comparaison des camions cochés — période : celle du sélecteur en haut de l'écran. Clique sur un camion (ligne du tableau) pour ouvrir son détail.</p>
        <div class="card">
          <h3>Comparaison chiffrée <button class="btn outline" id="btnExportComparateur" style="font-size:12px;padding:6px 12px;">⬇ Exporter CSV (comparateur)</button></h3>
          <div class="table-scroll">
          <table class="tbl-comparateur">
            <thead><tr id="theadComparateur"></tr></thead>
            <tbody id="tableComparateur"></tbody>
          </table>
          </div>
        </div>

        <div class="grid-3-eq">
          <div class="card card-graphique">
            <h3>Eco Score comparé</h3>
            <canvas id="chartCompareEco" height="180"></canvas>
          </div>
          <div class="card card-graphique">
            <h3>Consommation comparée (L/100km)</h3>
            <canvas id="chartCompareConso" height="180"></canvas>
          </div>
          <div class="card card-graphique">
            <h3>Consommation/Heure comparée (L/h)</h3>
            <canvas id="chartCompareConsoHoraire" height="180"></canvas>
          </div>
        </div>

        <div class="card card-graphique">
          <h3>Tendance mensuelle comparée (Eco Score)</h3>
          <canvas id="chartCompareTrend" height="90"></canvas>
        </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ ALERTES & QUALITÉ ============ -->
      <div id="tab-alertes" class="hidden">
        <h1>Alertes &amp; suggestions</h1>
        <p class="sub">Un seul flux trié par urgence : les <b>alertes</b> (problèmes détectés automatiquement, seuils ISO 50001) et les <b>suggestions</b> (recommandations issues de règles explicites — aucun apprentissage automatique, chacune reste traçable aux valeurs chiffrées qui l'ont déclenchée).</p>

        <div class="filters">
          <label>Période (date de détection)</label>
          <select id="filtrePeriodeAlertes">
            <option value="3">3 derniers mois</option>
            <option value="6">6 derniers mois</option>
            <option value="12">12 derniers mois</option>
            <option value="999" selected>Tout l'historique</option>
            <option value="custom">Plage personnalisée...</option>
          </select>
          <span id="dateRangeAlertes" class="date-range hidden">
            <label>Du</label><input type="date" id="dateDebutAlertes">
            <label>au</label><input type="date" id="dateFinAlertes">
            <button class="btn outline" id="btnAppliquerDatesAlertes">Appliquer</button>
          </span>
          <label>Alertes résolues</label>
          <select id="filtreStatutAlerte" style="font-size:12.5px;padding:8px 10px;border:1px solid var(--line);border-radius:6px;background:#fff;">
            <option value="active">Masquées</option>
            <option value="toutes">Incluses (historique)</option>
          </select>
        </div>
        <p class="sub" style="margin-top:-10px;">Seules les suggestions encore actives (non résolues) existent dans l'API : la période restreint leur date de détection, elle ne fait pas réapparaître de suggestion résolue.</p>

        <div class="chips" id="fluxFiltreType" role="group" aria-label="Type d'élément">
          <button class="chip active" data-type="tout">Tout <span class="flux-compte"></span></button>
          <button class="chip" data-type="alerte">⚠ Alertes <span class="flux-compte"></span></button>
          <button class="chip" data-type="suggestion">💡 Suggestions <span class="flux-compte"></span></button>
        </div>
        <div id="fluxAvertissement" class="note hidden" style="margin-bottom:10px;"></div>

        <div class="card">
          <h3>Flux par urgence <span id="fluxResumeNiveaux" style="font-weight:400;color:var(--grey);font-size:11.5px;"></span></h3>
          <div class="flux-legende">
            <span><span class="flux-type alerte niveau-orange" style="--niv:var(--amber);">Alerte</span>problème détecté (puce pleine, bordure continue ⚠)</span>
            <span><span class="flux-type suggestion" style="--niv:var(--amber);">Suggestion</span>recommandation (puce en contour, bordure pointillée 💡)</span>
          </div>
          <div id="fluxAlertesSuggestions"></div>
        </div>

        <div class="card">
          <h3>Qualité des données
            <button class="toggle-link" id="toggleQualite">voir le détail</button>
          </h3>
          <div id="qualiteResume"></div>
          <div id="qualiteDetail" class="hidden note"></div>
        </div>
      </div>

      <!-- ============ NORME DE RÉFÉRENCE (onglet data-tab="ser-standards") ============ -->
      <div id="tab-ser-standards" class="hidden">
        <h1>Norme interne de référence</h1>
        <p class="sub">SER officielle par véhicule, norme interne de référence — importée du registre</p>

        <div class="info-box">
          Lecture seule : Usage, SER, Incertitude et Cible sont importés automatiquement du registre (synchronisation hebdomadaire) — pour corriger une valeur, corriger le registre. SER×1,x% est calculé (SER × (1 + incertitude)).
        </div>

        <div class="filters">
          <input type="text" id="rechercheSerStandards" placeholder="🔍 Rechercher une immatriculation..." style="min-width:min(320px,100%);">
          <label>Statut norme interne</label>
          <select id="filtreStatutSerStandards">
            <option value="TOUS">Tous</option>
            <option value="VERT_FONCE">Vert foncé</option>
            <option value="VERT_CLAIR">Vert clair</option>
            <option value="ORANGE">Orange</option>
            <option value="ROUGE">Rouge</option>
            <option value="DONNEES_INSUFFISANTES">Donnée manquante</option>
          </select>
          <button class="btn outline" id="btnResetTableSerStandards">✕ Réinitialiser filtres et tri</button>
        </div>

        <div class="filters">
          <label>Période (colonnes « réel »)</label>
          <select id="filtrePeriodeSerStandards">
            <option value="3">3 derniers mois</option>
            <option value="6">6 derniers mois</option>
            <option value="12">12 derniers mois</option>
            <option value="999" selected>Tout l'historique</option>
            <option value="custom">Plage personnalisée...</option>
          </select>
          <span id="dateRangeSerStandards" class="date-range hidden">
            <label>Du mois</label><input type="month" id="dateDebutSerStandards">
            <label>au mois</label><input type="month" id="dateFinSerStandards">
            <button class="btn outline" id="btnAppliquerDatesSerStandards">Appliquer</button>
          </span>
        </div>
        <p class="sub" id="noteSerStandardsPeriode" style="margin-top:-8px;"></p>

        <div class="card">
          <h3>Tableau des standards</h3>
          <div class="table-scroll">
          <table class="tbl-ser-std">
            <thead>
              <tr>
                <th rowspan="2" data-sort-ser="immatriculation">Camion</th><th rowspan="2" data-sort-ser="groupe">Site</th>
                <th class="grp grp-ref" colspan="5">Référence norme interne — en vigueur</th>
                <th class="grp grp-statut" colspan="4">Dernier mois exploité</th>
                <th class="grp grp-reel" colspan="4">Réel — période choisie (Registre)</th>
              </tr>
              <tr>
                <th data-sort-ser="usage_norme_ref">Usage norme interne</th><th data-sort-ser="ser_norme_ref">SER <span class="info-t" title="Unité selon l'usage du véhicule : L/100km pour les usages Transport (Transport — catégorie 2, Transport — catégorie 1), L/h pour les autres usages. L'unité est rappelée sous chaque valeur de référence (SER, SER×1,x%, Cible).">i</span></th><th data-sort-ser="incertitude_pct">Incertitude (%)</th><th data-sort-ser="ser_1x">SER×1,x%</th><th data-sort-ser="cible">Cible</th>
                <th data-sort-ser="statut">Statut norme interne <span class="info-t" title="Code couleur officiel norme interne, calculé sur le dernier mois Registre exploitable (pas sur la période choisie) : rouge si IPÉ &gt; SER×1,x% · orange si entre SER et SER×1,x% · vert clair si entre Cible et SER · vert foncé si IPÉ &lt; Cible. « Donnée manquante » : survolez le statut pour connaître la raison.">i</span></th>
                <th data-sort-ser="pct_ser_usage">% SER / usage <span class="info-t" title="Part de la consommation de ce véhicule dans le total de son groupe d'usage, ce même mois — importée telle quelle depuis le registre (colonne « % SER / usage » de « Copy of Récap data studio »), jamais recalculée. Même mois que Statut norme interne, indépendant de la période choisie ci-dessous.">i</span></th>
                <th data-sort-ser="pe_lh_precis">P,E (l/h) précis <span class="info-t" title="Valeur non arrondie de P,E (l/h) calculée par le registre lui-même (colonne « All PE » de « Copy of Récap data studio ») — plus précise que l'IPÉ réel ci-dessous, qui est une somme sur la période choisie, pas ce seul mois. Même mois que Statut norme interne, indépendant de la période choisie ci-dessous.">i</span></th>
                <th data-sort-ser="litres_dernier_mois">Litres consommés <span class="info-t" title="Litres saisis dans le registre pour ce seul dernier mois exploitable — pas une somme sur la période choisie ci-dessous (cohérent avec Statut norme interne, % SER/usage et P,E précis, tous rattachés à ce même mois).">i</span></th>
                <th data-sort-ser="ipe">IPÉ réel <span class="info-t" title="Indicateur de Performance Énergétique, dans l'unité de la SER du véhicule : litres ÷ km × 100 (L/100km) pour les usages Transport, litres ÷ heures de fonctionnement (L/h) pour les autres usages — sur les mois de la période où les deux données sont saisies (somme ÷ somme, pas une moyenne de moyennes). Jugé atteint si l'IPÉ est inférieur à la Cible norme interne (SER officielle − 2 %). « x/y mois » = mois utilisés sur les mois attendus de la période.">i</span></th>
                <th data-sort-ser="km">Km parcourus</th><th data-sort-ser="heures">Heures</th>
                <th data-sort-ser="conso_l100">Conso. (L/100km) <span class="info-t" title="Litres ÷ km × 100, sur les mois de la période où les deux sont saisis. C'est l'IPÉ lui-même pour les usages Transport (SER en L/100km) ; information complémentaire, non comparable à la SER, pour les usages en L/h.">i</span></th>
              </tr>
            </thead>
            <tbody id="tableSerStandards"></tbody>
          </table>
          </div>
        </div>

        <div class="card">
          <h3>Camions suivis en L/100km</h3>
          <p class="sub" id="sousTitreChartSerL100"></p>
          <div class="chips" id="chipsSerGraphique" style="margin-bottom:8px;"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
            <button class="btn outline" id="btnSerGraphToutAfficher">Tout afficher</button>
            <button class="btn outline" id="btnSerGraphToutEffacer">Tout effacer</button>
          </div>
          <p id="chartSerL100Vide" class="sub" style="padding:18px 0;">Aucun véhicule sélectionné : choisissez des véhicules ci-dessus, ou « Tout afficher ».</p>
          <div id="chartSerL100Wrap" class="hidden" style="overflow-x:auto;">
            <div id="chartSerL100Zone" style="position:relative;height:420px;"><canvas id="chartSerL100"></canvas></div>
          </div>
        </div>
      </div>

      <!-- ============ COMPARAISON DES SOURCES ============ -->
      <div id="tab-registre-source_a" class="hidden">
        <h1>Comparaison des sources <span id="badgeNbSourcesComparateur" class="badge gris hidden"></span> <span id="badgeKmCorrompuComparateur" class="hidden"></span></h1>
        <p class="sub">Écart entre Télématique A, Registre et Télématique B (quand disponible), pour juger leur fiabilité relative</p>

        <input type="text" id="rechercheRegistre" placeholder="🔍 Filtrer les camions…" style="min-width:220px;margin-bottom:10px;background:#fff;border:1px solid var(--line);border-radius:6px;padding:8px 10px;font-size:13px;">
        <div class="chips" id="chipsRegistre"></div>

        <div class="filters">
          <label>Période</label>
          <select id="filtrePeriodeRegistre">
            <option value="3">3 derniers mois</option>
            <option value="6">6 derniers mois</option>
            <option value="12">12 derniers mois</option>
            <option value="999" selected>Tout l'historique</option>
            <option value="custom">Plage personnalisée...</option>
          </select>
          <span id="dateRangeRegistre" class="date-range hidden">
            <label>Du</label><input type="date" id="dateDebutRegistre">
            <label>au</label><input type="date" id="dateFinRegistre">
            <button class="btn outline" id="btnAppliquerDatesRegistre">Appliquer</button>
          </span>
        </div>

        <div class="filters">
          <button class="btn outline active" id="btnRegistreL100">L/100km</button>
          <button class="btn outline" id="btnRegistreLh">L/h</button>
        </div>

        <div class="card card-graphique">
          <h3>Consommation comparée</h3>
          <canvas id="chartComparateurRegistre" height="90"></canvas>
        </div>

        <div class="card">
          <h3>Détail mensuel</h3>
          <div class="table-scroll">
          <table>
            <thead><tr>
              <th>Mois</th>
              <th>Télématique A (L/100km)</th><th>Registre (L/100km)</th>
              <th id="thComparateurSourceBL100" class="hidden">Télématique B (L/100km)</th>
              <th>Écart % (Opti/Sheets)</th><th>Fiabilité</th>
              <th id="thComparateurSourceBEcart" class="hidden">Écart % (Télématique B/Sheets)</th>
              <th id="thComparateurSourceBFiab" class="hidden">Fiabilité (Télématique B/Sheets)</th>
              <th>Télématique A (L/h)</th><th>Registre (L/h)</th><th>Écart % (L/h)</th><th>Fiabilité (L/h)</th>
              <th>Remarque</th>
            </tr></thead>
            <tbody id="tableComparateurRegistre">
              <tr><td colspan="13" style="color:var(--grey);">Sélectionne un camion ci-dessus.</td></tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- ============ COURBES COMPLÈTES ============ -->
      <div id="tab-courbes" class="hidden">
        <h1>Courbes complètes</h1>
        <p class="sub">Toutes les métriques disponibles (Télématique A, Registre &amp; Télématique B), vue mensuelle ou annuelle. Sélectionne un ou plusieurs camions — 2+ camions verrouille automatiquement l'affichage à une seule métrique à la fois (une couleur par camion), comme dans la comparaison de l'écran Flotte.</p>

        <input type="text" id="rechercheCourbes" placeholder="🔍 Filtrer les camions…" style="min-width:220px;margin-bottom:10px;background:#fff;border:1px solid var(--line);border-radius:6px;padding:8px 10px;font-size:13px;">
        <div class="chips" id="chipsCourbes"></div>

        <div class="filters">
          <button class="btn outline active" id="btnCourbesMensuel">Mensuel</button>
          <button class="btn outline" id="btnCourbesAnnuel">Annuel</button>
        </div>

        <div class="filters">
          <label>Période</label>
          <select id="filtrePeriodeCourbes">
            <option value="3">3 derniers mois</option>
            <option value="6">6 derniers mois</option>
            <option value="12">12 derniers mois</option>
            <option value="999" selected>Tout l'historique</option>
            <option value="custom">Plage personnalisée...</option>
          </select>
          <span id="dateRangeCourbes" class="date-range hidden">
            <label>Du</label><input type="date" id="dateDebutCourbes">
            <label>au</label><input type="date" id="dateFinCourbes">
            <button class="btn outline" id="btnAppliquerDatesCourbes">Appliquer</button>
          </span>
        </div>
        <p class="sub" id="courbesAnnuelInfoPeriode" style="margin:-10px 0 14px;color:var(--grey);"></p>

        <div class="card">
          <h3>Métriques <span id="courbesMetriquesSousTitre" style="font-weight:400;font-size:11.5px;color:var(--grey);">jusqu'à 4 simultanées</span></h3>
          <p id="courbesLimiteMsg" class="sub hidden" style="color:var(--red);"></p>
          <div id="courbesMetriquesCheckboxes" style="display:flex;flex-wrap:wrap;gap:0 28px;"></div>
        </div>

        <div class="card card-graphique">
          <h3>Évolution <span class="info-i" data-tip="Les métriques de même unité partagent un axe pour une comparaison directe. Avec 2+ camions, une seule métrique est affichée à la fois — chaque camion a alors sa propre couleur.">i</span> <button class="btn outline" id="btnExportCourbesCsv" style="font-size:12px;padding:6px 12px;">⬇ Exporter CSV</button></h3>
          <p id="courbesEtat" class="sub hidden">Sélectionne au moins un camion et une métrique.</p>
          <p id="courbesNotePlausibiliteSourceB" class="sub hidden" style="margin-bottom:8px;"></p>
          <p id="courbesNoteKilometrageSourceB" class="sub hidden" style="margin-bottom:8px;"></p>
          <p id="badgeMoisPartielCourbes" class="sub hidden" style="margin-bottom:8px;color:var(--amber);"></p>
          <canvas id="chartCourbes" height="90"></canvas>
        </div>
      </div>

      <!-- ============ SUIVI GPS ============ -->
      <div id="tab-gps" class="hidden">
        <h1>Suivi GPS</h1>
        <p class="sub">Position actuelle de la flotte (API officielle constructeur) — actualisation automatique toutes les 45s. Sélectionne un ou plusieurs camions pour afficher leur trajet (une couleur par camion) sur la période choisie ci-dessous (aujourd'hui par défaut) — trait plein pour Télématique A, pointillé pour Télématique B (même couleur que le véhicule, quand un boîtier Télématique B est mappé). Les losanges sont les positions Télématique B (source complémentaire, archivées toutes les 15 min) ; leur couleur indique la fraîcheur de la position, pas le site.</p>
        <p id="gpsResumeFraicheurSourceB" class="sub hidden" style="font-weight:600;"></p>

        <input type="text" id="rechercheGps" placeholder="🔍 Filtrer les camions…" style="min-width:220px;margin-bottom:10px;background:#fff;border:1px solid var(--line);border-radius:6px;padding:8px 10px;font-size:13px;">
        <div class="chips" id="chipsGps"></div>

        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
            <div style="display:flex;gap:16px;align-items:center;font-size:12px;color:var(--grey);">
              <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1F618D;margin-right:5px;"></span>SITE A</span>
              <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#B9770E;margin-right:5px;"></span>SITE B</span>
              <span><span style="display:inline-block;width:9px;height:9px;background:var(--green);margin-right:5px;transform:rotate(45deg);"></span>Télématique B à jour (&lt;30 min)</span>
              <span><span style="display:inline-block;width:9px;height:9px;background:var(--amber);margin-right:5px;transform:rotate(45deg);"></span>Télématique B à surveiller (qq jours)</span>
              <span><span style="display:inline-block;width:9px;height:9px;background:var(--red);margin-right:5px;transform:rotate(45deg);"></span>Télématique B périmée (&gt;1 semaine)</span>
            </div>
            <span id="gpsDerniereMaj" class="sub" style="margin:0;"></span>
          </div>
          <div id="gpsPeriodeTrajet" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px;font-size:13px;">
            <label for="gpsDateDebut">Trajet du</label>
            <input type="date" id="gpsDateDebut">
            <label for="gpsDateFin">au</label>
            <input type="date" id="gpsDateFin">
          </div>
          <div id="gpsLegendeTrajets" class="hidden" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px;font-size:12px;"></div>
          <p id="gpsErreur" class="sub hidden" style="color:var(--red);"></p>
          <p id="gpsTrajetEtat" class="sub hidden"></p>
          <div id="mapGps"></div>
          <p id="gpsIndisponibles"></p>
          <p id="gpsIndisponiblesSourceB"></p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ============ Modal : Historique des changements de SER norme interne ============ -->
<div id="modalHistoriqueSer" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="titreModalHistoriqueSer">Historique SER norme interne</h3>
      <button type="button" class="modal-close" id="btnFermerHistoriqueSer">✕</button>
    </div>
    <div id="corpsModalHistoriqueSer" class="body"></div>
  </div>
</div>

<script src="/js/periode-selecteur.js"></script>
<script>
const API = {
  resume: '/api/flotte/resume',
  mesures: (id) => `/api/camions/${id}/mesures`,
  alertes: '/api/alertes',
  alertesTypes: '/api/alertes/types',
  serStandards: '/api/ser-standards',
  serHistorique: (id) => `/api/ser-historique/${id}`,
  analysesCorrectives: (id) => `/api/analyses-correctives/${id}`,
  consommationSourceB: (id) => `/api/consommation-source_b/${id}`,
  kilometrageSourceB: (id) => `/api/kilometrage-source_b/${id}`,
  serDynamique: '/api/ser-dynamique',
  comparateurRegistre: (id) => `/api/comparateur-registre/${id}`,
  courbesMetriques: '/api/courbes/metriques',
  courbesMensuel: (id, metrique) => `/api/courbes/${id}/mensuel/${metrique}`,
  courbesAnnuel: (id, metrique) => `/api/courbes/${id}/annuel/${metrique}`,
  comparateurSites: '/api/comparateur-sites',
  meteo: '/api/meteo',
  projection: (id, metrique) => `/api/projection/${id}/${metrique}`,
  suggestions: '/api/suggestions',
  prixCarburant: '/api/prix-carburant',
  exportFicheCamion: (id) => `/api/export/fiche-camion/${id}`,
  exportCourbes: (id, metriques, mode) => `/api/export/courbes/${id}?metriques=${metriques.join(',')}&mode=${mode}`,
  santeAutomatisations: '/api/sante-automatisations',
  positionsTempsReel: '/api/positions-temps-reel',
  positionsTempsReelSourceB: '/api/positions-temps-reel-source_b',
  positionsHistoriqueSourceB: (id) => `/api/positions-historique-source_b/${id}`,
  positionsHistorique: (id) => `/api/positions-historique/${id}`,
};

const FILTRES_KEY = 'fea_filters';

let DATA = null;
// Bandeau des 5 KPI de Vue d'ensemble — DÉLIBÉRÉMENT séparé de DATA, alimenté par un appel
// /api/flotte/resume distinct qui inclut en plus Site/Usage norme interne/Matricule.
let KPI_BANDEAU = null;
let PRIX_HISTORIQUE = [];
let CAMION_CACHE = {};
let CAMION_ACTIF = null;
let CONSOMMATION_SOURCE_B_CACHE = {};
let KILOMETRAGE_SOURCE_B_CACHE = {};
let COMPARATEUR_REGISTRE_KM_SOURCE_B = null;
// Sélection de l'écran « Accueil » : SOURCE UNIQUE.
let SELECTION_FLOTTE = [];
let SOUS_ONGLET_DETAIL = 'evolution'; // 'evolution' | 'etat' (détail d'UN camion)
let ETAT_RENDU_POUR = null;           // id du camion dont « État actuel » est déjà rendu (évite ~7 appels réseau à chaque bascule)
let SELECTION_COMPARATEUR = [];
let SER_STANDARDS_DATA = [];
let SER_DYN_DATA = [];
let COMPARATEUR_REGISTRE_ID = null;
let SYNTHESE_CAMION_ID = null;
let COURBES_METRIQUES = { source_a: [], registre: [] };
// Dès que 2+ camions sont sélectionnés, le nombre de métriques simultanées est verrouillé à 1 (cf.
// metriqueMaxActuel()) : au-delà, superposer plusieurs camions ET plusieurs métriques sur le même
// graphique deviendrait illisible (quelle couleur = quel camion, quelle métrique ?)
let COURBES_SELECTION = [];
let COURBES_RENDER_GENERATION = 0;
let COURBES_MODE = 'mensuel';
let TRI_COLONNE = 'eco_score_moyen';
let TRI_SENS = 'desc';
// Tableau de Standards SER : MÊME mécanisme que le tableau de l'Accueil — tri au clic sur
// l'en-tête (bascule asc/desc, sens par défaut propre à chaque colonne, flèche ▲/▼, mémorisé),
// recherche par immatriculation, filtre Statut norme interne — mais état SÉPARÉ
// (th[data-sort-ser], jamais th[data-sort].
let TRI_SER_COLONNE = 'groupe';
let SER_GRAPHIQUE_SELECTION = []; // ids des véhicules tracés sur le graphique « L/100km » de Standards SER (vide par défaut)
let TRI_SER_SENS = 'asc';
// Sens par défaut : texte/site/valeurs de référence croissants ; statut « à surveiller d'abord » (Rouge en tête) ;
// IPÉ et conso croissants (meilleur d'abord, comme la Conso de l'Accueil) ; km et heures décroissants.
const TRI_SER_SENS_DEFAUT = { immatriculation: 'asc', groupe: 'asc', usage_norme_ref: 'asc', ser_norme_ref: 'asc', incertitude_pct: 'asc', ser_1x: 'asc', cible: 'asc', statut: 'asc', pct_ser_usage: 'asc', pe_lh_precis: 'asc', litres_dernier_mois: 'desc', ipe: 'asc', km: 'desc', heures: 'desc', conso_l100: 'asc' };
const RANG_STATUT_SER = { ROUGE: 0, ORANGE: 1, VERT_CLAIR: 2, VERT_FONCE: 3, DONNEES_INSUFFISANTES: 4 };
let AFFICHER_PROJECTION = false;
let PROJECTION_CACHE = {};
let GPS_MAP = null;
let GPS_MARKERS = {};
// Marqueurs Télématique B — dict séparé de GPS_MARKERS ci-dessus, jamais fusionné : un camion core
// (ex: DEMO-003) a un vin (Télématique A) ET un device Télématique B mappé (+Télématique B), donc
// potentiellement 2 positions différentes affichées simultanément.
let GPS_MARKERS_SOURCE_B = {};
// Dernières réponses brutes (non filtrées) des 2 endpoints temps réel — mises en cache pour
// pouvoir redessiner les marqueurs quand un filtre général change (Site/Usage norme interne/
// Matricule/Marque/Périmètre), sans attendre le prochain polling 45s ni refaire un appel réseau
// inutile : la donnée n'a pas changé.
let DERNIERES_POSITIONS_SOURCE_A = [];
let DERNIERES_POSITIONS_SOURCE_B = [];
let GPS_TRAJET_LAYERS = {};
// Trajectoires Télématique B — calques SÉPARÉS des GPS_TRAJET_LAYERS ci-dessus (Télématique A),
// jamais fusionnés : un même camion peut avoir les deux simultanément (ex: DEMO-003, "+Télématique
// B"), les deux tracés doivent pouvoir être nettoyés/redessinés indépendamment sans se marcher
// dessus.
let GPS_TRAJET_LAYERS_SOURCE_B = {};
let GPS_SELECTION = [];
// Compteur de génération — un clic rapide sur plusieurs chips avant qu'un premier appel n'ait fini
// de résoudre ses fetch() laissait le PREMIER appel réappliquer un calque/une légende périmés
// APRÈS que le second (plus récent) ait déjà nettoyé l'état.
let GPS_TRAJET_GENERATION = 0;
// Valeurs des filtres globaux restaurées depuis localStorage, appliquées une fois que leurs
// panneaux sont peuplés (leurs cases n'existent pas encore à la restauration : elles dépendent de
// DATA et SER_STANDARDS_DATA, chargés de façon asynchrone). null = pas de préférence restaurée
// (repli sur "tout coché")
let RESTORE_USAGE_NORME_REF = null;
let RESTORE_MATRICULE = null;
let RESTORE_MARQUE = null;
// Marques QUI EXISTAIENT (options du filtre) au moment de l'enregistrement de RESTORE_MARQUE —
// sert à distinguer une marque décochée volontairement d'une marque apparue depuis. null = aucune
// restauration.
let RESTORE_MARQUES_CONNUES = null;
// État enregistré AVANT ce correctif (pas de liste des marques connues) : seules marque A et marque B
// existaient alors comme options du filtre — c'est exactement ce qu'un navigateur ancien avait vu.
const MARQUES_CONNUES_AVANT_CORRECTIF = ['Marque A', 'Marque B'];
const TRI_SENS_DEFAUT = { immatriculation: 'asc', groupe: 'asc', eco_score_moyen: 'desc', conso_moyenne: 'asc' };
const ORDRE_GROUPE_TRI = ['SITE A', 'SITE B'];

document.getElementById('printDate').textContent = 'Généré le ' + new Date().toLocaleDateString('fr-FR', {day:'2-digit',month:'long',year:'numeric'});

// ---------- Sidebar mobile (hamburger + overlay) ----------
document.getElementById('btnHamburger').addEventListener('click', () => {
  document.querySelector('.app').classList.toggle('sidebar-open');
});
document.getElementById('sidebarBackdrop').addEventListener('click', () => {
  document.querySelector('.app').classList.remove('sidebar-open');
});

// Sidebar rétractable + adaptative
// Défaut automatique par largeur (>=1200px complète, 769-1199px rail, <=768px tiroir mobile
// inchangé) + bouton .sidebar-toggle qui force l'autre état.
const SIDEBAR_KEY = 'fea_sidebar_force';
const MQ_SIDEBAR_COMPLETE = window.matchMedia('(min-width:1200px)');
const MQ_SIDEBAR_DESKTOP = window.matchMedia('(min-width:769px)');
let SIDEBAR_FORCE = null; // 'complete' | 'rail' | null (= défaut automatique)
try {
  const v = localStorage.getItem(SIDEBAR_KEY);
  if (v === 'complete' || v === 'rail') SIDEBAR_FORCE = v;
} catch (e) { /* localStorage indisponible : défaut automatique seulement */ }

function sidebarEtatAuto(){ return MQ_SIDEBAR_COMPLETE.matches ? 'complete' : 'rail'; }
function sidebarEtatEffectif(){ return SIDEBAR_FORCE || sidebarEtatAuto(); }

function appliquerEtatSidebar(){
  const rail = sidebarEtatEffectif() === 'rail';
  document.querySelector('.app').classList.toggle('sidebar-rail', rail);
  // Info-bulle (title) uniquement quand le libellé est réellement masqué (rail, au-dessus de 768px) ;
  // aria-label toujours posé : le libellé masqué en display:none n'est plus lu par les lecteurs d'écran.
  const titreActif = rail && MQ_SIDEBAR_DESKTOP.matches;
  document.querySelectorAll('.sidebar .nav-btn, .sidebar .nav-link').forEach(el => {
    const lbl = el.querySelector('.nav-label')?.textContent.trim();
    if (!lbl) return;
    el.setAttribute('aria-label', lbl);
    if (titreActif) el.title = lbl; else el.removeAttribute('title');
  });
  const btn = document.getElementById('btnSidebarToggle');
  const libelle = rail ? 'Étendre le menu' : 'Réduire le menu';
  document.getElementById('sidebarToggleIco').textContent = rail ? '»' : '«';
  document.getElementById('sidebarToggleLabel').textContent = libelle;
  btn.setAttribute('aria-expanded', rail ? 'false' : 'true');
  btn.setAttribute('aria-label', libelle);
  if (titreActif) btn.title = libelle; else btn.removeAttribute('title');
  // Filet de sécurité : si aucune transition ne se déclenche (prefers-reduced-motion, premier rendu),
  // transitionend ne tirerait jamais — cf. le vrai déclencheur juste en dessous.
  setTimeout(invalidaterCarteGpsSiVisible, 300);
}

// Leaflet n'observe pas la taille de son conteneur : toute transition qui change la largeur visible
// de #mapGps (ici la largeur de la sidebar) doit lui être signalée à la fin, pas au début.
function invalidaterCarteGpsSiVisible(){
  if (GPS_MAP && !document.getElementById('tab-gps').classList.contains('hidden')) {
    GPS_MAP.invalidateSize();
  }
}
document.querySelector('.sidebar').addEventListener('transitionend', e => {
  if (e.target === e.currentTarget && e.propertyName === 'width') invalidaterCarteGpsSiVisible();
});

document.getElementById('btnSidebarToggle').addEventListener('click', () => {
  const cible = sidebarEtatEffectif() === 'rail' ? 'complete' : 'rail';
  SIDEBAR_FORCE = (cible === sidebarEtatAuto()) ? null : cible;
  try {
    if (SIDEBAR_FORCE) localStorage.setItem(SIDEBAR_KEY, SIDEBAR_FORCE);
    else localStorage.removeItem(SIDEBAR_KEY);
  } catch (e) { /* non persisté, l'état reste appliqué pour la session */ }
  appliquerEtatSidebar();
});
MQ_SIDEBAR_COMPLETE.addEventListener('change', appliquerEtatSidebar);
MQ_SIDEBAR_DESKTOP.addEventListener('change', appliquerEtatSidebar);
appliquerEtatSidebar();

// Sidebar fixe : hauteur réelle de la barre de filtres globaux
// La sidebar (sticky) se cale juste sous .global-filterbar, dont la hauteur varie (retour à la
// ligne des filtres, mention persistante, largeur de fenêtre) : on la publie en variable CSS
// --gfb-h à chaque changement de taille plutôt que de coder une valeur en dur.
(function(){
  const fb = document.querySelector('.global-filterbar');
  if (!fb) return;
  const publier = () => document.documentElement.style.setProperty('--gfb-h', fb.offsetHeight + 'px');
  publier();
  if (window.ResizeObserver) new ResizeObserver(publier).observe(fb);
  window.addEventListener('resize', publier);
})();

// Info-bulles ⓘ : rester dans la fenêtre
// La bulle est centrée sur l'icône (230px max) : près d'un bord de l'écran elle en déborde.
function ajusterInfoBulle(el){
  const vw = document.documentElement.clientWidth;
  const w = Math.min(230, vw - 24);
  const r = el.getBoundingClientRect();
  const gauche = r.left + r.width / 2 - w / 2;
  let dx = 0;
  if (gauche < 12) dx = 12 - gauche;
  else if (gauche + w > vw - 12) dx = (vw - 12) - (gauche + w);
  el.style.setProperty('--tip-dx', Math.round(dx) + 'px');
}
['mouseover', 'touchstart', 'click'].forEach(type => {
  document.addEventListener(type, e => {
    const el = e.target.closest && e.target.closest('.info-i');
    if (el) ajusterInfoBulle(el);
  }, { passive: true });
});

// ---------- Navigation onglets ----------
document.querySelectorAll('.nav-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('hidden'));
    document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
    document.querySelector('.app').classList.remove('sidebar-open');
  });
});

// ---------- Impression / Export PDF ----------
document.getElementById('btnPrint').addEventListener('click', () => {
  const ongletActif = document.querySelector('[id^="tab-"]:not(.hidden)');
  document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.remove('print-active'));
  ongletActif.classList.add('print-active');
  window.print();
});

// Les graphiques Chart.js gardent leur taille écran (2-3 colonnes) telle quelle : le passage à
// @media print (grilles empilées en 1 colonne, cf. .grid-2-eq/.grid-3-eq ci-dessus) ne déclenche
// aucun resize automatique côté Chart.js. beforeprint se déclenche une fois la mise en page print
// déjà appliquée par le navigateur — on en profite pour forcer un resize générique de tous les
// graphiques actifs (Chart.instances, plutôt qu'une liste de variables nommées à maintenir à la
// main à chaque nouvel onglet/graphique).
window.addEventListener('beforeprint', () => {
  Object.values(Chart.instances).forEach(c => c.resize());
});

// score==null : jamais "À surveiller" (score>=8/>=6 sont tous deux faux pour null en JS, ce qui
// retombait par défaut sur ce badge négatif avant ce garde) — même convention "gris" que
// statutInfo()/DONNEES_INSUFFISANTES, pour ne jamais afficher un jugement de performance sur une
// donnée absente.
function bandeEco(score){
  if (score == null) return {label:'Sans donnée', cls:'gris'};
  if (score >= 8) return {label:'Bon', cls:'good'};
  if (score >= 6) return {label:'Moyen', cls:'mid'};
  return {label:'À surveiller', cls:'bad'};
}
// "Objectif IPÉ" = l'IPE norme interne comparé à sa cible officielle par véhicule.
function badgeObjectif(camion){
  const statut = statutNormeRefPour(camion.id)?.statut;
  if (!statut || statut === 'DONNEES_INSUFFISANTES') return {label:'Sans donnée', cls:'gris'};
  const atteint = statut === 'VERT_FONCE';
  return {label: atteint ? 'Atteint' : 'Non atteint', cls: atteint ? 'good' : 'bad'};
}
// Cellule "Consommation (L/100km)" de Vue d'ensemble et Comparateur — un camion sans mesure
// Télématique A (conso_moyenne null) affichait un "—" identique, qu'une consommation Télématique B
// existe ou non pour lui.
function celluleConsoSourceAHtml(c){
  if (c.conso_moyenne != null) return fmt(c.conso_moyenne);
  if (c.source_b_disponible) return '<span class="lien-source_b-fiche" title="Pas de données Télématique A — un calcul de consommation Télématique B existe pour ce véhicule (voir « État actuel » dans le détail du camion)">Voir détail →</span>';
  return '—';
}
// Badge(s) de source à côté de l'immatriculation — DEUX informations distinctes, jamais fusionnées
// dans un seul badge : 1.
function badgeElargieHtml(c){
  const sourceBadge = c.source_principale === 'SOURCE_B'
    ? '<span class="badge source_b" title="Véhicule suivi via Télématique B (plateforme complémentaire)">Source Télématique B</span>'
    : '<span class="badge good" title="Véhicule suivi via Télématique A (télématique automatique, source officielle norme interne)">Source Télématique A</span>';
  const badgeComplementaire = c.source_b_complementaire
    ? ' <span class="badge source_b-multi" title="Télématique A (source officielle norme interne) + Télématique B (complémentaire : distance et consommation)">+Télématique B</span>'
    : '';
  return sourceBadge + badgeComplementaire;
}
function statutNormeRefPour(camionId){
  return SER_DYN_DATA.find(s => s.id === camionId) ?? null;
}
// Badge norme interne de l'onglet Synthèse camion — extrait de ouvrirSyntheseCamion() pour être
// réutilisable depuis chargerSerDynamique(), sans dupliquer le HTML à deux endroits.
function rafraichirBadgeSyntheseStatut(){
  if (!SYNTHESE_CAMION_ID) return;
  const el = document.getElementById('syntheseStatutBadge');
  if (!el) return;
  const dynStatut = statutInfo(statutNormeRefPour(SYNTHESE_CAMION_ID)?.statut);
  el.innerHTML = `<span class="badge ${dynStatut.cls}" style="font-size:12px;padding:5px 14px;">${dynStatut.label}</span>`;
}
function filtrerChips(containerId, rechercheVal){
  const q = (rechercheVal || '').trim().toUpperCase();
  document.querySelectorAll(`#${containerId} .chip`).forEach(chip => {
    const camion = camionParId(parseInt(chip.dataset.id));
    const correspondRecherche = !q || chip.textContent.toUpperCase().includes(q);
    const correspondFiltresGlobaux = !camion || camionCorrespondFiltresGlobaux(camion);
    chip.classList.toggle('hidden', !(correspondRecherche && correspondFiltresGlobaux));
  });
}
function camionParId(id){
  return DATA?.par_camion?.find(c => c.id === id) ?? null;
}
// SER_STANDARDS_DATA est la seule source qui porte usage_norme_ref (absent de /api/flotte/resume) —
// utilisé en repli quand l'objet camion passé (ex: DATA.par_camion, SER_DYN_DATA) ne le porte pas.
function usageNormeRefParCamionId(id){
  const c = SER_STANDARDS_DATA.find(s => s.id === id);
  return c ? c.usage_norme_ref : null;
}
// Filtres généraux multi-select
// Chaque filtre est représenté par un Set des valeurs cochées : null tant qu'il n'a pas encore été
// peuplé au moins une fois (Usage/Matricule dépendent de données chargées en asynchrone — équivaut
// à "tout coché" le temps de cette initialisation, jamais un flash "aucun résultat"), sinon un Set
// réel.
let SITES_COCHES = new Set(['SITE A', 'SITE B']);
let USAGES_COCHES = null;
let MATRICULES_COCHES = null;
let USAGES_DISPONIBLES = [];
let MATRICULES_DISPONIBLES = [];
let MARQUES_COCHES = null;
let MARQUES_DISPONIBLES = [];
const SITES_CONNUS = ['SITE A', 'SITE B'];
// Périmètre — repli par défaut sur LES 2 (comme SITES_COCHES ci-dessus)
const SOURCES_CONNUES = ['SOURCE_A', 'SOURCE_B'];
let SOURCES_COCHES = new Set(SOURCES_CONNUES);

// camionCorrespondFiltresGlobaux() — même fonction, même rôle qu'avant le multi-select : chaque
// === devient un .has() sur l'ensemble coché (logique OU), le reste de l'app (Vue d'ensemble,
// Comparateur, Standards SER, SER dynamique, Alertes, Suggestions...) continue de l'appeler sans
// aucun changement, elle ne sait même pas que le mécanisme sous-jacent est devenu multi-select.
function camionCorrespondFiltresGlobaux(camion){
  if (!camion) return false;
  if (!SITES_COCHES.has(camion.groupe)) return false;
  if (USAGES_COCHES !== null) {
    const u = camion.usage_norme_ref !== undefined ? camion.usage_norme_ref : usageNormeRefParCamionId(camion.id);
    if (!USAGES_COCHES.has(u ?? '')) return false;
  }
  if (MATRICULES_COCHES !== null && !MATRICULES_COCHES.has(String(camion.id))) return false;
  if (MARQUES_COCHES !== null && !MARQUES_COCHES.has(camion.marque ?? '')) return false;
  // Périmètre : source_principale absent (ex: objets SER_STANDARDS_DATA/SER_DYN_DATA qui ne
  // portent pas ce champ) est traité comme 'SOURCE_A' — jamais un camion élargi qui apparaîtrait
  // par accident faute de champ transmis par un endpoint qui ne le connaît pas encore.
  if (!SOURCES_COCHES.has(camion.source_principale ?? 'SOURCE_A')) return false;
  return true;
}
// Variante de camionCorrespondFiltresGlobaux() pour les entités qui n'exposent que l'immatriculation,
// pas camion_id (Alertes, Suggestions — cf. /api/alertes, /api/suggestions) — même pattern de
// résolution déjà en place pour Synthèse camion (l'immatriculation est la clé métier unique du
// camion dans toute l'app, donc ce rattachement est exact, pas approximatif).
function immatriculationCorrespondFiltresGlobaux(immat){
  return camionCorrespondFiltresGlobaux(DATA?.par_camion?.find(c => c.immatriculation === immat) ?? null);
}

// Texte du bouton d'un multi-select : le libellé "tout" tant que rien n'est encore initialisé ou que
// tout est coché, "Aucun sélectionné" si le Set est vide, la valeur elle-même si un seul élément est
// coché (via labelDe, pour afficher l'immatriculation plutôt qu'un id brut côté Matricule), sinon un
// compteur — jamais une liste à rallonge qui ferait déborder le bouton.
function texteMultiSelect(libelleTout, coches, totalDisponible, labelDe){
  if (coches === null || coches.size === totalDisponible) return libelleTout;
  if (coches.size === 0) return 'Aucun sélectionné';
  if (coches.size === 1) return labelDe([...coches][0]);
  return `${coches.size} sélectionnés`;
}
// Reconstruit le contenu d'un panneau (cases à cocher + Tout cocher/décocher) — appelée à chaque
// re-population (Usage/Matricule dépendent de données chargées en asynchrone, Site jamais puisque
// ses 2 cases sont statiques dans le HTML). Les gestionnaires de clic sont posés une fois sur le
// panneau lui-même (délégation, cf. initMultiSelect()), donc remplacer innerHTML ici ne les perd pas.
function remplirPanelMultiSelect(panelId, options, coches){
  const panel = document.getElementById(panelId);
  panel.innerHTML = (options.length ? options.map(o => `
    <label><input type="checkbox" value="${o.value}" ${coches === null || coches.has(o.value) ? 'checked' : ''}> ${o.label}</label>
  `).join('') : '<div class="ms-vide">Aucune valeur disponible</div>') + `
    <div class="ms-actions">
      <button type="button" class="ms-tout">Tout cocher</button>
      <button type="button" class="ms-aucun">Tout décocher</button>
    </div>`;
}
// Câblage commun des 3 multi-select : ouverture/fermeture du panneau au clic sur le bouton, fermeture
// automatique au clic ailleurs sur la page, et délégation des clics à l'intérieur du panneau (case à
// cocher individuelle, ou les 2 boutons Tout cocher/décocher) vers onChange().
function initMultiSelect(prefix, onChange){
  const btn = document.getElementById('ms' + prefix + 'Btn');
  const panel = document.getElementById('ms' + prefix + 'Panel');
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const etaitOuvert = !panel.classList.contains('hidden');
    document.querySelectorAll('.ms-panel').forEach(p => p.classList.add('hidden'));
    if (!etaitOuvert) panel.classList.remove('hidden');
  });
  panel.addEventListener('click', (e) => {
    e.stopPropagation();
    if (e.target.matches('input[type=checkbox]')) {
      onChange();
    } else if (e.target.classList.contains('ms-tout')) {
      panel.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true);
      onChange();
    } else if (e.target.classList.contains('ms-aucun')) {
      panel.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
      onChange();
    }
  });
}
document.addEventListener('click', () => {
  document.querySelectorAll('.ms-panel').forEach(p => p.classList.add('hidden'));
});

function majSitesCoches(){
  SITES_COCHES = new Set([...document.querySelectorAll('#msSitePanel input:checked')].map(c => c.value));
  document.getElementById('msSiteBtn').textContent = texteMultiSelect('Tous les sites', SITES_COCHES, SITES_CONNUS.length, v => v);
  propagerFiltresGlobaux();
}
function majUsagesCoches(){
  USAGES_COCHES = new Set([...document.querySelectorAll('#msUsagePanel input:checked')].map(c => c.value));
  document.getElementById('msUsageBtn').textContent = texteMultiSelect('Tous les usages', USAGES_COCHES, USAGES_DISPONIBLES.length, v => v);
  propagerFiltresGlobaux();
}
function majMatriculesCoches(){
  MATRICULES_COCHES = new Set([...document.querySelectorAll('#msMatriculePanel input:checked')].map(c => c.value));
  const labelDe = (id) => camionParId(parseInt(id))?.immatriculation ?? id;
  document.getElementById('msMatriculeBtn').textContent = texteMultiSelect('Tous les camions', MATRICULES_COCHES, MATRICULES_DISPONIBLES.length, labelDe);
  propagerFiltresGlobaux();
}
function majMarquesCoches(){
  MARQUES_COCHES = new Set([...document.querySelectorAll('#msMarquePanel input:checked')].map(c => c.value));
  document.getElementById('msMarqueBtn').textContent = texteMultiSelect('Toutes les marques', MARQUES_COCHES, MARQUES_DISPONIBLES.length, v => v);
  propagerFiltresGlobaux();
}
// Périmètre : libellé du bouton toujours explicite ("Source Télématique A" / "Source Télématique
// B" / "Tous les véhicules"), jamais un simple compteur comme les 3 filtres ci-dessus — ce choix
// est plus lourd de sens qu'un filtre de commodité.
function majSourcesCoches(){
  SOURCES_COCHES = new Set([...document.querySelectorAll('#msPerimetrePanel input:checked')].map(c => c.value));
  const btn = document.getElementById('msPerimetreBtn');
  if (SOURCES_COCHES.size === 0) btn.textContent = 'Aucun périmètre sélectionné';
  else if (SOURCES_COCHES.size === SOURCES_CONNUES.length) btn.textContent = 'Tous les véhicules';
  else if (SOURCES_COCHES.has('SOURCE_B')) btn.textContent = 'Source Télématique B';
  else btn.textContent = 'Source Télématique A';
  document.getElementById('noteFlotteElargie').style.display = SOURCES_COCHES.has('SOURCE_B') ? '' : 'none';
  propagerFiltresGlobaux();
}
initMultiSelect('Site', majSitesCoches);
initMultiSelect('Usage', majUsagesCoches);
initMultiSelect('Matricule', majMatriculesCoches);
initMultiSelect('Marque', majMarquesCoches);
initMultiSelect('Perimetre', majSourcesCoches);

function remplirFiltreMatricule(){
  const camions = [...DATA.par_camion].sort((a, b) => a.immatriculation.localeCompare(b.immatriculation));
  MATRICULES_DISPONIBLES = camions.map(c => String(c.id));
  // Première population (MATRICULES_COCHES encore null) : reprend RESTORE_MATRICULE (tableau
  // restauré depuis localStorage, cf. restaurerFiltres()) si présent, sinon tout coché par défaut.
  MATRICULES_COCHES = new Set(
    (MATRICULES_COCHES === null ? (RESTORE_MATRICULE !== null ? RESTORE_MATRICULE : MATRICULES_DISPONIBLES) : [...MATRICULES_COCHES])
      .filter(id => MATRICULES_DISPONIBLES.includes(id))
  );
  remplirPanelMultiSelect('msMatriculePanel', camions.map(c => ({ value: String(c.id), label: `${c.immatriculation} (${c.groupe})` })), MATRICULES_COCHES);
  document.getElementById('msMatriculeBtn').textContent = texteMultiSelect('Tous les camions', MATRICULES_COCHES, MATRICULES_DISPONIBLES.length, (id) => camionParId(parseInt(id))?.immatriculation ?? id);
}
// Filtre "Marque" : même pattern que remplirFiltreMatricule() ci-dessus, sourcé sur
// DATA.par_camion.
function remplirFiltreMarque(){
  const marques = [...new Set(DATA.par_camion.map(c => c.marque).filter(Boolean))].sort();
  MARQUES_DISPONIBLES = marques;
  // Restauration : la sélection enregistrée + toute marque APPARUE depuis (absente de la liste des
  // marques connues à l'enregistrement) est cochée par défaut.
  const marquesCocheesInitiales = MARQUES_COCHES !== null
    ? [...MARQUES_COCHES]
    : (RESTORE_MARQUE !== null
      ? [...RESTORE_MARQUE, ...marques.filter(m => !(RESTORE_MARQUES_CONNUES ?? []).includes(m))]
      : marques);
  MARQUES_COCHES = new Set(marquesCocheesInitiales.filter(m => marques.includes(m)));
  remplirPanelMultiSelect('msMarquePanel', marques.map(m => ({ value: m, label: m })), MARQUES_COCHES);
  document.getElementById('msMarqueBtn').textContent = texteMultiSelect('Toutes les marques', MARQUES_COCHES, MARQUES_DISPONIBLES.length, v => v);
}
// '' représente "usage_norme_ref non renseigné" (même sentinel que camionCorrespondFiltresGlobaux,
// u ?? '') — ajouté explicitement à la liste des options.
function remplirFiltreUsage(){
  const usagesReels = [...new Set(SER_STANDARDS_DATA.map(c => c.usage_norme_ref).filter(Boolean))].sort();
  const auMoinsUnNonRenseigne = SER_STANDARDS_DATA.some(c => !c.usage_norme_ref);
  const usages = auMoinsUnNonRenseigne ? [...usagesReels, ''] : usagesReels;
  USAGES_DISPONIBLES = usages;
  // Le filtre .filter(u => usages.includes(u)) doit s'appliquer aussi à la toute première
  // population (pas seulement aux suivantes) : un état restauré depuis localStorage peut contenir
  // une option qui n'existe plus — sans ce filtrage, USAGES_COCHES.size ne correspond plus à
  // USAGES_DISPONIBLES.length.
  USAGES_COCHES = new Set(
    (USAGES_COCHES === null ? (RESTORE_USAGE_NORME_REF !== null ? RESTORE_USAGE_NORME_REF : usages) : [...USAGES_COCHES])
      .filter(u => usages.includes(u))
  );
  remplirPanelMultiSelect('msUsagePanel', usages.map(u => ({ value: u, label: u || 'Non renseigné' })), USAGES_COCHES);
  document.getElementById('msUsageBtn').textContent = texteMultiSelect('Tous les usages', USAGES_COCHES, USAGES_DISPONIBLES.length, v => v || 'Non renseigné');
}
// Remet les 3 filtres généraux à "tout coché" — réutilisée par les 2 boutons Réinitialiser existants.
function reinitialiserFiltresGlobaux(){
  RESTORE_USAGE_NORME_REF = null;
  RESTORE_MATRICULE = null;
  RESTORE_MARQUE = null;
  RESTORE_MARQUES_CONNUES = null;
  SITES_COCHES = new Set(SITES_CONNUS);
  USAGES_COCHES = null;
  MATRICULES_COCHES = null;
  MARQUES_COCHES = null;
  document.querySelectorAll('#msSitePanel input').forEach(c => c.checked = true);
  document.getElementById('msSiteBtn').textContent = 'Tous les sites';
  remplirFiltreUsage();
  remplirFiltreMatricule();
  remplirFiltreMarque();
  // Périmètre : "Réinitialiser" revient à "tout coché" (les 27), même principe que Site/Usage/
  // Matricule/Marque ci-dessus depuis l'unification — ce n'est plus un filtre à part avec un repli
  // restrictif spécial, juste un filtre de commodité comme les autres.
  SOURCES_COCHES = new Set(SOURCES_CONNUES);
  document.querySelectorAll('#msPerimetrePanel input').forEach(c => c.checked = true);
  document.getElementById('msPerimetreBtn').textContent = 'Tous les véhicules';
  document.getElementById('noteFlotteElargie').style.display = SOURCES_COCHES.has('SOURCE_B') ? '' : 'none';
}
// Les sélections mono-camion (Fiche camion, registre manuel vs Télématique A, Synthèse, Courbes) et les
// sélections multiples (Comparateur, Suivi GPS, Suivi SER/IPÉ) doivent être invalidées quand un
// camion sélectionné ne correspond plus aux filtres globaux — sinon l'onglet continuerait d'afficher
// les données d'un camion exclu par le filtre actif, contredisant "Statut norme interne doit refléter la
// vraie donnée" côté fiabilité (même principe appliqué ici à la cohérence du filtrage global).
function verifierSelectionsFiltrees(){
  // Écran Accueil : un camion coché qui ne correspond plus aux filtres globaux.
  const restant = SELECTION_FLOTTE.filter(id => { const c = camionParId(id); return c && camionCorrespondFiltresGlobaux(c); });
  if (restant.length !== SELECTION_FLOTTE.length) {
    definirSelectionFlotte(restant);
  } else if (SELECTION_FLOTTE.length === 0) {
    const correspondants = DATA.par_camion.filter(camionCorrespondFiltresGlobaux);
    if (correspondants.length === 1) definirSelectionFlotte([correspondants[0].id]);
  }
  if (COMPARATEUR_REGISTRE_ID != null && !camionCorrespondFiltresGlobaux(camionParId(COMPARATEUR_REGISTRE_ID))) {
    COMPARATEUR_REGISTRE_ID = null;
    viderComparateurRegistre();
  }
  if (SYNTHESE_CAMION_ID != null && !camionCorrespondFiltresGlobaux(camionParId(SYNTHESE_CAMION_ID))) {
    SYNTHESE_CAMION_ID = null;
    viderSyntheseCamion();
  }
  // Suivi GPS : une trajectoire d'un camion exclu par le filtre global ne reste pas affichée.
  const gpsAvant = GPS_SELECTION.length;
  GPS_SELECTION = GPS_SELECTION.filter(id => camionCorrespondFiltresGlobaux(camionParId(id)));
  if (GPS_SELECTION.length !== gpsAvant) {
    document.querySelectorAll('#chipsGps .chip').forEach(c => {
      c.classList.toggle('active', GPS_SELECTION.includes(parseInt(c.dataset.id)));
    });
    chargerTrajetGps();
  }
  // Courbes complètes : un camion exclu par le filtre global ne doit plus rester affiché sur le
  // graphique.
  const courbesAvant = COURBES_SELECTION.length;
  COURBES_SELECTION = COURBES_SELECTION.filter(id => camionCorrespondFiltresGlobaux(camionParId(id)));
  if (COURBES_SELECTION.length !== courbesAvant) {
    document.querySelectorAll('#chipsCourbes .chip').forEach(c => {
      c.classList.toggle('active', COURBES_SELECTION.includes(parseInt(c.dataset.id)));
    });
    appliquerVerrouMetriqueCourbes();
    chargerCourbes();
  }
}
// Point d'entrée unique appelé à chaque changement d'un filtre global et après chaque chargement
// de données — répercute Site/Usage norme interne/Matricule sur tous les onglets simultanément.
function appliquerFiltresGlobaux(){
  if (DATA) { remplirFiltreMatricule(); remplirFiltreMarque(); }
  remplirFiltreUsage();
  propagerFiltresGlobaux();
}
// Partie de appliquerFiltresGlobaux() qui ne touche PAS aux panneaux Usage/Matricule — appelée
// directement par les 3 gestionnaires de case à cocher (majSitesCoches/majUsagesCoches/
// majMatriculesCoches), qui ont déjà mis à jour SITES_COCHES/USAGES_COCHES/MATRICULES_COCHES et le
// texte du bouton à partir de l'état actuel du panneau — rien d'autre à repeupler ici.
function propagerFiltresGlobaux(){
  if (DATA) {
    renderApercu();
    filtrerChips('chipsRegistre', document.getElementById('rechercheRegistre').value);
    filtrerChips('chipsCourbes', document.getElementById('rechercheCourbes').value);
    filtrerChips('chipsGps', document.getElementById('rechercheGps').value);
    verifierSelectionsFiltrees();
    // Bandeau des 5 KPI : seul renderApercu() ci-dessus se rafraîchit de façon synchrone à ce
    // stade (retombe sur l'ancien KPI_BANDEAU/DATA le temps que cet appel réseau résolve) — cf.
    // rafraichirKpiBandeau()
    rafraichirKpiBandeau();
    renderFluxAlertesSuggestions();
    // Marqueurs GPS — redessine à partir des positions déjà en cache (pas de nouvel appel réseau,
    // la donnée n'a pas changé) pour que le Périmètre/Site/Usage/Matricule/Marque s'applique
    // immédiatement aux marqueurs affichés, pas seulement à la liste de chips en dessous.
    redessinerMarqueursGps();
    redessinerMarqueursSourceB();
  }
  renderSerStandards();
  renderGraphiqueSerL100();
  sauvegarderFiltres();
}
function sauvegarderFiltres(){
  const filtres = {
    // Tableaux depuis le passage au multi-select — usageNormeRef/matricule restent null tant
    // qu'ils n'ont encore jamais été peuplés (cf. USAGES_COCHES/MATRICULES_COCHES), pour ne jamais
    // écraser une préférence pas encore restaurée par un "tout coché" prématuré.
    site: [...SITES_COCHES],
    usageNormeRef: USAGES_COCHES !== null ? [...USAGES_COCHES] : null,
    matricule: MATRICULES_COCHES !== null ? [...MATRICULES_COCHES] : null,
    marque: MARQUES_COCHES !== null ? [...MARQUES_COCHES] : null,
    marquesConnues: MARQUES_DISPONIBLES,
    perimetre: [...SOURCES_COCHES],
    statutNormeRef: document.getElementById('filtreStatutNormeRef').value,
    nbAlertes: document.getElementById('filtreNbAlertes').value,
    typeAlerte: valeurFiltreTypeAlerte(),
    tri: document.getElementById('filtreTri').value,
    rechercheCamion: document.getElementById('rechercheCamion').value,
    selectionFlotte: SELECTION_FLOTTE,
    selectionGps: GPS_SELECTION,
    selectionCourbes: COURBES_SELECTION,
    periodeApercu: document.getElementById('filtrePeriodeApercu').value,
    dateDebutApercu: document.getElementById('dateDebutApercu').value,
    dateFinApercu: document.getElementById('dateFinApercu').value,
    triColonne: TRI_COLONNE,
    triSens: TRI_SENS,
    triSerColonne: TRI_SER_COLONNE,
    triSerSens: TRI_SER_SENS,
    statutSer: document.getElementById('filtreStatutSerStandards').value,
    rechercheSer: document.getElementById('rechercheSerStandards').value,
    selectionSerGraphique: SER_GRAPHIQUE_SELECTION,
  };
  localStorage.setItem(FILTRES_KEY, JSON.stringify(filtres));
}
function restaurerFiltres(){
  try {
    const raw = localStorage.getItem(FILTRES_KEY);
    if (!raw) return;
    const f = JSON.parse(raw);
    // Site : ses 2 cases sont statiques dans le HTML, donc restaurable immédiatement (pas besoin
    // d'attendre une population asynchrone comme Usage/Matricule ci-dessous)
    if (Array.isArray(f.site)) {
      SITES_COCHES = new Set(f.site);
    } else if (typeof f.site === 'string') {
      SITES_COCHES = f.site === 'TOUS' ? new Set(SITES_CONNUS) : new Set([f.site]);
    }
    document.querySelectorAll('#msSitePanel input').forEach(c => c.checked = SITES_COCHES.has(c.value));
    document.getElementById('msSiteBtn').textContent = texteMultiSelect('Tous les sites', SITES_COCHES, SITES_CONNUS.length, v => v);
    // Périmètre : n'accepte QUE des valeurs connues ('SOURCE_A'/'SOURCE_B') — si absent/invalide.
    if (Array.isArray(f.perimetre) && f.perimetre.every(v => SOURCES_CONNUES.includes(v))) {
      SOURCES_COCHES = new Set(f.perimetre);
    }
    document.querySelectorAll('#msPerimetrePanel input').forEach(c => c.checked = SOURCES_COCHES.has(c.value));
    const btnPerimetre = document.getElementById('msPerimetreBtn');
    if (SOURCES_COCHES.size === 0) btnPerimetre.textContent = 'Aucun périmètre sélectionné';
    else if (SOURCES_COCHES.size === SOURCES_CONNUES.length) btnPerimetre.textContent = 'Tous les véhicules';
    else if (SOURCES_COCHES.has('SOURCE_B')) btnPerimetre.textContent = 'Source Télématique B';
    else btnPerimetre.textContent = 'Source Télématique A';
    document.getElementById('noteFlotteElargie').style.display = SOURCES_COCHES.has('SOURCE_B') ? '' : 'none';
    // Usage/Matricule n'ont pas encore leurs cases à ce stade (dépendent de DATA/SER_STANDARDS_DATA,
    // chargés en asynchrone après restaurerFiltres()) : la valeur est gardée de côté et réappliquée
    // par remplirFiltreUsage()/remplirFiltreMatricule(). Compatible avec l'ancien format à valeur
    // unique (chaîne, jamais "TOUS" qui équivalait déjà à "pas de préférence").
    if (Array.isArray(f.usageNormeRef)) RESTORE_USAGE_NORME_REF = f.usageNormeRef;
    else if (typeof f.usageNormeRef === 'string' && f.usageNormeRef !== 'TOUS') RESTORE_USAGE_NORME_REF = [f.usageNormeRef];
    if (Array.isArray(f.matricule)) RESTORE_MATRICULE = f.matricule;
    else if (typeof f.matricule === 'string' && f.matricule !== 'TOUS') RESTORE_MATRICULE = [f.matricule];
    // Marque : jamais existé sous forme mono-valeur, pas de branche de compatibilité à gérer
    // contrairement à usageNormeRef/matricule ci-dessus.
    if (Array.isArray(f.marque)) {
      RESTORE_MARQUE = f.marque;
      RESTORE_MARQUES_CONNUES = Array.isArray(f.marquesConnues) ? f.marquesConnues : MARQUES_CONNUES_AVANT_CORRECTIF;
    }
    if (f.statutNormeRef) document.getElementById('filtreStatutNormeRef').value = f.statutNormeRef;
    if (['TOUS', '0', '1', '2+'].includes(f.nbAlertes)) document.getElementById('filtreNbAlertes').value = f.nbAlertes;
    if (typeof f.typeAlerte === 'string' && f.typeAlerte !== 'TOUS') TYPE_ALERTE_RESTAURE = f.typeAlerte;
    if (f.tri) document.getElementById('filtreTri').value = f.tri;
    if (f.triColonne) {
      TRI_COLONNE = f.triColonne;
      TRI_SENS = f.triSens || TRI_SENS_DEFAUT[f.triColonne] || 'asc';
    } else if (f.tri) {
      const [champ, sens] = f.tri.split('_');
      TRI_COLONNE = champ === 'eco' ? 'eco_score_moyen' : 'conso_moyenne';
      TRI_SENS = sens;
    }
    if (f.rechercheCamion != null) document.getElementById('rechercheCamion').value = f.rechercheCamion;
    // Standards SER : tri/recherche/statut du tableau — colonne et sens validés (un ancien état ou
    // une valeur inconnue est ignoré sans erreur)
    if (f.triSerColonne in TRI_SER_SENS_DEFAUT) { TRI_SER_COLONNE = f.triSerColonne; TRI_SER_SENS = f.triSerSens === 'desc' ? 'desc' : 'asc'; }
    if (f.statutSer in RANG_STATUT_SER) document.getElementById('filtreStatutSerStandards').value = f.statutSer;
    if (typeof f.rechercheSer === 'string') document.getElementById('rechercheSerStandards').value = f.rechercheSer;
    if (Array.isArray(f.selectionSerGraphique)) SER_GRAPHIQUE_SELECTION = f.selectionSerGraphique.filter(Number.isInteger);
    if (f.periodeApercu) document.getElementById('filtrePeriodeApercu').value = f.periodeApercu;
    if (f.dateDebutApercu) document.getElementById('dateDebutApercu').value = f.dateDebutApercu;
    if (f.dateFinApercu) document.getElementById('dateFinApercu').value = f.dateFinApercu;
    // Anciennes clés (camionActif, selectionComparateur, periodeCamion, rechercheComparateur) volontairement ignorées.
    if (Array.isArray(f.selectionFlotte)) SELECTION_FLOTTE = f.selectionFlotte.filter(Number.isInteger);
    if (Array.isArray(f.selectionGps)) GPS_SELECTION = f.selectionGps;
    if (Array.isArray(f.selectionCourbes)) COURBES_SELECTION = f.selectionCourbes;
  } catch(e) {}
}
function telechargerCsv(csv, prefix){
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${prefix}_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}
function fmt(n, d=1){ return (n ?? 0).toLocaleString('fr-FR', {minimumFractionDigits:d, maximumFractionDigits:d}); }

// Tous les <canvas> Chart.js du dashboard ont un attribut height="..." mais pas de width : sans
// aspectRatio explicite, Chart.js retombe sur le ratio des attributs HTML natifs du canvas
// (largeur par défaut du navigateur 300 / height déclaré, ex. 300/90 = 3.33:1)
const SEUIL_LARGEUR_MOBILE_GRAPHIQUES = 480;
const ASPECT_RATIO_MOBILE_GRAPHIQUES = 1.4;
function optionsAspectRatioGraphique(){
  return window.innerWidth < SEUIL_LARGEUR_MOBILE_GRAPHIQUES
    ? { maintainAspectRatio: true, aspectRatio: ASPECT_RATIO_MOBILE_GRAPHIQUES }
    : { maintainAspectRatio: true };
}
function formatKpiPeriode(valeur, nbMesuresPeriode, unite){
  if (!nbMesuresPeriode) return '<span style="color:var(--grey);">—</span><small>aucune donnée</small>';
  return `${fmt(valeur)}<small>${unite}</small>`;
}
function moyenne(arr, champ){ const v = arr.map(m=>m[champ]).filter(x=>x!=null); return v.length ? v.reduce((a,b)=>a+b,0)/v.length : 0; }
function somme(arr, champ){ return arr.map(m=>m[champ]).filter(x=>x!=null).reduce((a,b)=>a+b,0); }
// Sous-ensemble "exploitable" (conso_exploitable && !est_partielle, même définition que
// CamionController::filtrerParPeriode()/scopeExploitable()) des mesures d'un camion, restreint à
// une période.
function filtrerMesuresParPeriode(mesures, bornes){
  const base = mesures.filter(m => m.conso_exploitable && !m.est_partielle);
  if (!bornes.depuis && !bornes.jusquA) return base;
  return base.filter(m => dansPeriode(m.date, bornes));
}
// Reproduit exactement la sémantique de CamionController::resumeFlotte() (par_camion) sur une
// période donnée : eco_score_moyen ignore les ecoscore<=0, conso_moyenne et
// nb_mesures_exploitables portent sur toutes les mesures exploitables. null explicite quand aucune
// mesure exploitable n'existe sur la période.
function statsCamionPourPeriode(mesures, bornes){
  const filtrees = filtrerMesuresParPeriode(mesures, bornes);
  const avecEco = filtrees.filter(m => m.ecoscore > 0);
  return {
    nb_mesures_exploitables: filtrees.length,
    eco_score_moyen: avecEco.length ? Math.round(moyenne(avecEco, 'ecoscore') * 100) / 100 : null,
    conso_moyenne: filtrees.length ? Math.round(moyenne(filtrees, 'consommation_l100') * 100) / 100 : null,
  };
}
// Prix HT en vigueur à une date donnée, d'après PRIX_HISTORIQUE (table prix_carburant_historique).
// Repli sur prixDefaut (champ éditable Fiche camion) si aucune période de l'historique ne couvre
// cette date — estime=true signale alors que ce n'est pas un prix réellement connu à cette date.
function prixEnVigueur(dateStr, prixDefaut){
  const d = (dateStr || '').slice(0, 10);
  const periode = PRIX_HISTORIQUE.find(p => {
    const debut = p.date_debut.slice(0, 10);
    const fin = p.date_fin ? p.date_fin.slice(0, 10) : null;
    return d >= debut && (fin === null || d <= fin);
  });
  return periode ? { prix: periode.prix_ht, estime: false } : { prix: prixDefaut, estime: true };
}
// Coût carburant pondéré par mesure : chaque mois utilise le prix en vigueur à sa propre date,
// plutôt que d'appliquer le prix actuel rétroactivement à tout l'historique.
function calculerCoutCarburant(mesures, prixDefaut){
  let coutTotal = 0, kmTotal = 0, estime = false;
  mesures.forEach(m => {
    if (!(m.conso_exploitable && m.km_parcourus > 0)) return;
    const litres = (m.consommation_l100 * m.km_parcourus) / 100;
    const p = prixEnVigueur(m.date, prixDefaut);
    coutTotal += litres * p.prix;
    kmTotal += m.km_parcourus;
    if (p.estime) estime = true;
  });
  return { coutKm: kmTotal > 0 ? coutTotal / kmTotal : 0, coutTotal, estime };
}
function moisLisible2(m){ return moisLisible(m + '-01'); }
function moisLisible(d){ const dt = new Date(d); return dt.toLocaleDateString('fr-FR', {month:'short', year:'numeric'}); }

// Mois en cours (est_partielle) : signal temps réel visuellement distinct
// Jamais mélangé à une moyenne (cf. mesuresFiltrees, moyenne(), etc. qui l'excluent explicitement)
// mais toujours affiché sur les courbes, marqué comme provisoire.
function estMesurePartielle(m){ return !!(m && m.est_partielle); }

function hexVersRgba(hex, alpha){
  const h = hex.replace('#', '');
  const r = parseInt(h.substring(0, 2), 16), g = parseInt(h.substring(2, 4), 16), b = parseInt(h.substring(4, 6), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

// Construit les props Chart.js (per-point + segment) qui atténuent/distinguent visuellement le
// point d'indice idxPartiel dans un dataset de nbPoints points — point plus gros, forme différente
// (triangle), couleur atténuée, et segment de ligne qui y mène en pointillés. idxPartiel = -1 :
// aucun point partiel, style inchangé partout (aucun -1 ne matche un index réel).
function stylePointsAvecPartielIndex(nbPoints, idxPartiel, couleurBase){
  const indices = Array.from({length: nbPoints}, (_, i) => i);
  return {
    pointBackgroundColor: indices.map(i => i === idxPartiel ? hexVersRgba(couleurBase, 0.35) : couleurBase),
    pointBorderColor: indices.map(i => i === idxPartiel ? hexVersRgba(couleurBase, 0.6) : couleurBase),
    pointRadius: indices.map(i => i === idxPartiel ? 7 : 3),
    pointStyle: indices.map(i => i === idxPartiel ? 'triangle' : 'circle'),
    segment: {
      borderDash: (ctx) => (idxPartiel >= 0 && ctx.p1DataIndex === idxPartiel) ? [6, 4] : undefined,
      borderColor: (ctx) => (idxPartiel >= 0 && ctx.p1DataIndex === idxPartiel) ? hexVersRgba(couleurBase, 0.5) : undefined,
    },
  };
}

// Variante Fiche camion : idxPartiel retrouvé directement dans le tableau de mesures.
function stylePointsAvecPartiel(toutesLesMesures, couleurBase){
  return stylePointsAvecPartielIndex(toutesLesMesures.length, toutesLesMesures.findIndex(estMesurePartielle), couleurBase);
}

// "X jours sur Y" — calculable côté client sans champ dédié en base : une mesure est_partielle=true
// porte toujours sur le mois calendaire EN COURS, donc "aujourd'hui" suffit à dater son avancement.
function badgeMoisPartielTexte(m){
  const auj = new Date();
  const jours = auj.getDate();
  const joursDansMois = new Date(auj.getFullYear(), auj.getMonth() + 1, 0).getDate();
  return `📡 ${moisLisible(m.date)} en cours — donnée provisoire (${jours}/${joursDansMois} jours), non incluse dans les moyennes ci-dessus.`;
}

function tooltipAfterLabelPartiel(toutesLesMesures){
  return (context) => estMesurePartielle(toutesLesMesures[context.dataIndex]) ? '⚠ Mois en cours — donnée provisoire' : undefined;
}
// Palette scalable (teinte + cycle de variantes saturation/luminosité)
const ANGLE_OR_COULEURS = 137.508;
const VARIANTES_COULEURS = [
  { s: 62, l: 40 }, { s: 85, l: 30 }, { s: 45, l: 55 }, { s: 75, l: 48 }, { s: 55, l: 60 },
];
function genererCouleurs(n){
  return Array.from({length:n}, (_, i) => {
    const teinte = Math.round((i * ANGLE_OR_COULEURS) % 360);
    const {s, l} = VARIANTES_COULEURS[i % VARIANTES_COULEURS.length];
    return `hsl(${teinte}, ${s}%, ${l}%)`;
  });
}
async function getMesures(id){
  if (!CAMION_CACHE[id]){
    const res = await fetch(API.mesures(id));
    CAMION_CACHE[id] = await res.json();
  }
  return CAMION_CACHE[id];
}
// Consommation Télématique B : calcul complémentaire "plein à plein", pour les seuls véhicules équipés
// — {disponible:false} pour tous les autres, jamais un chiffre approximatif à la place.
async function getConsommationSourceB(id){
  if (!CONSOMMATION_SOURCE_B_CACHE[id]){
    const res = await fetch(API.consommationSourceB(id));
    CONSOMMATION_SOURCE_B_CACHE[id] = await res.json();
  }
  return CONSOMMATION_SOURCE_B_CACHE[id];
}

// Avertissement « kilométrage source corrompu » : le backend décide (jours de distance négative ou
// > 1 000 km dans mesures_source_b), jamais réimplémenté ici.
async function getKilometrageSourceB(id){
  if (!KILOMETRAGE_SOURCE_B_CACHE[id]){
    const res = await fetch(API.kilometrageSourceB(id));
    KILOMETRAGE_SOURCE_B_CACHE[id] = res.ok ? await res.json() : { applicable: false, corrompu: false };
  }
  return KILOMETRAGE_SOURCE_B_CACHE[id];
}
// sourceBAffiche=false : aucune valeur Télématique B n'est montrée pour ce véhicule (ex. DEMO-002, dont la consommation Télématique B n'est
// jamais calculée) — l'infobulle ne dit alors pas que « les valeurs Télématique B restent affichées ». Le badge, lui, s'affiche
// QUAND MÊME : l'absence de Télématique B est justement une conséquence du problème, pas une raison de le taire.
function badgeKilometrageCorrompuHtml(info, sourceBAffiche = true){
  if (!info?.corrompu) return '';
  const jours = (info.jours || []).slice(0, 8).map(j => `${dateFr(j.date)} : ${fmt(j.distance_km, 0)} km`).join(' ; ');
  const suite = info.nb_jours > 8 ? ` … (+${info.nb_jours - 8})` : '';
  const tip = `Le kilométrage journalier Télématique B de ce véhicule contient ${info.nb_jours} jour(s) négatif(s) ou aberrant(s) (au-delà de ${info.seuil_max_km} km) : ${jours}${suite}. Le compteur du boîtier a été faussé ces jours-là ; ${sourceBAffiche ? 'les valeurs Télématique B (distance, consommation) restent affichées telles quelles mais peuvent être fausses pour les périodes concernées.' : 'aucune consommation Télématique B n\'est calculée pour ce véhicule, et sa distance Télématique B peut être fausse pour les périodes concernées.'}`;
  return `<span class="badge corrompu" title="${tip.replace(/"/g, '&quot;')}">⚠ Kilométrage source corrompu</span>`;
}

// Construit "N véhicules · Site(s) X (& Y...) · Données Télématique A[+Télématique B]" à partir de
// /api/flotte/resume (par_camion.length, clés de par_groupe) — plus jamais "12" en dur, ce texte
// doit rester correct quelle que soit la taille réelle de la flotte.
function texteEffectifFlotte(data){
  const camions = (data?.par_camion ?? []).filter(camionCorrespondFiltresGlobaux);
  const nb = camions.length;
  const sites = [...new Set(camions.map(c => c.groupe))];
  const sitesTxt = sites.length === 0 ? 'Aucun site'
    : sites.length === 1 ? `Site ${sites[0]}`
    : `Sites ${sites.slice(0, -1).join(', ')} & ${sites[sites.length - 1]}`;
  const sourceTxt = SOURCES_COCHES.has('SOURCE_B')
    ? (SOURCES_COCHES.has('SOURCE_A') ? 'Données Télématique A + Télématique B' : 'Données Télématique B uniquement')
    : 'Données Télématique A';
  return `${nb} véhicule${nb > 1 ? 's' : ''} · ${sitesTxt} · ${sourceTxt}`;
}
// ---------- Chargement initial ----------
async function chargerFlotte(){
  CAMION_CACHE = {};
  ETAT_RENDU_POUR = null;
  PROJECTION_CACHE = {};
  // Récupère directement les données déjà scopées sur la période active (select
  // #filtrePeriodeApercu, restauré depuis localStorage par restaurerFiltres.
  const bornes = bornesPeriodeApercu();
  const res = await fetch(urlResumeAvecBornes(bornes.incomplet ? {} : bornes));
  DATA = await res.json();
  document.getElementById('lastUpdateLabel').textContent = 'Données à jour · ' + new Date().toLocaleString('fr-FR');
  document.getElementById('sousTitreFlotte').textContent = texteEffectifFlotte(DATA);
  remplirFiltreMatricule();
  remplirFiltreMarque();
  renderApercu();
  renderGroupes();
  renderMeteo();
  chargerAlertes();
  chargerTypesAlerte();
  renderQualite();
  remplirChipsRegistre();
  remplirChipsCourbes();
  remplirChipsGps();
  reappliquerFiltresApresChargement();
  chargerPrixCarburant();
  chargerPositionsTempsReel();
  chargerPositionsSourceBTempsReel();
  // Bandeau des 5 KPI, scopé en plus par Site/Usage norme interne/Matricule si déjà restaurés
  // depuis localStorage à ce stade (Usage ne le sera pas forcément encore — cf. commentaire de
  // rafraichirKpiBandeau() et chargerSerStandards(), qui rappelle appliquerFiltresGlobaux.
  rafraichirKpiBandeau();
}
async function chargerPrixCarburant(){
  try {
    const res = await fetch(API.prixCarburant);
    PRIX_HISTORIQUE = res.ok ? await res.json() : [];
  } catch (e) {
    PRIX_HISTORIQUE = [];
  }
  // Si la Fiche camion était déjà affichée avec le prix de repli, on la recalcule avec l'historique.
  if (CAMION_ACTIF && CAMION_CACHE[CAMION_ACTIF]) renderFicheCamion();
}
// bornesPeriodeGenerique()/dansPeriode()/initSelecteurPeriode()/dateFr() : voir
// public/js/periode-selecteur.js.
function bornesPeriodeApercu(){ return bornesPeriodeGenerique('Apercu'); }
// Période UNIQUE de l'écran Accueil, pour les blocs calculés côté client (évolution d'un camion,
// comparaison, graphiques par site). Une plage personnalisée pas encore renseignée retombe sur « tout
// l'historique » plutôt que de figer l'affichage sur le rendu précédent.
function bornesPeriodeEcran(){
  const b = bornesPeriodeApercu();
  return b.incomplet ? { depuis: null, jusquA: null } : b;
}
// Libellé humain de la période active du sélecteur #filtrePeriodeApercu.
function libellePeriodeApercu(){
  const sel = document.getElementById('filtrePeriodeApercu');
  // "Tout l'historique" (999) n'envoie aucune borne depuis/jusqu_a : le backend retombe alors sur
  // sa fenêtre glissante des 6 derniers mois pour ces 2 champs précis (jamais l'historique complet,
  // qui ne dirait rien d'une "tendance récente") — le libellé doit refléter ce repli, pas l'option
  // choisie dans le select.
  if (sel.value === '999') return '6 derniers mois';
  if (sel.value === 'custom') {
    const debut = document.getElementById('dateDebutApercu').value;
    const fin = document.getElementById('dateFinApercu').value;
    return (debut && fin) ? `${dateFr(debut)} → ${dateFr(fin)}` : 'période sélectionnée';
  }
  return sel.options[sel.selectedIndex].textContent.toLowerCase();
}
// URL de /api/flotte/resume pour des bornes données ({depuis, jusquA} — l'un ou l'autre peut être
// null). Partagée par chargerFlotte() (chargement initial / Actualiser) et rafraichirKpiPeriodeApercu()
// (changement de période sans rechargement complet), pour ne jamais construire cette URL de deux
// façons différentes.
function urlResumeAvecBornes(bornes){
  const params = new URLSearchParams();
  if (bornes.depuis) params.set('depuis', bornes.depuis);
  if (bornes.jusquA) params.set('jusqu_a', bornes.jusquA);
  // source_principale toujours explicite aux 2 valeurs — cet appel peuple DATA.par_camion, la
  // source commune de tous les chips/tableaux (comme Site/Usage/Matricule, jamais filtrée ici : le
  // filtrage réel se fait client-side via camionCorrespondFiltresGlobaux()/SOURCES_COCHES)
  params.set('source_principale', SOURCES_CONNUES.join(','));
  return API.resume + (params.toString() ? `?${params.toString()}` : '');
}
// Même URL que urlResumeAvecBornes(), avec en plus Site/Usage norme interne/Matricule ("filtres généraux",
// SITES_COCHES/USAGES_COCHES/MATRICULES_COCHES) quand ils représentent une vraie restriction — dédiée
// à rafraichirKpiBandeau() (jamais utilisée pour peupler DATA, cf. commentaire sur KPI_BANDEAU plus
// haut). "__AUCUN__" (cf. AuditController — même jeton que CamionController::AUCUNE_VALEUR) signale
// un Set explicitement vide : indispensable, une chaîne vide en query string ("site=") serait
// normalisée en paramètre absent par Laravel avant même d'atteindre le contrôleur.
function urlResumeAvecFiltresGlobaux(bornes){
  const params = new URLSearchParams();
  if (bornes.depuis) params.set('depuis', bornes.depuis);
  if (bornes.jusquA) params.set('jusqu_a', bornes.jusquA);
  ajouterParametreListeGlobale(params, 'site', SITES_COCHES, SITES_CONNUS.length);
  ajouterParametreListeGlobale(params, 'usage_norme_ref', USAGES_COCHES, USAGES_DISPONIBLES.length);
  ajouterParametreListeGlobale(params, 'matricule', MATRICULES_COCHES, MATRICULES_DISPONIBLES.length);
  ajouterParametreListeGlobale(params, 'marque', MARQUES_COCHES, MARQUES_DISPONIBLES.length);
  // source_principale : TOUJOURS explicite, jamais via ajouterParametreListeGlobale() ci-dessus —
  // cette dernière omet le paramètre quand "tout est coché", ce qui ferait retomber le backend sur
  // son repli par défaut ('SOURCE_A' seul, cf. CamionController::resumeFlotte()) même quand
  // l'utilisateur a explicitement coché les 2 périmètres. Ce repli backend protège les AUTRES
  // appelants de resumeFlotte() (rapport d'audit, Vue Direction, tout code qui ignore ce paramètre)
  // — le dashboard, lui, doit toujours déclarer son intention en toutes lettres.
  params.set('source_principale', SOURCES_COCHES.size === 0 ? '__AUCUN__' : [...SOURCES_COCHES].join(','));
  return API.resume + (params.toString() ? `?${params.toString()}` : '');
}
// coches===null (pas encore initialisé — Usage/Matricule avant leur 1re population) est traité comme
// "tout", même repli que camionCorrespondFiltresGlobaux().
function ajouterParametreListeGlobale(params, nom, coches, totalDisponible){
  if (coches === null || coches.size === totalDisponible) return;
  if (coches.size === 0) { params.set(nom, '__AUCUN__'); return; }
  params.set(nom, [...coches].join(','));
}

async function rafraichirKpiPeriodeApercu(){
  const bornes = bornesPeriodeApercu();
  if (bornes.incomplet) return;
  try {
    const res = await fetch(urlResumeAvecBornes(bornes));
    if (!res.ok) {
      const errJson = await res.json().catch(() => ({}));
      throw new Error(errJson.message || ('HTTP ' + res.status));
    }
    const json = await res.json();
    if (DATA) {
      // Le résumé période-scopée fait autorité pour tous ces champs — cf. CamionController::
      // resumeFlotte() : nb_mesures_*/distance_totale/par_groupe suivent maintenant la période au
      // même titre que eco_score_moyen/conso_moyenne (avant, seuls ces 2 derniers réagissaient — 2
      // KPI sur 5.
      DATA.eco_score_moyen = json.eco_score_moyen;
      DATA.conso_moyenne = json.conso_moyenne;
      DATA.nb_mesures_periode = json.nb_mesures_periode;
      DATA.nb_mesures_totales = json.nb_mesures_totales;
      DATA.nb_mesures_exploitables = json.nb_mesures_exploitables;
      DATA.nb_mesures_en_cours = json.nb_mesures_en_cours;
      DATA.nb_mesures_lacunaires = json.nb_mesures_lacunaires;
      DATA.qualite_mesures = json.qualite_mesures;
      DATA.distance_totale = json.distance_totale;
      if (json.par_camion) DATA.par_camion = json.par_camion;
      if (json.par_groupe) DATA.par_groupe = json.par_groupe;
      renderApercu();
      renderGroupes();
    }
    rafraichirEcransSuivantPeriode();
    rafraichirKpiBandeau();
  } catch(e) {
    console.error('Erreur /api/flotte/resume (période) :', e);
  }
}
// Bandeau des 5 KPI de Vue d'ensemble, scopé par la période ET par Site/Usage norme
// interne/Matricule.
async function rafraichirKpiBandeau(){
  const bornes = bornesPeriodeApercu();
  if (bornes.incomplet) return;
  try {
    const res = await fetch(urlResumeAvecFiltresGlobaux(bornes));
    if (!res.ok) {
      const errJson = await res.json().catch(() => ({}));
      throw new Error(errJson.message || ('HTTP ' + res.status));
    }
    KPI_BANDEAU = await res.json();
    renderApercu();
    // Carte "Qualité des données" (onglet Alertes) : même source KPI_BANDEAU, cf. renderQualite()
    renderQualite();
  } catch(e) {
    console.error('Erreur /api/flotte/resume (bandeau KPI) :', e);
  }
}
document.getElementById('filtrePeriodeApercu').addEventListener('change', (e) => {
  document.getElementById('dateRangeApercu').classList.toggle('hidden', e.target.value !== 'custom');
  if (e.target.value !== 'custom') rafraichirKpiPeriodeApercu();
  sauvegarderFiltres();
});
document.getElementById('btnAppliquerDatesApercu').addEventListener('click', () => { rafraichirKpiPeriodeApercu(); sauvegarderFiltres(); });

function reappliquerFiltresApresChargement(){
  filtrerChips('chipsRegistre', document.getElementById('rechercheRegistre').value);
  filtrerChips('chipsCourbes', document.getElementById('rechercheCourbes').value);
  filtrerChips('chipsGps', document.getElementById('rechercheGps').value);
  document.querySelectorAll('#chipsGps .chip').forEach(c => {
    const id = parseInt(c.dataset.id);
    c.classList.toggle('active', GPS_SELECTION.includes(id));
  });
  document.querySelectorAll('#chipsCourbes .chip').forEach(c => {
    const id = parseInt(c.dataset.id);
    c.classList.toggle('active', COURBES_SELECTION.includes(id));
  });
  // Restaure l'affichage d'un camion actif persisté (localStorage) après rechargement —
  // CAMION_CACHE repart vide à chaque chargement de page, donc un simple renderFicheCamion() ne
  // suffit pas tant que les mesures n'ont pas été refetchées.
  definirSelectionFlotte(SELECTION_FLOTTE);
  verifierSelectionsFiltrees();
  document.getElementById('dateRangeApercu').classList.toggle('hidden', document.getElementById('filtrePeriodeApercu').value !== 'custom');
  // Pas de rafraichirKpiPeriodeApercu() ici : chargerFlotte() (seul appelant de cette fonction) a
  // déjà récupéré /api/flotte/resume avec les bornes de la période active — un second appel
  // identique serait une requête réseau redondante.
  chargerTrajetGps();
  appliquerVerrouMetriqueCourbes();
  chargerCourbes();
}

function camionsFiltresTries(){
  let liste = [...DATA.par_camion];
  liste = liste.filter(camionCorrespondFiltresGlobaux);

  const statutNormeRef = document.getElementById('filtreStatutNormeRef').value;
  if (statutNormeRef !== 'TOUS') liste = liste.filter(c => (statutNormeRefPour(c.id)?.statut ?? 'DONNEES_INSUFFISANTES') === statutNormeRef);

  const recherche = document.getElementById('rechercheCamion').value.trim().toUpperCase();
  if (recherche) liste = liste.filter(c => c.immatriculation.toUpperCase().includes(recherche));

  // Filtres « Alertes actives » : nombre d'alertes actives (celui de la colonne, tous types
  // confondus) ET au moins une alerte active du type choisi ; les deux se combinent entre eux et
  // avec tout ce qui précède.
  if (filtresAlertesApplicables()) {
    const nbF = document.getElementById('filtreNbAlertes').value;
    const typeF = TYPES_ALERTE_INDISPONIBLES ? 'TOUS' : valeurFiltreTypeAlerte();
    if (nbF !== 'TOUS' || typeF !== 'TOUS') {
      const actives = new Map();
      ALERTES_TOUTES.forEach(a => {
        if (a.resolue) return;
        if (!actives.has(a.camion)) actives.set(a.camion, []);
        actives.get(a.camion).push(a.type);
      });
      liste = liste.filter(c => {
        const types = actives.get(c.immatriculation) || [];
        if (nbF === '0' && types.length !== 0) return false;
        if (nbF === '1' && types.length !== 1) return false;
        if (nbF === '2+' && types.length < 2) return false;
        if (typeF !== 'TOUS' && !types.includes(typeF)) return false;
        return true;
      });
    }
  }

  liste.sort((a, b) => {
    let comp;
    if (TRI_COLONNE === 'immatriculation') {
      comp = a.immatriculation.localeCompare(b.immatriculation);
    } else if (TRI_COLONNE === 'groupe') {
      comp = ORDRE_GROUPE_TRI.indexOf(a.groupe) - ORDRE_GROUPE_TRI.indexOf(b.groupe);
    } else {
      comp = a[TRI_COLONNE] - b[TRI_COLONNE];
    }
    return TRI_SENS === 'asc' ? comp : -comp;
  });
  return liste;
}

function renderApercu(){
  if (DATA) document.getElementById('sousTitreFlotte').textContent = texteEffectifFlotte(DATA);
  const kpi = document.getElementById('kpiApercu');
  // KPI_BANDEAU (scopé période + Site/Usage norme interne/Matricule) fait autorité pour ces 5
  // valeurs dès qu'il est disponible ; repli sur DATA (période seule) juste le temps du tout
  // premier rendu, avant que rafraichirKpiBandeau() n'ait résolu — jamais un flash de valeurs non
  // filtrées ensuite)
  const b = KPI_BANDEAU ?? DATA;
  // Éco Score flotte reste TOUJOURS purement Télématique A (aucun équivalent Télématique B, cf.
  // CamionController::resumeFlotte()) — sa valeur ne change jamais avec ce nombre.
  const nbAvecSourceA = (b.par_camion || []).filter(c => c.source_principale === 'SOURCE_A').length;
  const noteCamionsSuivis = b.nb_camions !== nbAvecSourceA
    ? `<div style="font-size:11px;color:var(--grey);margin-top:4px;">dont ${nbAvecSourceA} avec données Télématique A</div>` : '';
  // Badge "Source Télématique B" — même teinte violette que .badge.source_b déjà établie ailleurs
  // (Fiche camion, Synthèse camion, Comparaison des sources) pour l'association visuelle.
  const badgeConsoSourceB = b.conso_moyenne_source === 'source_b'
    ? `<div style="margin-top:4px;"><span class="badge source_b" title="Aucune mesure Télématique A dans la sélection filtrée — moyenne calculée depuis Télématique B (méthode plein-à-plein), jamais mélangée à une moyenne Télématique A. Écart important et systématique connu entre Télématique B et les deux autres sources : à lire comme une indication.">Source Télématique B</span></div>`
    : '';
  const noteDistanceSourceB = b.distance_totale_inclut_source_b
    ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">inclut du kilométrage Télématique B</div>` : '';
  const noteMesuresSourceB = b.nb_mesures_exploitables_inclut_source_b
    ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">inclut des intervalles Télématique B</div>` : '';
  // Litres consommés/Montant carburant : même repli Télématique B PAR VÉHICULE que Distance totale
  // ci-dessus (litres_consommes_inclut_source_b), jamais le repli en bloc de Conso. moyenne.
  const noteLitresSourceB = b.litres_consommes_inclut_source_b
    ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">inclut du carburant Télématique B</div>` : '';
  // Note méthodologique — dès que Distance ou Litres inclut une contribution Télématique B, les
  // deux valeurs ne viennent PLUS de la même source pour tous les véhicules : Distance totale
  // (Télématique B) vient de mesures_source_b (agrégats journaliers "mileage", jours corrompus
  // déjà exclus, cf. CamionController)
  const noteRatioSourceBNonComparable = (b.distance_totale_inclut_source_b || b.litres_consommes_inclut_source_b)
    ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">Distance et litres proviennent de mesures indépendantes par source — leur ratio direct n'est pas comparable à la consommation moyenne affichée.</div>` : '';
  const noteMontantCarburant = b.montant_carburant_prix_l != null
    ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">Prix fixe ${fmt(b.montant_carburant_prix_l, 3)} TND/L</div>` : '';
  // Info-bulle du KPI "Mesures exploitables" — reprend MOT POUR MOT l'explication déjà présente
  // sur la carte "Qualité des données" (onglet Alertes & suggestions, renderQualite()), sans la
  // phrase finale "Détail : « voir le détail »" (ce KPI n'a pas de panneau dépliable ici)
  const bornesSourceBTip = b.qualite_mesures?.source_b?.bornes_l100 || [5, 80];
  const maxSourceATip = b.qualite_mesures?.source_a?.bornes_max_l100 ?? 100;
  const tipMesuresExploitables = `Mesure : une ligne de donnée de consommation — un mois d'un véhicule Télématique A, ou un intervalle plein-à-plein d'un véhicule Télématique B. Exploitable : valeur de consommation valide et plausible (Télématique A : entre 0 et ${maxSourceATip} L/100km ; Télématique B : entre ${bornesSourceBTip[0]} et ${bornesSourceBTip[1]}). Détail complet : onglet Alertes &amp; suggestions, carte « Qualité des données ».`;
  kpi.innerHTML = `
    <div class="kpi"><div class="lbl">Eco Score flotte</div><div class="val" id="kpiEcoScoreFlotteVal">${formatKpiPeriode(b.eco_score_moyen, b.nb_mesures_periode, '/10')}</div></div>
    <div class="kpi"><div class="lbl">Conso. moyenne</div><div class="val" id="kpiConsoMoyenneVal">${formatKpiPeriode(b.conso_moyenne, b.conso_moyenne != null ? 1 : 0, 'L/100km')}</div>${badgeConsoSourceB}</div>
    <div class="kpi"><div class="lbl">Distance totale</div><div class="val">${(b.nb_mesures_periode || b.distance_totale_inclut_source_b) ? `${fmt(b.distance_totale,0)}<small>km</small>` : '<span style="color:var(--grey);">—</span><small>aucune donnée</small>'}</div>${noteDistanceSourceB}${noteRatioSourceBNonComparable}</div>
    <div class="kpi"><div class="lbl">Litres consommés</div><div class="val">${formatKpiPeriode(b.litres_consommes, b.litres_consommes != null ? 1 : 0, 'L')}</div>${noteLitresSourceB}${noteRatioSourceBNonComparable}</div>
    <div class="kpi"><div class="lbl">Montant carburant</div><div class="val">${formatKpiPeriode(b.montant_carburant, b.montant_carburant != null ? 1 : 0, 'TND')}</div>${noteMontantCarburant}</div>
    <div class="kpi"><div class="lbl">Mesures exploitables <span class="info-i" data-tip="${tipMesuresExploitables}">i</span></div><div class="val">${b.nb_mesures_exploitables}<small>/${b.nb_mesures_totales}</small></div>${noteMesuresSourceB}</div>
    <div class="kpi"><div class="lbl">Camions suivis</div><div class="val">${b.nb_camions}</div>${noteCamionsSuivis}</div>
  `;
  const tbody = document.getElementById('tableCamions');
  const liste = camionsFiltresTries();
  document.getElementById('nbCamionsAffiches').textContent = `(${liste.length} affiché(s))`;
  tbody.innerHTML = liste.length ? liste.map(c => {
    const b = bandeEco(c.eco_score_moyen);
    const o = badgeObjectif(c);
    const s = statutInfo(statutNormeRefPour(c.id)?.statut);
    const coche = SELECTION_FLOTTE.includes(c.id);
    return `<tr data-id="${c.id}"${coche ? ' class="sel"' : ''}>
      <td><input type="checkbox" class="chk-flotte" aria-label="Sélectionner ${c.immatriculation}" ${coche ? 'checked' : ''}></td>
      <td><b>${c.immatriculation}</b> ${badgeElargieHtml(c)}</td>
      <td><span class="badge ${c.groupe.toLowerCase()}">${c.groupe}</span></td>
      <td>${c.eco_score_moyen != null ? fmt(c.eco_score_moyen) : '—'}</td>
      <td>${celluleConsoSourceAHtml(c)}</td>
      <td><span class="badge ${b.cls}">${b.label}</span></td>
      <td><span class="badge ${o.cls}">${o.label}</span></td>
      <td><span class="badge ${s.cls}">${s.label}</span></td>
      <td>${celluleAlertesActivesHtml(c)}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="9" style="color:var(--grey);">Aucun camion ne correspond aux filtres actifs.</td></tr>';
  tbody.querySelectorAll('tr[data-id]').forEach(tr => {
    const id = parseInt(tr.dataset.id);
    // Case à cocher : ajoute/retire ce camion. Clic ailleurs sur la ligne : sélection EXCLUSIVE de ce camion
    // (re-clic sur l'unique camion coché = tout décocher).
    tr.querySelector('.chk-flotte').addEventListener('change', () => basculerCamionFlotte(id));
    tr.addEventListener('click', (e) => {
      if (e.target.closest('input')) return;
      definirSelectionFlotte(SELECTION_FLOTTE.length === 1 && SELECTION_FLOTTE[0] === id ? [] : [id]);
    });
  });
  majFlecheTri();
}

// Filtres « Alertes actives » de l'Accueil
// Liste des types d'alertes POSSIBLES : demandée au backend (GET /api/alertes/types, source unique
// AlerteService::TYPES), jamais codée ici.
let TYPE_ALERTE_RESTAURE = null;
let TYPES_ALERTE = [];          // [{type, libelle}]
let TYPES_ALERTE_INDISPONIBLES = false;
let ALERTES_EN_COURS = true;    // vrai tant que le premier chargement de /api/alertes n'a pas abouti (ou échoué)
function valeurFiltreTypeAlerte(){
  const v = document.getElementById('filtreTypeAlerte').value;
  // Avant le chargement de la liste, la préférence restaurée reste celle à sauvegarder (jamais écrasée par « Tous »).
  return (v === 'TOUS' && TYPE_ALERTE_RESTAURE) ? TYPE_ALERTE_RESTAURE : v;
}
function filtresAlertesApplicables(){
  return ALERTES_API_OK && !ALERTES_EN_COURS;
}
function majNoteFiltresAlertes(){
  const note = document.getElementById('noteFiltresAlertes');
  const nb = document.getElementById('filtreNbAlertes'), ty = document.getElementById('filtreTypeAlerte');
  const indispo = !ALERTES_EN_COURS && !ALERTES_API_OK;
  nb.disabled = indispo;
  ty.disabled = indispo || TYPES_ALERTE_INDISPONIBLES;
  const actifs = nb.value !== 'TOUS' || valeurFiltreTypeAlerte() !== 'TOUS';
  let msg = '';
  if (indispo && actifs) msg = 'Alertes indisponibles : les filtres « Alertes actives » ne sont pas appliqués (aucun véhicule n\'est supposé sans alerte).';
  else if (!ALERTES_EN_COURS && TYPES_ALERTE_INDISPONIBLES) msg = 'Liste des types d\'alertes indisponible : le filtre « Type d\'alerte » est désactivé.';
  note.textContent = msg;
  note.classList.toggle('hidden', !msg);
}
function remplirFiltreTypeAlerte(){
  const sel = document.getElementById('filtreTypeAlerte');
  const courant = valeurFiltreTypeAlerte();
  sel.innerHTML = '<option value="TOUS">Tous les types</option>' + TYPES_ALERTE.map(t => `<option value="${t.type}">${t.libelle}</option>`).join('');
  sel.value = TYPES_ALERTE.some(t => t.type === courant) ? courant : 'TOUS';
  if (!TYPES_ALERTE_INDISPONIBLES) TYPE_ALERTE_RESTAURE = null; // appliquée (ou abandonnée si le type n'existe plus) ; gardée si la liste est indisponible
}
async function chargerTypesAlerte(){
  try {
    const res = await fetch(API.alertesTypes);
    if (!res.ok) throw new Error('types indisponibles');
    const types = await res.json();
    if (!Array.isArray(types) || !types.every(t => t && typeof t.type === 'string' && typeof t.libelle === 'string')) throw new Error('réponse inattendue');
    TYPES_ALERTE = types;
    TYPES_ALERTE_INDISPONIBLES = false;
  } catch(e) {
    TYPES_ALERTE = [];
    TYPES_ALERTE_INDISPONIBLES = true;
  }
  remplirFiltreTypeAlerte();
  majNoteFiltresAlertes();
  if (DATA) renderApercu();
}

function celluleAlertesActivesHtml(c){
  if (!ALERTES_API_OK) return '<span style="color:var(--grey);" title="Alertes indisponibles">—</span>';
  const n = ALERTES_TOUTES.filter(a => a.camion === c.immatriculation && !a.resolue).length;
  return n ? `<span class="badge bad">${n}</span>` : '<span style="color:var(--grey);">0</span>';
}

function basculerCamionFlotte(id){
  definirSelectionFlotte(SELECTION_FLOTTE.includes(id) ? SELECTION_FLOTTE.filter(x => x !== id) : [...SELECTION_FLOTTE, id]);
}

// Point d'entrée UNIQUE de la sélection de l'écran Accueil : met à jour l'état, ses vues dérivées
// (CAMION_ACTIF / SELECTION_COMPARATEUR), le tableau maître, la zone de détail et les rendus dépendants.
function definirSelectionFlotte(ids){
  const valides = [...new Set(ids)].filter(id => camionParId(id));
  SELECTION_FLOTTE = valides;
  SELECTION_COMPARATEUR = [...valides];
  const actif = valides.length === 1 ? valides[0] : null;
  if (actif !== CAMION_ACTIF) viderSyntheseCamion(); // invalide aussi les réponses asynchrones en vol (gardes SYNTHESE_CAMION_ID)
  CAMION_ACTIF = actif;
  majAffichageSelectionFlotte();
  if (actif !== null) {
    renderDetailUnCamion();
  } else {
    viderFicheCamion();
    viderSyntheseCamion();
  }
  renderComparateur();
  sauvegarderFiltres();
}

// Rend la sous-section active du détail d'UN camion (l'autre est masquée, donc jamais dessinée).
function renderDetailUnCamion(){
  const actif = CAMION_ACTIF;
  if (actif === null) return;
  if (SOUS_ONGLET_DETAIL === 'etat') {
    if (ETAT_RENDU_POUR !== actif) { ETAT_RENDU_POUR = actif; ouvrirSyntheseCamion(actif); }
  } else {
    getMesures(actif).then(() => { if (CAMION_ACTIF === actif) renderFicheCamion(); }); // garde anti-course
  }
}
function majSousOngletsVisuel(){
  document.querySelectorAll('#sousOngletsDetail .chip').forEach(c => c.classList.toggle('active', c.dataset.sous === SOUS_ONGLET_DETAIL));
  document.getElementById('sousOngletEvolution').classList.toggle('hidden', SOUS_ONGLET_DETAIL !== 'evolution');
  document.getElementById('sousOngletEtat').classList.toggle('hidden', SOUS_ONGLET_DETAIL !== 'etat');
}
function fixerSousOngletDetail(nom){
  SOUS_ONGLET_DETAIL = nom;
  majSousOngletsVisuel();
}
document.querySelectorAll('#sousOngletsDetail .chip').forEach(chip => {
  chip.addEventListener('click', () => { fixerSousOngletDetail(chip.dataset.sous); renderDetailUnCamion(); });
});

function majAffichageSelectionFlotte(){
  document.querySelectorAll('#tableCamions tr[data-id]').forEach(tr => {
    const coche = SELECTION_FLOTTE.includes(parseInt(tr.dataset.id));
    tr.classList.toggle('sel', coche);
    const chk = tr.querySelector('.chk-flotte');
    if (chk) chk.checked = coche;
  });
  const n = SELECTION_FLOTTE.length;
  document.getElementById('detailVueFlotte').classList.toggle('hidden', n > 0);
  document.getElementById('detailSelection').classList.toggle('hidden', n === 0);
  if (n === 0) {
    setTimeout(rafraichirComparateurSites, 0); // graphiques par site : à (re)dessiner une fois le conteneur visible
    return;
  }
  document.getElementById('detailSelectionTitre').textContent = n === 1 ? 'Camion sélectionné' : `${n} camions sélectionnés`;
  document.getElementById('detailSelectionListe').textContent = SELECTION_FLOTTE.map(id => camionParId(id).immatriculation).join(', ');
  document.getElementById('detailUnCamion').classList.toggle('hidden', n !== 1);
  document.getElementById('detailComparaison').classList.toggle('hidden', n < 2);
  majSousOngletsVisuel();
}
// Après un changement de période : les blocs calculés côté client se recalculent sur la nouvelle fenêtre
// (le tableau maître et les KPI viennent, eux, de /api/flotte/resume, déjà rafraîchi par l'appelant).
function rafraichirEcransSuivantPeriode(){
  if (CAMION_ACTIF && CAMION_CACHE[CAMION_ACTIF]) renderFicheCamion();
  if (SELECTION_FLOTTE.length >= 2) renderComparateur();
  rafraichirComparateurSites();
}

function majFlecheTri(){
  document.querySelectorAll('th[data-sort]').forEach(th => {
    let fleche = th.querySelector('.tri-fleche');
    if (!fleche) {
      fleche = document.createElement('span');
      fleche.className = 'tri-fleche';
      fleche.style.marginLeft = '4px';
      th.appendChild(fleche);
    }
    fleche.textContent = th.dataset.sort === TRI_COLONNE ? (TRI_SENS === 'asc' ? '▲' : '▼') : '';
  });
}

function synchroniserSelectTri(){
  const sel = document.getElementById('filtreTri');
  if (TRI_COLONNE === 'eco_score_moyen') sel.value = TRI_SENS === 'desc' ? 'eco_desc' : 'eco_asc';
  else if (TRI_COLONNE === 'conso_moyenne') sel.value = TRI_SENS === 'asc' ? 'conso_asc' : 'conso_desc';
}

// Site/Usage norme interne/Matricule : plus d'écouteur "change" ici depuis le passage au
// multi-select — initMultiSelect('Site'/'Usage'/'Matricule', ...) plus haut câble déjà chaque case
// à cocher directement vers majSitesCoches()/majUsagesCoches()/majMatriculesCoches()
// (propagerFiltresGlobaux())
document.getElementById('btnResetFiltresGlobaux').addEventListener('click', () => {
  // reinitialiserFiltresGlobaux() repeuple déjà les panneaux Usage/Matricule (remplirFiltreUsage()/
  // remplirFiltreMatricule()) — propagerFiltresGlobaux() seule ensuite, jamais appliquerFiltresGlobaux()
  // qui les repeuplerait une 2e fois pour rien.
  reinitialiserFiltresGlobaux();
  propagerFiltresGlobaux();
});
document.getElementById('filtreStatutNormeRef').addEventListener('change', () => { renderApercu(); sauvegarderFiltres(); });
document.getElementById('filtreNbAlertes').addEventListener('change', () => { majNoteFiltresAlertes(); renderApercu(); sauvegarderFiltres(); });
document.getElementById('filtreTypeAlerte').addEventListener('change', () => { TYPE_ALERTE_RESTAURE = null; majNoteFiltresAlertes(); renderApercu(); sauvegarderFiltres(); });
document.getElementById('filtreTri').addEventListener('change', () => {
  const [champ, sens] = document.getElementById('filtreTri').value.split('_');
  TRI_COLONNE = champ === 'eco' ? 'eco_score_moyen' : 'conso_moyenne';
  TRI_SENS = sens;
  renderApercu();
  sauvegarderFiltres();
});
document.getElementById('btnRefresh').addEventListener('click', chargerFlotte);
document.getElementById('rechercheCamion').addEventListener('input', () => { renderApercu(); sauvegarderFiltres(); });
document.getElementById('rechercheRegistre').addEventListener('input', (e) => filtrerChips('chipsRegistre', e.target.value));
document.getElementById('rechercheCourbes').addEventListener('input', (e) => filtrerChips('chipsCourbes', e.target.value));
document.getElementById('rechercheGps').addEventListener('input', (e) => filtrerChips('chipsGps', e.target.value));
document.getElementById('gpsDateDebut').addEventListener('change', chargerTrajetGps);
document.getElementById('gpsDateFin').addEventListener('change', chargerTrajetGps);
document.getElementById('btnResetFiltres').addEventListener('click', () => {
  localStorage.removeItem(FILTRES_KEY);
  SER_GRAPHIQUE_SELECTION = [];
  reinitialiserFiltresGlobaux();
  document.getElementById('filtreStatutNormeRef').value = 'TOUS';
  document.getElementById('filtreNbAlertes').value = 'TOUS';
  TYPE_ALERTE_RESTAURE = null;
  document.getElementById('filtreTypeAlerte').value = 'TOUS';
  majNoteFiltresAlertes();
  document.getElementById('filtreTri').value = 'eco_desc';
  TRI_COLONNE = 'eco_score_moyen';
  TRI_SENS = 'desc';
  document.getElementById('rechercheCamion').value = '';
  document.getElementById('filtrePeriodeApercu').value = '999';
  document.getElementById('dateDebutApercu').value = '';
  document.getElementById('dateFinApercu').value = '';
  document.getElementById('dateRangeApercu').classList.add('hidden');
  definirSelectionFlotte([]);
  // reinitialiserFiltresGlobaux() ci-dessus a déjà repeuplé Usage/Matricule — propagerFiltresGlobaux()
  // seule ici, même raison que btnResetFiltresGlobaux plus haut.
  propagerFiltresGlobaux();
  rafraichirKpiPeriodeApercu();
});

document.querySelectorAll('th[data-sort]').forEach(th => {
  th.addEventListener('click', () => {
    const champ = th.dataset.sort;
    if (TRI_COLONNE === champ) {
      TRI_SENS = TRI_SENS === 'asc' ? 'desc' : 'asc';
    } else {
      TRI_COLONNE = champ;
      TRI_SENS = TRI_SENS_DEFAUT[champ];
    }
    synchroniserSelectTri();
    renderApercu();
    sauvegarderFiltres();
  });
});

// Libellé "Tendance vs SER" renommé "Tendance vs Référence site" : ser_initiale n'est PAS la SER
// norme interne officielle (propre à chaque véhicule), c'est l'ancienne référence de site du fleet
// report Télématique A, déjà dépréciée — garder le mot "SER" ici entretenait une confusion avec le
// reste de l'app.
function renderGroupes(){
  const el = document.getElementById('resumeGroupes');
  const libellePeriode = libellePeriodeApercu();
  el.innerHTML = Object.entries(DATA.par_groupe).map(([nom, g]) => {
    const ecoTxt = g.eco_score_moyen != null
      ? `${fmt(g.eco_score_moyen)} / 10`
      : `<span style="color:var(--grey);">— aucune donnée</span>`;
    const consoTxt = g.conso_moyenne_6_derniers_mois != null
      ? `${fmt(g.conso_moyenne_6_derniers_mois)} L/100km (${libellePeriode})`
      : `<span style="color:var(--grey);">aucune donnée (${libellePeriode})</span>`;
    const largeurBarre = g.conso_moyenne_6_derniers_mois
      ? Math.min(100, (g.objectif_officiel / g.conso_moyenne_6_derniers_mois) * 100)
      : 0;
    const favorable = g.tendance_recente_vs_ser_pct != null ? g.tendance_recente_vs_ser_pct >= 0 : null;
    const couleurBarre = favorable === null ? 'var(--grey)' : (favorable ? 'var(--green)' : 'var(--amber)');
    const couleurTendance = favorable === null ? 'var(--grey)' : (favorable ? 'var(--green)' : 'var(--red)');
    const texteTendance = g.tendance_recente_vs_ser_pct != null
      ? `${g.tendance_recente_vs_ser_pct > 0 ? '+' : ''}${fmt(g.tendance_recente_vs_ser_pct)}%`
      : 'Sans donnée';
    return `
    <div style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span class="badge ${nom.toLowerCase()}">${nom}</span>
        <b>${ecoTxt}</b>
      </div>
      <div style="font-size:12px;color:var(--grey);margin-bottom:4px;">
        ${consoTxt} — objectif ${fmt(g.objectif_officiel,0)} L/100km
      </div>
      <div class="bar-track"><div class="bar-fill" style="width:${largeurBarre}%;background:${couleurBarre};"></div></div>
      <div style="font-size:11px;color:var(--grey);margin-top:4px;">
        Tendance vs Référence site : <b style="color:${couleurTendance}">${texteTendance}</b>
      </div>
    </div>
  `;
  }).join('');
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

function badgeTemperature(temp){
  if (temp == null) return '';
  if (temp >= 35) return `<span class="temp-badge canicule" title="Canicule : risque accru de surconsommation, vigilance chauffeurs">Canicule</span>`;
  if (temp >= 30) return `<span class="temp-badge chaleur" title="Chaleur">Chaleur</span>`;
  if (temp < 10) return `<span class="temp-badge froid" title="Froid : démarrage moteur à froid, ralenti plus long">Froid</span>`;
  return '';
}

function majHorlogeTopbar(){
  const el = document.getElementById('topbarDateHeure');
  const maintenant = new Date();
  // Format court sous le même seuil que la sidebar mobile — la date longue avec jour de la semaine
  // ("vendredi 07 août 2026") contribuait au débordement de .topbar-info.
  const compact = window.matchMedia('(max-width:768px)').matches;
  const date = compact
    ? maintenant.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit'})
    : maintenant.toLocaleDateString('fr-FR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
  const heure = maintenant.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
  el.textContent = `${date} · ${heure}`;
}
majHorlogeTopbar();
setInterval(majHorlogeTopbar, 60000);

// Carte d'une analyse corrective (objet {mois, cause, action, responsable, date_realisation,
// statut_suivi, methode_mesure, suivi_efficacite}) — réutilisée par renderFluxAlertesSuggestions()
// (une par alerte, cf. a.analyses) ET par ouvrirSyntheseCamion() (toutes les analyses du camion
// affiché.
function carteAnalyseCorrectiveHTML(a){
  return `
    <div style="margin-top:8px;padding:10px 12px;background:var(--bg);border-radius:6px;border-left:3px solid var(--navy);font-size:12px;line-height:1.6;">
      <div style="font-weight:600;color:var(--navy);margin-bottom:4px;">📋 ${moisLisible(a.mois + '-01')}${a.statut_suivi ? ` — <span style="font-weight:400;">${a.statut_suivi}</span>` : ''}</div>
      ${a.cause ? `<div><b>Cause :</b> ${a.cause}</div>` : ''}
      ${a.action ? `<div><b>Action :</b> ${a.action}</div>` : ''}
      <div style="color:var(--grey);margin-top:4px;">
        ${a.responsable ? `Responsable : ${a.responsable}` : ''}
        ${a.date_realisation ? ` · Réalisée le ${new Date(a.date_realisation).toLocaleDateString('fr-FR')}` : ''}
        ${a.methode_mesure ? ` · Mesure : ${a.methode_mesure}` : ''}
        ${a.suivi_efficacite ? ` · Suivi efficacité : ${a.suivi_efficacite}` : ''}
      </div>
    </div>`;
}

// Dernière liste d'alertes chargée (toutes, non filtrées) — renderFluxAlertesSuggestions() (statut
// + période) et chargerAlertes()
let ALERTES_TOUTES = [];
let ALERTES_API_OK = false; // false = repli hors-ligne (aucun `camion` exploitable) : la colonne « Alertes actives » affiche « — », jamais 0
async function chargerAlertes(){
  ALERTES_API_OK = false;
  ALERTES_EN_COURS = true;
  try {
    const res = await fetch(API.alertes);
    if (res.ok) {
      ALERTES_TOUTES = await res.json();
      ALERTES_API_OK = true;
    } else {
      throw new Error('API alertes non disponible');
    }
  } catch(e) {
    ALERTES_TOUTES = [];
    DATA.par_camion.forEach(c => {
      if (c.eco_score_moyen < 7) ALERTES_TOUTES.push({severite:'critique', message:`${c.immatriculation} (${c.groupe}) — Eco Score faible : ${fmt(c.eco_score_moyen)}/10`, resolue:false, date_detection:null, jours_actifs:null});
      if (c.conso_moyenne > 45) ALERTES_TOUTES.push({severite:'warning', message:`${c.immatriculation} (${c.groupe}) — consommation élevée : ${fmt(c.conso_moyenne)} L/100km`, resolue:false, date_detection:null, jours_actifs:null});
    });
    Object.entries(DATA.par_groupe).forEach(([nom, g]) => {
      if (g.tendance_recente_vs_ser_pct > 0) ALERTES_TOUTES.push({severite:'warning', message:`Site ${nom} — toujours au-dessus de la SER (+${fmt(g.tendance_recente_vs_ser_pct)}%) sur ${libellePeriodeApercu()}`, resolue:false, date_detection:null, jours_actifs:null});
    });
  }
  ALERTES_EN_COURS = false;
  majNoteFiltresAlertes();
  renderFluxAlertesSuggestions();
  if (DATA) renderApercu(); // colonne « Alertes actives » du tableau maître
}

// Niveau d'urgence COMMUN aux deux : alerte critique / suggestion urgent = rouge ; alerte warning
// / suggestion attention = orange ; suggestion info = gris (une alerte n'a pas de niveau "info")
const NIVEAUX_FLUX = {
  rouge:  { rang: 0, libAlerte: 'Critique', libSuggestion: 'Urgent' },
  orange: { rang: 1, libAlerte: 'À surveiller', libSuggestion: 'Attention' },
  gris:   { rang: 2, libAlerte: 'Info', libSuggestion: 'Info' },
};
let FLUX_TYPE = 'tout'; // 'tout' | 'alerte' | 'suggestion'
let SUGGESTIONS_INDISPONIBLES = false;

function niveauAlerte(a){ return (a.severite === 'critique' || a.severite === 'danger') ? 'rouge' : 'orange'; }
function niveauSuggestion(sg){ return sg.gravite === 'urgent' ? 'rouge' : (sg.gravite === 'attention' ? 'orange' : 'gris'); }

function itemAlerteFluxHtml(a){
  const niveau = niveauAlerte(a);
  return `
    <div class="flux-item flux-alerte niveau-${niveau} ${a.resolue ? 'resolue' : ''}" data-type="alerte">
      <div class="flux-puce" aria-hidden="true">⚠</div>
      <div style="flex:1;min-width:0;">
        <div class="flux-tete">
          <span class="flux-type alerte">Alerte</span>
          <span class="flux-niveau">${NIVEAUX_FLUX[niveau].libAlerte}</span>
          ${a.resolue ? '<span class="badge good">Résolue</span>' : ''}
        </div>
        <div>${a.message}</div>
        <div style="font-size:11px;color:var(--grey);margin-top:2px;">
          ${a.date_detection ? `Détectée le ${new Date(a.date_detection).toLocaleDateString('fr-FR')} —` : ''}
          ${a.resolue
            ? `résolue le ${new Date(a.date_resolution).toLocaleDateString('fr-FR')}`
            : (a.jours_actifs ? `active depuis ${a.jours_actifs} jour(s)` : 'alerte active')}
        </div>
        ${a.analyses && a.analyses.length ? a.analyses.map(carteAnalyseCorrectiveHTML).join('') : ''}
      </div>
    </div>`;
}

function itemSuggestionFluxHtml(sg){
  const niveau = niveauSuggestion(sg);
  const details = Object.entries(sg.valeurs || {})
    .map(([cle, val]) => `${cle} : ${Array.isArray(val) ? val.join(', ') : (val ?? '—')}`)
    .join(' · ');
  return `
    <div class="flux-item flux-suggestion niveau-${niveau}" data-type="suggestion">
      <div class="flux-puce" aria-hidden="true">💡</div>
      <div style="flex:1;min-width:0;">
        <div class="flux-tete">
          <span class="flux-type suggestion">Suggestion</span>
          <span class="flux-niveau">${NIVEAUX_FLUX[niveau].libSuggestion}</span>
        </div>
        <div><b>${sg.camion}</b> — ${sg.message}</div>
        <div style="font-size:11px;color:var(--grey);margin-top:2px;">${sg.regle_declenchee} · ${details}</div>
      </div>
    </div>`;
}

function renderFluxAlertesSuggestions(){
  const filtreStatut = document.getElementById('filtreStatutAlerte').value;
  const bornesBrutes = bornesPeriodeGenerique('Alertes');
  const bornes = bornesBrutes.incomplet ? { depuis: null, jusquA: null } : bornesBrutes;

  // Mêmes filtres qu'avant la fusion, appliqués aux DEUX sources : période sur date_detection (une
  // entrée sans date connue — repli hors-ligne des alertes — est exclue dès qu'une période est active,
  // cf. dansPeriode()) et Site/Usage norme interne/Matricule/Marque/Périmètre via l'immatriculation (ni
  // /api/alertes ni /api/suggestions n'exposent camion_id). Alertes résolues masquées par défaut.
  const alertes = (filtreStatut === 'active' ? ALERTES_TOUTES.filter(a => !a.resolue) : ALERTES_TOUTES)
    .filter(a => dansPeriode(a.date_detection, bornes))
    .filter(a => immatriculationCorrespondFiltresGlobaux(a.camion));
  const suggestions = SUGGESTIONS_TOUTES
    .filter(sg => dansPeriode(sg.date_detection, bornes))
    .filter(sg => immatriculationCorrespondFiltresGlobaux(sg.camion));

  // Compteurs des 3 filtres : calculés AVANT le filtre de type (sinon "Alertes" afficherait 0 dès qu'on
  // est sur "Suggestions"). Suggestions indisponibles : "—", jamais 0 (pas une absence de suggestions).
  const compteSuggestions = SUGGESTIONS_INDISPONIBLES ? '—' : suggestions.length;
  const comptes = { tout: alertes.length + (SUGGESTIONS_INDISPONIBLES ? 0 : suggestions.length), alerte: alertes.length, suggestion: compteSuggestions };
  document.querySelectorAll('#fluxFiltreType .chip').forEach(chip => {
    chip.classList.toggle('active', chip.dataset.type === FLUX_TYPE);
    chip.querySelector('.flux-compte').textContent = `(${comptes[chip.dataset.type]})`;
  });

  const avert = document.getElementById('fluxAvertissement');
  avert.textContent = SUGGESTIONS_INDISPONIBLES ? 'Suggestions indisponibles pour le moment — seules les alertes sont affichées.' : '';
  avert.classList.toggle('hidden', !SUGGESTIONS_INDISPONIBLES);

  const items = [
    ...(FLUX_TYPE === 'suggestion' ? [] : alertes.map(a => ({ type: 'alerte', niveau: niveauAlerte(a), resolue: !!a.resolue, date: a.date_detection, html: itemAlerteFluxHtml(a) }))),
    ...(FLUX_TYPE === 'alerte' || SUGGESTIONS_INDISPONIBLES ? [] : suggestions.map(sg => ({ type: 'suggestion', niveau: niveauSuggestion(sg), resolue: false, date: sg.date_detection, html: itemSuggestionFluxHtml(sg) }))),
  ].sort((x, y) =>
    (Number(x.resolue) - Number(y.resolue))
    || (NIVEAUX_FLUX[x.niveau].rang - NIVEAUX_FLUX[y.niveau].rang)
    || (x.type === y.type ? 0 : (x.type === 'alerte' ? -1 : 1))
    || String(y.date || '').localeCompare(String(x.date || '')));

  const actifs = items.filter(i => !i.resolue);
  const n = niv => actifs.filter(i => i.niveau === niv).length;
  document.getElementById('fluxResumeNiveaux').textContent = actifs.length
    ? `— 🔴 ${n('rouge')} critique/urgent · 🟠 ${n('orange')} à surveiller/attention · ⚪ ${n('gris')} info`
    : '';

  // Suggestions indisponibles : jamais "Aucune suggestion" (ce serait affirmer une absence qu'on ne connaît pas).
  const vide = FLUX_TYPE === 'alerte' ? 'Aucune alerte à afficher.'
    : FLUX_TYPE === 'suggestion' ? (SUGGESTIONS_INDISPONIBLES ? 'Suggestions indisponibles pour le moment.' : 'Aucune suggestion à afficher.')
    : 'Aucune alerte ni suggestion à afficher.';
  document.getElementById('fluxAlertesSuggestions').innerHTML = items.length
    ? items.map(i => i.html).join('')
    : `<div class="alert-line">${vide}</div>`;
}
document.getElementById('filtreStatutAlerte').addEventListener('change', renderFluxAlertesSuggestions);
document.getElementById('fluxFiltreType').addEventListener('click', (e) => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  FLUX_TYPE = chip.dataset.type;
  renderFluxAlertesSuggestions();
});

function renderQualite(){
  // KPI_BANDEAU (scopé période + Site/Usage norme interne/Matricule) fait autorité ici aussi, même
  // substitution que renderApercu()
  const b = KPI_BANDEAU ?? DATA;
  const tot = b.nb_mesures_totales, ok = b.nb_mesures_exploitables;
  const nf = n => (n ?? 0).toLocaleString('fr-FR');
  const resume = document.getElementById('qualiteResume');
  const detail = document.getElementById('qualiteDetail');
  // Aucune mesure sur la sélection (ex. aucun véhicule coché) : jamais « » ni une barre à NaN%.
  if (!tot) {
    resume.innerHTML = '<span style="color:var(--grey);">Aucune mesure sur la sélection actuelle.</span>';
    detail.innerHTML = '';
    return;
  }
  // Définition courte en info-bulle (la définition complète, permanente, ouvre le panneau de détail).
  const bornesTip = b.qualite_mesures?.source_b?.bornes_l100 || [5, 80];
  const maxSourceATip = b.qualite_mesures?.source_a?.bornes_max_l100 ?? 100;
  const tip = `Mesure : une ligne de donnée de consommation — un mois d'un véhicule Télématique A, ou un intervalle plein-à-plein d'un véhicule Télématique B. Exploitable : valeur de consommation valide et plausible (Télématique A : entre 0 et ${maxSourceATip} L/100km ; Télématique B : entre ${bornesTip[0]} et ${bornesTip[1]}). Détail : « voir le détail ».`;
  resume.innerHTML = `
    <div class="qualite-ligne">
      <div><b style="font-size:22px;">${nf(ok)}</b><span style="color:var(--grey);">/${nf(tot)} mesures exploitables<span class="info-i" data-tip="${tip}">i</span></span></div>
      <div class="bar-track" style="flex:1;"><div class="bar-fill" style="width:${(ok/tot)*100}%;background:var(--navy);"></div></div>
    </div>`;

  const q = b.qualite_mesures;
  if (!q) { detail.innerHTML = 'Répartition détaillée indisponible pour le moment.'; return; }
  const o = q.source_a, t = q.source_b, c = q.camions;
  const bornesSourceB = t.bornes_l100 || [5, 80];
  const nonExploitables = tot - ok;
  const moisFr = m => { const [y, mo] = m.split('-'); return new Date(+y, +mo - 1, 1).toLocaleDateString('fr-FR', {month:'long', year:'numeric'}); };
  const ligne = (n, texte) => n > 0 ? `<li><b>${nf(n)}</b> ${texte}</li>` : '';
  const moisPartiels = (o.en_cours_mois || []).map(moisFr).join(', ');

  const compte = [];
  if (o.total > 0 || c.source_a > 0) compte.push(`<li><b>Véhicules Télématique A</b> (${nf(c.source_a)}) : une mesure = <b>un mois d'un véhicule</b> (couple véhicule + mois) — ${nf(o.total)} lignes${o.en_cours ? ', dont ' + nf(o.en_cours) + ' pour des mois non finalisés' : ''}.</li>`);
  if (t.total > 0) compte.push(`<li><b>Véhicules Télématique B à consommation jugée fiable</b> (${nf(c.source_b)}) : une mesure = <b>un intervalle plein-à-plein</b> (du plein N au plein N+1, indépendant du mois civil) — ${nf(t.total)} intervalles, sur tout l'historique Télématique B quel que soit le sélecteur de période.</li>`);
  if (c.non_comptes > 0) compte.push(`<li>${nf(c.non_comptes)} véhicule(s) de la sélection ne sont comptés dans aucun des deux (pas de donnée Télématique A, consommation Télématique B non jugée fiable).</li>`);

  const exclues = [
    ligne(o.exclues_sans_donnee, `mois <b>sans donnée Télématique A</b> (consommation absente) — constaté : mois précédant l'activation de l'abonnement Télématique A du véhicule.`),
    ligne(o.exclues_a_zero, `mois <b>à 0 L/100km</b> — Télématique A renvoie 0 quand il n'a aucune donnée (constaté : interruption de l'abonnement de mai à septembre 2025 sur DEMO-005, DEMO-006, DEMO-009, DEMO-010).`),
    ligne(o.exclues_hors_bornes, `valeur(s) <b>hors bornes de plausibilité</b> (négative ou supérieure à ${nf(o.bornes_max_l100)} L/100km) — anomalie capteur/API ; la valeur reste en base, seulement exclue des calculs.`),
    ligne(o.en_cours, `ligne(s) de <b>mois non finalisé${o.en_cours_mois && o.en_cours_mois.length > 1 ? 's' : ''}</b>${moisPartiels ? ' (' + moisPartiels + ')' : ''} — import intermédiaire encore partiel (mois en cours, ou mois précédent dont l'import de clôture n'a pas encore eu lieu) ; exclues pour ne jamais mélanger un mois complet et un mois entamé.`),
    ligne(t.exclues_sans_distance, `intervalle(s) plein-à-plein <b>sans distance Télématique B</b> enregistrée — consommation non calculable.`),
    ligne(t.exclues_hors_bornes, `intervalle(s) plein-à-plein <b>hors bornes de plausibilité</b> (consommation calculée inférieure à ${nf(bornesSourceB[0])} ou supérieure à ${nf(bornesSourceB[1])} L/100km) — volume de plein ou distance Télématique B suspects ; l'intervalle reste en base, seulement exclu du décompte.`),
  ].join('');

  detail.innerHTML = `
    <div class="qualite-bloc"><b>Ce qui est compté.</b> Une « mesure » n'a pas la même unité selon la source du véhicule :
      <ul>${compte.join('')}</ul></div>
    <div class="qualite-bloc"><b>Ce que « exploitable » signifie.</b> Télématique A : mois clos dont la consommation est <b>supérieure à 0 et au plus ${nf(o.bornes_max_l100)} L/100km</b>. Télématique B : intervalle dont la consommation calculée est <b>comprise entre ${nf(bornesSourceB[0])} et ${nf(bornesSourceB[1])} L/100km</b> (mêmes bornes que la plausibilité d'une moyenne Télématique B). Télématique A : seules les mesures exploitables entrent dans les moyennes. Télématique B : les bornes s'appliquent au décompte ; les moyennes pondérées par la distance, les litres et les kilomètres Télématique B incluent tous les intervalles à distance connue.</div>
    <div class="qualite-bloc"><b>Les ${nf(nonExploitables)} mesures non exploitables</b> (${nf(tot)} − ${nf(ok)}), par raison :
      <ul>${exclues}</ul>
      Elles ne sont jamais comptabilisées comme des valeurs nulles.</div>
  `;
}
document.getElementById('toggleQualite').addEventListener('click', e => {
  const ouvert = document.getElementById('qualiteDetail').classList.toggle('hidden') === false;
  e.currentTarget.textContent = ouvert ? 'masquer le détail' : 'voir le détail';
});

// ---------- Suggestions (règles explicites) — chargement ----------
// Toutes les suggestions actives (l'API ne renvoie déjà que resolue=false, cf. SuggestionController) —
// SUGGESTIONS_TOUTES est mise en cache pour qu'un changement de période n'ait pas besoin de re-fetcher
// /api/suggestions (même pattern que Comparateur/registre manuel/Courbes/Alertes). Rendu : flux fusionné ci-dessus.
let SUGGESTIONS_TOUTES = [];
async function chargerSuggestions(){
  try {
    const res = await fetch(API.suggestions);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    SUGGESTIONS_TOUTES = await res.json();
    SUGGESTIONS_INDISPONIBLES = false;
  } catch (e) {
    console.error('Erreur chargement /api/suggestions :', e);
    SUGGESTIONS_TOUTES = [];
    SUGGESTIONS_INDISPONIBLES = true;
  }
  renderFluxAlertesSuggestions();
}

// ---------- Export CSV réel ----------
document.getElementById('btnExport').addEventListener('click', () => {
  const liste = camionsFiltresTries();
  let csv = 'Immatriculation;Site;EcoScore;ConsommationL100\n';
  liste.forEach(c => { csv += `${c.immatriculation};${c.groupe};${c.eco_score_moyen};${c.conso_moyenne}\n`; });
  telechargerCsv(csv, 'fea_export');
});
document.getElementById('btnExportComparateur').addEventListener('click', () => {
  if (!SELECTION_COMPARATEUR.length){
    alert('Sélectionne au moins un camion dans le comparateur.');
    return;
  }
  // Mêmes bornes que le tableau actuellement affiché (cf. renderComparateur()) — jamais
  // DATA.par_camion brut, qui suit silencieusement la période de Vue d'ensemble, pas celle de cet
  // onglet.
  const bornes = bornesPeriodeEcran();
  let csv = 'Immatriculation;Site;EcoScore;ConsommationL100;ObjectifAtteint\n';
  SELECTION_COMPARATEUR.forEach(id => {
    const base = DATA.par_camion.find(x => x.id === id);
    if (!base || !CAMION_CACHE[id]) return;
    const c = { ...base, ...statsCamionPourPeriode(CAMION_CACHE[id].mesures, bornes) };
    const o = badgeObjectif(c);
    csv += `${c.immatriculation};${c.groupe};${c.eco_score_moyen};${c.conso_moyenne};${o.label}\n`;
  });
  telechargerCsv(csv, 'fea_comparateur');
});

// Export CSV du détail mensuel de Fiche camion — généré côté serveur (league/csv, cf.
// ExportCsvController), contrairement aux exports ci-dessus qui construisent le CSV en JS à partir
// de données déjà en mémoire. Simple navigation vers l'URL : le Content-Disposition:attachment de la
// réponse déclenche le téléchargement du navigateur sans quitter la page.
document.getElementById('btnExportFicheCamionCsv').addEventListener('click', () => {
  if (!CAMION_ACTIF){
    alert('Sélectionne un camion.');
    return;
  }
  window.location.href = API.exportFicheCamion(CAMION_ACTIF);
});

// Fiche camion
// Plus de chips locales : ouvrirFicheCamion() reste appelée depuis une navigation directe (ligne
// de tableau Vue d'ensemble, Comparateur, Synthèse camion...), mais la sélection "de départ".
async function ouvrirFicheCamion(id){
  document.querySelector('.nav-btn[data-tab="apercu"]').click();
  fixerSousOngletDetail('evolution');
  definirSelectionFlotte([id]);
}

document.getElementById('ficheCamionPrix').addEventListener('input', () => {
  if (!CAMION_ACTIF || !CAMION_CACHE[CAMION_ACTIF]) return;
  const mesures = mesuresFiltrees(CAMION_CACHE[CAMION_ACTIF].mesures);
  const prixGasoil = parseFloat(document.getElementById('ficheCamionPrix').value) || 0;
  const { coutKm, estime } = calculerCoutCarburant(mesures, prixGasoil);
  const el = document.getElementById('kpiCoutKm');
  if (el) el.innerHTML = `${fmt(coutKm, 3)}<small>DT/km</small>`;
  const msgEl = document.getElementById('kpiCoutKmEstime');
  if (msgEl) msgEl.style.display = estime ? '' : 'none';
  majCoutAnnuelFiche();
});

// Coût carburant estimé /an — repris de l'ancien Suivi SER/IPÉ.
function calculerCoutAnnuelEstime(mesures, prix){
  const exploitables = mesures.filter(m => m.conso_exploitable && m.km_parcourus > 0);
  if (!exploitables.length) return null;
  const litres = exploitables.reduce((t, m) => t + (m.consommation_l100 * m.km_parcourus) / 100, 0);
  return { coutAn: (litres / exploitables.length) * 12 * prix, litresMois: litres / exploitables.length, moisUtilises: exploitables.length };
}

function majCoutAnnuelFiche(){
  const el = document.getElementById('ficheCoutAnnuel');
  if (!el || !CAMION_ACTIF || !CAMION_CACHE[CAMION_ACTIF]) return;
  const prix = parseFloat(document.getElementById('ficheCamionPrix').value) || 0;
  const r = calculerCoutAnnuelEstime(mesuresFiltrees(CAMION_CACHE[CAMION_ACTIF].mesures), prix);
  el.innerHTML = r
    ? `<div class="lbl">Coût carburant estimé /an</div><div class="val">${fmt(r.coutAn, 0)} <small style="font-weight:400;">TND</small></div>
       <div class="sub">${fmt(r.litresMois, 0)} L/mois en moyenne (${r.moisUtilises} mois de la période) × 12 × ${fmt(prix, 3)} TND/L</div>`
    : `<div class="lbl">Coût carburant estimé /an</div><div class="val">—</div>
       <div class="sub">Aucune mesure Télématique A exploitable sur la période.</div>`;
}

function mesuresFiltrees(mesuresCompletes){
  // Fenêtre EN DATES sur la période unique de l'écran Accueil — même sémantique que
  // /api/flotte/resume (tableau maître) et que le Comparateur, pour qu'un même camion n'affiche
  // jamais deux valeurs différentes sur un même écran.
  return filtrerMesuresParPeriode(mesuresCompletes, bornesPeriodeEcran());
}

// Conducteur Télématique B : saisie manuelle unique depuis le portail Télématique B pour les 6
// camions SITE A (jamais pour les 6 SITE B, absents du parc Télématique B) — pas de
// synchronisation automatique, fraîcheur non garantie.
function conducteurSourceBLigneHtml(infoCamion){
  if (!infoCamion?.conducteur_source_b) return '';
  return `Conducteur (source Télématique B, à confirmer) : <b>${infoCamion.conducteur_source_b}</b> `
    + `<span class="info-i" data-tip="Donnée non vérifiée en fraîcheur — à confirmer avec un utilisateur métier avant utilisation critique.">i</span>`;
}

// Badge de fiabilité Télématique B : réutilise tel quel les 3 statuts de
// ConsommationSourceB::statutPlausibilite()/ statutValeurL100km() (backend, jamais réimplémentés
// côté JS) — PLAUSIBLE (vert, discret), IMPLAUSIBLE_HORS_BORNES (rouge, message d'avertissement
// explicite), DONNEES_INSUFFISANTES (neutre, gris)
function badgePlausibiliteSourceBHtml(statut){
  if (statut === 'PLAUSIBLE') return '<span class="badge good" title="Consommation dans la plage plausible (5-80 L/100km)">Plausible</span>';
  if (statut === 'IMPLAUSIBLE_HORS_BORNES') return '<span class="badge bad" title="Hors de la plage plausible (5-80 L/100km) — valeur affichée telle quelle, jamais masquée">⚠ Implausible — écart avec Télématique A/Registre à vérifier</span>';
  if (statut === 'DONNEES_INSUFFISANTES') return '<span class="badge gris" title="Moins de 5 intervalles ou 1000km cumulés — fiabilité statistique insuffisante pour juger">Donnée insuffisante</span>';
  return '';
}

// Consommation Télématique B : calcul complémentaire "plein à plein" (REPORT_FUEL + distance
// mesures_source_b)
function consommationSourceBCarteHtml(resume, kmInfo){
  if (!resume?.disponible) {
    // Pas de consommation Télématique B : la carte n'apparaît que si le kilométrage Télématique B est lui-même corrompu (sinon rien à dire).
    if (!kmInfo?.corrompu) return '';
    const mois = (kmInfo.mois_jours_aberrants || []).map(moisLisible2).join(', ');
    return `
      <p class="sub">Aucune consommation Télématique B n'est calculée pour ce véhicule. ${badgeKilometrageCorrompuHtml(kmInfo, false)}</p>
      <p class="note">Kilométrage Télématique B : ${kmInfo.nb_jours} jour(s) négatif(s) ou aberrant(s)${mois ? ' — ' + mois : ''}.</p>`;
  }
  return `
    <div class="kpi-strip kpi-strip-6" style="margin-bottom:10px;">
      <div class="kpi"><div class="lbl">Consommation (plein à plein)</div><div class="val">${resume.consommation_l100km}<small>L/100km</small></div></div>
      <div class="kpi"><div class="lbl">Distance couverte</div><div class="val">${fmt(resume.distance_totale_km, 0)}<small>km</small></div></div>
      <div class="kpi"><div class="lbl">Intervalles plein-à-plein</div><div class="val">${resume.nombre_intervalles}</div></div>
    </div>
    <p class="sub">Fiabilité : ${badgePlausibiliteSourceBHtml(resume.statut_plausibilite)}${badgeKilometrageCorrompuHtml(kmInfo) ? ' ' + badgeKilometrageCorrompuHtml(kmInfo) : ''} · Période couverte : ${dateFr(resume.periode_debut)} → ${dateFr(resume.periode_fin)}${resume.partielle_incluse ? ' (inclut le mois en cours, encore incomplet)' : ''}</p>
    <p class="note">Donnée complémentaire dérivée des évènements de plein détectés par Télématique B , <b>pas une mesure continue</b> comme Télématique A — calculée par intervalle entre deux pleins successifs (volume du plein ÷ distance parcourue, distance recalculée depuis notre propre historique Télématique B plutôt que le calcul interne du rapport). Non intégrée à norme interne/SER ni aux KPI Télématique A — à considérer comme une observation de recoupement, pas une source officielle.${resume.statut_plausibilite === 'IMPLAUSIBLE_HORS_BORNES' ? ' <b>Valeur jugée implausible</b> : affichée pour transparence, mais exclue de tout calcul agrégé (moyennes, KPI flotte, graphiques de tendance).' : ''}</p>
  `;
}

function renderFicheCamion(){
  if (!CAMION_ACTIF || !CAMION_CACHE[CAMION_ACTIF]) {
    viderFicheCamion();
    return;
  }
  // Sous-section « Évolution » masquée (ou aucun camion unique affiché) : rien à dessiner — un Chart.js créé dans
  // un conteneur masqué reste bloqué à 0x0 ; afficherSousOngletDetail() re-rend dès qu'elle redevient visible.
  if (document.getElementById('detailUnCamion').classList.contains('hidden') || document.getElementById('sousOngletEvolution').classList.contains('hidden')) return;
  const data = CAMION_CACHE[CAMION_ACTIF];
  const mesures = mesuresFiltrees(data.mesures);
  const infoCamion = DATA.par_camion.find(c => c.id === CAMION_ACTIF);
  const objBadge = infoCamion ? badgeObjectif(infoCamion) : {label:'—', cls:''};

  // --- Calcul L/Heure Moyen ---
  let totalLitres = 0; let totalHeures = 0;
  mesures.forEach(m => {
    if(m.conso_exploitable && m.heures_moteur > 0) {
      totalLitres += (m.consommation_l100 * m.km_parcourus) / 100;
      totalHeures += m.heures_moteur;
    }
  });
  const lHeureMoyenne = totalHeures > 0 ? (totalLitres / totalHeures) : 0;

  const dynStatut = statutInfo(statutNormeRefPour(CAMION_ACTIF)?.statut);
  const consoMoyenne = moyenne(mesures, 'consommation_l100');
  const prixGasoil = parseFloat(document.getElementById('ficheCamionPrix').value) || 0;
  // mesures.length===0 (véhicule élargi Télématique B, ou tout camion sans mesure exploitable sur
  // la période)
  const { coutKm, estime: coutCarburantEstime } = mesures.length
    ? calculerCoutCarburant(mesures, prixGasoil)
    : { coutKm: null, estime: false };
  const distancePeriode = mesures.length ? somme(mesures, 'km_parcourus') : null;

  // mesures.length===0 : Eco Score/Conso affichent "—", jamais "0,0" (moyenne() replie sur 0 pour
  // une liste vide, un repli légitime ailleurs mais qui laisserait ici croire à une vraie mesure
  // nulle — même famille de correctif que resumeFlotte()/Comparateur)
  document.getElementById('kpiCamion').innerHTML = `
    <div class="kpi"><div class="lbl">Camion</div><div class="val" style="font-size:18px;">${data.camion.immatriculation}</div></div>
    <div class="kpi"><div class="lbl">Site</div><div class="val" style="font-size:18px;"><span class="badge ${infoCamion?.groupe?.toLowerCase()}">${infoCamion?.groupe ?? '—'}</span></div></div>
    <div class="kpi"><div class="lbl">Eco Score</div><div class="val">${mesures.length ? fmt(moyenne(mesures,'ecoscore')) : '—'}<small>/10</small></div></div>
    <div class="kpi"><div class="lbl">Conso. (L/100)</div><div class="val">${mesures.length ? fmt(consoMoyenne) : '—'}</div></div>
    <div class="kpi"><div class="lbl">Conso. (L/h)</div><div class="val">${totalHeures > 0 ? fmt(lHeureMoyenne, 1) : '—'}</div></div>
    <div class="kpi"><div class="lbl">Objectif IPÉ</div><div class="val" style="font-size:16px;"><span class="badge ${objBadge.cls}">${objBadge.label}</span></div></div>
    <div class="kpi"><div class="lbl">Statut norme interne</div><div class="val" style="font-size:16px;"><span class="badge ${dynStatut.cls}">${dynStatut.label}</span></div></div>
    <div class="kpi"><div class="lbl">Mesures exploitables <span class="info-i" data-tip="Nombre de relevés mensuels valides, excluant les mois sans données Télématique A (abonnement inactif ou interruption).">i</span></div><div class="val">${mesures.length}</div></div>
    <div class="kpi"><div class="lbl">Coût carburant</div><div class="val" id="kpiCoutKm">${coutKm != null ? fmt(coutKm, 3) : '—'}<small>DT/km</small></div><div id="kpiCoutKmEstime" class="sub" style="margin:4px 0 0;color:var(--amber);font-size:10.5px;line-height:1.3;${coutCarburantEstime ? '' : 'display:none;'}">Prix estimé (pas d'historique renseigné pour cette période)</div></div>
    <div class="kpi"><div class="lbl">Distance (période)</div><div class="val">${distancePeriode != null ? fmt(distancePeriode, 0) : '—'}<small>km</small></div></div>
  `;
  majCoutAnnuelFiche();

  document.getElementById('tableMesuresCamion').innerHTML = mesures.length
    ? [...mesures].reverse().map(m => {
        const litresMois = (m.consommation_l100 * m.km_parcourus) / 100;
        const lHeureMois = m.heures_moteur > 0 ? (litresMois / m.heures_moteur) : 0;
        return `
          <tr>
            <td>${moisLisible(m.date)}</td>
            <td>${fmt(m.consommation_l100)}</td>
            <td class="fw-bold" style="color:var(--navy);">${fmt(lHeureMois, 1)}</td>
            <td>${fmt(m.km_parcourus,0)}</td>
            <td>${m.heures_moteur ? fmt(m.heures_moteur,0) : '—'}</td>
            <td>${fmt(m.ecoscore)}</td>
          </tr>`;
      }).join('')
    : '<tr><td colspan="6" style="color:var(--grey);">Aucune mesure exploitable.</td></tr>';

  // Le mois en cours (au plus un, par construction — cf. estMesurePartielle) n'entre pas dans
  // `mesures` (KPI/tableau ci-dessus) mais reste affiché sur la courbe, visuellement distinct.
  const mesurePartielle = data.mesures.find(estMesurePartielle);
  dessinerChartCamion(mesures, mesurePartielle);

  // LOGIQUE SOUS-SCORES
  const sousScoresDef = [
    { champ:'anticipation',        label:'Anticipation',         couleur:'#1E8449' },
    { champ:'arret',               label:'Arrêts / Ralenti',     couleur:'#B9770E' },
    { champ:'utilisation_optimale',label:'Utilisation moteur',   couleur:'#1F618D' },
    { champ:'utilisation_abusive', label:'Utilisation abusive',  couleur:'#C0392B' },
  ];

  document.getElementById('souscores').innerHTML = sousScoresDef.map(ss => {
    const val = moyenne(mesures, ss.champ);
    const pct = Math.max(0, Math.min(100, (val / 10) * 100));
    const couleurBarre = val >= 7 ? 'var(--green)' : val >= 5 ? 'var(--amber)' : 'var(--red)';
    return `
      <div style="background:var(--bg);border-radius:8px;padding:12px 14px;">
        <div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">${ss.label}</div>
        <div style="font-size:20px;font-weight:700;color:${couleurBarre};margin-bottom:6px;">${val > 0 ? fmt(val) : '—'}<small style="font-size:11px;font-weight:400;color:var(--grey);margin-left:3px;">/10</small></div>
        <div style="height:5px;background:#EAECEE;border-radius:3px;overflow:hidden;">
          <div style="width:${pct}%;height:100%;background:${couleurBarre};border-radius:3px;transition:width .4s;"></div>
        </div>
      </div>`;
  }).join('') + (() => {
    // usage_mode_puissance est un indicateur brut binaire, pas un score /10 : une moyenne serait
    // illisible.
    const modePuissanceMesures = mesures.filter(m => m.usage_mode_puissance !== null && m.usage_mode_puissance !== undefined);
    const moisDetectes = modePuissanceMesures
      .filter(m => m.usage_mode_puissance !== 0)
      .sort((a, b) => new Date(a.date) - new Date(b.date))
      .map(m => moisLisible(m.date));
    const nbDetectes = moisDetectes.length;
    const nbTotal = modePuissanceMesures.length;
    const pct = nbTotal ? Math.round((nbDetectes / nbTotal) * 100) : null;
    const couleur = pct === null ? 'var(--grey)' : pct === 0 ? 'var(--green)' : pct < 50 ? 'var(--amber)' : 'var(--red)';
    return `
      <div style="background:var(--bg);border-radius:8px;padding:12px 14px;">
        <div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Usage mode Puissance (mode assistance)</div>
        <div style="font-size:20px;font-weight:700;color:${couleur};margin-bottom:6px;">${nbTotal ? nbDetectes : '—'}<small style="font-size:11px;font-weight:400;color:var(--grey);margin-left:3px;">${nbTotal ? '/' + nbTotal + ' mois' : ''}</small></div>
        <div style="font-size:11px;color:var(--grey);">${nbTotal ? `Détecté sur ${nbDetectes} mois sur ${nbTotal}` : 'Donnée non disponible sur la période'}</div>
        ${nbDetectes ? `<div style="font-size:10.5px;color:var(--grey);margin-top:4px;">Détecté en : ${moisDetectes.join(', ')}</div>` : ''}
      </div>`;
  })();

  const derniereMesureGlobale = data.mesures.length
    ? data.mesures.reduce((a, b) => (a.date > b.date ? a : b))
    : null;
  document.getElementById('alerteSurregime').classList.toggle(
    'hidden',
    !(derniereMesureGlobale && derniereMesureGlobale.engine_overrev !== null && derniereMesureGlobale.engine_overrev !== undefined && derniereMesureGlobale.engine_overrev !== 0)
  );

  const ctxSS = document.getElementById('chartSousScores');
  if (window._chartSS) window._chartSS.destroy();
  window._chartSS = new Chart(ctxSS, {
    type: 'line',
    data: {
      labels: mesures.map(m => moisLisible(m.date)),
      datasets: sousScoresDef.map(ss => ({
        label: ss.label,
        data: mesures.map(m => m[ss.champ] > 0 ? m[ss.champ] : null),
        borderColor: ss.couleur,
        backgroundColor: 'transparent',
        tension: 0,
        pointRadius: 3,
        spanGaps: true,
      }))
    },
    options: {
      responsive: true,
      ...optionsAspectRatioGraphique(),
      interaction: { mode:'index', intersect:false },
      scales: {
        y: { min:0, max:10, title:{ display:true, text:'Score /10' } },
        x: { grid:{ display:false } }
      }
    }
  });
}

const METRIQUES_SUPP_FICHE = [
  { champ: 'heures_moteur', label: 'Heures moteur' },
  { champ: 'vitesse_moyenne', label: 'Vitesse moyenne (km/h)' },
  { champ: 'nb_arrets', label: "Nombre d'arrêts" },
  { champ: 'conso_horaire', label: 'Consommation horaire (L/h)' },
];
const COULEURS_SUPP_FICHE = ['#8E44AD', '#1F618D'];
const MAX_METRIQUES_SUPP_FICHE = 2;
let FICHE_METRIQUES_SUPP_SELECTIONNEES = [];

function remplirCheckboxesMetriquesSupp(){
  const container = document.getElementById('ficheCamionMetriquesSupp');
  container.innerHTML = METRIQUES_SUPP_FICHE.map(m => `
    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
      <input type="checkbox" class="chk-metrique-supp-fiche" value="${m.champ}">
      ${m.label}
    </label>`).join('');
  container.querySelectorAll('.chk-metrique-supp-fiche').forEach(chk => {
    chk.addEventListener('change', () => toggleMetriqueSuppFiche(chk.value, chk));
  });
}

function toggleMetriqueSuppFiche(champ, chkEl){
  const idx = FICHE_METRIQUES_SUPP_SELECTIONNEES.indexOf(champ);
  if (idx >= 0){
    FICHE_METRIQUES_SUPP_SELECTIONNEES.splice(idx, 1);
  } else if (FICHE_METRIQUES_SUPP_SELECTIONNEES.length >= MAX_METRIQUES_SUPP_FICHE){
    chkEl.checked = false;
    document.getElementById('ficheCamionLimiteMsg').classList.remove('hidden');
    return;
  } else {
    FICHE_METRIQUES_SUPP_SELECTIONNEES.push(champ);
  }
  document.getElementById('ficheCamionLimiteMsg').classList.add('hidden');
  const atteint = FICHE_METRIQUES_SUPP_SELECTIONNEES.length >= MAX_METRIQUES_SUPP_FICHE;
  document.querySelectorAll('.chk-metrique-supp-fiche').forEach(chk => {
    if (!chk.checked) chk.disabled = atteint;
  });
  renderFicheCamion();
}

let chartCamionInstance = null;
function viderFicheCamion(){
  document.getElementById('kpiCamion').innerHTML = '';
  document.getElementById('tableMesuresCamion').innerHTML = '';
  if (chartCamionInstance) { chartCamionInstance.destroy(); chartCamionInstance = null; }
}
function dessinerChartCamion(mesures, mesurePartielle){
  const ctx = document.getElementById('chartCamion');
  // Le mois en cours (s'il existe) rejoint la série uniquement pour l'affichage — jamais compté
  // dans `mesures` lui-même (KPI/tableau, calculés en amont sans lui). Cf. stylePointsAvecPartiel
  // pour le rendu distinct de ce dernier point.
  const toutesLesMesures = mesurePartielle ? [...mesures, mesurePartielle] : mesures;
  const labels = toutesLesMesures.map(m => moisLisible(m.date));
  if (chartCamionInstance) chartCamionInstance.destroy();

  const datasets = [
    { label:'Eco Score', data: toutesLesMesures.map(m=>m.ecoscore), borderColor:'#B9770E', yAxisID:'y', tension:0, ...stylePointsAvecPartiel(toutesLesMesures, '#B9770E') },
    { label:'Conso L/100km', data: toutesLesMesures.map(m=>m.consommation_l100), borderColor:'#1C2833', yAxisID:'y1', tension:0, ...stylePointsAvecPartiel(toutesLesMesures, '#1C2833') },
  ];
  const scales = {
    y: { position:'left', min:0, max:10, title:{display:true,text:'Eco Score'} },
    y1:{ position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'L/100km'} },
  };

  // Cible norme interne — même logique de choix d'unité que SerDynamiqueController (via /api/ser-dynamique).
  // Pas de conversion numérique entre l/h et l/100km : la cible est déjà exprimée dans l'unité
  // propre au camion. En mode l/100km elle partage l'axe déjà présent (comparable à Conso L/100km) ;
  // en mode l/h elle utilise un axe dédié mais masqué pour ne pas fausser l'échelle L/100km existante
  // ni ajouter un axe visible supplémentaire (budget de 4 axes visibles réservé aux métriques cochables).
  const dynInfo = statutNormeRefPour(CAMION_ACTIF);
  if (dynInfo && dynInfo.cible != null){
    const axeCible = dynInfo.unite === 'l/h' ? 'yCibleNormeRef' : 'y1';
    datasets.push({
      label: `Cible norme interne (${fmt(dynInfo.cible)} ${libelleUnite(dynInfo.unite)})`,
      data: toutesLesMesures.map(() => dynInfo.cible),
      borderColor: '#1E8449',
      borderDash: [6, 4],
      pointRadius: 0,
      yAxisID: axeCible,
      tension: 0,
    });
    if (axeCible === 'yCibleNormeRef'){
      scales.yCibleNormeRef = { display: false, position: 'right' };
    }
  }

  FICHE_METRIQUES_SUPP_SELECTIONNEES.forEach((champ, i) => {
    const def = METRIQUES_SUPP_FICHE.find(m => m.champ === champ);
    const axisId = 'ySupp' + i;
    const couleur = COULEURS_SUPP_FICHE[i % COULEURS_SUPP_FICHE.length];
    datasets.push({
      label: def.label,
      data: toutesLesMesures.map(m => m[champ] != null && m[champ] > 0 ? m[champ] : null),
      borderColor: couleur,
      backgroundColor: couleur,
      yAxisID: axisId,
      tension: 0,
      spanGaps: true,
      ...stylePointsAvecPartiel(toutesLesMesures, couleur),
    });
    scales[axisId] = {
      position: i % 2 === 0 ? 'left' : 'right',
      title: { display: true, text: def.label, color: couleur },
      ticks: { color: couleur },
      grid: { drawOnChartArea: false },
    };
  });

  document.getElementById('projectionInfo').classList.add('hidden');

  const badgeEl = document.getElementById('badgeMoisPartielCamion');
  if (mesurePartielle){
    badgeEl.textContent = badgeMoisPartielTexte(mesurePartielle);
    badgeEl.classList.remove('hidden');
  } else {
    badgeEl.classList.add('hidden');
  }

  chartCamionInstance = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive:true,
      ...optionsAspectRatioGraphique(),
      interaction:{mode:'index',intersect:false},
      plugins: { tooltip: { callbacks: { afterLabel: tooltipAfterLabelPartiel(toutesLesMesures) } } },
      scales,
    }
  });

  if (AFFICHER_PROJECTION){
    ajouterProjectionAuChart(CAMION_ACTIF, toutesLesMesures, labels, datasets, scales);
  }
}

async function ajouterProjectionAuChart(camionId, mesures, labelsBase, datasetsBase, scales){
  const [projEco, projConso] = await Promise.all([
    getProjection(camionId, 'ecoscore'),
    getProjection(camionId, 'consommation_l100'),
  ]);

  // Le camion actif (ou l'option) a pu changer pendant l'attente de la réponse.
  if (camionId !== CAMION_ACTIF || !AFFICHER_PROJECTION) return;

  const messages = [];
  const datasets = [...datasetsBase];
  let projectionAAjouter = null;

  const ajouter = (proj, label, valeurReelle, axisId) => {
    if (!proj || proj.erreur){
      messages.push(`${label} : projection indisponible`);
      return;
    }
    if (!proj.fiable){
      messages.push(`${label} : ${proj.message} (R²=${fmt(proj.r2, 2)})`);
      return;
    }
    projectionAAjouter = proj.projection;
    const data = new Array(labelsBase.length - 1).fill(null);
    data.push(valeurReelle);
    proj.projection.forEach(p => data.push(p.valeur));
    datasets.push({
      label: `${label} (projection)`,
      data,
      borderColor: '#909497',
      borderDash: [5, 4],
      pointRadius: 0,
      yAxisID: axisId,
      tension: 0,
    });
    messages.push(`${label} : R²=${fmt(proj.r2, 2)}`);
  };

  ajouter(projEco, 'Eco Score', mesures[mesures.length - 1]?.ecoscore ?? null, 'y');
  ajouter(projConso, 'Conso L/100km', mesures[mesures.length - 1]?.consommation_l100 ?? null, 'y1');

  const infoEl = document.getElementById('projectionInfo');
  if (messages.length){
    infoEl.textContent = 'Fiabilité de la tendance : ' + messages.join(' · ');
    infoEl.classList.remove('hidden');
  }

  if (!projectionAAjouter) return; // aucune des deux métriques n'a une tendance fiable

  const labels = [...labelsBase, ...projectionAAjouter.map(p => moisLisible(p.mois + '-01'))];
  datasets.forEach(ds => { while (ds.data.length < labels.length) ds.data.push(null); });

  if (chartCamionInstance) chartCamionInstance.destroy();
  chartCamionInstance = new Chart(document.getElementById('chartCamion'), {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive:true,
      ...optionsAspectRatioGraphique(),
      interaction:{mode:'index',intersect:false},
      plugins: { tooltip: { callbacks: { afterLabel: tooltipAfterLabelPartiel(mesures) } } },
      scales,
    }
  });
}

// ---------- Comparateur ----------
let chartCompareEcoInstance = null, chartCompareConsoInstance = null, chartCompareConsoHoraireInstance = null, chartCompareTrendInstance = null;

function derniereValeur(mesures, champ){
  for (let i = mesures.length - 1; i >= 0; i--){
    if (mesures[i][champ] != null && mesures[i][champ] > 0) return mesures[i][champ];
  }
  return null;
}

async function renderComparateur(){
  const thead = document.getElementById('theadComparateur');
  const tbody = document.getElementById('tableComparateur');

  // Comparaison = 2 camions ou plus (1 seul : sous-sections Évolution / État actuel). En dessous, on vide et on
  // détruit les graphiques : ils sont dans un conteneur masqué (0x0) et seraient bloqués à cette taille.
  if (SELECTION_COMPARATEUR.length < 2){
    thead.innerHTML = '<th>Camion</th>';
    tbody.innerHTML = '<tr><td style="color:var(--grey);">Coche au moins deux camions pour les comparer.</td></tr>';
    if (chartCompareEcoInstance) chartCompareEcoInstance.destroy();
    if (chartCompareConsoInstance) chartCompareConsoInstance.destroy();
    if (chartCompareConsoHoraireInstance) chartCompareConsoHoraireInstance.destroy();
    if (chartCompareTrendInstance) chartCompareTrendInstance.destroy();
    return;
  }

  // Instantané figé de la sélection pour toute la durée de cette exécution — SELECTION_COMPARATEUR
  // est un tableau mutable partagé, et cette fonction est async (plusieurs `await` avant la fin) :
  // sans ça.
  const selection = [...SELECTION_COMPARATEUR];

  await Promise.all(selection.map(id => getMesures(id)));
  // Appel périmé : la sélection a changé pendant le chargement (un rendu plus récent a pris le relais, ou on
  // est repassé sous 2 camions et le conteneur est masqué) — ne rien dessiner.
  if (selection.length !== SELECTION_COMPARATEUR.length || selection.some((id, i) => id !== SELECTION_COMPARATEUR[i])) return;

  // Une plage personnalisée incomplète retombe sur "tout l'historique" plutôt que de figer
  // l'affichage sur les données du rendu précédent.
  const bornes = bornesPeriodeEcran();

  const infos = selection.map(id => {
    const base = DATA.par_camion.find(c => c.id === id);
    return { ...base, ...statsCamionPourPeriode(CAMION_CACHE[id].mesures, bornes) };
  });
  const consoHoraireParCamion = {};
  selection.forEach(id => {
    consoHoraireParCamion[id] = derniereValeur(filtrerMesuresParPeriode(CAMION_CACHE[id].mesures, bornes), 'conso_horaire');
  });

  // Orientation : UN CAMION PAR LIGNE, UN INDICATEUR PAR COLONNE (avant : l'inverse)
  const colonnes = [
    {lbl:'Site', fn:c => `<span class="badge ${c.groupe.toLowerCase()}">${c.groupe}</span>`},
    {lbl:'Eco Score', fn:c => c.eco_score_moyen != null ? fmt(c.eco_score_moyen) : '—'},
    {lbl:'Consommation (L/100km)', fn:c => celluleConsoSourceAHtml(c)},
    {lbl:'Consommation/Heure (L/h)', fn:c => consoHoraireParCamion[c.id] != null ? fmt(consoHoraireParCamion[c.id]) : '—'},
    {lbl:`Objectif IPÉ <span class="info-t" title="Atteint = statut norme interne Vert foncé (IPE ≤ cible officielle du véhicule). Dérivé du même calcul que Statut norme interne, jamais de la consommation Télématique A — les deux ne peuvent donc plus se contredire.">i</span>`, fn:c => { const o = badgeObjectif(c); return `<span class="badge ${o.cls}">${o.label}</span>`; }},
    {lbl:`Mesures exploitables <span class="info-t" title="Nombre de relevés mensuels valides, excluant les mois sans données Télématique A (abonnement inactif ou interruption).">i</span>`, fn:c => c.nb_mesures_exploitables},
  ];
  thead.innerHTML = '<th>Camion</th>' + colonnes.map(k => `<th>${k.lbl}</th>`).join('');
  tbody.innerHTML = infos.map(c => `<tr data-id="${c.id}" title="Ouvrir le détail du camion"><td><b>${c.immatriculation}</b> ${badgeElargieHtml(c)}</td>${colonnes.map(k => `<td>${k.fn(c)}</td>`).join('')}</tr>`).join('');

  tbody.querySelectorAll('tr[data-id]').forEach(tr => {
    tr.addEventListener('click', () => ouvrirFicheCamion(parseInt(tr.dataset.id)));
  });

  const couleurs = genererCouleurs(infos.length);

  const ctxEco = document.getElementById('chartCompareEco');
  if (chartCompareEcoInstance) chartCompareEcoInstance.destroy();
  chartCompareEcoInstance = new Chart(ctxEco, {
    type:'line',
    data:{ labels: infos.map(c=>c.immatriculation), datasets:[{ data: infos.map(c=>c.eco_score_moyen), backgroundColor: couleurs, pointBackgroundColor: couleurs, pointRadius: 5, borderColor: '#1C2833', tension: 0 }] },
    options:{ responsive:true, ...optionsAspectRatioGraphique(), plugins:{legend:{display:false}}, scales:{y:{min:0,max:10}} }
  });

  const ctxConso = document.getElementById('chartCompareConso');
  if (chartCompareConsoInstance) chartCompareConsoInstance.destroy();
  chartCompareConsoInstance = new Chart(ctxConso, {
    type:'line',
    data:{ labels: infos.map(c=>c.immatriculation), datasets:[{ data: infos.map(c=>c.conso_moyenne), backgroundColor: couleurs, pointBackgroundColor: couleurs, pointRadius: 5, borderColor: '#1C2833', tension: 0 }] },
    options:{ responsive:true, ...optionsAspectRatioGraphique(), plugins:{legend:{display:false}} }
  });

  const ctxConsoHoraire = document.getElementById('chartCompareConsoHoraire');
  if (chartCompareConsoHoraireInstance) chartCompareConsoHoraireInstance.destroy();
  chartCompareConsoHoraireInstance = new Chart(ctxConsoHoraire, {
    type:'bar',
    data:{ labels: infos.map(c=>c.immatriculation), datasets:[{ data: infos.map(c => consoHoraireParCamion[c.id]), backgroundColor: couleurs, borderRadius: 4 }] },
    options:{ responsive:true, ...optionsAspectRatioGraphique(), plugins:{legend:{display:false}}, scales:{y:{title:{display:true,text:'L/h'}}} }
  });

  const datasets = selection.map((id, i) => {
    // Pilotée par le même sélecteur de période que le reste de l'onglet (plus de "12 derniers
    // mois" fixe) — "Tout l'historique" (bornes vides) n'a alors plus besoin d'un slice().
    const mesures = filtrerMesuresParPeriode(CAMION_CACHE[id].mesures, bornes).filter(m => m.ecoscore > 0);
    return {
      label: infos.find(c=>c.id===id).immatriculation,
      data: mesures.map(m => ({x: moisLisible(m.date), date: m.date, y: m.ecoscore})),
      borderColor: couleurs[i], tension:0, fill:false,
    };
  });
  // labelsUnion trié chronologiquement par la vraie date sous-jacente (pas par ordre de rencontre
  // des véhicules) — sans ce tri.
  const dateParLabel = new Map();
  datasets.forEach(d => d.data.forEach(p => { if (!dateParLabel.has(p.x)) dateParLabel.set(p.x, p.date); }));
  const labelsUnion = [...dateParLabel.keys()].sort((a, b) => new Date(dateParLabel.get(a)) - new Date(dateParLabel.get(b)));
  const ctxTrend = document.getElementById('chartCompareTrend');
  if (chartCompareTrendInstance) chartCompareTrendInstance.destroy();
  chartCompareTrendInstance = new Chart(ctxTrend, {
    type:'line',
    data:{ labels: labelsUnion, datasets: datasets.map(d => ({
      label:d.label, borderColor:d.borderColor, tension:0,
      data: labelsUnion.map(l => { const pt = d.data.find(p=>p.x===l); return pt ? pt.y : null; })
    })) },
    options:{ responsive:true, ...optionsAspectRatioGraphique(), scales:{y:{min:0,max:10,title:{display:true,text:'Eco Score'}}} }
  });
}

// Standards SER
// Toutes les valeurs (Usage, SER, Incertitude, Cible) viennent du registre via
// sheets:sync-registre : plus d'édition en ligne.

// Bornes de la période des colonnes "réel" : Registre est mensuel, donc la période est ramenée à
// des MOIS ENTIERS côté serveur (SerStandardsController)
function bornesMoisSerStandards(){
  if (document.getElementById('filtrePeriodeSerStandards').value !== 'custom') {
    return bornesPeriodeGenerique('SerStandards');
  }
  const debut = document.getElementById('dateDebutSerStandards').value;
  const fin = document.getElementById('dateFinSerStandards').value;
  if (!debut || !fin) return { incomplet: true };
  const [anneeFin, moisFin] = fin.split('-').map(Number);
  const dernierJour = new Date(anneeFin, moisFin, 0).getDate();
  return { depuis: debut + '-01', jusquA: fin + '-' + String(dernierJour).padStart(2, '0') };
}

function libellePeriodeSerStandards(){
  const select = document.getElementById('filtrePeriodeSerStandards');
  if (select.value === 'custom') {
    const debut = document.getElementById('dateDebutSerStandards').value;
    const fin = document.getElementById('dateFinSerStandards').value;
    return debut && fin ? `du mois ${debut} au mois ${fin}` : 'plage personnalisée incomplète';
  }
  return select.options[select.selectedIndex].text.toLowerCase();
}

function majNoteSerStandardsPeriode(){
  document.getElementById('noteSerStandardsPeriode').textContent =
    `Colonnes « réel » : ${libellePeriodeSerStandards()}, en mois entiers — données Registre uniquement (saisies en M+1 : le mois en cours est vide), en sommes et non en moyennes. « x/y mois » : mois renseignés sur mois attendus ; « — » : valeur non saisie. Les colonnes de référence norme interne et le statut ne dépendent pas de la période.`;
}

async function chargerSerStandards(){
  const bornes = bornesMoisSerStandards();
  // Plage personnalisée pas encore complète : on garde l'affichage courant (jamais de requête sur des
  // bornes devinées).
  if (bornes.incomplet) return;
  majNoteSerStandardsPeriode();
  const params = new URLSearchParams();
  if (bornes.depuis) params.set('depuis', bornes.depuis);
  if (bornes.jusquA) params.set('jusqu_a', bornes.jusquA);
  try {
    const res = await fetch(API.serStandards + (params.toString() ? '?' + params.toString() : ''));
    if (!res.ok) throw new Error('HTTP ' + res.status);
    SER_STANDARDS_DATA = await res.json();
  } catch(e) {
    console.error('Erreur chargement /api/ser-standards :', e);
    SER_STANDARDS_DATA = [];
  }
  // usage_norme_ref (utilisé par le filtre global Usage/Type) n'est connu qu'à travers cette réponse —
  // appliquerFiltresGlobaux() répercute donc aussi sur les onglets déjà chargés (apercu, chips, select SER).
  appliquerFiltresGlobaux();
}

// Standards SER enrichi : cellules "réel" et "statut"
// Graphie d'affichage d'une unité de SER/IPÉ renvoyée par l'API ('l/h' | 'l/100km'), l'unité
// dépend de l'usage du véhicule (NormeRefStatutService::uniteSer), plus une unique « norme en L/h
// ».
function libelleUnite(u){ return u === 'l/h' ? 'L/h' : (u === 'l/100km' ? 'L/100km' : (u ?? '')); }
function valeurAvecUnite(v, u){ return v != null ? `${fmt(v)} <small>${libelleUnite(u)}</small>` : '—'; }

function escAttr(texte){
  return String(texte).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

// Une cellule "réel" : valeur + "x/y mois" (orange si la couverture est partielle), ou "—" avec une
// info-bulle qui explique POURQUOI la valeur est absente — jamais un 0 à la place d'une absence.
function celluleReelleHtml(valeur, mois, moisPeriode, formatter, unite, titreAbsent){
  const couverture = `<small class="sub-mois ${mois < moisPeriode ? 'partiel' : ''}">${mois}/${moisPeriode} mois</small>`;
  if (valeur == null) {
    return `<td class="reel"><span style="color:var(--grey);" title="${escAttr(titreAbsent)}">—</span>${couverture}</td>`;
  }
  return `<td class="reel"><b>${formatter(valeur)}</b>${unite ? ` <small>${unite}</small>` : ''}${couverture}</td>`;
}

const RAISONS_COURTES_SER = {
  ABONNEMENT_RECENT: 'abonnement récent',
  UNITE_INCOMPATIBLE: 'unité incompatible',
  SER_NORME_REF_NON_DEFINIE: 'SER non définie',
  MOIS_NON_SAISI: 'mois non saisi',
};

// Statut norme interne du DERNIER mois exploité (SER_DYN_DATA, cf. /api/ser-dynamique) — jamais recalculé sur la
// période choisie : la SER peut avoir changé dans l'intervalle, comparer un agrégat à la SER actuelle
// serait trompeur. La raison d'un statut "Donnée manquante" (texteDonneesInsuffisantes) vit ici, en
// info-bulle + libellé court, depuis la suppression de l'écran SER dynamique.
function contenuStatutSerHtml(camionId){
  const d = statutNormeRefPour(camionId);
  if (!d) return '<span class="sub-mois">…</span>';
  const s = statutInfo(d.statut);
  if (d.statut === 'DONNEES_INSUFFISANTES') {
    const raison = RAISONS_COURTES_SER[d.raison_insuffisance] ?? 'raison inconnue';
    const saisi = d.unite === 'l/100km' && d.ipe != null
      ? ` · saisi : ${fmt(d.ipe)} L/100km` : '';
    return `<span class="badge ${s.cls}" title="${escAttr(texteDonneesInsuffisantes(d))}" style="cursor:help;">${s.label}</span>`
      + `<small class="sub-mois" title="${escAttr(texteDonneesInsuffisantes(d))}">${raison}${saisi}</small>`;
  }
  return `<span class="badge ${s.cls}">${s.label}</span>`
    + `<small class="sub-mois">IPÉ ${fmt(d.ipe)} ${libelleUnite(d.unite)} · ${d.derniere_date ? moisLisible(d.derniere_date) : '—'}</small>`;
}

// Mise à jour d'UNE cellule de statut, sans reconstruire le tableau : chargerSerDynamique() est pollé
// toutes les 45 s (et poussé en direct par WebSocket) — un rendu complet fermerait une édition de
// cellule (SER/Incertitude/Cible) en cours de saisie.
function majCelluleStatutSer(camionId){
  const cellule = document.querySelector(`#tableSerStandards tr[data-ligne-id="${camionId}"] .cell-statut`);
  if (cellule) cellule.innerHTML = contenuStatutSerHtml(camionId);
}
function rafraichirStatutsSerStandards(){
  // Lecture seule (plus d'édition de cellule à préserver) : si le filtre Statut ou le tri dépend
  // du statut.
  if (document.getElementById('filtreStatutSerStandards').value !== 'TOUS' || TRI_SER_COLONNE === 'statut') { renderSerStandards(); return; }
  document.querySelectorAll('#tableSerStandards tr[data-ligne-id]').forEach(tr => {
    majCelluleStatutSer(parseInt(tr.dataset.ligneId));
  });
}

function ligneSerStandardsHtml(c){
  const p = c.periode ?? { mois_periode: 0, ipe_mois: 0, ipe_lh_mois: 0, km_mois: 0, heures_mois: 0, conso_l100_mois: 0 };
  const y = p.mois_periode;
  const sansHeures = p.heures_mois === 0;
  const enL100 = c.unite_ser === 'l/100km';
  const uniteSer = libelleUnite(c.unite_ser);
  const titreIpe = y === 0 ? 'Aucun mois Registre attendu sur cette période pour ce véhicule.'
    : (enL100
      ? 'Litres et km jamais saisis ensemble sur cette période.'
      : (sansHeures && p.conso_l100 != null
        ? "Heures de fonctionnement non saisies dans Registre sur cette période — IPÉ (L/h) non calculable, la SER de cet usage étant en L/h (voir Conso. L/100km)."
        : 'Litres et heures jamais saisis ensemble sur cette période.'));
  const avecUnite = v => v != null ? `${fmt(v)} <small>${uniteSer}</small>` : '—';
  const titreAbsent = y === 0 ? 'Aucun mois Registre attendu sur cette période pour ce véhicule.' : 'Non saisi dans Registre sur cette période.';
  return `
    <tr data-ligne-id="${c.id}">
      <td data-id="${c.id}" title="Ouvrir le détail du camion"><b>${c.immatriculation}</b> ${badgeElargieHtml(c)} <button type="button" class="btn-historique-ser" data-id="${c.id}" data-immat="${c.immatriculation}" title="Historique des changements de SER norme interne">🕒</button></td>
      <td data-id="${c.id}" title="Ouvrir le détail du camion"><span class="badge ${(c.groupe || '').toLowerCase()}">${c.groupe ?? '—'}</span></td>
      <td>${c.usage_norme_ref ?? '—'}</td>
      <td>${avecUnite(c.ser_norme_ref)}</td>
      <td>${c.incertitude_pct != null ? fmt(c.incertitude_pct * 100, 0) + '%' : '—'}</td>
      <td>${avecUnite(c.ser_1x)}</td>
      <td>${avecUnite(c.cible)}</td>
      <td class="cell-statut">${contenuStatutSerHtml(c.id)}</td>
      <td>${c.pct_ser_usage != null ? fmt(c.pct_ser_usage * 100, 1) + '%' : '—'}</td>
      <td>${c.pe_lh_precis != null ? `${fmt(c.pe_lh_precis, 4)} <small>L/h</small>` : '—'}</td>
      <td>${c.litres_dernier_mois != null ? `${fmt(c.litres_dernier_mois, 0)} <small>L</small>` : '—'}</td>
      ${celluleReelleHtml(p.ipe, p.ipe_mois, y, v => fmt(v, 2), uniteSer, titreIpe)}
      ${celluleReelleHtml(p.km, p.km_mois, y, v => fmt(v, 0), 'km', titreAbsent)}
      ${celluleReelleHtml(p.heures, p.heures_mois, y, v => fmt(v, 0), 'h', y === 0 ? titreAbsent : 'Heures de fonctionnement non saisies dans Registre sur cette période.')}
      ${celluleReelleHtml(p.conso_l100, p.conso_l100_mois, y, v => fmt(v, 1), 'L/100km', titreAbsent)}
    </tr>`;
}

// Valeur triée d'une colonne du tableau ; null = « — » (jamais trié comme un 0 : les absences vont toujours en
// dernier, quel que soit le sens).
function valeurTriSer(c, colonne){
  const p = c.periode ?? {};
  switch (colonne){
    case 'immatriculation': return c.immatriculation;
    case 'groupe': { const i = ORDRE_GROUPE_TRI.indexOf(c.groupe); return i < 0 ? 99 : i; }
    case 'usage_norme_ref': return c.usage_norme_ref ?? null;
    case 'statut': return RANG_STATUT_SER[statutNormeRefPour(c.id)?.statut ?? 'DONNEES_INSUFFISANTES'];
    case 'ipe': return p.ipe ?? null;
    case 'km': return p.km ?? null;
    case 'heures': return p.heures ?? null;
    case 'conso_l100': return p.conso_l100 ?? null;
    default: return c[colonne] ?? null; // ser_norme_ref, incertitude_pct, ser_1x, cible, pct_ser_usage, pe_lh_precis, litres_dernier_mois
  }
}

// Filtres globaux (Site/Usage/Matricule/Marque/Périmètre) PUIS recherche + statut du tableau, puis tri.
function serStandardsFiltresTries(){
  let liste = SER_STANDARDS_DATA.filter(camionCorrespondFiltresGlobaux);

  const statut = document.getElementById('filtreStatutSerStandards').value;
  if (statut !== 'TOUS') liste = liste.filter(c => (statutNormeRefPour(c.id)?.statut ?? 'DONNEES_INSUFFISANTES') === statut);

  const recherche = document.getElementById('rechercheSerStandards').value.trim().toUpperCase();
  if (recherche) liste = liste.filter(c => c.immatriculation.toUpperCase().includes(recherche));

  liste.sort((a, b) => {
    const va = valeurTriSer(a, TRI_SER_COLONNE), vb = valeurTriSer(b, TRI_SER_COLONNE);
    if (va == null || vb == null) return va == null && vb == null ? 0 : (va == null ? 1 : -1);
    const comp = typeof va === 'string' ? va.localeCompare(vb, 'fr') : va - vb;
    return TRI_SER_SENS === 'asc' ? comp : -comp;
  });
  return liste;
}

function majFlecheTriSer(){
  document.querySelectorAll('th[data-sort-ser]').forEach(th => {
    let fleche = th.querySelector('.tri-fleche');
    if (!fleche) {
      fleche = document.createElement('span');
      fleche.className = 'tri-fleche';
      fleche.style.marginLeft = '4px';
      th.appendChild(fleche);
    }
    fleche.textContent = th.dataset.sortSer === TRI_SER_COLONNE ? (TRI_SER_SENS === 'asc' ? '▲' : '▼') : '';
  });
}

function renderSerStandards(){
  const tbody = document.getElementById('tableSerStandards');
  const donnees = serStandardsFiltresTries();
  tbody.innerHTML = donnees.length ? donnees.map(ligneSerStandardsHtml).join('')
    : `<tr><td colspan="15" style="color:var(--grey);">${SER_STANDARDS_DATA.length ? 'Aucun camion ne correspond aux filtres actifs.' : 'Aucune donnée SER disponible.'}</td></tr>`;
  majFlecheTriSer();
}

document.querySelectorAll('th[data-sort-ser]').forEach(th => {
  th.addEventListener('click', (e) => {
    if (e.target.closest('.info-t')) return; // toucher la pastille d'aide ne doit pas trier
    const champ = th.dataset.sortSer;
    if (TRI_SER_COLONNE === champ) {
      TRI_SER_SENS = TRI_SER_SENS === 'asc' ? 'desc' : 'asc';
    } else {
      TRI_SER_COLONNE = champ;
      TRI_SER_SENS = TRI_SER_SENS_DEFAUT[champ];
    }
    renderSerStandards();
    sauvegarderFiltres();
  });
});
document.getElementById('rechercheSerStandards').addEventListener('input', () => { renderSerStandards(); sauvegarderFiltres(); });
document.getElementById('filtreStatutSerStandards').addEventListener('change', () => { renderSerStandards(); sauvegarderFiltres(); });
document.getElementById('btnResetTableSerStandards').addEventListener('click', () => {
  document.getElementById('rechercheSerStandards').value = '';
  document.getElementById('filtreStatutSerStandards').value = 'TOUS';
  TRI_SER_COLONNE = 'groupe';
  TRI_SER_SENS = 'asc';
  renderSerStandards();
  sauvegarderFiltres();
});

let chartSerL100Instance = null;

// Il est VIDE par défaut : on choisit les véhicules à tracer par des chips, ou « Tout afficher ».
function construireDatasetsSerStandards(camions, unite){
  const lbl = libelleUnite(unite);
  return [
    { label: `SER officielle (${lbl})`, data: camions.map(c => c.ser_norme_ref), borderColor: '#5D6D7E', pointRadius: 5, tension: 0 },
    { label: `Seuil incertitude (${lbl})`, data: camions.map(c => c.ser_1x), borderColor: '#B9770E', pointRadius: 5, tension: 0 },
    { label: `Cible (${lbl})`, data: camions.map(c => c.cible), borderColor: '#1E8449', pointRadius: 5, tension: 0 },
    { label: `Valeur réelle — dernier mois exploité (${lbl})`, data: camions.map(c => c.unite_reelle === unite ? (c.valeur_reelle ?? null) : null), borderColor: '#1C2833', pointRadius: 5, tension: 0, spanGaps: false },
  ];
}

// Véhicules proposables : filtres globaux ET SER exprimée en L/100km (les autres unités n'ont plus de graphique).
function candidatsGraphiqueSerL100(){
  return SER_STANDARDS_DATA.filter(camionCorrespondFiltresGlobaux).filter(c => c.unite_ser === 'l/100km');
}
function camionsTracesSerL100(){
  return candidatsGraphiqueSerL100().filter(c => SER_GRAPHIQUE_SELECTION.includes(c.id));
}
function basculerCamionGraphiqueSer(id){
  SER_GRAPHIQUE_SELECTION = SER_GRAPHIQUE_SELECTION.includes(id) ? SER_GRAPHIQUE_SELECTION.filter(x => x !== id) : [...SER_GRAPHIQUE_SELECTION, id];
  renderGraphiqueSerL100();
  sauvegarderFiltres();
}

function renderGraphiqueSerL100(){
  if (SER_STANDARDS_DATA.length) SER_GRAPHIQUE_SELECTION = SER_GRAPHIQUE_SELECTION.filter(id => SER_STANDARDS_DATA.some(c => c.id === id));
  const candidats = candidatsGraphiqueSerL100();
  const traces = camionsTracesSerL100();
  const chips = document.getElementById('chipsSerGraphique');
  chips.innerHTML = candidats.map(c => `<button class="chip${SER_GRAPHIQUE_SELECTION.includes(c.id) ? ' active' : ''}" data-id="${c.id}">${c.immatriculation} ${badgeElargieHtml(c)}</button>`).join('');
  chips.querySelectorAll('.chip').forEach(chip => chip.addEventListener('click', () => basculerCamionGraphiqueSer(parseInt(chip.dataset.id))));
  document.getElementById('btnSerGraphToutAfficher').disabled = !candidats.length || traces.length === candidats.length;
  document.getElementById('btnSerGraphToutEffacer').disabled = !traces.length;

  const nbHorsL100 = SER_STANDARDS_DATA.filter(camionCorrespondFiltresGlobaux).length - candidats.length;
  const noteHors = nbHorsL100 ? ` · ${nbHorsL100} véhicule(s) en L/h non représenté(s) (le graphique L/h a été retiré)` : '';
  document.getElementById('sousTitreChartSerL100').textContent = candidats.length
    ? `${traces.length} véhicule(s) affiché(s) sur ${candidats.length}${noteHors}`
    : `Aucun véhicule en L/100km avec les filtres actifs${noteHors}`;

  const canvas = document.getElementById('chartSerL100');
  const wrap = document.getElementById('chartSerL100Wrap');
  const zone = document.getElementById('chartSerL100Zone');
  const vide = document.getElementById('chartSerL100Vide');
  if (chartSerL100Instance) { chartSerL100Instance.destroy(); chartSerL100Instance = null; }
  wrap.classList.toggle('hidden', !traces.length);
  vide.classList.toggle('hidden', !!traces.length);
  vide.textContent = candidats.length ? 'Aucun véhicule sélectionné : choisissez des véhicules ci-dessus, ou « Tout afficher ».' : 'Aucun véhicule à afficher avec les filtres actifs.';
  if (!traces.length) return;

  // Sur mobile, 27 points ne tiennent pas dans ~300 px : la zone du graphique prend alors ~30 px
  // par véhicule et défile horizontalement (le conteneur, pas la page) plutôt que d'écraser le
  // tracé.
  const mobile = window.innerWidth < SEUIL_LARGEUR_MOBILE_GRAPHIQUES;
  const largeurNecessaire = traces.length * 30;
  // Largeur disponible ESTIMÉE depuis la fenêtre (marges de page + carte ≈ 100 px) : le conteneur, lui, mesure 0 tant
  // que l'onglet est masqué (rendu au chargement), ce qui aurait donné un graphique de quelques dizaines de pixels.
  const defile = mobile && largeurNecessaire > window.innerWidth - 100;
  zone.style.width = defile ? largeurNecessaire + 'px' : '100%';
  zone.style.height = mobile ? '360px' : '420px';
  if (defile) document.getElementById('sousTitreChartSerL100').textContent += ' · faites défiler le graphique horizontalement';
  chartSerL100Instance = new Chart(canvas, {
    type: 'line',
    data: { labels: traces.map(c => c.immatriculation), datasets: construireDatasetsSerStandards(traces, 'l/100km') },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { boxWidth: mobile ? 14 : 40, font: { size: mobile ? 10 : 12 } } } },
      scales: { y: { title: { display: true, text: 'L/100km' } }, x: { ticks: { autoSkip: false, maxRotation: 90, minRotation: traces.length > 6 ? 60 : 0, font: { size: 10 } } } } },
  });
}
document.getElementById('btnSerGraphToutAfficher').addEventListener('click', () => {
  SER_GRAPHIQUE_SELECTION = [...new Set([...SER_GRAPHIQUE_SELECTION, ...candidatsGraphiqueSerL100().map(c => c.id)])];
  renderGraphiqueSerL100();
  sauvegarderFiltres();
});
document.getElementById('btnSerGraphToutEffacer').addEventListener('click', () => {
  const visibles = new Set(candidatsGraphiqueSerL100().map(c => c.id));
  SER_GRAPHIQUE_SELECTION = SER_GRAPHIQUE_SELECTION.filter(id => !visibles.has(id)); // n'efface que ce qui est proposé
  renderGraphiqueSerL100();
  sauvegarderFiltres();
});

document.getElementById('tableSerStandards').addEventListener('click', (e) => {
  const btnHistorique = e.target.closest('.btn-historique-ser');
  if (btnHistorique) {
    ouvrirHistoriqueSer(parseInt(btnHistorique.dataset.id), btnHistorique.dataset.immat);
    return;
  }
  const identCell = e.target.closest('td[data-id]');
  if (identCell) ouvrirFicheCamion(parseInt(identCell.dataset.id));
});

// ---------- Historique des changements de SER norme interne (modal, depuis Standards SER) ----------
let chartHistoriqueSerInstance = null;
let HISTORIQUE_SER_UNITE = ''; // unité de la SER du véhicule dont l'historique est ouvert (cf. ouvrirHistoriqueSer)

function fermerHistoriqueSer(){
  document.getElementById('modalHistoriqueSer').classList.add('hidden');
  if (chartHistoriqueSerInstance) { chartHistoriqueSerInstance.destroy(); chartHistoriqueSerInstance = null; }
}
document.getElementById('btnFermerHistoriqueSer').addEventListener('click', fermerHistoriqueSer);
document.getElementById('modalHistoriqueSer').addEventListener('click', (e) => {
  if (e.target.id === 'modalHistoriqueSer') fermerHistoriqueSer();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && !document.getElementById('modalHistoriqueSer').classList.contains('hidden')) fermerHistoriqueSer();
});

async function ouvrirHistoriqueSer(camionId, immatriculation){
  const modal = document.getElementById('modalHistoriqueSer');
  const corps = document.getElementById('corpsModalHistoriqueSer');
  document.getElementById('titreModalHistoriqueSer').textContent = `Historique SER norme interne — ${immatriculation}`;
  HISTORIQUE_SER_UNITE = libelleUnite(SER_STANDARDS_DATA.find(s => s.id === camionId)?.unite_ser);
  corps.innerHTML = '<p class="sub">Chargement…</p>';
  modal.classList.remove('hidden');

  try {
    const res = await fetch(API.serHistorique(camionId));
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    renderHistoriqueSer(data.historique ?? []);
  } catch(e) {
    corps.innerHTML = `<p class="sub" style="color:var(--red);">Historique indisponible pour le moment (${e.message}).</p>`;
  }
}

function renderHistoriqueSer(periodes){
  const corps = document.getElementById('corpsModalHistoriqueSer');
  if (!periodes.length) {
    corps.innerHTML = '<p class="sub">Aucun historique de changement de SER enregistré pour ce véhicule.</p>';
    return;
  }

  const ligneTableau = (p) => `
    <tr>
      <td>${moisLisible(p.date_debut)} → ${p.date_fin ? moisLisible(p.date_fin) : '<b style="color:var(--green);">en cours</b>'}</td>
      <td>${fmt(p.ser)}${HISTORIQUE_SER_UNITE ? ' ' + HISTORIQUE_SER_UNITE : ''}</td>
      <td>${p.incertitude != null ? fmt(p.incertitude * 100, 0) + '%' : '—'}</td>
      <td>${p.cible != null ? fmt(p.cible) : '—'}</td>
      <td style="color:var(--grey);font-size:11px;">${p.source ?? '—'}</td>
    </tr>`;

  corps.innerHTML = `
    <canvas id="chartHistoriqueSer" height="140"></canvas>
    <table style="margin-top:16px;">
      <thead><tr><th>Période</th><th>SER</th><th>Incertitude</th><th>Cible</th><th>Source</th></tr></thead>
      <tbody>${periodes.map(ligneTableau).join('')}</tbody>
    </table>`;

  // Courbe en escalier (step chart) : chaque période garde sa valeur de SER jusqu'à son changement
  // suivant, plutôt qu'une interpolation linéaire qui suggérerait une transition progressive alors
  // que le changement de SER est ponctuel (nouvelle décision/donnée à une date précise).
  const labels = [];
  const valeurs = [];
  periodes.forEach(p => {
    labels.push(p.date_debut);
    valeurs.push(p.ser);
    if (p.date_fin) {
      labels.push(p.date_fin);
      valeurs.push(p.ser);
    }
  });
  labels.push(new Date().toISOString().slice(0, 10));
  valeurs.push(periodes[periodes.length - 1].ser);

  const ctx = document.getElementById('chartHistoriqueSer');
  chartHistoriqueSerInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels.map(d => moisLisible(d)),
      datasets: [{ label: `SER norme interne${HISTORIQUE_SER_UNITE ? ' (' + HISTORIQUE_SER_UNITE + ')' : ''}`, data: valeurs, borderColor: '#1C2833', stepped: 'before', pointRadius: 3, tension: 0 }],
    },
    options: { responsive: true, ...optionsAspectRatioGraphique(), plugins: { legend: { display: false } }, scales: { y: { title: { display: true, text: HISTORIQUE_SER_UNITE } } } },
  });
}

// SER dynamique
// Source unique de rafraîchissement pour tout ce qui dépend de SER_DYN_DATA (colonne Statut de
// Standards SER, Vue d'ensemble, Fiche camion, Synthèse camion) — appelée au chargement initial et
// par le polling périodique (cf. "Démarrage" en bas de fichier)
async function chargerSerDynamique(){
  try {
    const res = await fetch(API.serDynamique);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    SER_DYN_DATA = await res.json();
  } catch(e) {
    console.error('Erreur chargement /api/ser-dynamique :', e);
    SER_DYN_DATA = [];
  }
  rafraichirStatutsSerStandards();
  if (DATA) renderApercu();
  if (CAMION_ACTIF && CAMION_CACHE[CAMION_ACTIF]) renderFicheCamion();
  rafraichirBadgeSyntheseStatut();
}

function statutInfo(statut){
  const map = {
    ROUGE: { cls: 'rouge', bord: 'var(--red)', label: 'Rouge' },
    ORANGE: { cls: 'orange', bord: 'var(--amber)', label: 'Orange' },
    VERT_CLAIR: { cls: 'vert-clair', bord: 'var(--green)', label: 'Vert clair' },
    VERT_FONCE: { cls: 'vert-fonce', bord: 'var(--green)', label: 'Vert foncé' },
    DONNEES_INSUFFISANTES: { cls: 'gris', bord: 'var(--grey)', label: 'Donnée manquante' },
  };
  return map[statut] || { cls: 'gris', bord: 'var(--grey)', label: statut ?? '—' };
}

// raison_insuffisance vient du backend (NormeRefStatutService) — aucune déduction de date côté JS,
// pour que le frontend ne devine jamais une raison qu'il ne peut pas connaître avec certitude.
function texteDonneesInsuffisantes(c){
  switch (c.raison_insuffisance){
    case 'ABONNEMENT_RECENT':
      return `Abonnement Télématique A récent (actif depuis ${c.source_a_actif_depuis ? new Date(c.source_a_actif_depuis).toLocaleDateString('fr-FR') : '—'})`;
    case 'UNITE_INCOMPATIBLE':
      return "Donnée saisie en L/100km, mais la SER de ce véhicule (usage hors Transport) est en L/h — heures de fonctionnement non renseignées ce mois-ci, statut non calculable de façon fiable.";
    case 'SER_NORME_REF_NON_DEFINIE':
      return "Mesure Registre disponible, mais aucune SER norme interne n'est renseignée pour ce véhicule dans le registre pour l'instant.";
    case 'MOIS_NON_SAISI':
    default:
      return 'Donnée du mois en cours pas encore saisie dans le registre.';
  }
}

// Prototype temps réel — statut norme interne
// Met à jour SER_DYN_DATA puis UNE SEULE cellule de statut de Standards SER (majCelluleStatutSer),
// sans reconstruire le tableau.
function mettreAJourUnStatutSer(c){
  const idx = SER_DYN_DATA.findIndex(s => s.id === c.id);
  if (idx >= 0) SER_DYN_DATA[idx] = c; else SER_DYN_DATA.push(c);

  majCelluleStatutSer(c.id);

  if (DATA) renderApercu();
  if (CAMION_ACTIF === c.id && CAMION_CACHE[CAMION_ACTIF]) renderFicheCamion();
  rafraichirBadgeSyntheseStatut();
}

// ---------- Comparateur registre manuel vs Télématique A ----------
let chartComparateurRegistreInstance = null;
let COMPARATEUR_REGISTRE_UNITE = 'l100';
let COMPARATEUR_REGISTRE_DERNIERE_COMPARAISON = [];
// Comparaison complète (tous les mois) du dernier /api/comparateur-registre/{id} chargé — jamais
// re-fetchée pour un simple changement de période, contrairement à COMPARATEUR_REGISTRE_DERNIERE_
// COMPARAISON ci-dessus qui, elle, ne contient que le sous-ensemble filtré actuellement affiché.
let COMPARATEUR_REGISTRE_TOUTE = [];
// 3e source Télématique B — vrai/faux pour le camion actuellement affiché, pilote à la fois
// l'affichage des 3 colonnes Télématique B du tableau et le badge "N sources" à côté du titre.
let COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE = false;

function viderComparateurRegistre(){
  document.querySelectorAll('#chipsRegistre .chip').forEach(c => c.classList.remove('active'));
  document.getElementById('tableComparateurRegistre').innerHTML = '<tr><td colspan="13" style="color:var(--grey);">Sélectionne un camion ci-dessus.</td></tr>';
  document.getElementById('badgeNbSourcesComparateur').classList.add('hidden');
  document.getElementById('badgeKmCorrompuComparateur').classList.add('hidden');
  document.getElementById('thComparateurSourceBL100').classList.add('hidden');
  document.getElementById('thComparateurSourceBEcart').classList.add('hidden');
  document.getElementById('thComparateurSourceBFiab').classList.add('hidden');
  COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE = false;
  if (chartComparateurRegistreInstance) { chartComparateurRegistreInstance.destroy(); chartComparateurRegistreInstance = null; }
}

function remplirChipsRegistre(){
  const el = document.getElementById('chipsRegistre');
  el.innerHTML = DATA.par_camion.map(c => `<button class="chip" data-id="${c.id}">${c.immatriculation} ${badgeElargieHtml(c)}</button>`).join('');
  el.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
      COMPARATEUR_REGISTRE_ID = parseInt(chip.dataset.id);
      document.querySelectorAll('#chipsRegistre .chip').forEach(c => c.classList.toggle('active', c === chip));
      chargerComparateurRegistre();
    });
  });
}

function fiabiliteInfo(f){
  const map = {
    COHERENT: { cls: 'good', label: 'Cohérent' },
    A_VERIFIER: { cls: 'mid', label: 'À vérifier' },
    DIVERGENT: { cls: 'bad', label: 'Divergent' },
    DONNEE_MANQUANTE: { cls: 'gris', label: 'Donnée manquante' },
  };
  return map[f] || { cls: 'gris', label: f ?? '—' };
}

async function chargerComparateurRegistre(){
  if (!COMPARATEUR_REGISTRE_ID) return;
  const tbody = document.getElementById('tableComparateurRegistre');
  tbody.innerHTML = '<tr><td colspan="13" style="color:var(--grey);">Chargement…</td></tr>';
  let json;
  try {
    const res = await fetch(API.comparateurRegistre(COMPARATEUR_REGISTRE_ID));
    if (!res.ok) throw new Error('HTTP ' + res.status);
    json = await res.json();
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="13" style="color:var(--red);">Erreur de chargement : ${e.message}</td></tr>`;
    console.error('Erreur /api/comparateur-registre :', e);
    return;
  }
  renderComparateurRegistre(json);
}

function renderComparateurRegistre(json){
  COMPARATEUR_REGISTRE_TOUTE = json.comparaison || [];
  COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE = !!json.source_b_disponible;
  // Seules les sources qui ont réellement au moins un point pour ce véhicule sont comptées (json.sources) —
  // jamais Télématique A « fantôme » pour un véhicule Télématique B-only. Quand Télématique B manque, le motif distingue l'absence
  // structurelle de boîtier (SITE B) d'un boîtier présent dont la consommation n'est pas calculable.
  const src = json.sources || { source_a: true, registre: true, source_b: COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE };
  const nomsSources = [src.source_a && 'Télématique A', src.registre && 'Registre', src.source_b && 'Télématique B'].filter(Boolean);
  const nbSources = nomsSources.length;

  const badge = document.getElementById('badgeNbSourcesComparateur');
  badge.classList.remove('hidden');
  badge.className = 'badge ' + (nbSources === 3 ? 'source_b' : 'gris');
  badge.textContent = (nbSources === 0 ? 'Aucune source de données' : `${nbSources} source${nbSources > 1 ? 's' : ''} : ${nomsSources.join(' + ')}`)
    + (!src.source_b && json.source_b_motif_indisponible ? ` (${json.source_b_motif_indisponible})` : '');

  // Kilométrage Télématique B corrompu : badge à côté de « 3 sources », uniquement quand les colonnes Télématique B sont affichées
  // (sans valeur Télématique B visible sur cet écran, il n'y a rien à avertir). Les mois dont un intervalle plein-à-plein
  // contient un jour aberrant portent en plus le badge sur la valeur elle-même (cf. celluleSourceBL100).
  COMPARATEUR_REGISTRE_KM_SOURCE_B = json.kilometrage_source_b || null;
  const badgeKm = document.getElementById('badgeKmCorrompuComparateur');
  // Toujours affiché quand le kilométrage Télématique B est corrompu, même sans colonne Télématique B (véhicule dont la consommation Télématique B n'est
  // pas calculée, ex. DEMO-002). Les badges PAR MOIS, eux, n'existent que dans les colonnes Télématique B (plus bas).
  badgeKm.innerHTML = badgeKilometrageCorrompuHtml(COMPARATEUR_REGISTRE_KM_SOURCE_B, COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE);
  badgeKm.classList.toggle('hidden', !badgeKm.innerHTML);

  document.getElementById('thComparateurSourceBL100').classList.toggle('hidden', !COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE);
  document.getElementById('thComparateurSourceBEcart').classList.toggle('hidden', !COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE);
  document.getElementById('thComparateurSourceBFiab').classList.toggle('hidden', !COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE);

  appliquerPeriodeRegistre();
}

// Reapplique le sélecteur de période sur COMPARATEUR_REGISTRE_TOUTE (déjà en cache, pas de nouvel
// appel réseau) — appelée après chargement et à chaque changement de période.
function appliquerPeriodeRegistre(){
  const bornesBrutes = bornesPeriodeGenerique('Registre');
  const bornes = bornesBrutes.incomplet ? { depuis: null, jusquA: null } : bornesBrutes;
  const comparaison = (bornes.depuis || bornes.jusquA)
    ? COMPARATEUR_REGISTRE_TOUTE.filter(l => dansPeriode(l.mois + '-01', bornes))
    : COMPARATEUR_REGISTRE_TOUTE;
  COMPARATEUR_REGISTRE_DERNIERE_COMPARAISON = comparaison;

  const tbody = document.getElementById('tableComparateurRegistre');
  const moisKmSourceBAffectes = new Set(COMPARATEUR_REGISTRE_KM_SOURCE_B?.mois_intervalles_affectes || []);
  tbody.innerHTML = comparaison.length ? comparaison.map(l => {
    const f100 = fiabiliteInfo(l.fiabilite);
    const fLh = fiabiliteInfo(l.fiabilite_lh);
    const fSourceB = fiabiliteInfo(l.fiabilite_source_b);
    const remarque = [l.remarque, l.remarque_lh, l.remarque_source_b].filter(Boolean).join(' · ');
    // Colonnes Télématique B omises entièrement (pas juste "—") quand la 3e source est
    // indisponible pour ce camion.
    const celluleSourceBL100 = COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE
      ? `<td>${l.source_b_l100 != null ? fmt(Number(l.source_b_l100)) + ' ' + badgePlausibiliteSourceBHtml(l.statut_plausibilite_source_b) + (moisKmSourceBAffectes.has(l.mois) ? ' ' + badgeKilometrageCorrompuHtml(COMPARATEUR_REGISTRE_KM_SOURCE_B) : '') : '—'}</td>` : '';
    const celluleSourceBEcartFiab = COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE ? `
      <td>${l.ecart_pct_source_b != null ? (l.ecart_pct_source_b > 0 ? '+' : '') + fmt(l.ecart_pct_source_b) + '%' : '—'}</td>
      <td><span class="badge ${fSourceB.cls}">${fSourceB.label}</span></td>` : '';
    return `<tr>
      <td>${moisLisible(l.mois)}</td>
      <td>${l.source_a_l100 != null ? fmt(Number(l.source_a_l100)) : '—'}</td>
      <td>${l.registre_l100 != null ? fmt(Number(l.registre_l100)) : '—'}</td>
      ${celluleSourceBL100}
      <td>${l.ecart_pct != null ? (l.ecart_pct > 0 ? '+' : '') + fmt(l.ecart_pct) + '%' : '—'}</td>
      <td><span class="badge ${f100.cls}">${f100.label}</span></td>
      ${celluleSourceBEcartFiab}
      <td>${l.source_a_lh != null ? fmt(Number(l.source_a_lh)) : '—'}</td>
      <td>${l.registre_lh != null ? fmt(Number(l.registre_lh)) : '—'}</td>
      <td>${l.ecart_pct_lh != null ? (l.ecart_pct_lh > 0 ? '+' : '') + fmt(l.ecart_pct_lh) + '%' : '—'}</td>
      <td><span class="badge ${fLh.cls}">${fLh.label}</span></td>
      <td style="color:var(--grey);font-size:12px;">${remarque}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="13" style="color:var(--grey);">Aucune donnée pour ce camion.</td></tr>';

  dessinerChartComparateurRegistre();
}

// Un seul canvas, jamais dupliqué : la bascule L/100km ↔ L/h (boutons btnRegistreL100/btnRegistreLh,
// même pattern que Mensuel/Annuel sur Courbes complètes) change juste quels champs sont tracés,
// pas de second graphique — les deux comparatifs cohabitent déjà côte à côte dans le tableau
// au-dessus, où l'espace pour 2 unités simultanées ne pose pas ce problème de lisibilité.
function dessinerChartComparateurRegistre(){
  const comparaison = COMPARATEUR_REGISTRE_DERNIERE_COMPARAISON;
  const enLh = COMPARATEUR_REGISTRE_UNITE === 'lh';
  const champOpti = enLh ? 'source_a_lh' : 'source_a_l100';
  const champSolu = enLh ? 'registre_lh' : 'registre_l100';
  const unite = enLh ? 'L/h' : 'L/100km';

  // Une série sans aucun point (ex. Télématique A pour un véhicule Télématique B-only) n'est pas ajoutée : pas de légende fantôme.
  const datasets = [
    { label: `Télématique A (${unite})`, data: comparaison.map(l => l[champOpti] != null ? Number(l[champOpti]) : null), borderColor: '#1C2833', tension: 0, spanGaps: true },
    { label: `Registre (${unite})`, data: comparaison.map(l => l[champSolu] != null ? Number(l[champSolu]) : null), borderColor: '#B9770E', tension: 0, spanGaps: true },
  ].filter(d => d.data.some(v => v !== null));
  // Télématique B n'existe qu'en L/100km (pas d'heures moteur dans son modèle plein-à-plein) — jamais tracé
  // en mode L/h, et jamais tracé du tout pour un camion hors whitelist (courbe plate à zéro serait
  // trompeuse, cf. consignes de fiabilité). Même violet #7D3C98 que le badge .badge.source_b déjà établi
  // ailleurs dans le projet (Fiche camion, Synthèse camion, filtre Périmètre).
  if (!enLh && COMPARATEUR_REGISTRE_SOURCE_B_DISPONIBLE) {
    datasets.push({ label: `Télématique B (${unite})`, data: comparaison.map(l => l.source_b_l100 != null ? Number(l.source_b_l100) : null), borderColor: '#7D3C98', tension: 0, spanGaps: true });
  }

  const ctx = document.getElementById('chartComparateurRegistre');
  if (chartComparateurRegistreInstance) chartComparateurRegistreInstance.destroy();
  chartComparateurRegistreInstance = new Chart(ctx, {
    type: 'line',
    data: { labels: comparaison.map(l => moisLisible(l.mois)), datasets },
    options: { responsive: true, ...optionsAspectRatioGraphique(), interaction: { mode: 'index', intersect: false }, scales: { y: { title: { display: true, text: unite } } } },
  });
}

function basculerUniteComparateurRegistre(unite){
  COMPARATEUR_REGISTRE_UNITE = unite;
  document.getElementById('btnRegistreL100').classList.toggle('active', unite === 'l100');
  document.getElementById('btnRegistreLh').classList.toggle('active', unite === 'lh');
  dessinerChartComparateurRegistre();
}
document.getElementById('btnRegistreL100').addEventListener('click', () => basculerUniteComparateurRegistre('l100'));
document.getElementById('btnRegistreLh').addEventListener('click', () => basculerUniteComparateurRegistre('lh'));

// ---------- Synthèse camion ----------
// Onglet d'assemblage pur : aucune donnée recalculée ici, tout provient d'appels déjà utilisés
// ailleurs dans le dashboard (mesures, alertes, suggestions, comparateur registre manuel, historique SER) —
// juste filtré côté client sur le camion sélectionné (par immatriculation pour /api/alertes et
// /api/suggestions, qui n'exposent pas camion_id ; l'immatriculation est la clé métier unique du
// camion dans toute l'appli donc ce filtrage est exact, pas approximatif).
function viderSyntheseCamion(){
  SYNTHESE_CAMION_ID = null;
  ETAT_RENDU_POUR = null;
}

function renderListeAlertesSynthese(elId, liste, texteVide){
  const el = document.getElementById(elId);
  el.innerHTML = liste.length ? liste.map(a => `
    <div class="alert-line">
      <div class="dot" style="background:${a.severite === 'critique' ? 'var(--red)' : 'var(--amber)'}"></div>
      <div style="flex:1;">
        <div>${a.message}</div>
        <div style="font-size:11px;color:var(--grey);margin-top:2px;">
          ${a.date_detection ? `Détectée le ${new Date(a.date_detection).toLocaleDateString('fr-FR')}` : ''}
          ${a.resolue && a.date_resolution ? ` — résolue le ${new Date(a.date_resolution).toLocaleDateString('fr-FR')}` : ''}
        </div>
      </div>
      ${a.resolue ? '<span class="badge good">Résolue</span>' : ''}
    </div>`).join('') : `<p class="sub">${texteVide}</p>`;
}

function renderListeSuggestionsSynthese(elId, liste, texteVide){
  const el = document.getElementById(elId);
  const couleurs = { urgent: 'var(--red)', attention: 'var(--amber)', info: 'var(--grey)' };
  el.innerHTML = liste.length ? liste.map(s => `
    <div class="alert-line">
      <div class="dot" style="background:${couleurs[s.gravite] || 'var(--grey)'}"></div>
      <div style="flex:1;">
        <div>${s.message}</div>
        <div style="font-size:11px;color:var(--grey);margin-top:2px;">
          ${s.regle_declenchee}${s.date_detection ? ` · Détectée le ${new Date(s.date_detection).toLocaleDateString('fr-FR')}` : ''}
          ${s.resolue && s.date_resolution ? ` — résolue le ${new Date(s.date_resolution).toLocaleDateString('fr-FR')}` : ''}
        </div>
      </div>
      ${s.resolue ? '<span class="badge good">Résolue</span>' : ''}
    </div>`).join('') : `<p class="sub">${texteVide}</p>`;
}

async function ouvrirSyntheseCamion(id){
  SYNTHESE_CAMION_ID = id;

  const infoCamion = camionParId(id);
  const immat = infoCamion?.immatriculation ?? '—';

  // --- En-tête (données déjà en mémoire, synchrone) ---
  document.getElementById('syntheseImmat').textContent = immat;
  const usage = usageNormeRefParCamionId(id);
  document.getElementById('syntheseSousTitre').innerHTML = `
    <span class="badge ${(infoCamion?.groupe || '').toLowerCase()}">${infoCamion?.groupe ?? '—'}</span>
    ${usage ? ` · ${usage}` : ' · <span style="color:var(--grey);">Usage norme interne non renseigné</span>'}
  `;
  rafraichirBadgeSyntheseStatut();

  const ligneConducteurSynthese = document.getElementById('conducteurSourceBSynthese');
  const conducteurHtmlSynthese = conducteurSourceBLigneHtml(infoCamion);
  ligneConducteurSynthese.innerHTML = conducteurHtmlSynthese;
  ligneConducteurSynthese.classList.toggle('hidden', !conducteurHtmlSynthese);

  Promise.all([getConsommationSourceB(id), getKilometrageSourceB(id)]).then(([resume, kmInfo]) => {
    if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps
    const contenuConsoSourceB = consommationSourceBCarteHtml(resume, kmInfo);
    document.getElementById('syntheseConsommationSourceB').innerHTML = contenuConsoSourceB;
    document.getElementById('cardConsommationSourceB').style.display = contenuConsoSourceB ? '' : 'none';
  });

  // --- Mois en cours (provisoire) ---
  // (Les 4 KPI « 6 derniers mois » de l'ancienne Synthèse camion sont supprimés : doublon des KPI de la
  // sous-section Évolution, avec une période différente — cf. docs/refonte-flotte-parite.md, C5.)
  const data = await getMesures(id);
  if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps
  const mesurePartielleSynthese = data.mesures.find(estMesurePartielle);
  const badgePartielEl = document.getElementById('syntheseBadgePartiel');
  if (mesurePartielleSynthese){
    badgePartielEl.textContent = badgeMoisPartielTexte(mesurePartielleSynthese);
    badgePartielEl.classList.remove('hidden');
  } else {
    badgePartielEl.classList.add('hidden');
  }

  // --- SER norme interne actuelle + lien historique (modal déjà construit pour Standards SER) ---
  const serInfo = SER_STANDARDS_DATA.find(s => s.id === id);
  document.getElementById('syntheseSer').innerHTML = (serInfo && serInfo.ser_norme_ref != null) ? `
    <div style="font-size:26px;font-weight:700;">${fmt(serInfo.ser_norme_ref)}<small style="font-size:13px;color:var(--grey);font-weight:500;"> ${libelleUnite(serInfo.unite_ser)}</small></div>
    <div style="font-size:12.5px;color:var(--grey);margin:6px 0 12px;">
      Cible : ${serInfo.cible != null ? fmt(serInfo.cible) + ' ' + libelleUnite(serInfo.unite_ser) : '—'} · Seuil rouge : ${serInfo.ser_1x != null ? fmt(serInfo.ser_1x) + ' ' + libelleUnite(serInfo.unite_ser) : '—'}
    </div>
    <button type="button" class="btn outline" onclick="ouvrirHistoriqueSer(${id}, '${immat}')">🕒 Voir l'historique des changements</button>
  ` : '<p class="sub">Aucune SER norme interne renseignée pour ce véhicule.</p>';

  // --- Dernière comparaison registre manuel vs Télématique A (dernier mois du même endpoint que l'onglet dédié) ---
  const elComparateur = document.getElementById('syntheseComparateur');
  elComparateur.innerHTML = '<p class="sub">Chargement…</p>';
  try {
    const resComp = await fetch(API.comparateurRegistre(id));
    if (!resComp.ok) throw new Error('HTTP ' + resComp.status);
    const jsonComp = await resComp.json();
    if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps
    const derniere = (jsonComp.comparaison || []).slice(-1)[0];
    if (derniere) {
      const f = fiabiliteInfo(derniere.fiabilite);
      elComparateur.innerHTML = `
        <div style="font-size:12.5px;color:var(--grey);margin-bottom:8px;">${moisLisible(derniere.mois)}</div>
        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px;">
          <div><div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;">Télématique A</div><div style="font-size:18px;font-weight:700;">${derniere.source_a_l100 != null ? fmt(derniere.source_a_l100) : '—'}</div></div>
          <div><div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;">Registre</div><div style="font-size:18px;font-weight:700;">${derniere.registre_l100 != null ? fmt(derniere.registre_l100) : '—'}</div></div>
          <div><div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;">Écart</div><div style="font-size:18px;font-weight:700;">${derniere.ecart_pct != null ? (derniere.ecart_pct > 0 ? '+' : '') + fmt(derniere.ecart_pct) + '%' : '—'}</div></div>
        </div>
        <span class="badge ${f.cls}">${f.label}</span>
        ${derniere.remarque ? `<div style="font-size:12px;color:var(--grey);margin-top:8px;">${derniere.remarque}</div>` : ''}
      `;
    } else {
      elComparateur.innerHTML = '<p class="sub">Aucune donnée de comparaison pour ce véhicule.</p>';
    }
  } catch(e) {
    elComparateur.innerHTML = `<p class="sub" style="color:var(--red);">Comparaison indisponible (${e.message}).</p>`;
    console.error('Erreur /api/comparateur-registre (Synthèse camion) :', e);
  }

  // --- Alertes (actives + historique) et analyses correctives (déjà imbriquées dans /api/alertes) ---
  document.getElementById('syntheseAlertesActives').innerHTML = '<p class="sub">Chargement…</p>';
  document.getElementById('syntheseAlertesResolues').innerHTML = '';
  try {
    const resAl = await fetch(API.alertes);
    if (!resAl.ok) throw new Error('HTTP ' + resAl.status);
    const toutesAlertes = await resAl.json();
    if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps
    const alertesCamion = toutesAlertes.filter(a => a.camion === immat);

    renderListeAlertesSynthese('syntheseAlertesActives', alertesCamion.filter(a => !a.resolue), 'Aucune alerte active pour ce véhicule.');
    renderListeAlertesSynthese('syntheseAlertesResolues', alertesCamion.filter(a => a.resolue), 'Aucun historique d\'alerte résolue pour ce véhicule.');
  } catch(e) {
    document.getElementById('syntheseAlertesActives').innerHTML = `<p class="sub" style="color:var(--red);">Alertes indisponibles (${e.message}).</p>`;
    document.getElementById('syntheseAlertesResolues').innerHTML = '';
    console.error('Erreur /api/alertes (Synthèse camion) :', e);
  }

  // Analyses correctives : appel direct WHERE camion_id, indépendant des alertes.
  document.getElementById('syntheseAnalyses').innerHTML = '<p class="sub">Chargement…</p>';
  try {
    const resAn = await fetch(API.analysesCorrectives(id));
    if (!resAn.ok) throw new Error('HTTP ' + resAn.status);
    const analyses = await resAn.json();
    if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps

    document.getElementById('syntheseAnalyses').innerHTML = analyses.length
      ? analyses.map(carteAnalyseCorrectiveHTML).join('')
      : '<p class="sub">Aucune analyse corrective documentée pour ce véhicule.</p>';
  } catch(e) {
    document.getElementById('syntheseAnalyses').innerHTML = `<p class="sub" style="color:var(--red);">Analyses correctives indisponibles (${e.message}).</p>`;
    console.error('Erreur /api/analyses-correctives (Synthèse camion) :', e);
  }

  // --- Suggestions (actives + historique) ---
  // /api/suggestions appelle WeatherService en interne (règle CANICULE_IPE_HAUSSE) et peut prendre
  // plusieurs secondes (~3s mesurées) — indicateur de chargement explicite pour ne pas laisser la
  // section vide sans explication pendant ce délai.
  document.getElementById('syntheseSuggestionsActives').innerHTML = '<p class="sub">Chargement…</p>';
  document.getElementById('syntheseSuggestionsResolues').innerHTML = '';
  try {
    const resSug = await fetch(API.suggestions);
    if (!resSug.ok) throw new Error('HTTP ' + resSug.status);
    const toutesSuggestions = await resSug.json();
    if (SYNTHESE_CAMION_ID !== id) return; // un autre camion a été sélectionné entretemps
    const suggestionsCamion = toutesSuggestions.filter(s => s.camion === immat);

    renderListeSuggestionsSynthese('syntheseSuggestionsActives', suggestionsCamion.filter(s => !s.resolue), 'Aucune suggestion active pour ce véhicule.');
    renderListeSuggestionsSynthese('syntheseSuggestionsResolues', suggestionsCamion.filter(s => s.resolue), 'Aucun historique de suggestion pour ce véhicule.');
  } catch(e) {
    document.getElementById('syntheseSuggestionsActives').innerHTML = `<p class="sub" style="color:var(--red);">Suggestions indisponibles (${e.message}).</p>`;
    document.getElementById('syntheseSuggestionsResolues').innerHTML = '';
    console.error('Erreur /api/suggestions (Synthèse camion) :', e);
  }
}

// ---------- Courbes complètes ----------
let chartCourbesInstance = null;
let COURBES_METRIQUES_SELECTIONNEES = [];
let METRIQUE_LABELS = {};
let METRIQUE_UNITES = {};
const MAX_METRIQUES_COURBES = 4;
const COULEURS_AXES_COURBES = ['#1C2833', '#B9770E', '#1E8449', '#8E44AD'];

async function chargerCourbesMetriques(){
  try {
    const res = await fetch(API.courbesMetriques);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    COURBES_METRIQUES = await res.json();
  } catch(e) {
    console.error('Erreur chargement /api/courbes/metriques :', e);
    COURBES_METRIQUES = { source_a: [], registre: [], source_b: [] };
  }
  METRIQUE_LABELS = {};
  METRIQUE_UNITES = {};
  [...(COURBES_METRIQUES.source_a || []), ...(COURBES_METRIQUES.registre || []), ...(COURBES_METRIQUES.source_b || [])].forEach(m => {
    METRIQUE_LABELS[m.champ] = m.label;
    METRIQUE_UNITES[m.champ] = m.unite;
  });
  remplirCheckboxesMetriques();
}

// Regroupement par grandeur mesurée — les métriques qui mesurent LA MÊME CHOSE dans plusieurs
// sources (consommation en L/100km, L/h, litres bruts, distance/km, heures de fonctionnement) sont
// affichées sous UN SEUL en-tête de grandeur ; dans ce cas.
const GROUPES_FUSIONNES = [
  { titre: 'Consommation (L/100km)', membres: [
    ['source_a', 'consommation_l100', 'Télématique A'],
    ['registre', 'pe_l100', 'Registre'],
    ['source_b', 'consommation_l100km', 'Télématique B'],
  ] },
  { titre: 'Consommation (L/h)', membres: [
    ['source_a', 'conso_horaire', 'Télématique A'],
    ['registre', 'pe_lh', 'Registre'],
  ] },
  { titre: 'Consommation (L)', membres: [
    ['source_a', 'litres_consommes', 'Télématique A'],
    ['registre', 'litres', 'Registre'],
    ['source_b', 'litres_source_b', 'Télématique B'],
  ] },
  { titre: 'Distance (km)', membres: [
    ['source_a', 'km_parcourus', 'Télématique A'],
    ['registre', 'km', 'Registre'],
    ['source_b', 'distance_km', 'Télématique B'],
  ] },
  { titre: 'Heures (h)', membres: [
    ['source_a', 'heures_moteur', 'Télématique A'],
    ['registre', 'heures', 'Registre'],
  ] },
];

function trouverMetrique(source, champ){
  return (COURBES_METRIQUES[source] || []).find(m => m.champ === champ);
}

// m.info : le .info-i est un SIBLING du <label>, jamais un enfant — à l'intérieur, un clic dessus
// déclencherait aussi le <label> (association implicite label→checkbox), cochant/décochant la
// métrique par erreur.
function ligneMetriqueHtml(champ, texte, info){
  return `
        <div style="display:flex;align-items:center;gap:6px;padding:4px 0;">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;flex:1;">
            <input type="checkbox" class="chk-metrique" value="${champ}">
            ${texte}
          </label>
          ${info ? `<span class="info-i" data-tip="${info}">i</span>` : ''}
        </div>`;
}

function remplirCheckboxesMetriques(){
  const container = document.getElementById('courbesMetriquesCheckboxes');
  const groupe = (nom, itemsHtml) => `
    <div style="min-width:230px;margin-bottom:14px;">
      <div style="font-size:10.5px;color:var(--grey);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;font-weight:600;">${nom}</div>
      ${itemsHtml}
    </div>`;

  // 1er passage : les 5 groupes fusionnés par grandeur — mémorise au passage les champs déjà placés,
  // pour ne jamais les faire apparaître une 2e fois dans les groupes "par source" ci-dessous.
  const champsFusionnes = new Set();
  const blocsFusion = GROUPES_FUSIONNES.map(({ titre, membres }) => {
    const lignes = membres.map(([source, champ, labelSource]) => {
      const m = trouverMetrique(source, champ);
      if (!m) return '';
      champsFusionnes.add(champ);
      return ligneMetriqueHtml(m.champ, labelSource, m.info);
    }).join('');
    return lignes ? groupe(titre, lignes) : '';
  }).join('');

  // 2e passage : ce qui reste de chaque source une fois les champs fusionnés retirés — un groupe
  // devenu entièrement vide (Télématique B : ses 3 métriques sont TOUTES fusionnées ci-dessus) n'affiche aucun
  // en-tête, jamais un panneau vide.
  const blocRestant = (nom, items) => {
    const lignes = (items || [])
      .filter(m => !champsFusionnes.has(m.champ))
      .map(m => ligneMetriqueHtml(m.champ, m.label, m.info))
      .join('');
    return lignes ? groupe(nom, lignes) : '';
  };

  container.innerHTML = blocsFusion
    + blocRestant('Télématique A', COURBES_METRIQUES.source_a)
    + blocRestant('Registre', COURBES_METRIQUES.registre)
    + blocRestant('Télématique B', COURBES_METRIQUES.source_b);

  container.querySelectorAll('.chk-metrique').forEach(chk => {
    chk.addEventListener('change', () => toggleMetriqueCourbes(chk.value, chk));
  });
}

// Plafond de métriques simultanées : 4 avec 0-1 camion sélectionné (comportement historique
// inchangé) ; verrouillé à 1 dès que 2+ camions sont sélectionnés.
function metriqueMaxActuel(){
  return COURBES_SELECTION.length >= 2 ? 1 : MAX_METRIQUES_COURBES;
}

function toggleMetriqueCourbes(champ, chkEl){
  const idx = COURBES_METRIQUES_SELECTIONNEES.indexOf(champ);
  const max = metriqueMaxActuel();
  if (idx >= 0){
    COURBES_METRIQUES_SELECTIONNEES.splice(idx, 1);
  } else if (COURBES_METRIQUES_SELECTIONNEES.length >= max){
    chkEl.checked = false;
    document.getElementById('courbesLimiteMsg').textContent = max === 1
      ? 'Une seule métrique à la fois avec 2+ camions sélectionnés (pour rester lisible — une couleur par camion).'
      : `Maximum ${MAX_METRIQUES_COURBES} métriques simultanées pour rester lisible.`;
    document.getElementById('courbesLimiteMsg').classList.remove('hidden');
    return;
  } else {
    COURBES_METRIQUES_SELECTIONNEES.push(champ);
  }
  document.getElementById('courbesLimiteMsg').classList.add('hidden');
  majEtatCheckboxesMetriques();
  chargerCourbes();
}

function majEtatCheckboxesMetriques(){
  const max = metriqueMaxActuel();
  const atteint = COURBES_METRIQUES_SELECTIONNEES.length >= max;
  document.querySelectorAll('.chk-metrique').forEach(chk => {
    if (!chk.checked) chk.disabled = atteint;
  });
  document.getElementById('courbesMetriquesSousTitre').textContent = max === 1
    ? '1 seule (2+ camions sélectionnés — une couleur par camion)'
    : `jusqu'à ${MAX_METRIQUES_COURBES} simultanées`;
}

// Appelée après chaque changement de COURBES_SELECTION (toggle chip, nettoyage par filtre global) —
// si le passage à 2+ camions laisse plus d'une métrique cochée, ne garde que la première (ordre de
// sélection de l'utilisateur) et décoche visuellement les autres, puis regrise les cases selon le
// nouveau plafond. Symétrique dans l'autre sens : repasser sous 2 camions débloque à nouveau jusqu'à
// 4 métriques, sans rien re-cocher automatiquement (l'utilisateur recoche s'il veut comparer à
// nouveau plusieurs métriques).
function appliquerVerrouMetriqueCourbes(){
  if (COURBES_SELECTION.length >= 2 && COURBES_METRIQUES_SELECTIONNEES.length > 1){
    const gardee = COURBES_METRIQUES_SELECTIONNEES[0];
    COURBES_METRIQUES_SELECTIONNEES = [gardee];
    document.querySelectorAll('.chk-metrique').forEach(chk => {
      if (chk.value !== gardee) chk.checked = false;
    });
  }
  document.getElementById('courbesLimiteMsg').classList.add('hidden');
  majEtatCheckboxesMetriques();
}

function remplirChipsCourbes(){
  const el = document.getElementById('chipsCourbes');
  el.innerHTML = DATA.par_camion.map(c => `<button class="chip" data-id="${c.id}">${c.immatriculation} ${badgeElargieHtml(c)}</button>`).join('');
  el.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => toggleCourbesSelection(parseInt(chip.dataset.id), chip));
  });
}
function toggleCourbesSelection(id, chipEl){
  const idx = COURBES_SELECTION.indexOf(id);
  if (idx >= 0){
    COURBES_SELECTION.splice(idx, 1);
    chipEl.classList.remove('active');
  } else {
    COURBES_SELECTION.push(id);
    chipEl.classList.add('active');
  }
  sauvegarderFiltres();
  appliquerVerrouMetriqueCourbes();
  chargerCourbes();
}

document.getElementById('btnCourbesMensuel').addEventListener('click', () => basculerModeCourbes('mensuel'));
document.getElementById('btnCourbesAnnuel').addEventListener('click', () => basculerModeCourbes('annuel'));

function basculerModeCourbes(mode){
  COURBES_MODE = mode;
  document.getElementById('btnCourbesMensuel').classList.toggle('active', mode === 'mensuel');
  document.getElementById('btnCourbesAnnuel').classList.toggle('active', mode === 'annuel');
  chargerCourbes();
}

// Export CSV des métriques cochées telles quelles (mêmes données que la courbe affichée, même mode
// mensuel/annuel) — généré côté serveur (league/csv), réutilise CourbesController pour ne jamais
// diverger du tracé affiché à l'écran. CourbesController::export() n'accepte qu'un seul camion à la
// fois (jamais étendu au multi-camion) : avec 2+ camions sélectionnés, message explicite plutôt que
// d'exporter silencieusement un seul des camions affichés à l'écran sans le dire.
document.getElementById('btnExportCourbesCsv').addEventListener('click', () => {
  if (!COURBES_SELECTION.length || !COURBES_METRIQUES_SELECTIONNEES.length){
    alert('Sélectionne au moins un camion et une métrique.');
    return;
  }
  if (COURBES_SELECTION.length > 1){
    alert('Export CSV disponible pour un seul camion à la fois — réduis la sélection à 1 camion pour exporter.');
    return;
  }
  window.location.href = API.exportCourbes(COURBES_SELECTION[0], COURBES_METRIQUES_SELECTIONNEES, COURBES_MODE);
});

// Filtre les points d'une courbe (mensuel : {mois, ...} ; annuel : {annee, ...}) selon le sélecteur
// de période de l'onglet Courbes. En mode annuel, une année est incluse dès qu'elle chevauche la
// plage plutôt que d'être coupée à l'intérieur : l'agrégat (moyenne/somme) est déjà calculé côté
// serveur pour l'année entière, impossible à recouper au jour près sans re-agréger côté client —
// #courbesAnnuelInfoPeriode prévient l'utilisateur de cette granularité plus grossière.
function filtrerDonneesCourbesParPeriode(data, bornes){
  if (!bornes.depuis && !bornes.jusquA) return data;
  if (COURBES_MODE === 'mensuel') {
    return data.filter(p => dansPeriode(p.mois + '-01', bornes));
  }
  const anneeDebut = bornes.depuis ? parseInt(bornes.depuis.slice(0, 4)) : -Infinity;
  const anneeFin = bornes.jusquA ? parseInt(bornes.jusquA.slice(0, 4)) : Infinity;
  return data.filter(p => p.annee >= anneeDebut && p.annee <= anneeFin);
}

// Compteur de génération : deux clics rapprochés sur des chips camion ou des cases métrique
// pouvaient laisser le PREMIER appel de chargerCourbes() redessiner le graphique avec sa propre
// sélection périmée APRÈS que le second (plus récent) ait déjà dessiné la bonne.
async function chargerCourbes(){
  const generation = ++COURBES_RENDER_GENERATION;
  const zone = document.getElementById('courbesEtat');
  const infoPeriode = document.getElementById('courbesAnnuelInfoPeriode');
  if (!COURBES_SELECTION.length || !COURBES_METRIQUES_SELECTIONNEES.length) {
    zone.textContent = 'Sélectionne au moins un camion et une métrique.';
    zone.classList.remove('hidden');
    infoPeriode.textContent = '';
    document.getElementById('courbesNotePlausibiliteSourceB').classList.add('hidden');
    document.getElementById('courbesNoteKilometrageSourceB').classList.add('hidden');
    if (chartCourbesInstance) { chartCourbesInstance.destroy(); chartCourbesInstance = null; }
    return;
  }
  zone.classList.add('hidden');
  const bornesBrutes = bornesPeriodeGenerique('Courbes');
  const bornes = bornesBrutes.incomplet ? { depuis: null, jusquA: null } : bornesBrutes;
  infoPeriode.textContent = (COURBES_MODE === 'annuel' && (bornes.depuis || bornes.jusquA))
    ? "Mode annuel : toute année chevauchant la période choisie est incluse en entier (l'agrégat annuel n'est pas recoupé au jour près)."
    : '';

  // Instantanés figés pour toute la durée de cette exécution async — même raison que
  // renderComparateur()/chargerTrajetGps() : un clic pendant les fetch() en vol ne doit
  // jamais mélanger deux sélections successives.
  const selectionCamions = [...COURBES_SELECTION];
  const metriques = [...COURBES_METRIQUES_SELECTIONNEES];

  // Note de plausibilité Télématique B — uniquement quand la métrique "Consommation (Télématique
  // B)" est seule cochée sur un seul camion (jamais pour la distance, qui n'a jamais été
  // "implausible" au même sens ; jamais en mode multi-camion, où le statut varierait d'un camion à
  // l'autre sur la même note)
  const noteSourceB = document.getElementById('courbesNotePlausibiliteSourceB');
  if (selectionCamions.length === 1 && metriques.length === 1 && metriques[0] === 'consommation_l100km') {
    const resume = await getConsommationSourceB(selectionCamions[0]);
    if (generation !== COURBES_RENDER_GENERATION) return;
    if (resume?.disponible) {
      noteSourceB.innerHTML = 'Fiabilité de cette courbe Télématique B : ' + badgePlausibiliteSourceBHtml(resume.statut_plausibilite);
      noteSourceB.classList.remove('hidden');
    } else {
      noteSourceB.classList.add('hidden');
    }
  } else {
    noteSourceB.classList.add('hidden');
  }

  // Avertissement « kilométrage source corrompu » : dès qu'une métrique Télématique B (distance OU
  // consommation) est affichée pour un camion dont le kilométrage Télématique B contient un jour
  // négatif/aberrant.
  const noteKm = document.getElementById('courbesNoteKilometrageSourceB');
  const champsSourceB = (COURBES_METRIQUES.source_b || []).map(m => m.champ);
  if (metriques.some(m => champsSourceB.includes(m))) {
    const infos = await Promise.all(selectionCamions.map(id => getKilometrageSourceB(id)));
    if (generation !== COURBES_RENDER_GENERATION) return;
    const lignes = selectionCamions.map((id, i) => {
      if (!infos[i]?.corrompu) return '';
      const immat = (DATA.par_camion || []).find(c => c.id === id)?.immatriculation ?? id;
      const mois = (infos[i].mois_jours_aberrants || []).map(moisLisible2).join(', ');
      return `<div><b>${immat}</b> ${badgeKilometrageCorrompuHtml(infos[i])} <span style="color:var(--grey);">jour(s) concerné(s) en : ${mois}</span></div>`;
    }).filter(Boolean);
    noteKm.innerHTML = lignes.join('');
    noteKm.classList.toggle('hidden', !lignes.length);
  } else {
    noteKm.classList.add('hidden');
  }

  try {
    if (selectionCamions.length === 1) {
      // Mode historique inchangé : 1 camion × jusqu'à 4 métriques, un axe par unité.
      const resultats = await Promise.all(metriques.map(async metrique => {
        const url = COURBES_MODE === 'mensuel'
          ? API.courbesMensuel(selectionCamions[0], metrique)
          : API.courbesAnnuel(selectionCamions[0], metrique);
        const res = await fetch(url);
        if (!res.ok) {
          const errJson = await res.json().catch(() => ({}));
          throw new Error(errJson.message || ('HTTP ' + res.status));
        }
        const data = await res.json();
        return { metrique, data: filtrerDonneesCourbesParPeriode(data, bornes) };
      }));
      if (generation !== COURBES_RENDER_GENERATION) return;
      dessinerChartCourbesParMetrique(resultats);
    } else {
      // Mode 2+ camions : verrouillé à 1 seule métrique (cf. metriqueMaxActuel()) — une série par
      // camion, couleur genererCouleurs() (déjà construit pour Comparateur/GPS)
      const metrique = metriques[0];
      const resultats = await Promise.all(selectionCamions.map(async id => {
        const url = COURBES_MODE === 'mensuel'
          ? API.courbesMensuel(id, metrique)
          : API.courbesAnnuel(id, metrique);
        const res = await fetch(url);
        if (!res.ok) {
          const errJson = await res.json().catch(() => ({}));
          throw new Error(errJson.message || ('HTTP ' + res.status));
        }
        const data = await res.json();
        return { id, data: filtrerDonneesCourbesParPeriode(data, bornes) };
      }));
      if (generation !== COURBES_RENDER_GENERATION) return;
      dessinerChartCourbesParCamion(metrique, resultats);
    }
  } catch(e) {
    if (generation !== COURBES_RENDER_GENERATION) return;
    zone.textContent = 'Erreur de chargement : ' + e.message;
    zone.classList.remove('hidden');
    console.error('Erreur /api/courbes :', e);
    if (chartCourbesInstance) { chartCourbesInstance.destroy(); chartCourbesInstance = null; }
  }
}

// Mode historique (1 camion × jusqu'à 4 métriques) — renommée (dessinerChartCourbes →
// dessinerChartCourbesParMetrique) pour la distinguer de dessinerChartCourbesParCamion()
// ci-dessous (2+ camions × 1 métrique verrouillée), logique inchangée.
function dessinerChartCourbesParMetrique(resultats){
  const ctx = document.getElementById('chartCourbes');
  if (chartCourbesInstance) chartCourbesInstance.destroy();

  // est_partielle n'existe que côté Télématique A et seulement en mode mensuel (CourbesController::
  // mensuel()) — annuel() est déjà une moyenne/somme qui exclut le mois en cours côté backend,
  // il n'y a donc aucun point partiel à distinguer dans ce mode.
  const parMetrique = resultats.map(({metrique, data}) => ({
    metrique,
    points: COURBES_MODE === 'mensuel'
      ? data.map(p => ({periode: p.mois, valeur: p.valeur != null ? Number(p.valeur) : null, est_partielle: !!p.est_partielle}))
      : data.map(p => ({periode: String(p.annee), valeur: p.moyenne != null ? Number(p.moyenne) : null, est_partielle: false})),
  }));

  const periodesUnion = [...new Set(parMetrique.flatMap(m => m.points.map(p => p.periode)))].sort();
  const labels = COURBES_MODE === 'mensuel' ? periodesUnion.map(moisLisible) : periodesUnion;

  // Le mois en cours est toujours le dernier de periodesUnion (tri chronologique) s'il est partiel
  // pour au moins une des métriques cochées — un seul indice partagé par toutes les séries pour
  // rester visuellement cohérent (même dernier point atténué sur toutes les courbes affichées).
  const dernierePeriode = periodesUnion[periodesUnion.length - 1];
  const dernierePeriodeEstPartielle = COURBES_MODE === 'mensuel' && parMetrique.some(
    m => m.points.some(p => p.periode === dernierePeriode && p.est_partielle)
  );
  const idxPartielCourbes = dernierePeriodeEstPartielle ? periodesUnion.length - 1 : -1;

  const badgeCourbesEl = document.getElementById('badgeMoisPartielCourbes');
  if (dernierePeriodeEstPartielle){
    badgeCourbesEl.textContent = badgeMoisPartielTexte({ date: dernierePeriode + '-01' });
    badgeCourbesEl.classList.remove('hidden');
  } else {
    badgeCourbesEl.classList.add('hidden');
  }

  // Un axe par unité réelle distincte parmi les métriques cochées, pas un axe par métrique : deux
  // métriques de même unité (ex. consommation_l100 + pe_l100, toutes deux en L/100km) partagent le
  // même axe pour rester directement comparables visuellement, au lieu de 2 échelles séparées.
  const unitesDistinctes = [...new Set(parMetrique.map(m => METRIQUE_UNITES[m.metrique] || m.metrique))];
  const axisIdParUnite = Object.fromEntries(unitesDistinctes.map((u, i) => [u, 'y' + i]));

  const scales = { x: { grid: { display: false } } };
  unitesDistinctes.forEach((unite, i) => {
    scales[axisIdParUnite[unite]] = {
      type: 'linear',
      position: i % 2 === 0 ? 'left' : 'right',
      title: { display: true, text: unite },
      grid: { drawOnChartArea: i === 0 },
    };
  });

  const datasets = parMetrique.map((m, i) => {
    const couleur = COULEURS_AXES_COURBES[i % COULEURS_AXES_COURBES.length];
    const label = METRIQUE_LABELS[m.metrique] || m.metrique;
    const axisId = axisIdParUnite[METRIQUE_UNITES[m.metrique] || m.metrique];
    const parPeriode = Object.fromEntries(m.points.map(p => [p.periode, p.valeur]));
    return {
      label,
      data: periodesUnion.map(p => parPeriode[p] ?? null),
      borderColor: couleur,
      backgroundColor: couleur,
      yAxisID: axisId,
      tension: 0,
      spanGaps: true,
      ...stylePointsAvecPartielIndex(periodesUnion.length, idxPartielCourbes, couleur),
    };
  });

  chartCourbesInstance = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      ...optionsAspectRatioGraphique(),
      interaction: { mode: 'index', intersect: false },
      plugins: { tooltip: { callbacks: { afterLabel: (context) => context.dataIndex === idxPartielCourbes ? '⚠ Mois en cours — donnée provisoire' : undefined } } },
      scales,
    },
  });
}

// Mode 2+ camions × 1 métrique verrouillée — une série par camion, couleur genererCouleurs(), un
// seul axe Y (une seule unité puisqu'une seule métrique)
function dessinerChartCourbesParCamion(metrique, resultats){
  const ctx = document.getElementById('chartCourbes');
  if (chartCourbesInstance) chartCourbesInstance.destroy();

  const parCamion = resultats.map(({id, data}) => ({
    id,
    points: COURBES_MODE === 'mensuel'
      ? data.map(p => ({periode: p.mois, valeur: p.valeur != null ? Number(p.valeur) : null, est_partielle: !!p.est_partielle}))
      : data.map(p => ({periode: String(p.annee), valeur: p.moyenne != null ? Number(p.moyenne) : null, est_partielle: false})),
  }));

  const periodesUnion = [...new Set(parCamion.flatMap(m => m.points.map(p => p.periode)))].sort();
  const labels = COURBES_MODE === 'mensuel' ? periodesUnion.map(moisLisible) : periodesUnion;

  // Badge "mois en cours — provisoire" partagé, affiché dès qu'AU MOINS un camion a un point partiel
  // sur la dernière période — même sémantique que le badge du mode 1-camion/N-métriques ci-dessus,
  // pour ne pas avoir deux messages différents selon le mode affiché.
  const dernierePeriode = periodesUnion[periodesUnion.length - 1];
  const dernierePeriodeEstPartielle = COURBES_MODE === 'mensuel' && parCamion.some(
    m => m.points.some(p => p.periode === dernierePeriode && p.est_partielle)
  );
  const badgeCourbesEl = document.getElementById('badgeMoisPartielCourbes');
  if (dernierePeriodeEstPartielle){
    badgeCourbesEl.textContent = badgeMoisPartielTexte({ date: dernierePeriode + '-01' });
    badgeCourbesEl.classList.remove('hidden');
  } else {
    badgeCourbesEl.classList.add('hidden');
  }

  const unite = METRIQUE_UNITES[metrique] || metrique;
  const scales = {
    x: { grid: { display: false } },
    y: { type: 'linear', title: { display: true, text: unite } },
  };

  const couleurs = genererCouleurs(parCamion.length);
  const datasets = parCamion.map((m, i) => {
    const couleur = couleurs[i];
    const camion = camionParId(m.id);
    const parPeriode = Object.fromEntries(m.points.map(p => [p.periode, p.valeur]));
    const idxPartielCeCamion = periodesUnion.findIndex(p => {
      const pt = m.points.find(pp => pp.periode === p);
      return pt && pt.est_partielle;
    });
    return {
      label: camion?.immatriculation ?? String(m.id),
      data: periodesUnion.map(p => parPeriode[p] ?? null),
      borderColor: couleur,
      backgroundColor: couleur,
      yAxisID: 'y',
      tension: 0,
      spanGaps: true,
      ...stylePointsAvecPartielIndex(periodesUnion.length, idxPartielCeCamion, couleur),
    };
  });

  chartCourbesInstance = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      ...optionsAspectRatioGraphique(),
      interaction: { mode: 'index', intersect: false },
      plugins: { tooltip: { callbacks: { afterLabel: (context) => {
        const camionData = parCamion[context.datasetIndex];
        const pt = camionData?.points.find(p => p.periode === periodesUnion[context.dataIndex]);
        return pt?.est_partielle ? '⚠ Mois en cours — donnée provisoire' : undefined;
      } } } },
      scales,
    },
  });
}

// ---------- Suivi GPS (position temps réel + trajet historique) ----------
// Carte créée une seule fois (le conteneur #mapGps existe dès le chargement de la page, même
// masqué sous l'onglet "apercu" par défaut — cf. écouteur dédié plus bas qui appelle invalidateSize()
// au moment où l'onglet devient visible, Leaflet calculant sinon la taille du conteneur à 0x0).
function initGpsMap(){
  if (GPS_MAP) return;
  GPS_MAP = L.map('mapGps').setView([45.3, 4.9], 8);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(GPS_MAP);
}

// Multi-sélection — l'ancien chip spécial "Tous (positions actuelles)" a disparu : les positions
// temps réel (marqueurs) s'affichent déjà pour toute la flotte filtrée indépendamment de la
// sélection ci-dessous (chargerPositionsTempsReel()
function remplirChipsGps(){
  const el = document.getElementById('chipsGps');
  el.innerHTML = DATA.par_camion.map(c => `<button class="chip" data-id="${c.id}">${c.immatriculation} ${badgeElargieHtml(c)}</button>`).join('');
  el.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => toggleGpsSelection(parseInt(chip.dataset.id), chip));
  });
}
function toggleGpsSelection(id, chipEl){
  const idx = GPS_SELECTION.indexOf(id);
  if (idx >= 0){
    GPS_SELECTION.splice(idx, 1);
    chipEl.classList.remove('active');
  } else {
    GPS_SELECTION.push(id);
    chipEl.classList.add('active');
  }
  sauvegarderFiltres();
  chargerTrajetGps();
}

// Jamais de marqueur à des coordonnées (0,0) ou par défaut trompeuses.
function mettreAJourUnMarqueurGps(p){
  if (p.latitude === null || p.longitude === null) return false;

  const camion = camionParId(p.camion_id);
  const couleur = COULEURS_SITE[camion?.groupe] || COULEURS_SITE['SITE A'];
  const style = { radius: 8, color: '#fff', weight: 2, fillColor: couleur, fillOpacity: 1 };

  let marker = GPS_MARKERS[p.camion_id];
  if (!marker) {
    marker = L.circleMarker([p.latitude, p.longitude], style).addTo(GPS_MAP);
    GPS_MARKERS[p.camion_id] = marker;
  } else {
    marker.setLatLng([p.latitude, p.longitude]);
    marker.setStyle(style);
  }

  const horodatage = p.horodatage_position ? new Date(p.horodatage_position).toLocaleString('fr-FR') : 'inconnu';
  const vitesse = p.vitesse != null ? Math.round(p.vitesse) + ' km/h' : '—';
  marker.bindPopup(`<b>${p.immatriculation}</b><br>Vitesse actuelle : ${vitesse}<br>Dernière position : ${horodatage}`);
  return true;
}

function dessinerMarqueursGps(positions){
  initGpsMap();
  const idsVus = new Set();

  positions.forEach(p => {
    if (mettreAJourUnMarqueurGps(p)) idsVus.add(p.camion_id);
  });

  // Retire les marqueurs de camions qui n'ont plus de position exploitable dans cette réponse
  // (ex: sorti du champ de couverture depuis le dernier appel) plutôt que de laisser un marqueur
  // figé sur une ancienne position sans que rien ne le signale.
  Object.keys(GPS_MARKERS).forEach(id => {
    if (!idsVus.has(parseInt(id))) {
      GPS_MAP.removeLayer(GPS_MARKERS[id]);
      delete GPS_MARKERS[id];
    }
  });

  const indisponibles = positions.filter(p => p.latitude === null || p.longitude === null);
  const zoneIndispo = document.getElementById('gpsIndisponibles');
  zoneIndispo.innerHTML = indisponibles.length
    ? `<span class="sub" style="color:var(--grey);">Position indisponible pour : ${indisponibles.map(p => p.immatriculation).join(', ')}</span>`
    : '';
}

// Filtre une liste de positions (Télématique A ou Télématique B) par les filtres généraux actifs
// (Site/Usage norme interne/Matricule/Marque/Périmètre) — même fonction que celle déjà utilisée
// partout ailleurs pour les chips (camionCorrespondFiltresGlobaux()), pas une logique de filtre
// réinventée pour GPS.
function positionsFiltrees(positions){
  return positions.filter(p => {
    const camion = camionParId(p.camion_id);
    return camion && camionCorrespondFiltresGlobaux(camion);
  });
}
function redessinerMarqueursGps(){
  dessinerMarqueursGps(positionsFiltrees(DERNIERES_POSITIONS_SOURCE_A));
}
function redessinerMarqueursSourceB(){
  dessinerMarqueursSourceB(positionsFiltrees(DERNIERES_POSITIONS_SOURCE_B));
}

async function chargerPositionsTempsReel(){
  const elErreur = document.getElementById('gpsErreur');
  try {
    const res = await fetch(API.positionsTempsReel);
    if (!res.ok) {
      const errJson = await res.json().catch(() => ({}));
      throw new Error(errJson.message || ('HTTP ' + res.status));
    }
    const json = await res.json();
    DERNIERES_POSITIONS_SOURCE_A = json.positions;
    redessinerMarqueursGps();
    elErreur.classList.add('hidden');
    document.getElementById('gpsDerniereMaj').textContent = 'Positions à jour · ' + new Date().toLocaleTimeString('fr-FR');
  } catch(e) {
    console.error('Erreur /api/positions-temps-reel :', e);
    elErreur.textContent = 'Positions temps réel indisponibles : ' + e.message;
    elErreur.classList.remove('hidden');
  }
}

// Marqueurs Télématique B
// Position instantanée (marqueur temps réel) — le trajet historique Télématique B (polyligne),
// lui, est géré par chargerTrajetGps() plus haut, pas ici : cette section ne dessine que le
// losange de dernière position, jamais de ligne.
const SEUIL_SOURCE_B_FRAICHE_MIN = 30;
const SEUIL_SOURCE_B_PERIME_MIN = 7 * 24 * 60;
function fraicheurSourceB(minutes){
  if (minutes < SEUIL_SOURCE_B_FRAICHE_MIN) return { cls: 'fraiche', couleur: 'var(--green)', label: 'à jour' };
  if (minutes < SEUIL_SOURCE_B_PERIME_MIN) return { cls: 'attention', couleur: 'var(--amber)', label: 'à surveiller' };
  return { cls: 'perimee', couleur: 'var(--red)', label: 'périmée' };
}

// Losange coloré selon la fraîcheur (couleur param) — la forme (losange, pas un cercle) reste ce
// qui distingue une position Télématique B d'une position Télématique A (mettreAJourUnMarqueurGps ci-dessus) ;
// la couleur, elle, ne code plus rien sur la source (toujours Télématique B ici) mais sur l'âge de la donnée.
function iconMarqueurSourceB(couleur){
  return L.divIcon({
    className: 'marqueur-source_b-gps',
    html: `<div style="width:13px;height:13px;background:${couleur};border:2px solid #fff;transform:rotate(45deg);box-shadow:0 0 2px rgba(0,0,0,.5);"></div>`,
    iconSize: [13, 13],
    iconAnchor: [6, 6],
  });
}

// Décalage entre l'horodatage réel de la position et maintenant — formaté lisiblement
// (min/h/jours) et classé dans un des 3 paliers ci-dessus.
function formaterDecalageSourceB(horodatageIso){
  if (!horodatageIso) return { texte: 'horodatage inconnu', minutes: null, jours: null, fraicheur: fraicheurSourceB(Infinity) };
  const diffMs = Date.now() - new Date(horodatageIso).getTime();
  const minutes = diffMs / 60000;
  let texte;
  if (minutes < 1) texte = "à l'instant";
  else if (minutes < 60) texte = Math.round(minutes) + ' min';
  else if (minutes < 1440) texte = Math.round(minutes / 60) + ' h';
  else texte = Math.round(minutes / 1440) + ' j';
  return { texte, minutes, jours: Math.floor(minutes / 1440), fraicheur: fraicheurSourceB(minutes) };
}

function mettreAJourUnMarqueurSourceB(p){
  if (p.latitude === null || p.longitude === null) return false;

  const decalage = formaterDecalageSourceB(p.horodatage_position);
  const couleur = decalage.fraicheur.couleur;

  let marker = GPS_MARKERS_SOURCE_B[p.camion_id];
  if (!marker) {
    marker = L.marker([p.latitude, p.longitude], { icon: iconMarqueurSourceB(couleur) }).addTo(GPS_MAP);
    GPS_MARKERS_SOURCE_B[p.camion_id] = marker;
  } else {
    marker.setLatLng([p.latitude, p.longitude]);
    // La fraîcheur peut changer entre deux rafraîchissements même sans nouvelle position (un
    // marqueur "à jour" devient "à surveiller" avec le temps qui passe) — l'icône doit donc être
    // réévaluée à chaque passage, pas seulement à la création du marqueur.
    marker.setIcon(iconMarqueurSourceB(couleur));
  }

  const vitesse = p.vitesse != null ? Math.round(p.vitesse) + ' km/h' : '—';
  const horodatageLisible = p.horodatage_position ? new Date(p.horodatage_position).toLocaleString('fr-FR') : 'inconnu';
  const ligneAdresse = p.adresse ? `<br>${p.adresse}` : '';

  // Texte factuel demandé explicitement — jamais "en panne"/"déconnecté".
  let ligneDecalage;
  if (decalage.fraicheur.cls === 'fraiche') {
    ligneDecalage = `<br><span style="color:var(--grey);">Position il y a ${decalage.texte}.</span>`;
  } else if (decalage.jours !== null && decalage.jours >= 1) {
    ligneDecalage = `<br><span style="color:${couleur};">⚠ Dernière position il y a ${decalage.jours} jour${decalage.jours > 1 ? 's' : ''} — ce véhicule semble à l'arrêt ou son boîtier n'émet plus.</span>`;
  } else {
    ligneDecalage = `<br><span style="color:${couleur};">⚠ Position il y a ${decalage.texte} — à surveiller.</span>`;
  }

  marker.bindPopup(
    `<b>${p.immatriculation}</b> <span style="color:#7D3C98;">Source Télématique B</span>` +
    `<br>Vitesse : ${vitesse}` +
    ligneAdresse +
    `<br>Horodatage : ${horodatageLisible}` +
    ligneDecalage
  );
  return true;
}

// Résumé de fraîcheur affiché en haut de l'écran, pour que l'ampleur du décrochage saute aux yeux
// sans cliquer véhicule par véhicule — regroupe "à surveiller" et "périmée" en une seule catégorie
// "position ancienne" comme demandé, même si le marqueur/popup, eux.
function resumeFraicheurSourceB(positions){
  const avecPosition = positions.filter(p => p.latitude !== null && p.longitude !== null);
  let fraiches = 0, anciennes = 0;
  avecPosition.forEach(p => {
    if (formaterDecalageSourceB(p.horodatage_position).fraicheur.cls === 'fraiche') fraiches++;
    else anciennes++;
  });
  return { fraiches, anciennes, total: avecPosition.length };
}

function dessinerMarqueursSourceB(positions){
  initGpsMap();
  const idsVus = new Set();

  positions.forEach(p => {
    if (mettreAJourUnMarqueurSourceB(p)) idsVus.add(p.camion_id);
  });

  Object.keys(GPS_MARKERS_SOURCE_B).forEach(id => {
    if (!idsVus.has(parseInt(id))) {
      GPS_MAP.removeLayer(GPS_MARKERS_SOURCE_B[id]);
      delete GPS_MARKERS_SOURCE_B[id];
    }
  });

  const indisponibles = positions.filter(p => p.latitude === null || p.longitude === null);
  const zoneIndispo = document.getElementById('gpsIndisponiblesSourceB');
  if (zoneIndispo) {
    zoneIndispo.innerHTML = indisponibles.length
      ? `<span class="sub" style="color:var(--grey);">Position Télématique B indisponible pour : ${indisponibles.map(p => p.immatriculation).join(', ')}</span>`
      : '';
  }

  const resume = resumeFraicheurSourceB(positions);
  const elResume = document.getElementById('gpsResumeFraicheurSourceB');
  if (elResume) {
    if (resume.total) {
      elResume.innerHTML = `${resume.fraiches} véhicule${resume.fraiches > 1 ? 's' : ''} à jour (&lt; 30 min) · `
        + `<span style="color:${resume.anciennes ? 'var(--amber)' : 'inherit'};">${resume.anciennes} véhicule${resume.anciennes > 1 ? 's' : ''} avec position ancienne (plusieurs jours)</span>`;
      elResume.classList.remove('hidden');
    } else {
      elResume.classList.add('hidden');
    }
  }
}

async function chargerPositionsSourceBTempsReel(){
  try {
    const res = await fetch(API.positionsTempsReelSourceB);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    DERNIERES_POSITIONS_SOURCE_B = json.positions;
    redessinerMarqueursSourceB();
  } catch(e) {
    console.error('Erreur /api/positions-temps-reel-source_b :', e);
  }
}

// GPS_SELECTION vide : recentre/dézoome sur les positions actuelles de toute la flotte, même
// mécanisme fitBounds() que pour les trajectoires ci-dessous — sans ça, revenir à 0 camion sélectionné
// après avoir zoomé sur une trajectoire laissait la carte cadrée sur cette ancienne sélection, sans
// action pour la recentrer soi-même sur la flotte entière.
function recentrerSurFlotte(){
  const positions = [...Object.values(GPS_MARKERS), ...Object.values(GPS_MARKERS_SOURCE_B)].map(m => m.getLatLng());
  if (positions.length) {
    GPS_MAP.fitBounds(L.latLngBounds(positions), { padding: [30, 30] });
  }
}

// Prototype temps réel — GPS + SER dynamique
// Une seule connexion Echo/Reverb partagée entre les deux cas d'usage — deux canaux publics
// distincts ('positions-flotte' et 'ser-dynamique-flotte'), aucune authentification de canal pour
// ce POC.
let ECHO_TEMPS_REEL_CONNECTE = false;

function initEchoTempsReel(){
  // Le build IIFE de laravel-echo (echo.iife.js) expose window.Echo comme un objet namespace
  // ({Channel, Connector, EventFormatter, default}), pas comme le constructeur directement — le
  // constructeur réel est Echo.default.
  const EchoConstructeur = (typeof Echo !== 'undefined' && typeof Echo.default === 'function')
    ? Echo.default
    : (typeof Echo === 'function' ? Echo : null);
  if (!EchoConstructeur) {
    console.warn('Prototype temps réel : Echo non chargé (CDN indisponible ?) — le polling 45s reste actif seul, sur les deux écrans.');
    return;
  }

  window.Echo = new EchoConstructeur({
    broadcaster: 'reverb',
    key: '{{ config('broadcasting.connections.reverb.key') }}',
    wsHost: '{{ config('broadcasting.connections.reverb.options.host') }}',
    wsPort: {{ (int) config('broadcasting.connections.reverb.options.port') }},
    wssPort: {{ (int) config('broadcasting.connections.reverb.options.port') }},
    forceTLS: {{ config('broadcasting.connections.reverb.options.scheme') === 'https' ? 'true' : 'false' }},
    enabledTransports: ['ws', 'wss'],
  });

  window.Echo.connector.pusher.connection.bind('connected', () => {
    ECHO_TEMPS_REEL_CONNECTE = true;
    console.log('Prototype temps réel : connecté à Reverb (GPS + SER dynamique).');
  });
  window.Echo.connector.pusher.connection.bind('disconnected', () => {
    ECHO_TEMPS_REEL_CONNECTE = false;
    console.warn('Prototype temps réel : déconnecté de Reverb — repli sur le polling 45s des deux écrans.');
  });

  // broadcastAs('position.maj') sans namespace : le '.' initial est requis par Echo pour un nom
  // d'event personnalisé (sinon il cherche un event namespacé par défaut et ne matche jamais).
  window.Echo.channel('positions-flotte').listen('.position.maj', (e) => {
    // mettreAJourUnMarqueurGps() (pas dessinerMarqueursGps()) : un event ne porte qu'UN camion —
    // passer ça à dessinerMarqueursGps() purgerait les 11 autres marqueurs de la carte.
    initGpsMap();
    // Filtre général — un event WS pour un camion actuellement exclu par
    // Site/Usage/Matricule/Marque/Périmètre ne doit pas le faire réapparaître sur la carte, même
    // mis à jour un par un plutôt que par le rechargement complet ci-dessus.
    const camionEvent = camionParId(e.camion_id);
    if (!camionEvent || !camionCorrespondFiltresGlobaux(camionEvent)) return;
    mettreAJourUnMarqueurGps({
      camion_id: e.camion_id,
      latitude: e.latitude,
      longitude: e.longitude,
      vitesse: e.vitesse,
      horodatage_position: e.horodatage,
      immatriculation: camionParId(e.camion_id)?.immatriculation ?? '—',
    });
  });

  // Canal 'ser-dynamique-flotte' — 2e cas d'usage du prototype. e porte déjà exactement la forme
  // d'un élément de SER_DYN_DATA (même clés que /api/ser-dynamique, cf.
  // StatutSerMisAJour::broadcastWith() côté backend) : pas de reconstruction d'objet nécessaire
  // ici, contrairement au canal GPS ci-dessus.
  window.Echo.channel('ser-dynamique-flotte').listen('.statut.maj', (e) => {
    mettreAJourUnStatutSer(e);
  });
}
@if (config('broadcasting.connections.reverb.key'))
initEchoTempsReel();
@endif

// Date locale au format AAAA-MM-JJ, à partir des getters LOCAUX (pas toISOString(), qui convertit
// d'abord en UTC — près de minuit heure locale, ça peut renvoyer la veille ou le lendemain selon
// le fuseau du navigateur). Utilisée pour la valeur par défaut des <input type="date"> GPS.
function dateLocaleISO(d){
  const annee = d.getFullYear();
  const mois = String(d.getMonth() + 1).padStart(2, '0');
  const jour = String(d.getDate()).padStart(2, '0');
  return `${annee}-${mois}-${jour}`;
}
// 'T00:00:00' explicite : sans ça, new Date est interprété comme minuit UTC, puis
// toLocaleDateString() peut afficher le jour précédent selon le fuseau du navigateur.
function formatDateFrCourt(dateStr){
  return new Date(`${dateStr}T00:00:00`).toLocaleDateString('fr-FR');
}

// Multi-sélection — une trajectoire colorée par camion de GPS_SELECTION, avec légende
// couleur-par-camion (#gpsLegendeTrajets). initGpsMap() en tête : cette fonction peut s'exécuter
// avant chargerPositionsTempsReel()
function estSourceBMappe(camion){
  return !!(camion && (camion.source_b_complementaire || camion.source_principale === 'SOURCE_B'));
}

async function chargerTrajetGps(){
  const generation = ++GPS_TRAJET_GENERATION;
  initGpsMap();
  Object.values(GPS_TRAJET_LAYERS).forEach(layer => GPS_MAP.removeLayer(layer));
  GPS_TRAJET_LAYERS = {};
  // Calques Télématique B — nettoyés/redessinés en même temps que les calques Télématique A
  // ci-dessus, mais dans leur propre dict (cf. déclaration de GPS_TRAJET_LAYERS_SOURCE_B) : un
  // même camion peut avoir les deux tracés simultanément, jamais l'un n'écrase l'autre.
  Object.values(GPS_TRAJET_LAYERS_SOURCE_B).forEach(layer => GPS_MAP.removeLayer(layer));
  GPS_TRAJET_LAYERS_SOURCE_B = {};
  const zone = document.getElementById('gpsTrajetEtat');
  const legende = document.getElementById('gpsLegendeTrajets');

  if (!GPS_SELECTION.length) {
    zone.classList.add('hidden');
    legende.classList.add('hidden');
    legende.innerHTML = '';
    recentrerSurFlotte();
    return;
  }

  const depuis = document.getElementById('gpsDateDebut').value;
  const jusquA = document.getElementById('gpsDateFin').value;
  if (!depuis || !jusquA) { zone.classList.add('hidden'); return; }
  const periodeTxt = depuis === jusquA
    ? `le ${formatDateFrCourt(depuis)}`
    : `du ${formatDateFrCourt(depuis)} au ${formatDateFrCourt(jusquA)}`;

  // Instantané figé de la sélection + couleurs assignées par position AVANT les await — même raison
  // que renderComparateur() : un clic pendant les fetch() en vol ne doit pas mélanger les
  // couleurs ni les trajectoires entre deux sélections successives.
  const selection = [...GPS_SELECTION];
  const couleurs = genererCouleurs(selection.length);
  const messages = [];
  // Camions dont le trajet Télématique B a réellement été dessiné (≥1 point) — pour noter "+ trajet Télématique B" à
  // côté du bon swatch dans la légende, sans devoir refaire cette vérification à ce moment-là.
  const idsAvecTrajetSourceB = new Set();

  await Promise.all(selection.map(async (id, idx) => {
    const camion = camionParId(id);
    try {
      const res = await fetch(`${API.positionsHistorique(id)}?depuis=${depuis}&jusqu_a=${jusquA}`);
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || ('HTTP ' + res.status));
      }
      const json = await res.json();
      if (!json.points.length) {
        // "Télématique A" explicite depuis l'ajout du trajet Télématique B — sans ce mot, ce
        // message pouvait laisser croire à une absence totale de trajet pour un véhicule
        // Télématique B-pur qui, lui, affiche bien une trajectoire Télématique B juste à côté (cf.
        // message symétrique plus bas)
        messages.push(`Aucun point de trajet Télématique A archivé ${periodeTxt} pour ${json.immatriculation}.`);
        return;
      }
      // generation revérifiée juste avant d'écrire sur la carte : un appel plus récent (déjà passé
      // par le nettoyage synchrone en tête de fonction) a priorité, ce calque périmé ne doit jamais
      // s'y ajouter après coup.
      if (generation !== GPS_TRAJET_GENERATION) return;
      const latlngs = json.points.map(p => [p.latitude, p.longitude]);
      GPS_TRAJET_LAYERS[id] = L.polyline(latlngs, { color: couleurs[idx], weight: 3, opacity: 0.8 }).addTo(GPS_MAP);
    } catch(e) {
      console.error('Erreur /api/positions-historique :', e);
      messages.push(`Erreur de chargement du trajet pour ${camion?.immatriculation ?? id} : ${e.message}`);
    }
  }));

  // Trajet Télématique B — SEULEMENT pour les camions réellement mappés Télématique B, jamais un
  // appel réseau systématique pour les 27 : même couleur que le tracé Télématique A du même camion
  // (couleurs[idx], réutilisée telle quelle) mais en pointillé (dashArray)
  await Promise.all(selection.map(async (id, idx) => {
    const camion = camionParId(id);
    if (!estSourceBMappe(camion)) return;
    try {
      const res = await fetch(`${API.positionsHistoriqueSourceB(id)}?depuis=${depuis}&jusqu_a=${jusquA}`);
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || ('HTTP ' + res.status));
      }
      const json = await res.json();
      if (!json.points.length) {
        messages.push(`Aucun point de trajet Télématique B archivé ${periodeTxt} pour ${json.immatriculation}.`);
        return;
      }
      if (generation !== GPS_TRAJET_GENERATION) return;
      const latlngs = json.points.map(p => [p.latitude, p.longitude]);
      GPS_TRAJET_LAYERS_SOURCE_B[id] = L.polyline(latlngs, { color: couleurs[idx], weight: 3, opacity: 0.8, dashArray: '6,6' }).addTo(GPS_MAP);
      idsAvecTrajetSourceB.add(id);
    } catch(e) {
      console.error('Erreur /api/positions-historique-source_b :', e);
      messages.push(`Erreur de chargement du trajet Télématique B pour ${camion?.immatriculation ?? id} : ${e.message}`);
    }
  }));

  // Un appel plus récent a démarré (et déjà nettoyé/redessiné) pendant nos fetch() — abandonner
  // silencieusement plutôt que d'écraser la légende/l'état "gpsTrajetEtat" avec un résultat périmé.
  if (generation !== GPS_TRAJET_GENERATION) return;

  legende.innerHTML = selection.map((id, idx) => {
    const c = camionParId(id);
    const noteSourceB = idsAvecTrajetSourceB.has(id)
      ? ' <span style="color:#7D3C98;" title="Trajet Télématique B affiché en pointillé, même couleur">(+ Télématique B ┄)</span>'
      : '';
    return `<span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${couleurs[idx]};margin-right:5px;"></span>${c?.immatriculation ?? id}${noteSourceB}</span>`;
  }).join('');
  legende.classList.remove('hidden');

  if (messages.length) {
    zone.textContent = messages.join(' ');
    zone.classList.remove('hidden');
  } else {
    zone.classList.add('hidden');
  }

  const layers = [...Object.values(GPS_TRAJET_LAYERS), ...Object.values(GPS_TRAJET_LAYERS_SOURCE_B)];
  if (layers.length) {
    GPS_MAP.fitBounds(L.featureGroup(layers).getBounds(), { padding: [30, 30] });
  }
}

// invalidateSize() : le conteneur #mapGps est à 0x0 tant que l'onglet "gps" n'a jamais été affiché
// (display:none sur un ancêtre), Leaflet a donc besoin d'être notifié explicitement une fois la
// vraie taille disponible — sinon la carte reste tronquée/mal centrée au premier affichage.
document.querySelector('.nav-btn[data-tab="gps"]').addEventListener('click', () => {
  if (GPS_MAP) setTimeout(() => GPS_MAP.invalidateSize(), 0);
});

// #mapGps est maintenant dimensionné en clamp(...vh...) plutôt qu'en hauteur fixe (cf. CSS) : sa
// taille réelle en pixels change avec le viewport (resize fenêtre, rotation mobile), pas seulement
// à la première ouverture de l'onglet — Leaflet ne l'observe pas tout seul, invalidateSize() doit
// donc aussi être redéclenché ici, uniquement si l'onglet GPS est bien celui affiché.
window.addEventListener('resize', () => {
  if (GPS_MAP && !document.getElementById('tab-gps').classList.contains('hidden')) {
    GPS_MAP.invalidateSize();
  }
});

// ---------- Comparaison par site (SITE A vs SITE B) ----------
let chartSitesEcoInstance = null, chartSitesConsoInstance = null;
const COULEURS_SITE = { 'SITE A': '#1F618D', 'SITE B': '#B9770E' };

let COMPARATEUR_SITES_TOUTES = [];
async function chargerComparateurSites(){
  try {
    const res = await fetch(API.comparateurSites);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    COMPARATEUR_SITES_TOUTES = await res.json();
  } catch(e) {
    console.error('Erreur chargement /api/comparateur-sites :', e);
    COMPARATEUR_SITES_TOUTES = [];
  }
  rafraichirComparateurSites();
}
// Reapplique le sélecteur de période de l'onglet Comparateur sur les données déjà en cache (pas de
// nouvel appel réseau) — appelée au chargement initial et à chaque changement de période.
function rafraichirComparateurSites(){
  // Canvas dans la zone « vue flotte » : masqués (0x0) dès qu'un camion est coché ou hors de l'écran Accueil — un
  // Chart.js né à 0x0 y reste bloqué (cf. commentaire plus bas) ; on ne dessine donc que si visible.
  if (document.getElementById('tab-apercu').classList.contains('hidden') || document.getElementById('detailVueFlotte').classList.contains('hidden')) return;
  const bornes = bornesPeriodeEcran();
  const lignes = (bornes.depuis || bornes.jusquA)
    ? COMPARATEUR_SITES_TOUTES.filter(l => dansPeriode(l.mois + '-01', bornes))
    : COMPARATEUR_SITES_TOUTES;
  renderComparateurSites(lignes);
}
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
    options: { responsive: true, ...optionsAspectRatioGraphique(), interaction: { mode: 'index', intersect: false }, scales: { y: { min: 0, max: 10, title: { display: true, text: 'Eco Score' } } } },
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
    options: { responsive: true, ...optionsAspectRatioGraphique(), interaction: { mode: 'index', intersect: false }, scales: { y: { title: { display: true, text: 'L/100km' } } } },
  });
}

// chargerComparateurSites() (appelée une seule fois au démarrage, cf. section Démarrage plus bas)
// s'exécute pendant que #tab-comparateur est encore display:none (l'onglet par défaut est "apercu")
// : Chart.js initialise alors chartSitesEco/chartSitesConso dans un conteneur à 0x0. Même piège que
// la carte GPS (invalidateSize() sur clic d'onglet, cf. plus bas), mais remède différent : un simple
// .resize() ne suffit PAS ici (vérifié) — un chart Chart.js né dans un conteneur à 0x0 reste bloqué à
// 0x0 même après resize(), la taille par défaut (dérivée des attributs HTML natifs du canvas) ayant
// déjà été figée à la création. Il faut détruire et recréer le chart une fois le conteneur réellement
// visible, d'où le simple rappel de chargerComparateurSites() (destroy+recreate déjà géré dedans,
// même pattern que tous les autres graphiques du dashboard qui se redessinent à chaque interaction).
document.querySelector('.nav-btn[data-tab="apercu"]').addEventListener('click', () => {
  setTimeout(rafraichirComparateurSites, 0);
});
document.getElementById('btnDetailEffacer').addEventListener('click', () => definirSelectionFlotte([]));

document.getElementById('chkAfficherProjection').addEventListener('change', (e) => {
  AFFICHER_PROJECTION = e.target.checked;
  if (CAMION_ACTIF && CAMION_CACHE[CAMION_ACTIF]) renderFicheCamion();
});

async function getProjection(camionId, metrique){
  const cle = `${camionId}_${metrique}`;
  if (PROJECTION_CACHE[cle]) return PROJECTION_CACHE[cle];
  try {
    const res = await fetch(API.projection(camionId, metrique));
    const data = await res.json();
    PROJECTION_CACHE[cle] = res.ok ? data : { erreur: true, message: data.message ?? 'Projection indisponible.' };
  } catch (e) {
    PROJECTION_CACHE[cle] = { erreur: true, message: 'Projection indisponible.' };
  }
  return PROJECTION_CACHE[cle];
}

// Santé des automatisations (badge topbar)
async function chargerSanteAutomatisations(){
  const badge = document.getElementById('badgeSanteAutomatisations');
  try {
    const res = await fetch(API.santeAutomatisations);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    const enRetard = (data.taches || []).filter(t => t.en_retard);

    if (!enRetard.length){
      badge.classList.add('hidden');
      return;
    }
    badge.textContent = enRetard.length === 1
      ? `⚠ 1 automatisation en retard`
      : `⚠ ${enRetard.length} automatisations en retard`;
    badge.title = enRetard.map(t => {
      const derniere = t.derniere_execution_reussie ? new Date(t.derniere_execution_reussie).toLocaleString('fr-FR') : 'jamais';
      return `${t.tache} — dernier succès : ${derniere}`;
    }).join('\n');
    badge.classList.remove('hidden');
  } catch(e) {
    console.error('Erreur /api/sante-automatisations :', e);
    // Ni vert ni rouge sur une erreur réseau : on ne sait juste pas — le badge reste dans son état
    // précédent plutôt que de basculer silencieusement à "tout va bien".
  }
}

// ---------- Démarrage ----------
document.getElementById('gpsDateDebut').value = dateLocaleISO(new Date());
document.getElementById('gpsDateFin').value = dateLocaleISO(new Date());
restaurerFiltres();
remplirCheckboxesMetriquesSupp();
// Sélecteurs de période ajoutés : chaque écran ne re-fetch jamais son API sur un simple changement
// de période, il refiltre les données déjà en cache (cf. onChange ci-dessous)
initSelecteurPeriode('Registre', appliquerPeriodeRegistre);
initSelecteurPeriode('Courbes', chargerCourbes);
initSelecteurPeriode('Alertes', renderFluxAlertesSuggestions);
initSelecteurPeriode('SerStandards', chargerSerStandards);
chargerFlotte();
chargerComparateurSites();
chargerSerStandards();
chargerSerDynamique();
chargerCourbesMetriques();
chargerSuggestions();
chargerSanteAutomatisations();

// Polling périodique : garde les statuts norme interne à jour quand sheets:sync-registre importe
// de nouvelles valeurs pendant qu'un onglet est ouvert. 45s : milieu de la fourchette 30-60s
// retenue.
setInterval(chargerSerDynamique, 45000);

// Suivi GPS : mêmes 45s que le polling SER ci-dessus (milieu de la fourchette 30-60s), pour une
// impression de suivi temps réel sans recharger la page. Rafraîchit aussi le trajet du jour des
// camions sélectionnés, sans qu'il faille re-cliquer sur les chips).
setInterval(() => {
  chargerPositionsTempsReel();
  chargerPositionsSourceBTempsReel();
  if (GPS_SELECTION.length) chargerTrajetGps();
}, 45000);

// Santé des automatisations : les tâches surveillées sont au mieux quotidiennes, un polling toutes les
// 5 min suffit largement.
setInterval(chargerSanteAutomatisations, 300000);
</script>
</body>
</html>
