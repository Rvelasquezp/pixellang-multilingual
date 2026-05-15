# WP Multilingual — Agence Pixel

Une extension WordPress multilingue construite from scratch pour gérer des sites en plusieurs langues sans dépendre de WPML ou de plugins payants.

---

## Fonctionnalités

- Détection automatique de la langue via l'URL, le cookie et le navigateur
- Remplacement automatique des menus de navigation et des formulaires Gravity Forms par langue
- Balises SEO `hreflang` automatiques dans le `<head>`
- Panneau d'administration avec sélecteur visuel de 48 langues avec drapeaux
- Meta box dans l'éditeur de pages pour assigner une langue et lier les traductions
- Panneau dans le Site Editor pour assigner une langue à chaque menu de navigation
- Colonnes dans la liste des pages : Language et Translations avec bouton Edit
- Bloc Gutenberg `Language Switcher` (3 styles : liste, drapeaux, liste déroulante)

---

## Prérequis

- WordPress 6.0+
- PHP 7.0+
- (Optionnel) Gravity Forms pour le remplacement de formulaires par langue

---

## Installation

1. Copier le dossier `pixellang-multilingual` dans `/wp-content/plugins/`
2. Activer l'extension dans **WP Admin → Extensions**
3. Aller dans **WP Admin → Multilingual** pour configurer les langues

---

## Configuration initiale

### 1. Ajouter des langues

Aller dans **Multilingual → Languages** :
- Dans la colonne de droite, rechercher une langue et cliquer sur **+ Add**
- Marquer quelle langue est la langue **Default** (affichée quand aucun cookie ni préférence de navigateur n'est détecté)
- Sauvegarder avec **Save Settings**

### 2. Assigner les menus de navigation

Éditer chaque menu de navigation dans **Apparence → Éditeur → Navigation** :
- Le panneau **🌐 Menu Language** apparaît dans la barre latérale
- Sélectionner la langue correspondante → sauvegardé automatiquement

### 3. Assigner une langue aux pages

Lors de l'édition d'une page dans l'éditeur de blocs :
- Le panneau **🌐 Language & Translations** apparaît dans la barre latérale
- Sélectionner la langue de cette page
- Lier les pages équivalentes dans les autres langues via les listes déroulantes

### 4. Assigner des formulaires (optionnel)

Aller dans **Multilingual → Gravity Forms** :
- Saisir l'ID du formulaire Gravity Forms pour chaque langue

---

## Structure des fichiers

```
pixellang-multilingual/
├── wp-multilingual.php                  # Bootstrap principal
├── includes/
│   ├── languages-data.php               # Liste des 48 langues disponibles
│   ├── class-language-manager.php       # Gestion des langues et page map en DB
│   ├── class-url-handler.php            # Détection de langue et redirections
│   ├── class-content-switcher.php       # Remplacement des menus et formulaires
│   ├── class-hreflang.php               # Balises SEO hreflang automatiques
│   ├── class-admin.php                  # Page de réglages dans WP Admin
│   ├── class-meta-box.php               # Meta box dans l'éditeur de pages et menus
│   └── class-admin-columns.php          # Colonnes Language et Translations
├── admin/
│   ├── views/settings-page.php          # Vue HTML de la page de réglages
│   ├── css/admin.css                    # Styles du panneau d'administration
│   ├── css/meta-box.css                 # Styles de la meta box
│   ├── js/admin.js                      # JS du panneau d'administration
│   ├── js/meta-box.js                   # JS de la meta box des pages
│   └── js/nav-panel.js                  # Panneau Gutenberg pour les menus (Site Editor)
├── blocks/
│   └── language-switcher/
│       ├── block.json                   # Définition du bloc Gutenberg
│       ├── render.php                   # Rendu PHP du bloc
│       └── style.css                    # Styles du language switcher
└── languages/                           # Fichiers de traduction (.po / .mo)
```

---

## Comment ça fonctionne

### Flux de détection de langue

À chaque chargement de page par un visiteur :

```
1. ?set_lang=fr dans l'URL ?          → change la langue + cookie + redirection
2. Cookie wpm_lang existe ?           → utilise cette langue
3. Navigateur envoie Accept-Language ? → utilise le meilleur correspondant disponible
4. Aucun des cas ci-dessus            → utilise la langue Default
```

### Où sont stockées les données

L'extension ne crée pas de nouvelles tables — elle utilise les tables standard de WordPress :

| Donnée | Table | Clé |
|---|---|---|
| Langues actives | `wp_options` | `wpm_languages` |
| IDs des menus par langue | `wp_options` | `wpm_menus` |
| IDs des formulaires par langue | `wp_options` | `wpm_forms` |
| Groupes de traductions de pages | `wp_options` | `wpm_page_map` |
| Langue de chaque page/article | `wp_postmeta` | `_wpm_language` |

---

## REST API

L'extension expose un endpoint personnalisé pour sauvegarder la langue des menus depuis le Site Editor :

```
POST /wp-json/wpm/v1/nav-language
```

**Paramètres :**

| Paramètre | Type | Description |
|---|---|---|
| `nav_id` | integer | ID du post wp_navigation |
| `lang` | string | Slug de la langue (fr, en, es…) |

**Requiert :** un utilisateur authentifié avec la capacité `edit_posts`.

---

## Bloc Language Switcher

Insérer le bloc **Language Switcher** depuis l'éditeur de blocs n'importe où dans votre thème.

**Attributs :**

| Attribut | Type | Défaut | Options |
|---|---|---|---|
| `style` | string | `flags` | `flags`, `text`, `dropdown` |
| `showLabel` | boolean | `true` | — |
| `align` | string | — | `left`, `center`, `right` |

---

## Développement

### Ajouter une nouvelle langue à la liste

Éditer `includes/languages-data.php` et ajouter une entrée au tableau :

```php
'xx' => array(
    'label'   => 'Nom en langue native',
    'locale'  => 'xx_XX',
    'flag'    => '🏳️',
    'english' => 'Name in English',
),
```

### Hooks disponibles

```php
// Modifier la langue détectée avant de l'appliquer
add_filter( 'wpm_detected_language', function( $lang ) {
    return $lang;
});
```

---

## Auteur

**Ricardo Velasquez — Agence Pixel**

- 🌐 Web : [ingcloud.ca](https://ingcloud.ca)
- 🐙 GitHub : [github.com/Rvelasquezp/pixellang-multilingual](https://github.com/Rvelasquezp/pixellang-multilingual)

Construit avec Claude Code.
