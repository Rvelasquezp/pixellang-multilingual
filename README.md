# WP Multilingual — Agence Pixel

Plugin WordPress multilingüe construido desde cero para gestionar sitios en múltiples idiomas sin depender de WPML ni plugins de pago.

---

## Características

- Detección automática de idioma por URL, cookie y browser
- Swap automático de menús de navegación y Gravity Forms por idioma
- Tags SEO `hreflang` automáticos en el `<head>`
- Panel de administración con selector visual de 48 idiomas con banderas
- Meta box en el editor de páginas para asignar idioma y vincular traducciones
- Panel en el Site Editor para asignar idioma a cada menú de navegación
- Columnas en la lista de páginas: Language y Translations con botón Edit
- Bloque Gutenberg `Language Switcher` (3 estilos: lista, flags, dropdown)

---

## Requisitos

- WordPress 6.0+
- PHP 7.0+
- (Opcional) Gravity Forms para el swap de formularios por idioma

---

## Instalación

1. Copiar la carpeta `pixellang-multilingual` en `/wp-content/plugins/`
2. Activar el plugin en **WP Admin → Plugins**
3. Ir a **WP Admin → Multilingual** para configurar idiomas

---

## Configuración inicial

### 1. Agregar idiomas

Ir a **Multilingual → Languages**:
- En la columna derecha busca el idioma y haz clic en **+ Add**
- Marca cuál es el idioma **Default** (el que se muestra cuando no hay cookie ni preferencia de browser)
- Guarda con **Save Settings**

### 2. Asignar menús de navegación

Editar cada menú de navegación en **Appearance → Editor → Navigation**:
- En el panel lateral aparece **🌐 Menu Language**
- Seleccionar el idioma correspondiente → se guarda automáticamente

### 3. Asignar idioma a las páginas

Al editar cualquier página en el editor de bloques:
- En el panel lateral aparece **🌐 Language & Translations**
- Seleccionar el idioma de esa página
- Vincular las páginas equivalentes en otros idiomas con los dropdowns

### 4. Asignar formularios (opcional)

Ir a **Multilingual → Gravity Forms**:
- Ingresar el Form ID de Gravity Forms para cada idioma

---

## Estructura de archivos

```
pixellang-multilingual/
├── wp-multilingual.php                  # Bootstrap principal
├── includes/
│   ├── languages-data.php               # Lista de 48 idiomas disponibles
│   ├── class-language-manager.php       # Gestión de idiomas y page map en DB
│   ├── class-url-handler.php            # Detección de idioma y redirects
│   ├── class-content-switcher.php       # Swap de menús y Gravity Forms
│   ├── class-hreflang.php               # Tags SEO hreflang automáticos
│   ├── class-admin.php                  # Página de ajustes en WP Admin
│   ├── class-meta-box.php               # Meta box en editor de páginas y menús
│   └── class-admin-columns.php          # Columnas Language y Translations
├── admin/
│   ├── views/settings-page.php          # Vista HTML de la página de ajustes
│   ├── css/admin.css                    # Estilos del panel de administración
│   ├── css/meta-box.css                 # Estilos del meta box
│   ├── js/admin.js                      # JS del panel de administración
│   ├── js/meta-box.js                   # JS del meta box de páginas
│   └── js/nav-panel.js                  # Panel Gutenberg para menús (Site Editor)
├── blocks/
│   └── language-switcher/
│       ├── block.json                   # Definición del bloque Gutenberg
│       ├── render.php                   # Render PHP del bloque
│       └── style.css                    # Estilos del language switcher
└── languages/                           # Archivos de traducción (.po / .mo)
```

---

## Cómo funciona

### Flujo de detección de idioma

Cada vez que un visitante carga una página:

```
1. ¿Viene ?set_lang=fr en la URL?  → cambia idioma + guarda cookie + redirige
2. ¿Existe cookie wpm_lang?        → usa ese idioma
3. ¿Browser envía Accept-Language? → usa el mejor match disponible
4. Ninguno de los anteriores       → usa el idioma Default
```

### Dónde se guardan los datos

El plugin no crea tablas nuevas — usa las tablas estándar de WordPress:

| Dato | Tabla | Clave |
|---|---|---|
| Idiomas activos | `wp_options` | `wpm_languages` |
| IDs de menús por idioma | `wp_options` | `wpm_menus` |
| IDs de formularios por idioma | `wp_options` | `wpm_forms` |
| Grupos de traducciones de páginas | `wp_options` | `wpm_page_map` |
| Idioma de cada página/post | `wp_postmeta` | `_wpm_language` |

---

## REST API

El plugin expone un endpoint propio para guardar el idioma de los menús desde el Site Editor:

```
POST /wp-json/wpm/v1/nav-language
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `nav_id` | integer | ID del post wp_navigation |
| `lang` | string | Slug del idioma (fr, en, es…) |

**Requiere:** usuario autenticado con capacidad `edit_posts`.

---

## Bloque Language Switcher

Insertar el bloque **Language Switcher** desde el editor de bloques en cualquier lugar del tema.

**Atributos:**

| Atributo | Tipo | Default | Opciones |
|---|---|---|---|
| `style` | string | `flags` | `flags`, `text`, `dropdown` |
| `showLabel` | boolean | `true` | — |
| `align` | string | — | `left`, `center`, `right` |

---

## Desarrollo

### Agregar un nuevo idioma a la lista

Editar `includes/languages-data.php` y agregar una entrada al array:

```php
'xx' => array(
    'label'   => 'Nombre en nativo',
    'locale'  => 'xx_XX',
    'flag'    => '🏳️',
    'english' => 'Name in English',
),
```

### Hooks disponibles

```php
// Modificar el idioma detectado antes de aplicarlo
add_filter( 'wpm_detected_language', function( $lang ) {
    return $lang;
});
```

---

## Autor

**Ricardo Velasquez — Agence Pixel**

- 🌐 Web: [ingcloud.ca](https://ingcloud.ca)
- 🐙 GitHub: [github.com/Rvelasquezp/pixellang-multilingual](https://github.com/Rvelasquezp/pixellang-multilingual)

Construido con Claude Code.
