# PixelLang Multilingual (Agence Pixel)

Multilingual plugin for WordPress with no dependencies (no WPML, no WooCommerce, no paid plugin). Works with block (FSE) and classic themes.

Versions: [Español](README.md) · [Français](README.fr.md)

## Features

- **Languages**: catalog of 48 languages plus custom ones (fr-ca, pt-br…), switcher order, name, WordPress locale, default language.
- **Linked translations** of pages, posts, custom post types and taxonomy terms.
- **One-click translation**: draft copy (content, custom fields, featured image, translated terms, translated parent), linked and opened in the editor.
- **Language from the URL**: a translated page always shows in its language; `<html lang>` and theme strings follow.
- **Menus**: Navigation blocks per language (block themes) and menu locations per language (classic themes).
- **Template parts** (header, footer…) with one version per language, managed in the Site Editor.
- **Forms**: Contact Form 7 and Gravity Forms, through form groups.
- **SEO**: `hreflang` tags with clean permalinks and `x-default`.
- **Language Switcher block**: codes, names or dropdown; options to hide the current language or languages without translation.
- **Admin**: dashboard (coverage, site health), translations table with a "missing" filter, language columns and filter in lists, "Set language" bulk action, Quick Edit, maintenance tools.
- **WPML compatibility**: answers `wpml_current_language`, `wpml_default_language`, `wpml_active_languages`, `wpml_object_id` and `wpml_element_language_code` when WPML is not active.
- Interface in English and French (fr_FR, fr_CA).

## Requirements

WordPress 6.4+ (tested on 7.1.4), PHP 7.4+ (linted on 7.4, 8.0, 8.3).

## Getting started

1. Activate the plugin: **PixelLang** menu in the admin.
2. **Languages**: add, order, pick the default language.
3. **Settings > Content**: enable the content types and taxonomies to translate.
4. **Tools > Assign the default language** for existing content.
5. In the editor, the **Language & translations** box sets the language and links or creates translations (saved immediately).
6. **Settings > Menus**: one menu per language. In the Site Editor, each template part has a **Languages** panel.
7. Insert the **Language Switcher** block in the header.

## How the language is chosen

```
1. ?set_lang=xx      explicit choice: cookie + redirect to the translation of the referring page
2. The content       a post or term with a language shows in that language
3. wpm_lang cookie   last language seen or chosen
4. Browser           Accept-Language, by preference (optional)
5. Default language
```

On a static front page, visitors of another language are sent to the front page of their language (optional, on by default). The admin always keeps the user's own language.

## Data

No custom table. Languages: option `wpm_languages`; types: `wpm_post_types`; 1.1 settings: `wpm_options`; menus: `wpm_menus`; template parts: `wpm_template_map`; language of a post or term: meta `_wpm_language`; translation group (`lang => id`, on every member): meta `_wpm_translations`.

**1.0 migration**: on the first 1.1 load, groups of the `wpm_page_map` option become `_wpm_translations` meta (the first group containing a post wins, like 1.0). Empty, missing, duplicate, mixed-type or contradictory entries are skipped and listed in **Tools**. The `wpm_page_map` option is kept unchanged.

## Theme API

```php
$lang = WPM_Language_Manager::instance()->get_current(); // 1.0 API, unchanged
$lang = wpm_current_language();
$id   = wpm_get_post_translation( get_the_ID(), 'en' );
$url  = wpm_get_language_url( 'en' );
$lang = apply_filters( 'wpml_current_language', null );  // existing WPML code
```

Filters: `wpm_available_languages`, `wpm_hreflang_alternates`, `wpm_translation_skip_meta`. Action: `wpm_translation_created`.

## Block

`wp-multilingual/language-switcher`: `style` (`flags` codes, `text` names, `dropdown`), `hideCurrent`, `hideMissing`, plus alignment, color, typography and spacing. Classes `.wpm-lang-list`, `.wpm-lang-item`, `.wpm-active`, `.wpm-lang-link`, `.wpm-lang-current` are kept. The PixelLang theme look lives in `themes/pixellang/assets/css/language-switcher.css`.

## Tests

```bash
wp eval-file wp-content/plugins/pixellang-multilingual/tests/integration.php --exec='define("WP_ADMIN", true);'
```

They run in one SQL transaction rolled back at the end: no data is left behind.

## Page cache

Translated pages have their own URL, so page caches serve the right language. Only pages without a language (static front page with redirect, archives, search) depend on the `wpm_lang` cookie.
