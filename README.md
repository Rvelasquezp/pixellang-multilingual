# PixelLang Multilingual (Agence Pixel)

Plugin multilingüe para WordPress, sin dependencias (ni WPML, ni WooCommerce, ni plugins de pago). Funciona con temas de bloques (FSE) y temas clásicos.

Versiones: [Français](README.fr.md) · [English](README.en.md)

## Funcionalidades

- **Idiomas**: catálogo de 48 idiomas + idiomas personalizados (fr-ca, pt-br…), orden del selector, nombre, locale WordPress e idioma por defecto.
- **Traducciones vinculadas** de páginas, entradas, tipos de contenido personalizados (CPT) y términos de taxonomías.
- **Crear una traducción en un clic**: copia en borrador (contenido, campos personalizados, imagen destacada, términos traducidos, página madre traducida), vinculada y abierta en el editor.
- **Idioma por URL**: una página traducida siempre se muestra en su idioma; el `<html lang>`, los textos del tema y del plugin siguen ese idioma.
- **Menús**: bloques Navigation por idioma (temas de bloques) y ubicaciones de menú por idioma (temas clásicos).
- **Template parts** (cabecera, pie…) con una versión por idioma, gestionadas desde el Site Editor.
- **Formularios**: Contact Form 7 y Gravity Forms, por grupos de formularios.
- **SEO**: etiquetas `hreflang` con permalinks limpios y `x-default`.
- **Bloque Language Switcher**: códigos, nombres o lista desplegable; opciones para ocultar el idioma actual o los idiomas sin traducción.
- **Administración**: tablero (cobertura por idioma, estado del sitio), tabla de traducciones con filtro "faltantes", columnas y filtro por idioma en las listas, acción masiva "Definir el idioma", edición rápida, herramientas de mantenimiento.
- **Compatibilidad WPML**: responde a `wpml_current_language`, `wpml_default_language`, `wpml_active_languages`, `wpml_object_id` y `wpml_element_language_code` cuando WPML no está activo.
- Interfaz en inglés, francés (fr_FR y fr_CA).

## Requisitos

- WordPress 6.4 o superior (probado en 7.1.4)
- PHP 7.4 o superior (lint en 7.4, 8.0 y 8.3)

## Puesta en marcha

1. Activar el plugin. Menú **PixelLang** en el admin.
2. **Idiomas**: añadir los idiomas, ordenarlos, elegir el idioma por defecto.
3. **Ajustes > Contenido**: activar los tipos de contenido y las taxonomías a traducir.
4. **Herramientas > Atribuir el idioma por defecto**: da un idioma al contenido existente.
5. En el editor, la caja **Idioma y traducciones** asigna el idioma y vincula o crea las traducciones (se guarda al instante).
6. **Ajustes > Menús**: un menú por idioma. En el Site Editor, cada template part tiene un panel **Idiomas**.
7. Insertar el bloque **Language Switcher** en la cabecera.

## Cómo se decide el idioma

```
1. ?set_lang=xx         elección explícita: cookie + redirección a la traducción de la página de origen
2. El contenido         una entrada o un término con idioma se muestra en ese idioma (y actualiza la cookie)
3. Cookie wpm_lang      último idioma visto o elegido (duración configurable)
4. Navegador            Accept-Language, por orden de preferencia (opcional)
5. Idioma por defecto
```

En la portada estática, un visitante cuyo idioma es otro se redirige a la portada de su idioma (opcional, activado por defecto). El admin conserva siempre el idioma del usuario.

## Datos

El plugin no crea tablas.

| Dato | Ubicación |
|---|---|
| Idiomas activos | option `wpm_languages` |
| Tipos de contenido traducibles | option `wpm_post_types` |
| Ajustes 1.1 (detección, hreflang, taxonomías, menús clásicos, formularios) | option `wpm_options` |
| Menús Navigation por idioma | option `wpm_menus` |
| Template parts por idioma | option `wpm_template_map` |
| Idioma de una entrada o de un término | meta `_wpm_language` |
| Grupo de traducciones (`lang => id`, en cada miembro) | meta `_wpm_translations` |
| Versión de datos y reporte de migración | options `wpm_db_version`, `wpm_migration_report` |

### Migración desde 1.0

Al primer arranque de la 1.1, los grupos del option `wpm_page_map` se convierten en meta `_wpm_translations`. El orden se respeta (el primer grupo que contiene una entrada gana, como en 1.0). Se ignoran y se documentan en **Herramientas**: espacios vacíos, contenido inexistente, grupos de un solo elemento, duplicados, tipos de contenido mezclados e idiomas contradictorios. El option `wpm_page_map` se conserva sin cambios como copia de seguridad.

