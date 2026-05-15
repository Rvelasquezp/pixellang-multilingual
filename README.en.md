# WP Multilingual — Agence Pixel

A WordPress multilingual plugin built from scratch to manage multi-language sites without relying on WPML or paid plugins.

---

## Features

- Automatic language detection via URL, cookie, and browser
- Automatic swap of navigation menus and Gravity Forms per language
- Automatic SEO `hreflang` tags in the `<head>`
- Admin panel with a visual selector for 48 languages with flags
- Meta box in the page editor to assign a language and link translations
- Panel in the Site Editor to assign a language to each navigation menu
- Columns in the page list: Language and Translations with an Edit button
- Gutenberg block `Language Switcher` (3 styles: list, flags, dropdown)

---

## Requirements

- WordPress 6.0+
- PHP 7.0+
- (Optional) Gravity Forms for per-language form swapping

---

## Installation

1. Copy the `pixellang-multilingual` folder to `/wp-content/plugins/`
2. Activate the plugin in **WP Admin → Plugins**
3. Go to **WP Admin → Multilingual** to configure languages

---

## Initial Setup

### 1. Add Languages

Go to **Multilingual → Languages**:
- In the right column, search for a language and click **+ Add**
- Mark which language is the **Default** (shown when no cookie or browser preference is detected)
- Save with **Save Settings**

### 2. Assign Navigation Menus

Edit each navigation menu in **Appearance → Editor → Navigation**:
- The **🌐 Menu Language** panel appears in the sidebar
- Select the corresponding language → saved automatically

### 3. Assign Language to Pages

When editing any page in the block editor:
- The **🌐 Language & Translations** panel appears in the sidebar
- Select the language for that page
- Link the equivalent pages in other languages using the dropdowns

### 4. Assign Forms (optional)

Go to **Multilingual → Gravity Forms**:
- Enter the Gravity Form ID for each language

---

## File Structure

```
pixellang-multilingual/
├── wp-multilingual.php                  # Main bootstrap
├── includes/
│   ├── languages-data.php               # List of 48 available languages
│   ├── class-language-manager.php       # Language and page map management in DB
│   ├── class-url-handler.php            # Language detection and redirects
│   ├── class-content-switcher.php       # Menu and Gravity Forms swap
│   ├── class-hreflang.php               # Automatic SEO hreflang tags
│   ├── class-admin.php                  # WP Admin settings page
│   ├── class-meta-box.php               # Meta box in page and menu editors
│   └── class-admin-columns.php          # Language and Translations columns
├── admin/
│   ├── views/settings-page.php          # HTML view of the settings page
│   ├── css/admin.css                    # Admin panel styles
│   ├── css/meta-box.css                 # Meta box styles
│   ├── js/admin.js                      # Admin panel JS
│   ├── js/meta-box.js                   # Page meta box JS
│   └── js/nav-panel.js                  # Gutenberg panel for menus (Site Editor)
├── blocks/
│   └── language-switcher/
│       ├── block.json                   # Gutenberg block definition
│       ├── render.php                   # PHP block renderer
│       └── style.css                    # Language switcher styles
└── languages/                           # Translation files (.po / .mo)
```

---

## How It Works

### Language Detection Flow

Every time a visitor loads a page:

```
1. Is ?set_lang=fr in the URL?     → switch language + save cookie + redirect
2. Does cookie wpm_lang exist?     → use that language
3. Does browser send Accept-Language? → use the best available match
4. None of the above               → use the Default language
```

### Where Data Is Stored

The plugin does not create new tables — it uses WordPress's standard tables:

| Data | Table | Key |
|---|---|---|
| Active languages | `wp_options` | `wpm_languages` |
| Menu IDs per language | `wp_options` | `wpm_menus` |
| Form IDs per language | `wp_options` | `wpm_forms` |
| Page translation groups | `wp_options` | `wpm_page_map` |
| Language of each page/post | `wp_postmeta` | `_wpm_language` |

---

## REST API

The plugin exposes a custom endpoint to save menu language from the Site Editor:

```
POST /wp-json/wpm/v1/nav-language
```

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `nav_id` | integer | wp_navigation post ID |
| `lang` | string | Language slug (fr, en, es…) |

**Requires:** authenticated user with `edit_posts` capability.

---

## Language Switcher Block

Insert the **Language Switcher** block from the block editor anywhere in your theme.

**Attributes:**

| Attribute | Type | Default | Options |
|---|---|---|---|
| `style` | string | `flags` | `flags`, `text`, `dropdown` |
| `showLabel` | boolean | `true` | — |
| `align` | string | — | `left`, `center`, `right` |

---

## Development

### Adding a New Language to the List

Edit `includes/languages-data.php` and add an entry to the array:

```php
'xx' => array(
    'label'   => 'Native name',
    'locale'  => 'xx_XX',
    'flag'    => '🏳️',
    'english' => 'Name in English',
),
```

### Available Hooks

```php
// Modify the detected language before it is applied
add_filter( 'wpm_detected_language', function( $lang ) {
    return $lang;
});
```

---

## Author

**Ricardo Velasquez — Agence Pixel**

- 🌐 Web: [ingcloud.ca](https://ingcloud.ca)
- 🐙 GitHub: [github.com/Rvelasquezp/pixellang-multilingual](https://github.com/Rvelasquezp/pixellang-multilingual)

Built with Claude Code.
