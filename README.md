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
- **SEO**: etiquetas `hreflang` con permalinks limpios y `x-default`, alternativas `xhtml:link` en los sitemaps XML (WordPress, Yoast SEO, Rank Math) y `og:locale` / `og:locale:alternate`.
- **Traducciones desactualizadas**: cuando cambia el texto del original, sus traducciones se marcan "À mettre à jour" en el editor, las listas, la tabla de traducciones y el tablero, con enlace a los cambios (revisiones) y acción "Marcar como al día" (individual o masiva).
- **Banner de sugerencia de idioma**: en vez de redirigir según el navegador, propone la traducción de la página en el idioma del visitante; compatible con caché de página y buscadores.
- **Selector de idioma**: bloque con 4 diseños y opciones de diseño, y colocación automática en un menú o flotante, como WPML (ver "Selector de idioma (1.4)").
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
4. Navegador            Accept-Language, solo en modo "redirect" (el modo "banner" solo sugiere)
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

## String Translation (1.2)

Traduce los textos de temas, plugins y plantillas sin modificar sus archivos (menú **PixelLang > Traduction de chaînes**).

- **Registro**: escaneo de un tema o plugin (PHP con `token_get_all` y JS con `wp.i18n`: `__`, `_e`, `esc_html__`, `esc_html_e`, `esc_attr__`, `esc_attr_e`, `_x`, `_n`…), modo descubrimiento (30 min, solo administradores: registra las cadenas realmente mostradas, con su archivo y línea) y registro manual.
- **Identidad**: dominio + contexto + texto original. El mismo texto en otro dominio o contexto es otra cadena.
- **Resolución**: filtros `gettext`, `gettext_with_context`, `ngettext` y `ngettext_with_context`, solo en el frontend y para el idioma activo de PixelLang. Una traducción escrita aquí tiene prioridad sobre el `.mo` del componente; sin traducción aquí no se toca nada. Los dominios sin traducciones salen de inmediato (unos 0,7 µs por llamada).
- **Texto escrito directamente en HTML**: tipo «Texte HTML». Se sustituye solo en el texto visible de la página, nunca en atributos, enlaces, scripts, estilos ni comentarios. Límites: el texto debe coincidir exactamente, y los textos creados por JavaScript no se pueden traducir así.
- **Datos**: tablas `{prefix}wpm_strings` y `{prefix}wpm_string_translations` (creadas por la migración 3, nunca se borran). Las traducciones se sanean con `wp_kses_post`.
- **Permisos**: `manage_options` (filtro `wpm_strings_capability`). REST: `POST`/`DELETE /wpm/v1/strings/{id}`.
- Filtro `wpm_strings_ignored_domains`: dominios que el descubrimiento no registra.

## SEO y calidad (1.3)

**Traducciones desactualizadas.** Cada contenido guarda un hash de su título, contenido y extracto (`_wpm_content_hash`). Una traducción guarda su original (`_wpm_source`) y el hash del original cuando se puso al día (`_wpm_source_hash`), más la revisión del original en ese momento (`_wpm_source_revision`) para el enlace "Voir les changements". Solo cambia el estado si cambia el texto: estados, fechas y campos personalizados no cuentan. La migración db 4 asigna un original a cada grupo existente (el contenido del idioma por defecto) y deja todas las traducciones al día. REST: `POST wpm/v1/mark-synced`. Filtro `wpm_sync_text` para ampliar el texto comparado.

**Sitemaps.** El renderer de los sitemaps de WordPress se sustituye por una subclase que añade `<xhtml:link rel="alternate" hreflang>` a cada URL traducida (mismas reglas que las etiquetas `hreflang`: solo traducciones publicadas, al menos dos idiomas, `x-default`). Con Yoast SEO o Rank Math se usan sus filtros `*sitemap_entry` / `*sitemap_url`. Se desactiva junto con la opción hreflang o con el filtro `wpm_sitemap_alternates`.

