# Intermediate Auto — code du site

Code source maison du site **intermediate-auto.com** (intermédiaire automobile, Boufarik / Blida).

Ce dépôt versionne **uniquement** :

- `wp-content/themes/intermediate-auto/` — le thème (design or/noir, catalogue, fiche véhicule, simulateur de douane)
- `wp-content/plugins/intermediate-auto-core/` — le plugin maison (table `wp_ia_vehicles`, dashboard d'administration, formulaire véhicules, export Excel)

> Le cœur de WordPress, les médias (`uploads`), `wp-config.php` et la base de données ne sont **pas** versionnés.

## Déploiement (cPanel Git Version Control)

1. En local : `git add . && git commit -m "..."` puis `git push`.
2. Sur cPanel → **Git Version Control** → **Update from Remote** puis **Deploy**.
3. Le fichier `.cpanel.yml` copie automatiquement le thème et le plugin dans `public_html`.
4. Après un changement de réécriture d'URL : **Réglages → Permaliens → Enregistrer**.

## Développement local

WordPress local : `C:\xampp\htdocs\nouveau` (XAMPP, base `intermediate_auto`).
