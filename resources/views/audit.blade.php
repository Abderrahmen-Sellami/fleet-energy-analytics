<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet Energy Analytics · Rapport d'audit ISO 50001</title>
<style>
  :root{
    --navy:#1C2833; --navy-soft:#25313D; --card:#FFFFFF; --bg:#F4F6F7;
    --amber:#B9770E; --amber-soft:#FEF5E7; --green:#1E8449; --green-soft:#EAF7EE;
    --red:#C0392B; --red-soft:#FDEDEC; --grey:#5D6D7E; --line:#E1E4E6; --text:#212B33;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:-apple-system,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);
    -webkit-font-smoothing:antialiased;}
  h1,h2,h3{font-family:Georgia,"Times New Roman",serif;}

  /* ---------- Barre d'actions (écran seulement) ---------- */
  .toolbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;
    padding:12px 28px;background:#fff;border-bottom:1px solid var(--line);box-shadow:0 1px 3px rgba(0,0,0,.05);}
  .toolbar a{color:var(--grey);text-decoration:none;font-size:13px;font-weight:600;}
  .toolbar a:hover{color:var(--navy);}
  .btn-print{display:flex;align-items:center;gap:7px;background:var(--navy);color:#fff;border:none;
    border-radius:7px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;}
  .btn-print:hover{background:var(--navy-soft);}

  .sheet{max-width:900px;margin:28px auto 80px;background:#fff;border:1px solid var(--line);
    border-radius:10px;box-shadow:0 2px 18px rgba(0,0,0,.06);padding:52px 56px;}

  /* ---------- En-tête du document ---------- */
  .doc-header{display:flex;justify-content:space-between;align-items:flex-end;
    border-bottom:3px solid var(--navy);padding-bottom:18px;margin-bottom:28px;}
  .doc-header .brand b{display:block;font-size:24px;letter-spacing:.01em;color:var(--navy);}
  .doc-header .brand span{display:block;font-size:12px;color:var(--grey);margin-top:2px;}
  .doc-title{font-size:15px;font-weight:700;color:var(--navy);margin-top:10px;}
  .doc-meta{text-align:right;font-size:11.5px;color:var(--grey);line-height:1.7;}
  .doc-meta b{color:var(--text);}

  .section{margin:34px 0;}
  .section-title{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:700;color:var(--navy);
    margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid var(--line);}
  .section-num{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;
    border-radius:50%;background:var(--navy);color:#fff;font-size:12px;font-family:-apple-system,Arial,sans-serif;
    flex-shrink:0;}

  /* ---------- KPI ---------- */
  .kpi-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:9px;padding:14px 16px;}
  .kpi .lbl{font-size:10px;color:var(--grey);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
  .kpi .val{font-size:21px;font-weight:700;letter-spacing:-.01em;}
  .kpi .val small{font-size:11.5px;color:var(--grey);font-weight:500;margin-left:3px;}
  .kpi .foot{font-size:10px;color:var(--amber);margin-top:4px;}

  /* ---------- Tableaux ---------- */
  table{width:100%;border-collapse:separate;border-spacing:0;font-size:11.3px;}
  th{text-align:left;padding:7px 8px;color:#fff;background:var(--navy);font-weight:600;font-size:10px;
    text-transform:uppercase;letter-spacing:.03em;}
  /* En-têtes fixes : collés sous la barre d'actions (sticky, --toolbar-h mesurée en JS) pendant le
     défilement de la page. */
  thead th{position:sticky;top:var(--toolbar-h,58px);z-index:3;}
  td{padding:7px 8px;border-bottom:1px solid var(--line);vertical-align:top;}
  tbody tr:nth-child(even){background:#FBFCFC;}
  .num{text-align:right;font-variant-numeric:tabular-nums;}

  .badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:9.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
  .badge.site-a{background:#EAF2F8;color:#1F618D;}
  .badge.site-b{background:var(--amber-soft);color:var(--amber);}
  .badge.rouge{background:var(--red-soft);color:var(--red);}
  .badge.orange{background:var(--amber-soft);color:var(--amber);}
  .badge.vert-clair{background:var(--green-soft);color:var(--green);}
  .badge.vert-fonce{background:var(--green);color:#fff;}
  .badge.gris{background:#EAECEE;color:var(--grey);}

  .muted{color:var(--grey);}
  .small{font-size:10.3px;}

  .info-box{background:var(--amber-soft);border-left:4px solid var(--amber);border-radius:6px;padding:11px 14px;
    font-size:11.5px;color:#6E4B0A;line-height:1.6;margin-top:14px;}

  .analyse-box{background:var(--green-soft);border-left:3px solid var(--green);border-radius:5px;padding:7px 10px;
    font-size:10.3px;color:#14532A;line-height:1.55;margin-top:4px;}
  .analyse-box b{display:block;color:#0F3D1F;}
  .no-analyse{font-size:10.3px;color:var(--grey);font-style:italic;}

  .methodo p{font-size:12px;line-height:1.75;margin:0 0 12px;}
  .methodo h4{font-size:12.5px;margin:18px 0 6px;color:var(--navy);}
  .methodo ul{margin:0 0 12px;padding-left:20px;font-size:12px;line-height:1.7;}
  .methodo li{margin-bottom:4px;}

  .signature-block{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:44px;padding-top:24px;
    border-top:1px solid var(--line);}
  .signature-block div{font-size:11px;color:var(--grey);}
  .signature-line{margin-top:46px;border-top:1px solid var(--text);padding-top:5px;font-size:10.5px;}

  .doc-footer{margin-top:36px;padding-top:14px;border-top:1px solid var(--line);font-size:9.5px;color:var(--grey);
    text-align:center;}

  .empty-state{padding:20px;text-align:center;color:var(--grey);font-size:12px;font-style:italic;}

  @media (max-width:768px){
    /* .kpi-strip (4 colonnes) : même traitement que .kpi-strip du dashboard (5 colonnes -> 2 sur
       mobile) — 4 colonnes fixes ne laissent que ~50px par carte à 375px une fois .sheet padding
       (52px 56px) retiré, le contenu forçait un débordement horizontal de toute la page. */
    .kpi-strip{grid-template-columns:repeat(2,minmax(0,1fr));}

    /* Tableaux larges (jusqu'à 9 colonnes en section 2) : overflow-x posé sur un conteneur dédié
       autour de chaque <table>, pas sur toute la page — même pattern que .table-scroll du
       dashboard — pour que seul le tableau scrolle horizontalement sans faire déborder .sheet. */
    .table-scroll{overflow:auto;-webkit-overflow-scrolling:touch;max-height:min(72vh,720px);}
    .table-scroll thead th{top:0;}
  }

  /* ---------- Impression / Export PDF ---------- */
  @media print{
    body{background:#fff;}
    .toolbar{display:none !important;}
    .sheet{max-width:none;margin:0;border:none;box-shadow:none;border-radius:0;padding:0;}
    .section{break-inside:auto;}
    /* break-inside:avoid : break-after:avoid seul n'empêchait pas de couper LE TITRE LUI-MÊME en
       deux. */
    .section-title{break-after:avoid;break-inside:avoid;}
    /* Section 2 (statut norme interne par véhicule, 10 colonnes dont « Consommation Télématique B ») en PAYSAGE : en portrait A4 la
       dernière colonne dépassait la largeur imprimable et était tronquée (« CONSO… », valeurs coupées) — inacceptable
       sur un document d'audit, où aucune donnée ne doit être perdue. Page nommée (même mécanisme que « paysage » du
       dashboard) : la section commence sur sa propre page paysage, la suite du rapport repasse en portrait. */
    @page paysage{size:A4 landscape;margin:16mm 14mm;}
    .section-paysage{page:paysage;}
    .kpi{page-break-inside:avoid;}
    table{page-break-inside:auto;}
    .table-scroll{max-height:none !important;}
    thead th{position:static !important;}
    tr{page-break-inside:avoid;}
    thead{display:table-header-group;}
    .signature-block{page-break-inside:avoid;}
    @page{size:A4;margin:16mm 14mm;}
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="/direction">← Retour à la Vue Direction</a>
  <button class="btn-print" id="btnPrint">🖨️ Imprimer / Exporter en PDF</button>
</div>

<div class="sheet">

  <div class="doc-header">
    <div class="brand">
      <b>Fleet Energy Analytics</b>
      <span>Application de démonstration</span>
      <div class="doc-title">Rapport d'audit — Système de management de l'énergie (ISO 50001)</div>
    </div>
    <div class="doc-meta">
      <div>Généré le <b>{{ $genereLe->translatedFormat('d/m/Y à H:i') }}</b></div>
      {{-- La période est obligatoire (formulaire de génération) ; son libellé est calculé une seule fois par le contrôleur. --}}
      <div>Période couverte : <b>{{ $periodeLabel }}</b></div>
      {{-- Plus jamais "12" en dur : $camions/$resume['par_groupe'] viennent de resumeFlotte(), donc
         l'effectif et les sites affichés ici ne peuvent pas diverger de la vraie flotte en base. --}}
      <div>Périmètre : <b>{{ $camions->count() }} véhicule{{ $camions->count() > 1 ? 's' : '' }} · {{ implode(' & ', array_keys($resume['par_groupe'])) }}</b></div>
    </div>
  </div>

  {{-- ============== SECTION 1 — KPI flotte ============== --}}
  <div class="section">
    <div class="section-title"><span class="section-num">1</span> Indicateurs de performance énergétique</div>

    <div class="kpi-strip">
      <div class="kpi">
        <div class="lbl">Éco-conduite moyenne (flotte)</div>
        <div class="val">
          @if($resume['nb_mesures_periode'])
            {{ number_format($resume['eco_score_moyen'], 2, ',', ' ') }}<small>/10</small>
          @else
            <span class="muted">—</span><small>aucune donnée</small>
          @endif
        </div>
      </div>
      <div class="kpi">
        <div class="lbl">Consommation moyenne (flotte)</div>
        <div class="val">
          @if($resume['nb_mesures_periode'])
            {{ number_format($resume['conso_moyenne'], 2, ',', ' ') }}<small>L/100km</small>
          @else
            <span class="muted">—</span><small>aucune donnée</small>
          @endif
        </div>
      </div>
      <div class="kpi">
        <div class="lbl">Distance totale parcourue</div>
        <div class="val">@if(($resume['nb_mesures_periode'] ?? 0) > 0 || ! empty($resume['distance_totale_inclut_source_b'])){{ number_format($resume['distance_totale'], 0, ',', ' ') }}<small>km</small>@else — <small>aucune donnée</small>@endif</div>
      </div>
      <div class="kpi">
        <div class="lbl">Coût carburant moyen</div>
        <div class="val">
          @if($resume['cout_carburant_moyen'] !== null)
            {{ number_format($resume['cout_carburant_moyen'], 3, ',', ' ') }}<small>DT/km</small>
          @else
            <span class="muted">—</span><small>aucune donnée</small>
          @endif
        </div>
        @if($resume['cout_carburant_estime'])
          <div class="foot">Prix estimé (historique de prix incomplet)</div>
        @endif
      </div>
    </div>

    @php
      // Même logique que la note "dont N avec données Télématique A" du bandeau KPI Vue
      // d'ensemble.
      $nbSourceAParGroupe = collect($resume['par_camion'] ?? [])
        ->where('source_principale', 'SOURCE_A')
        ->groupBy('groupe')
        ->map->count();
    @endphp
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Site</th>
          <th class="num">Véhicules</th>
          <th class="num">Conso. moyenne (historique)</th>
          {{-- depuis/jusquA toujours renseignés désormais (formulaire obligatoire) : ce champ suit
               systématiquement la période choisie, jamais un repli "6 derniers mois" implicite (cf.
               CamionController::resumeFlotte(), par_groupe.conso_moyenne_6_derniers_mois). --}}
          <th class="num">Conso. moyenne (période couverte)</th>
          <th class="num">Objectif générique</th>
          <th class="num">Écart vs objectif générique</th>
          <th class="num">Éco Score moyen</th>
        </tr>
      </thead>
      <tbody>
        @foreach($resume['par_groupe'] as $site => $g)
          @php $nbSourceA = $nbSourceAParGroupe->get($site, 0); @endphp
          <tr>
            <td><span class="badge {{ strtolower($site) }}">{{ $site }}</span></td>
            <td class="num">
              {{ $g['nb_camions'] }}
              @if($nbSourceA !== $g['nb_camions'])
                <div class="small muted">dont {{ $nbSourceA }} avec données Télématique A</div>
              @endif
            </td>
            <td class="num">{{ $g['conso_moyenne_globale'] !== null ? number_format($g['conso_moyenne_globale'], 2, ',', ' ') . ' L/100km' : '—' }}</td>
            <td class="num">{{ $g['conso_moyenne_6_derniers_mois'] !== null ? number_format($g['conso_moyenne_6_derniers_mois'], 2, ',', ' ') . ' L/100km' : '—' }}</td>
            <td class="num">{{ number_format($g['objectif_officiel'], 0, ',', ' ') }} L/100km</td>
            <td class="num">{{ $g['ipe_vs_objectif_pct'] !== null ? ($g['ipe_vs_objectif_pct'] > 0 ? '+' : '') . number_format($g['ipe_vs_objectif_pct'], 1, ',', ' ') . ' %' : '—' }}</td>
            <td class="num">{{ $g['eco_score_moyen'] !== null ? number_format($g['eco_score_moyen'], 2, ',', ' ') . '/10' : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>

    @php
      // Tiré de $resume['par_groupe'] (même source que le tableau juste au-dessus), jamais codé en
      // dur — si les sites divergent un jour sur objectif_officiel, ce texte ne prétend plus à une
      // valeur unique plutôt que d'en afficher une fausse.
      $objectifsUniques = collect($resume['par_groupe'])->pluck('objectif_officiel')->unique()->values();
      $objectifGeneriqueTexte = $objectifsUniques->count() === 1
        ? number_format($objectifsUniques->first(), 0, ',', ' ') . ' L/100km'
        : 'variable selon le site';
    @endphp
    <div class="info-box">
      <b>À propos de l'objectif « générique » ci-dessus ({{ $objectifGeneriqueTexte }})</b> — cette valeur provient
      de l'ancien fleet report Télématique A ; elle est indicative et secondaire. La <b>référence officielle de
      performance énergétique est la norme interne de référence (SER, exprimée en L/100km pour les usages Transport et en L/h pour les autres usages)</b>, propre à chaque véhicule et non à un
      objectif de flotte unique — voir le détail par véhicule en section 2.
    </div>
  </div>

  {{-- ============== SECTION 2 — Statut norme interne par véhicule ============== --}}
  <div class="section section-paysage">
    <div class="section-title"><span class="section-num">2</span> Statut norme interne par véhicule</div>
    <p class="small muted" style="margin:-8px 0 14px;">
      Statut actuel de chaque véhicule (dernière mesure registre (norme interne) réellement exploitable) — indépendant de
      la période du rapport ci-dessus, un statut "à ce jour" n'ayant pas de sens recalculé à une date passée
      (voir section 5, "Délai de saisie du registre (norme interne)").
    </p>

    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Immatriculation</th>
          <th>Site</th>
          <th>Statut</th>
          <th class="num">IPE actuel</th>
          <th class="num">SER norme interne</th>
          <th class="num">Seuil rouge (SER&times;1,x)</th>
          <th class="num">Cible</th>
          <th>Dernière réf. registre (norme interne)</th>
          <th>Dernière mesure Télématique A</th>
          <th class="num">Consommation Télématique B</th>
        </tr>
      </thead>
      <tbody>
        @foreach($camions as $ligne)
          @php
            $c = $ligne->camion; $s = $ligne->statut;
            // Unité de la SER du véhicule (NormeRefStatutService::uniteSer, dérivée de l'usage)
            $libUnite = fn ($u) => $u === 'l/h' ? 'L/h' : 'L/100km';
            $uniteSerLib = $libUnite($s['unite_ser'] ?? null);
          @endphp
          <tr>
            <td><b>{{ $c->immatriculation }}</b></td>
            <td><span class="badge {{ strtolower($c->groupe) }}">{{ $c->groupe }}</span></td>
            <td>
              @php
                $statutClasses = [
                  'ROUGE' => 'rouge', 'ORANGE' => 'orange',
                  'VERT_CLAIR' => 'vert-clair', 'VERT_FONCE' => 'vert-fonce',
                  'DONNEES_INSUFFISANTES' => 'gris',
                ];
                $statutLabels = [
                  'ROUGE' => 'Rouge', 'ORANGE' => 'Orange',
                  'VERT_CLAIR' => 'Vert clair', 'VERT_FONCE' => 'Vert foncé',
                  'DONNEES_INSUFFISANTES' => 'Données insuffisantes',
                ];
                $statutCode = $s['statut'] ?? 'DONNEES_INSUFFISANTES';

                // Mêmes libellés que texteDonneesInsuffisantes() dans dashboard.blade.php, pour ne
                // jamais diverger entre le dashboard technique et ce rapport sur la formulation exacte
                // d'une même raison — raison_insuffisance vient du backend (NormeRefStatutService), jamais
                // déduite ici.
                $raisonTexte = null;
                if ($statutCode === 'DONNEES_INSUFFISANTES') {
                    $raisonTexte = match($s['raison_insuffisance'] ?? null) {
                        'ABONNEMENT_RECENT' => 'Abonnement Télématique A récent (actif depuis ' . ($c->source_a_actif_depuis ? \Carbon\Carbon::parse($c->source_a_actif_depuis)->format('d/m/Y') : '—') . ')',
                        'UNITE_INCOMPATIBLE' => 'Donnée saisie en L/100km, mais la SER de ce véhicule (usage hors Transport) est en L/h — heures de fonctionnement non renseignées ce mois-ci, statut non calculable de façon fiable.',
                        default => 'Donnée du mois en cours pas encore saisie dans le registre.',
                    };
                }
              @endphp
              <span class="badge {{ $statutClasses[$statutCode] }}">{{ $statutLabels[$statutCode] }}</span>
              @if($raisonTexte)
                <div class="small muted" style="margin-top:4px;max-width:210px;">{{ $raisonTexte }}</div>
              @endif
            </td>
            <td class="num">{{ isset($s['ipe']) ? number_format($s['ipe'], 2, ',', ' ') . ' ' . $libUnite($s['unite'] ?? null) : '—' }}</td>
            <td class="num">{{ $c->ser_norme_ref !== null ? number_format($c->ser_norme_ref, 2, ',', ' ') . ' ' . $uniteSerLib : '—' }}</td>
            <td class="num">{{ isset($s['ser_1x']) ? number_format($s['ser_1x'], 2, ',', ' ') . ' ' . $uniteSerLib : '—' }}</td>
            <td class="num">{{ $c->ser_norme_ref_cible !== null ? number_format($c->ser_norme_ref_cible, 2, ',', ' ') . ' ' . $uniteSerLib : '—' }}</td>
            <td class="small">
              {{ isset($s['derniere_date']) ? \Carbon\Carbon::parse($s['derniere_date'])->format('d/m/Y') : '—' }}
              <span class="muted">· registre (norme interne)</span>
            </td>
            <td class="small">
              {{ $ligne->derniere_mesure_source_a ? \Carbon\Carbon::parse($ligne->derniere_mesure_source_a->date)->format('d/m/Y') : '—' }}
              <span class="muted">· Télématique A</span>
            </td>
            {{-- Complément, jamais un remplacement de la consommation Télématique A ci-dessus : source
                 distincte (plein-à-plein Télématique B, cf. section 5), affichée uniquement pour les véhicules
                 où elle est jugée plausible (VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE côté contrôleur)
                 — "—" partout ailleurs, jamais un chiffre douteux dans un document d'audit. --}}
            <td class="num small">
              @if($ligne->consommation_source_b)
                {{ number_format($ligne->consommation_source_b['consommation_l100km'], 1, ',', ' ') }} L/100km
                <div class="muted">· Télématique B</div>
              @else
                <span class="muted">—</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>
  </div>

  {{-- ============== SECTION 3 — Alertes de la période couverte ============== --}}
  <div class="section">
    <div class="section-title"><span class="section-num">3</span> Historique des alertes ({{ $periodeLabel }})</div>

    @if($alertes->isEmpty())
      <div class="empty-state">Aucune alerte détectée sur cette période.</div>
    @else
      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Détectée le</th>
            <th>Camion</th>
            <th>Site</th>
            <th>Type</th>
            <th>Message</th>
            <th>Statut</th>
            <th>Analyse corrective</th>
          </tr>
        </thead>
        <tbody>
          @foreach($alertes as $a)
            <tr>
              <td class="small">{{ \Carbon\Carbon::parse($a['date_detection'])->format('d/m/Y') }}</td>
              <td><b>{{ $a['camion'] }}</b></td>
              <td><span class="badge {{ strtolower($a['groupe']) }}">{{ $a['groupe'] }}</span></td>
              <td><span class="badge {{ $a['severite'] === 'critique' ? 'rouge' : 'orange' }}">{{ ucfirst($a['severite']) }}</span></td>
              <td class="small">{{ $a['message'] }}</td>
              <td class="small">{{ $a['resolue'] ? 'Résolue' : 'Active' }}</td>
              <td>
                @if(!empty($a['analyses']))
                  {{-- Rattachement par camion_id uniquement : toutes les analyses du véhicule, pas
                     seulement celle du même mois que l'alerte — plusieurs lignes possibles,
                     chacune datée. --}}
                  @foreach($a['analyses'] as $an)
                    <div class="analyse-box" style="margin-bottom:6px;">
                      <b>{{ \Carbon\Carbon::parse($an['mois'].'-01')->format('m/Y') }} — Cause :</b> {{ $an['cause'] ?? '—' }}
                      <b style="margin-top:4px;">Action :</b> {{ $an['action'] ?? '—' }}
                      @if(!empty($an['responsable']))
                        <div class="muted">Responsable : {{ $an['responsable'] }}</div>
                      @endif
                    </div>
                  @endforeach
                @else
                  <span class="no-analyse">Aucune analyse documentée</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @endif
  </div>

  {{-- ============== SECTION 4 — Suggestions de la période couverte ============== --}}
  <div class="section">
    <div class="section-title"><span class="section-num">4</span> Historique des suggestions ({{ $periodeLabel }})</div>

    @if($suggestions->isEmpty())
      <div class="empty-state">Aucune suggestion détectée sur cette période.</div>
    @else
      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Détectée le</th>
            <th>Camion</th>
            <th>Règle déclenchée</th>
            <th>Message</th>
            <th>Gravité</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @foreach($suggestions as $s)
            @php
              $graviteClasses = ['urgent' => 'rouge', 'attention' => 'orange', 'info' => 'gris'];
              $graviteClasse = $graviteClasses[$s['gravite']] ?? 'gris';
            @endphp
            <tr>
              <td class="small">{{ \Carbon\Carbon::parse($s['date_detection'])->format('d/m/Y') }}</td>
              <td><b>{{ $s['camion'] }}</b></td>
              <td class="small">{{ $s['regle_declenchee'] }}</td>
              <td class="small">{{ $s['message'] }}</td>
              <td><span class="badge {{ $graviteClasse }}">{{ ucfirst($s['gravite']) }}</span></td>
              <td class="small">{{ $s['resolue'] ? 'Résolue' : 'Active' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>

      <div class="info-box">
        Les suggestions sont historisées (et non recalculées à la volée) : leurs dates de détection ne
        sont connues que depuis la mise en place de cet historique, pas rétroactivement. Une suggestion
        « Active » depuis longtemps peut donc simplement signifier
        qu'elle est suivie depuis la mise en place de cet historique, pas qu'elle vient d'apparaître.
      </div>
    @endif
  </div>

  {{-- ============== SECTION 5 — Note méthodologique ============== --}}
  <div class="section methodo">
    <div class="section-title"><span class="section-num">5</span> Note méthodologique</div>

    <h4>Sources de données</h4>
    <p>
      Ce rapport combine trois sources de données indépendantes, qu'il convient de ne jamais confondre :
    </p>
    <ul>
      <li><b>Télématique A</b> (télématique automatique) — collecte en continu par les boîtiers embarqués sur les
        véhicules : consommation, kilométrage, heures moteur, éco-score. Ces données alimentent la section 1
        (indicateurs de performance) et la colonne « Dernière mesure Télématique A » de la section 2.</li>
      <li><b>Registre (norme interne)</b> (saisie manuelle) — relevé effectué par le chauffeur au pompiste, consolidé par le
        gestionnaire du registre puis par le responsable énergie selon la norme interne de référence. Ces données alimentent le
        statut norme interne (SER, cible, IPE) de la section 2.</li>
      <li><b>Télématique B</b> (plateforme télématique complémentaire) — distance et consommation calculée par intervalles
        entre pleins de carburant successifs, disponible pour un sous-ensemble de véhicules (colonne
        « Consommation Télématique B » de la section 2). Cette donnée est <b>affichée à titre indicatif</b> : il existe
        un écart important et systématique entre Télématique B et les deux autres sources (Télématique A, registre) —
        Télématique B n'est donc pas considérée comme une source de vérité pour le calcul norme interne, et n'entre dans aucune moyenne de la
        section 1 ni dans le statut norme interne de la section 2.</li>
    </ul>

    <h4>Délai de saisie du registre (norme interne) (M+1)</h4>
    <p>
      L'exploitant vérifie et saisit les données d'un mois M dans le registre/norme interne durant le mois M+1 — il s'agit
      d'un délai structurel normal du processus de collecte, non d'un dysfonctionnement. Le mois calendaire en
      cours au moment de la génération de ce rapport est donc presque toujours incomplet ou vide côté registre (norme interne).
      Pour cette raison, le statut norme interne de la section 2 se base systématiquement sur la <b>dernière mesure
      réellement exploitable</b> pour chaque véhicule, et non sur le mois calendaire courant.
    </p>

    <h4>Exclusions de données appliquées</h4>
    <ul>
      <li>Les mesures de consommation Télématique A supérieures à {{ number_format($consoMaxPlausible, 0, ',', ' ') }}
        L/100km sont exclues des moyennes et graphiques : il s'agit d'anomalies capteur/API confirmées, non de
        consommations réelles. Elles restent conservées telles quelles dans la base pour traçabilité, mais ne sont
        jamais incluses dans un calcul de moyenne.</li>
      <li>Les mesures de consommation nulles ou absentes (aucune remontée Télématique A ce mois-ci) sont exclues des
        moyennes plutôt que comptées comme une consommation de 0 L/100km.</li>
      <li>Les mois précédant la date d'activation réelle de l'abonnement Télématique A CHECK de chaque véhicule
        (<code>source_a_actif_depuis</code>) n'ont légitimement aucune donnée <code>mesures</code> : ce n'est pas
        une anomalie de collecte.</li>
    </ul>

    <h4>Statut norme interne et code couleur officiel</h4>
    <p>
      Le statut par véhicule (section 2) applique le code couleur officiel de la norme interne de référence, en comparant l'IPE
      (indice de performance énergétique) actuel du véhicule à sa SER (situation énergétique de référence), dans
      l'unité de cette SER : <b>L/100km</b> (litres ÷ km × 100) pour les usages Transport (Transport — catégorie 2,
      Transport — catégorie 1), <b>L/h</b> (litres ÷ heures) pour les autres usages. Les valeurs de SER sont
      reprises du registre telles quelles :
      <span class="badge rouge">Rouge</span> si IPE&nbsp;&gt;&nbsp;SER&times;1,x ·
      <span class="badge orange">Orange</span> si SER&nbsp;&lt;&nbsp;IPE&nbsp;&le;&nbsp;SER&times;1,x ·
      <span class="badge vert-clair">Vert clair</span> si Cible&nbsp;&lt;&nbsp;IPE&nbsp;&le;&nbsp;SER ·
      <span class="badge vert-fonce">Vert foncé</span> si IPE&nbsp;&le;&nbsp;Cible.
      Le statut <span class="badge gris">Données insuffisantes</span> signale soit l'absence de mesure registre (norme interne)
      exploitable ou de SER de référence renseignée pour ce véhicule, soit une mesure existante mais dans une unité
      incompatible avec sa SER (L/100km au lieu de L/h pour un usage en L/h sans heures saisies, rendant la comparaison invalide) — il ne doit jamais être
      interprété comme un résultat favorable.
    </p>

    <h4>Rattachement des analyses correctives</h4>
    <p>
      En section 3, une analyse corrective est associée à une alerte lorsque le véhicule concerné correspond à une
      entrée du registre des analyses correctives — le rattachement se fait uniquement par véhicule, indépendamment
      du mois ou de l'état (actif/résolu) de l'alerte. Toutes les analyses connues pour ce véhicule s'affichent donc
      sous chaque alerte, chacune datée individuellement, plutôt qu'une correspondance stricte par mois calendaire
      de détection (comportement précédent, qui manquait des analyses réelles dès que leur mois ne
      correspondait à aucune alerte détectée précisément ce mois-là).
    </p>
  </div>

  <div class="signature-block">
    <div>
      Établi par le responsable énergie<br>
      <div class="signature-line">Nom, date, signature</div>
    </div>
    <div>
      Validé par la direction<br>
      <div class="signature-line">Nom, date, signature</div>
    </div>
  </div>

  <div class="doc-footer">
    Fleet Energy Analytics · Rapport généré par l'application
  </div>

</div>

<script>
document.getElementById('btnPrint').addEventListener('click', () => window.print());

// Hauteur réelle de la barre d'actions (sticky) : les en-têtes de tableau se collent juste dessous.
(function(){
  const barre = document.querySelector('.toolbar');
  if (!barre) return;
  const maj = () => document.documentElement.style.setProperty('--toolbar-h', barre.offsetHeight + 'px');
  maj();
  if (window.ResizeObserver) new ResizeObserver(maj).observe(barre);
})();
</script>
</body>
</html>
