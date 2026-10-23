# Fleet Energy Analytics

> **Application de démonstration — toutes les données sont fictives.**
> Les 12 camions (DEMO-001 à DEMO-012), les deux sites (SITE A, SITE B), les normes de
> référence, les mesures, les conducteurs et les coordonnées GPS sont générés par un seeder.
> Aucune source externe ni clé d'API n'est nécessaire pour l'utiliser.

Plateforme web de suivi de la performance énergétique d'une flotte de poids lourds, pensée pour
un système de management de l'énergie de type **ISO 50001**. Elle croise plusieurs sources de
mesures indépendantes, compare chaque véhicule à une **norme interne de référence** (SER : seuil
énergétique de référence, cible, incertitude) et rend visibles les limites de fiabilité des
données au lieu de les masquer.

## Fonctionnalités

- **Accueil** : indicateurs de la flotte (éco-score, consommation, distance, litres, coût
  estimé, mesures exploitables), tableau maître triable et filtrable, détail d'un camion
  (évolution, état actuel) ou comparaison de plusieurs camions cochés.
- **Norme interne de référence** : SER, seuil d'incertitude et cible par véhicule, statut
  couleur (vert foncé / vert clair / orange / rouge / donnée manquante) calculé sur le dernier
  mois réellement exploitable, IPÉ et consommation sur une période au choix.
- **Alertes & suggestions** : flux unique trié par urgence (consommation élevée, statut rouge
  deux mois de suite, données manquantes, divergence entre sources…), analyses correctives,
  carte « qualité des données » qui explique pourquoi une mesure est exclue.
- **Comparaison des sources** : registre manuel vs source télématique A vs source télématique B,
  écart en % et niveau de fiabilité mois par mois.
- **Courbes complètes** : toutes les métriques disponibles, en mensuel ou annuel, une ou
  plusieurs sources, un ou plusieurs camions.
- **Suivi GPS** : positions et trajets (Leaflet), fraîcheur des positions signalée.
- **Vue Direction** et **rapport d'audit ISO 50001** imprimable sur une période choisie.
- **Comptes et rôles** : Admin (gestion des utilisateurs) et Consultant (lecture seule).
- **Santé des automatisations** : badge signalant une tâche planifiée en retard.

### Garde-fous de fiabilité (visibles dans la démonstration)

- Une valeur absente s'affiche « — », jamais 0, et n'entre dans aucune moyenne.
- Les valeurs aberrantes (consommation > 100 L/100km, vitesse impossible, jour de kilométrage
  corrompu) sont exclues des calculs et signalées par un badge, sans être effacées.
- Une consommation calculée hors de la plage plausible porte un badge « Implausible ».
- Le mois en cours est marqué « provisoire » et n'est jamais mélangé aux mois clos.
- Un statut favorable n'est jamais affiché sur une donnée manquante.

Le seeder place volontairement quelques-uns de ces cas (mois sans donnée, valeur hors bornes,
véhicule absent du registre, kilométrage corrompu, positions anciennes…) pour les montrer.

## Architecture

| Couche | Choix |
|---|---|
| Back-end | Laravel 13, PHP 8.3 |
| Base de données | MySQL 8 / MariaDB 10.6+ |
| Front-end | Vues Blade, JavaScript sans framework, Chart.js, Leaflet |
| Temps réel (facultatif) | Laravel Reverb (WebSocket) |
| Tâches planifiées | Scheduler Laravel (`routes/console.php`) |

- `app/Http/Controllers/Api/` : API JSON interne consommée par le tableau de bord
  (`/api/flotte/resume`, `/api/ser-standards`, `/api/alertes`, `/api/courbes/...`, etc.).
  Toute entrée est validée par liste blanche ; les erreurs renvoient un JSON explicite.
- `app/Services/` : logique métier — statut par rapport à la norme de référence
  (`NormeRefStatutService`), détection d'alertes et de suggestions, détection du kilométrage
  corrompu, santé des automatisations, météo.
- `app/Console/Commands/` : tâches planifiées (détection des alertes et suggestions,
  synchronisation du registre tableur, archivage météo, sauvegarde, santé des automatisations).
  Les connecteurs vers les sources télématiques et l'API constructeur ne font pas partie de cette
  version de démonstration : leurs données sont générées par le seeder. La synchronisation du
  registre et la météo restent optionnelles et sans appel réseau tant qu'elles ne sont pas
  configurées.
- `resources/views/` : `dashboard` (6 onglets), `direction`, `audit`, `connexion`, `utilisateurs`.
- `database/seeders/DemoSeeder.php` : génération déterministe des données fictives.

## Installation

Prérequis : PHP 8.3 (extensions habituelles de Laravel), Composer, MySQL 8 ou MariaDB.

> Seuls MySQL et MariaDB sont pris en charge (certaines migrations utilisent du SQL spécifique) : SQLite n'est pas supporté. Sans fichier `.env`, Laravel bascule par défaut sur SQLite et la migration échoue : copier `.env.example` et renseigner `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

```bash
composer install
cp .env.example .env            # puis renseigner DB_* (base vide, ex. fleet_energy)
php artisan key:generate
php artisan migrate --seed      # schéma + données de démonstration fictives
php artisan serve
```

Ouvrir http://localhost:8000 puis se connecter avec :

| Identifiant | Mot de passe | Rôle |
|---|---|---|
| `demo` | `demo` | Admin |
| `consultant` | `consultant` | Consultant (lecture seule) |

Pour repartir de zéro : `php artisan migrate:fresh --seed`.

La position temps réel de la source A et la météo s'affichent comme indisponibles (message
explicite) : aucune source temps réel n'est connectée. Tout le reste fonctionne sur les données
fictives (trajets, positions de la source B, mesures, registre).

## Tests

Les tests utilisent une base MySQL/MariaDB dédiée, `fleet_energy_testing` (configuration dans
`phpunit.xml`), réinitialisée à chaque test. Aucun appel réseau : les API externes sont simulées
(`Http::fake`, Mockery).

```bash
php artisan test
```

Si votre serveur n'écoute pas sur le port 3306 : `DB_PORT=3307 php artisan test`.

## Licence

Tous droits réservés. Code publié à titre de démonstration et de consultation.
