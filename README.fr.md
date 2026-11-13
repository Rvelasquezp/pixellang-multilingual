# PixelLang Multilingual (Agence Pixel)

Extension multilingue pour WordPress, sans dépendance (ni WPML, ni WooCommerce, ni extension payante). Fonctionne avec les thèmes de blocs (FSE) et les thèmes classiques.

Versions : [Español](README.md) · [English](README.en.md)

## Fonctionnalités

- **Langues** : catalogue de 48 langues et langues personnalisées (fr-ca, pt-br…), ordre du sélecteur, nom, locale WordPress, langue par défaut.
- **Traductions liées** des pages, articles, types de contenu personnalisés et termes de taxonomies.
- **Créer une traduction en un clic** : copie en brouillon (contenu, champs personnalisés, image mise en avant, termes traduits, page parente traduite), liée et ouverte dans l’éditeur.
- **Langue selon l’URL** : une page traduite s’affiche toujours dans sa langue ; `<html lang>` et les textes du thème suivent.
- **Menus** : blocs Navigation par langue (thèmes de blocs) et emplacements de menu par langue (thèmes classiques).
- **Parties de modèle** (en-tête, pied de page…) avec une version par langue, gérées dans l’éditeur de site.
- **Formulaires** : Contact Form 7 et Gravity Forms, par groupes de formulaires.
- **SEO** : balises `hreflang` avec permaliens propres et `x-default`.
- **Bloc Sélecteur de langue** : codes, noms ou liste déroulante ; options pour masquer la langue courante ou les langues sans traduction.
- **Administration** : tableau de bord (couverture, état du site), table des traductions avec filtre « manquantes », colonnes et filtre par langue dans les listes, action groupée « Définir la langue », modification rapide, outils d’entretien.
- **Compatibilité WPML** : répond à `wpml_current_language`, `wpml_default_language`, `wpml_active_languages`, `wpml_object_id` et `wpml_element_language_code` quand WPML n’est pas actif.
- Interface en anglais et en français (fr_FR, fr_CA).

## Prérequis

WordPress 6.4 ou plus (testé sur 7.1.4), PHP 7.4 ou plus (lint 7.4, 8.0, 8.3).

## Mise en route

1. Activer l’extension : menu **PixelLang** dans l’administration.
2. **Langues** : ajouter, ordonner, choisir la langue par défaut.
3. **Réglages > Contenu** : activer les types de contenu et les taxonomies à traduire.
4. **Outils > Attribuer la langue par défaut** pour le contenu existant.
5. Dans l’éditeur, la boîte **Langue et traductions** attribue la langue et lie ou crée les traductions (enregistrement immédiat).
6. **Réglages > Menus** : un menu par langue. Dans l’éditeur de site, chaque partie de modèle a un panneau **Langues**.
7. Insérer le bloc **Sélecteur de langue** dans l’en-tête.

## Choix de la langue

```
1. ?set_lang=xx      choix explicite : témoin + redirection vers la traduction de la page d’origine
2. Le contenu        un article ou un terme avec une langue s’affiche dans cette langue
3. Témoin wpm_lang   dernière langue vue ou choisie
4. Navigateur        Accept-Language, par ordre de préférence (optionnel)
5. Langue par défaut
```

Sur la page d’accueil statique, un visiteur d’une autre langue est dirigé vers l’accueil de sa langue (optionnel, actif par défaut). L’administration garde toujours la langue de l’utilisateur.

## Données

Aucune table créée. Langues : option `wpm_languages` ; types : `wpm_post_types` ; réglages 1.1 : `wpm_options` ; menus : `wpm_menus` ; parties de modèle : `wpm_template_map` ; langue d’un contenu ou terme : méta `_wpm_language` ; groupe de traductions (`langue => id`, sur chaque membre) : méta `_wpm_translations`.

**Migration 1.0** : au premier démarrage de la 1.1, les groupes de l’option `wpm_page_map` deviennent des métas `_wpm_translations` (le premier groupe qui contient un contenu l’emporte, comme en 1.0). Les entrées vides, inexistantes, en double, de types mélangés ou de langue contradictoire sont ignorées et listées dans **Outils**. L’option `wpm_page_map` est conservée telle quelle.

## Traduction de chaînes (1.2)

Traduit les textes des thèmes, extensions et modèles sans modifier leurs fichiers (menu **PixelLang > Traduction de chaînes**).

