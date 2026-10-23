// Sélecteur de période générique — partagé par dashboard.blade.php (Vue d'ensemble, Fiche camion,
// Comparateur, registre manuel vs Télématique A, Courbes complètes, Alertes, Suggestions) et
// direction.blade.php.

// Les <input type="date"> renvoient déjà AAAA-MM-JJ, le format attendu par les comparaisons de
// chaînes utilisées partout ici (backend ou client) — aucune conversion nécessaire.
function bornesPeriodeGenerique(prefix){
  const mode = document.getElementById('filtrePeriode' + prefix).value;
  if (mode === '999') return { depuis: null, jusquA: null };
  if (mode === 'custom'){
    const debut = document.getElementById('dateDebut' + prefix).value;
    const fin = document.getElementById('dateFin' + prefix).value;
    if (!debut || !fin) return { incomplet: true };
    return { depuis: debut, jusquA: fin };
  }
  const jusquA = new Date();
  const depuis = new Date();
  depuis.setMonth(depuis.getMonth() - parseInt(mode));
  return { depuis: depuis.toISOString().slice(0, 10), jusquA: jusquA.toISOString().slice(0, 10) };
}

// true si dateStr (AAAA-MM-JJ, ou tout timestamp ISO plus long — seuls les 10 premiers caractères
// comptent) tombe dans {depuis, jusquA} (l'un ou l'autre peut être null)
function dansPeriode(dateStr, bornes){
  if (!bornes.depuis && !bornes.jusquA) return true;
  if (!dateStr) return false;
  const d = dateStr.slice(0, 10);
  if (bornes.depuis && d < bornes.depuis) return false;
  if (bornes.jusquA && d > bornes.jusquA) return false;
  return true;
}

// Câblage commun du sélecteur de période : bascule l'affichage de la plage personnalisée au
// changement de mode, relance onChange() immédiatement pour tout mode sauf "custom" (qui attend le
// clic sur Appliquer une fois les deux dates saisies).
function initSelecteurPeriode(prefix, onChange){
  document.getElementById('filtrePeriode' + prefix).addEventListener('change', (e) => {
    document.getElementById('dateRange' + prefix).classList.toggle('hidden', e.target.value !== 'custom');
    if (e.target.value !== 'custom') onChange();
  });
  document.getElementById('btnAppliquerDates' + prefix).addEventListener('click', onChange);
}

// Formatage JJ/MM/AAAA d'une date ISO (AAAA-MM-JJ) — utilisé pour l'affichage humain des bornes
// d'une plage personnalisée.
function dateFr(iso){
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

// Met à jour le lien "Générer rapport d'audit" pour transmettre la période actuellement
// sélectionnée sur CETTE page comme pré- remplissage par défaut du petit formulaire de génération.
function majLienAudit(prefix, idLien){
  const lien = document.getElementById(idLien);
  if (!lien) return;
  const bornes = bornesPeriodeGenerique(prefix);
  if (bornes.incomplet || (!bornes.depuis && !bornes.jusquA)) {
    lien.href = '/audit-iso50001';
    return;
  }
  const params = new URLSearchParams();
  if (bornes.depuis) params.set('depuis', bornes.depuis);
  if (bornes.jusquA) params.set('jusqu_a', bornes.jusquA);
  lien.href = '/audit-iso50001?' + params.toString();
}