**Banner.** Ajustes > Visiteurs et SEO > Langue du navigateur: `banner` (recomendado, por defecto en instalaciones nuevas), `redirect` (comportamiento 1.2) u `off`. El servidor imprime las traducciones de la página con sus textos ya en cada idioma; `assets/js/banner.js` elige según `navigator.languages`. No vuelve a mostrarse cuando el visitante lo cierra, lo sigue o usa un selector de idioma (`localStorage`). Colores personalizables con las variables `--wpm-banner-*`. Filtro `wpm_banner_suggestions`.

## Selector de idioma (1.4)

**Bloque Language Switcher** con cuatro diseños, elegidos en una cuadrícula visual de los ajustes del bloque: *Minimal* (enlaces, idioma actual subrayado, separador opcional), *Segmented* (grupo de píldoras), *Dropdown* (botón con globo + panel claro u oscuro, patrón *disclosure*: Escape, flechas, Inicio/Fin, se abre hacia arriba si no hay sitio) y *Select* (lista nativa). Opciones: códigos, nombres o ambos, tamaño, icono de globo, ocultar el idioma actual o los idiomas sin traducción; colores, tipografía, borde y espacios desde la pestaña Estilos. Zonas táctiles de 44 px, foco visible, nombres accesibles que empiezan por el texto visible (WCAG 2.5.3). Tamaños en px/em (temas con `html { font-size: 62.5% }`).

**Colocación automática** (Réglages > Sélecteur de langue), como WPML: *Dans un menu* (los idiomas son los últimos elementos de los bloques Navigation o ubicaciones de menú clásicas elegidos, con el aspecto del menú, también en el menú móvil; un menú con versiones por idioma lo recibe en todas), *Flottant* (desplegable fijo abajo a la derecha o a la izquierda, claro u oscuro; se oculta mientras el banner de sugerencia está visible) o *Bloc seulement*.

Página sin traducción: el idioma enlaza a su portada; un idioma sin la página ni portada traducida no se muestra (nunca lleva a contenido en otro idioma; filtro `wpm_language_has_home`).

Compatibilidad: el atributo `style` (flags/text/dropdown) de la 1.0 chocaba con los estilos de bloque de WordPress; ya no se declara, pero el valor guardado se sigue leyendo (`$block->parsed_block`), así que los bloques existentes no cambian. Clases 1.0 conservadas. Filtro `wpm_switcher_items`.

## Licencia (1.5)

PixelLang Multilingual es solo para clientes de Agence Pixel. Cada sitio necesita una **clave firmada** por Pixel para su dominio (pantalla *Licence* o `wp pixellang license activate <clave>`).

- **Sin clave válida** (falta, no es válida, otro dominio o vencida): el sitio público sigue funcionando en todos sus idiomas; las pantallas del plugin se bloquean (salvo *Licence*), los formularios de ajustes no guardan y el admin muestra un aviso. El editor de contenido sigue funcionando.
- **Sitios de desarrollo** (`localhost`, `*.local`, `*.test`, `*.localhost`): no necesitan clave.
- **Aviso** 30 días antes del vencimiento.

**Emitir una clave** (solo Pixel). Hace falta la clave privada de firma en `~/.pixellang/license-signing.key` (nunca en el repo; copia en LastPass):

```bash
php bin/license-key.php --client="Centre le CAP" --domain=centrelecap.ca --expires=2027-12-31
php bin/license-key.php --check=PXL1.xxx.yyy
```

`client.com` cubre también `www.client.com`; `*.client.com` cubre sus subdominios; varios `--domain` posibles (por ejemplo el de staging de WP Engine). Sin `--expires`, la clave no vence.

Formato `PXL1.<datos base64url>.<firma Ed25519>`: el plugin solo contiene la clave pública (`WPM_License::PUBLIC_KEY`), así que puede verificar una clave sin servidor pero no crearla. Si se pierde la clave privada hay que generar un par nuevo, cambiar la clave pública del plugin y volver a emitir las claves. Es un freno, no un DRM: el plugin es código PHP GPL.

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