- **Enregistrement** : analyse d’un thème ou d’une extension (PHP via `token_get_all`, JS via `wp.i18n`), mode découverte (30 min, administrateurs seulement : enregistre les chaînes réellement affichées, avec fichier et ligne) et enregistrement manuel.
- **Identité** : domaine + contexte + texte original.
- **Résolution** : filtres `gettext`, `gettext_with_context`, `ngettext`, `ngettext_with_context`, sur le site public, pour la langue active de PixelLang. Une traduction saisie ici l’emporte sur le `.mo` du composant ; sans traduction ici, rien ne change.
- **Texte écrit directement dans le HTML** : type « Texte HTML », remplacé seulement dans le texte visible (jamais dans les attributs, liens, scripts, styles, commentaires). Limites : correspondance exacte, et les textes créés par JavaScript ne sont pas traduisibles ainsi.
- **Données** : tables `{prefix}wpm_strings` et `{prefix}wpm_string_translations`. Permission `manage_options` (filtre `wpm_strings_capability`).

## SEO et qualité (1.3)

- **Traductions à mettre à jour** : quand le texte d'un original change (titre, contenu, extrait), ses traductions sont signalées « À mettre à jour » dans l'éditeur, les listes, l'écran Traductions et le tableau de bord, avec un lien vers les changements (révisions) et l'action « Marquer comme à jour » (une à une ou en lot).
- **Plans de site multilingues** : chaque URL traduite des plans de site WordPress, Yoast SEO et Rank Math liste ses traductions (`xhtml:link hreflang`, `x-default`). `og:locale` suit la langue de la page avec Yoast SEO et Rank Math.
- **Bannière de suggestion de langue** : au lieu de rediriger selon le navigateur, la page reste affichée et une bannière, écrite dans la langue du visiteur, propose sa traduction. Compatible avec les caches de page et les moteurs de recherche. Réglage : Visiteurs et SEO > Langue du navigateur.

## Sélecteur de langue (1.4)

- **Bloc Sélecteur de langue** en quatre designs (minimal, segmenté, menu déroulant, liste native), choisis dans une grille visuelle des réglages du bloc, avec codes, noms ou les deux, taille, icône de globe, couleurs, typographie, bordure et espacements. Accessible au clavier (Échap, flèches), zones tactiles de 44 px.
- **Placement automatique** (Réglages > Sélecteur de langue), comme WPML : dans un menu (prend l'apparence du menu, aussi sur mobile), flottant (coin inférieur, clair ou foncé) ou bloc seulement.
- Les blocs existants gardent leur apparence.

## Identité du site, médias et URL (1.6)

- Titre et slogan du site par langue (Réglages > Identité du site).
- Texte alternatif et légende des images par langue, dans la médiathèque.
- Slugs d'URL traduits pour les types de contenu et taxonomies (`/projets/` → `/projects/`), avec redirection 301 depuis l'autre base.

## Licence (1.5)

- Réservé aux clients d'Agence Pixel : chaque site a besoin d'une clé signée pour son domaine (écran Licence ou `wp pixellang license activate <clé>`).
- Sans clé valide, le site public continue de fonctionner ; les écrans de l'extension sont verrouillés et l'administration affiche un avis.
- Les sites de développement (.local, .test, localhost) n'ont pas besoin de clé.

## API pour les thèmes

```php
$lang = WPM_Language_Manager::instance()->get_current(); // API 1.0 inchangée
$lang = wpm_current_language();
$id   = wpm_get_post_translation( get_the_ID(), 'en' );
$url  = wpm_get_language_url( 'en' );
$lang = apply_filters( 'wpml_current_language', null );  // code WPML existant
```

Filtres : `wpm_available_languages`, `wpm_hreflang_alternates`, `wpm_translation_skip_meta`. Action : `wpm_translation_created`.

## Bloc

`wp-multilingual/language-switcher` : `style` (`flags` codes, `text` noms, `dropdown`), `hideCurrent`, `hideMissing`, plus alignement, couleur, typographie et espacement. Les classes `.wpm-lang-list`, `.wpm-lang-item`, `.wpm-active`, `.wpm-lang-link`, `.wpm-lang-current` sont conservées. Le style propre au thème PixelLang est dans `themes/pixellang/assets/css/language-switcher.css`.

## Tests

```bash
wp eval-file wp-content/plugins/pixellang-multilingual/tests/integration.php --exec='define("WP_ADMIN", true);'
```

Exécutés dans une transaction SQL annulée à la fin : aucune donnée ne reste.

## Cache de page

Les pages traduites ont leur propre URL : le cache de page sert la bonne langue. Seules les pages sans langue (accueil statique avec redirection, archives, recherche) dépendent du témoin `wpm_lang`.