## API para temas

```php
$lang = WPM_Language_Manager::instance()->get_current(); // 'fr', 'en'… (API 1.0, sin cambios)
$lang = wpm_current_language();
$id   = wpm_get_post_translation( get_the_ID(), 'en' );  // 0 si no hay
$url  = wpm_get_language_url( 'en' );                    // URL de la página actual en inglés

// Código escrito para WPML funciona tal cual:
$lang = apply_filters( 'wpml_current_language', null );
```

Filtrar una consulta por idioma (el contenido sin idioma también puede incluirse con `WPM_Query_Filter::language_clause( $lang )`):

```php
new WP_Query( array(
	'post_type'  => 'equipe',
	'meta_query' => array( array( 'key' => '_wpm_language', 'value' => wpm_current_language() ) ),
) );
```

Filtros: `wpm_available_languages`, `wpm_hreflang_alternates`, `wpm_translation_skip_meta`. Acción: `wpm_translation_created`.

## Bloque Language Switcher

`wp-multilingual/language-switcher` (nombre sin cambios para no romper el contenido existente).

| Atributo | Valores | Por defecto |
|---|---|---|
| `style` | `flags` (códigos), `text` (nombres), `dropdown` | `flags` |
| `hideCurrent` | booleano | `false` |
| `hideMissing` | booleano | `false` |

Soporta alineación, color, tipografía y espaciado. Las clases `.wpm-lang-list`, `.wpm-lang-item`, `.wpm-active`, `.wpm-lang-link` y `.wpm-lang-current` se mantienen para los temas. El aspecto propio del tema PixelLang está en `themes/pixellang/assets/css/language-switcher.css`.

## REST (`wpm/v1`)

| Ruta | Uso | Permiso |
|---|---|---|
| `POST /link` | vincular o desvincular una traducción | `edit_post` sobre ambos contenidos |
| `POST /post-language` | cambiar el idioma de un contenido | `edit_post` |
| `GET /search` | candidatos para vincular | `edit_posts` del tipo |
| `POST /nav-language` | idioma de un menú Navigation | `edit_theme_options` |
| `POST /template-language`, `/template-duplicate`, `/template-group-remove`, `/template-repair` | template parts | `edit_theme_options` |

## Estructura

```
wp-multilingual.php              arranque, activación, carga de traducciones
includes/
  class-settings.php             opciones, valores por defecto, saneamiento
  class-language-manager.php     idiomas activos, idioma actual, locale
  class-translations.php         idiomas y grupos de entradas y términos, creación de traducciones, estadísticas
  class-migration.php            migración de datos 1.0 -> 1.1
  class-url-handler.php          resolución del idioma, URLs de idioma
  class-content-switcher.php     menús, formularios, clase body
  class-template-switcher.php    template parts por idioma
  class-query-filter.php         filtrado opcional de listados
  class-hreflang.php             etiquetas hreflang
  class-compat.php               filtros WPML, funciones públicas
  class-rest.php                 rutas REST del editor
  class-admin.php                pantallas, controladores, componentes de interfaz
  class-translations-table.php   tabla de la pantalla Traducciones
  class-meta-box.php             caja del editor y panel de menús
  class-admin-columns.php        columnas, filtro, acción masiva, edición rápida
  class-term-language.php        idioma y traducciones de los términos
  class-icons.php                iconos SVG
admin/views/                     vistas de las pantallas
admin/css, admin/js              estilos y scripts (sin jQuery salvo la edición rápida de WP)
blocks/language-switcher/        bloque
languages/                       .pot, fr_FR, fr_CA (PHP y JS)
tests/integration.php            pruebas de integración
```

## Pruebas

```bash
wp eval-file wp-content/plugins/pixellang-multilingual/tests/integration.php --exec='define("WP_ADMIN", true);'
```

Las pruebas se ejecutan dentro de una transacción SQL anulada al final (ROLLBACK): no dejan datos.

Regenerar las traducciones tras cambiar textos:

```bash
wp i18n make-pot wp-content/plugins/pixellang-multilingual wp-content/plugins/pixellang-multilingual/languages/pixellang-multilingual.pot --exclude=tests
```

## Caché de página

Las páginas traducidas tienen una URL propia, por lo que la caché de página (WP Engine…) sirve el idioma correcto. Solo dependen de la cookie las páginas sin idioma (portada estática con redirección, archivos, búsqueda): si la caché no varía según la cookie `wpm_lang`, esas páginas se sirven en el idioma de la versión en caché.
