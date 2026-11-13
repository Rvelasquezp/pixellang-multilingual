<?php
/**
 * Language switcher: markup of the block designs and automatic placement.
 *
 * Designs (block attribute "design"):
 * - minimal:   inline links, current language underlined; optional separator.
 * - segmented: pill group, current language highlighted.
 * - dropdown:  button (globe, current language, chevron) opening a list of
 *              links. Disclosure pattern: aria-expanded on the button,
 *              Escape closes, arrow keys move between links (view.js).
 *              Without JavaScript the list opens on focus.
 * - select:    native <select>, the most compact and familiar on mobile.
 *
 * Blocks saved before 1.4 only have "style" (flags, text, dropdown): it maps
 * to minimal + code, minimal + name and select, so their markup is unchanged.
 * Class names of 1.0 (.wpm-lang-list, .wpm-lang-item, .wpm-active,
 * .wpm-lang-link, .wpm-lang-current) are kept in every design: themes style them.
 *
 * Automatic placement (Settings > Language switcher), like WPML:
 * - menu:     the languages become the last items of chosen Navigation
 *             blocks or classic menu locations, so they take the look of
 *             the menu in any theme, mobile overlay included.
 * - floating: a dropdown fixed in a corner of the screen.
 *
 * Colors come from the theme (currentColor) or the block color settings;
 * only the dropdown panel has its own surface, light or dark.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Switcher {

	const DESIGNS  = array( 'minimal', 'segmented', 'dropdown', 'select' );
	const DISPLAYS = array( 'code', 'name', 'both' );

	public static function init() {
		add_action( 'save_post_wp_template_part', array( __CLASS__, 'forget_header_check' ) );
		add_action( 'switch_theme', array( __CLASS__, 'forget_header_check' ) );
		if ( is_admin() ) {
			return;
		}
		add_filter( 'render_block_core/navigation', array( __CLASS__, 'add_to_navigation_block' ), 20, 2 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'add_to_classic_menu' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_floating' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating' ), 60 );
	}

	/**
	 * The floating switcher is printed in the footer, after the styles: load
	 * the block style and script in the head.
	 */
	public static function enqueue_floating() {
		if ( 'floating' === self::placement() ) {
			wp_enqueue_style( 'wp-multilingual-language-switcher-style' );
			wp_enqueue_script( 'wp-multilingual-language-switcher-view-script' );
		}
	}

	// ---------------------------------------------------------------------
	// Data
	// ---------------------------------------------------------------------

	/**
	 * Block attributes with defaults and the 1.0 "style" mapped to 1.4 options.
	 *
	 * @param array $attrs Raw attributes.
	 * @return array
	 */
	public static function normalize( array $attrs ) {
		$style  = isset( $attrs['style'] ) && is_string( $attrs['style'] ) ? $attrs['style'] : 'flags';
		$design = isset( $attrs['design'] ) && in_array( $attrs['design'], self::DESIGNS, true ) ? $attrs['design'] : '';
		if ( '' === $design ) {
			$design = 'dropdown' === $style ? 'select' : 'minimal';
		}
		$display = isset( $attrs['display'] ) && in_array( $attrs['display'], self::DISPLAYS, true ) ? $attrs['display'] : '';
		if ( '' === $display ) {
			$display = 'text' === $style || 'select' === $design ? 'name' : ( 'dropdown' === $design ? 'both' : 'code' );
		}
		$separator = isset( $attrs['separator'] ) && in_array( $attrs['separator'], array( 'none', 'slash', 'pipe', 'dot' ), true ) ? $attrs['separator'] : 'none';
		$size      = isset( $attrs['size'] ) && in_array( $attrs['size'], array( 'small', 'medium', 'large' ), true ) ? $attrs['size'] : '';

		return array(
			'design'      => $design,
			'display'     => $display,
			'separator'   => $separator,
			'size'        => $size,
			'showIcon'    => ! empty( $attrs['showIcon'] ),
			'menuAlign'   => isset( $attrs['menuAlign'] ) && 'left' === $attrs['menuAlign'] ? 'left' : 'right',
			'menuTheme'   => isset( $attrs['menuTheme'] ) && 'dark' === $attrs['menuTheme'] ? 'dark' : 'light',
			'hideCurrent' => ! empty( $attrs['hideCurrent'] ),
			'hideMissing' => ! empty( $attrs['hideMissing'] ),
		);
	}

	/**
	 * Languages to show: slug => label, code, hreflang, url, current.
	 *
	 * @param bool $hide_current Leave out the current language.
	 * @param bool $hide_missing Leave out languages without a translation of
	 *                           the page (otherwise they link to their home).
	 * @return array<string,array>
	 */
	public static function get_items( $hide_current, $hide_missing ) {
		$manager = WPM_Language_Manager::instance();
		$urls    = WPM_Url_Handler::instance();
		$current = $manager->get_current();
		$items   = array();
		foreach ( $manager->get_all() as $slug => $cfg ) {
			$is_current = $slug === $current;
			$url        = '';
			if ( ! $is_current ) {
				$url = $urls->get_language_url( $slug, false );
				// No translation of this page: the home page of that language,
				// unless the language has no home page yet.
				if ( ! $url && ! $hide_missing && self::has_home( $slug ) ) {
					$url = $urls->get_language_url( $slug, true );
				}
			}
			if ( ( $is_current && $hide_current ) || ( ! $is_current && ! $url ) ) {
				continue;
			}
			$items[ $slug ] = array(
				'label'    => $cfg['label'],
				'code'     => $slug,
				'hreflang' => $manager->get_hreflang( $slug ),
				'url'      => $url,
				'current'  => $is_current,
			);
		}
		/**
		 * Filters the languages listed by a language switcher.
		 *
		 * @param array $items slug => label, code, hreflang, url, current.
		 */
		return apply_filters( 'wpm_switcher_items', $items );
	}

	/**
	 * Whether a language has a home page of its own. With a static front
	 * page, a language whose front page is not translated would send the
	 * visitor to the front page of another language: such a language is
	 * only offered on pages that are translated into it.
	 *
	 * @param string $slug Language code.
	 * @return bool
	 */
	public static function has_home( $slug ) {
		$front = (int) get_option( 'page_on_front' );
		if ( 'page' !== get_option( 'show_on_front' ) || ! $front || ! WPM_Translations::get_post_language( $front ) ) {
			$has = true; // Posts home or no language on the front page: it shows every language.
		} else {
			$has = (bool) WPM_Translations::get_public_post_translation( $front, $slug );
		}
		/**
		 * Filters whether a language has a home page to fall back to.
		 *
		 * @param bool   $has  Whether the language has a home page.
		 * @param string $slug Language code.
		 */
		return (bool) apply_filters( 'wpm_language_has_home', $has, $slug );
	}

	// ---------------------------------------------------------------------
	// Block markup
	// ---------------------------------------------------------------------

	/**
	 * Markup of a switcher.
	 *
	 * @param array  $attrs    Block attributes (raw or normalized).
	 * @param string $classes  Extra classes of the <nav>.
	 * @param bool   $as_block Rendered as a block: the <nav> gets the block
	 *                         support classes and styles (colors, typography,
	 *                         spacing, border) from get_block_wrapper_attributes().
	 * @return string '' when there is nothing to show.
	 */
	public static function render( array $attrs, $classes = '', $as_block = false ) {
		$o     = self::normalize( $attrs );
		// Dropdown: the current language stays on the button in any case.
		$items = self::get_items( $o['hideCurrent'], $o['hideMissing'] );
		if ( ! $items || ( 1 === count( $items ) && reset( $items )['current'] && 'dropdown' !== $o['design'] ) ) {
			return '';
		}

		$legacy = array(
			'minimal' => 'name' === $o['display'] ? 'text' : 'flags',
			'select'  => 'dropdown',
		);
		$class = array(
			'wpm-language-switcher',
			'wpm-design-' . $o['design'],
			'wpm-display-' . $o['display'],
		);
		if ( isset( $legacy[ $o['design'] ] ) ) {
			$class[] = 'wpm-style-' . $legacy[ $o['design'] ];
		}
		if ( $o['size'] ) {
			$class[] = 'wpm-size-' . $o['size'];
		}
		if ( 'minimal' === $o['design'] && 'none' !== $o['separator'] ) {
			$class[] = 'wpm-sep-' . $o['separator'];
		}
		if ( $classes ) {
			$class[] = $classes;
		}

		$label = __( 'Language', 'pixellang-multilingual' );
		if ( $as_block && function_exists( 'get_block_wrapper_attributes' ) ) {
			$html = '<nav ' . get_block_wrapper_attributes(
				array(
					'class'      => implode( ' ', $class ),
					'aria-label' => $label,
				)
			) . '>';
		} else {
			$html = sprintf( '<nav class="%s" aria-label="%s">', esc_attr( implode( ' ', $class ) ), esc_attr( $label ) );
		}

		switch ( $o['design'] ) {
			case 'select':
				$html .= self::render_select( $items, $o );
				break;
			case 'dropdown':
				$html .= self::render_dropdown( $items, $o );
				break;
			default:
				$html .= self::render_list( $items, $o );
		}
		return $html . '</nav>';
	}

	/**
	 * Visible text of a language.
	 *
	 * @param array  $item    Item.
	 * @param string $display code, name or both.
	 * @return string HTML.
	 */
	private static function text( array $item, $display ) {
		if ( 'name' === $display ) {
			return esc_html( $item['label'] );
		}
		if ( 'both' === $display ) {
			return '<span class="wpm-lang-name">' . esc_html( $item['label'] ) . '</span><span class="wpm-lang-code">' . esc_html( $item['code'] ) . '</span>';
		}
		return esc_html( $item['code'] );
	}

	/**
	 * Accessible name when only the code is visible: starts with the visible
	 * text (WCAG 2.5.3 Label in Name).
	 *
	 * @param array  $item    Item.
	 * @param string $display Display.
	 * @return string Attribute or ''.
	 */
	private static function aria( array $item, $display ) {
		return 'code' === $display ? ' aria-label="' . esc_attr( sprintf( '%1$s (%2$s)', $item['code'], $item['label'] ) ) . '"' : '';
	}

	/**
	 * Minimal and segmented designs.
	 *
	 * @param array $items Items.
	 * @param array $o     Options.
	 * @return string
	 */
	private static function render_list( array $items, array $o ) {
		$html = '';
		if ( $o['showIcon'] ) {
			$html .= self::icon( 'globe', 'wpm-switcher-icon' );
		}
		$html .= '<ul class="wpm-lang-list">';
		foreach ( $items as $item ) {
			$html .= '<li class="wpm-lang-item' . ( $item['current'] ? ' wpm-active' : '' ) . '">';
			if ( $item['current'] ) {
				$html .= sprintf( '<span class="wpm-lang-current" aria-current="true" lang="%s"%s>%s</span>', esc_attr( $item['hreflang'] ), self::aria( $item, $o['display'] ), self::text( $item, $o['display'] ) );
			} else {
				$html .= sprintf( '<a class="wpm-lang-link" href="%s" hreflang="%s" lang="%s"%s>%s</a>', esc_url( $item['url'] ), esc_attr( $item['hreflang'] ), esc_attr( $item['hreflang'] ), self::aria( $item, $o['display'] ), self::text( $item, $o['display'] ) );
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Native select (1.0 "dropdown" style).
	 *
	 * @param array $items Items.
	 * @param array $o     Options.
	 * @return string
	 */
	private static function render_select( array $items, array $o ) {
		$id   = wp_unique_id( 'wpm-switcher-' );
		$html = sprintf( '<label class="screen-reader-text" for="%s">%s</label>', esc_attr( $id ), esc_html__( 'Choose a language', 'pixellang-multilingual' ) );
		if ( $o['showIcon'] ) {
			$html .= self::icon( 'globe', 'wpm-switcher-icon' );
		}
		$html .= sprintf( '<select id="%s" data-wpm-switch>', esc_attr( $id ) );
		foreach ( $items as $item ) {
			$text  = 'code' === $o['display'] ? strtoupper( $item['code'] ) : $item['label'];
			$html .= sprintf( '<option value="%s" lang="%s"%s>%s</option>', esc_url( $item['url'] ), esc_attr( $item['hreflang'] ), selected( $item['current'], true, false ), esc_html( $text ) );
		}
		return $html . '</select>';
	}

	/**
	 * Dropdown: button + list of links (disclosure).
	 *
	 * @param array $items Items.
	 * @param array $o     Options.
	 * @return string
	 */
	private static function render_dropdown( array $items, array $o ) {
		$manager = WPM_Language_Manager::instance();
		$current = $manager->get_current();
		$id      = wp_unique_id( 'wpm-switcher-menu-' );
		$cur     = array(
			'label'    => $manager->get_label( $current ),
			'code'     => $current,
			'hreflang' => $manager->get_hreflang( $current ),
		);
		$button  = 'name' === $o['display'] ? esc_html( $cur['label'] ) : esc_html( $cur['code'] );

		$html  = sprintf( '<div class="wpm-dd wpm-dd--%1$s wpm-dd--%2$s" data-wpm-dropdown>', esc_attr( $o['menuAlign'] ), esc_attr( $o['menuTheme'] ) );
		// The accessible name starts with the visible text (WCAG 2.5.3).
		$hint = 'name' === $o['display']
			? __( ', change language', 'pixellang-multilingual' )
			/* translators: %s: name of the current language. */
			: sprintf( __( ' (%s), change language', 'pixellang-multilingual' ), $cur['label'] );
		$html .= sprintf(
			'<button type="button" class="wpm-dd__toggle" aria-expanded="false" aria-controls="%1$s">%2$s<span class="wpm-dd__current" lang="%3$s">%4$s</span><span class="screen-reader-text">%5$s</span>%6$s</button>',
			esc_attr( $id ),
			$o['showIcon'] ? self::icon( 'globe', 'wpm-switcher-icon' ) : '',
			esc_attr( $cur['hreflang'] ),
			$button,
			esc_html( $hint ),
			self::icon( 'chevron', 'wpm-dd__chevron' )
		);
		$html .= sprintf( '<ul class="wpm-lang-list wpm-dd__menu" id="%s">', esc_attr( $id ) );
		foreach ( $items as $item ) {
			$inner = '<span class="wpm-lang-name">' . esc_html( $item['label'] ) . '</span>';
			if ( 'name' !== $o['display'] ) {
				$inner .= '<span class="wpm-lang-code">' . esc_html( $item['code'] ) . '</span>';
			}
			$html .= '<li class="wpm-lang-item' . ( $item['current'] ? ' wpm-active' : '' ) . '">';
			if ( $item['current'] ) {
				$html .= sprintf( '<span class="wpm-lang-current" aria-current="true" lang="%s">%s%s</span>', esc_attr( $item['hreflang'] ), $inner, self::icon( 'check', 'wpm-dd__check' ) );
			} else {
				$html .= sprintf( '<a class="wpm-lang-link" href="%s" hreflang="%s" lang="%s">%s</a>', esc_url( $item['url'] ), esc_attr( $item['hreflang'] ), esc_attr( $item['hreflang'] ), $inner );
			}
			$html .= '</li>';
		}
		return $html . '</ul></div>';
	}

	/**
	 * Inline SVG icons (Lucide shapes, 24 grid, stroke = currentColor).
	 *
	 * @param string $name  globe, chevron or check.
	 * @param string $class Class.
	 * @return string
	 */
	public static function icon( $name, $class ) {
		$paths = array(
			'globe'   => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
			'chevron' => '<path d="m6 9 6 6 6-6"/>',
			'check'   => '<path d="M20 6 9 17l-5-5"/>',
		);
		return sprintf(
			'<svg class="%s" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $class ),
			$paths[ $name ]
		);
	}

	// ---------------------------------------------------------------------
	// Automatic placement
	// ---------------------------------------------------------------------

	/**
	 * Placement chosen in the settings: none, menu or floating.
	 *
	 * @return string
	 */
	public static function placement() {
		$placement = (string) WPM_Settings::get( 'switcher_placement' );
		return count( WPM_Language_Manager::instance()->get_all() ) > 1 ? $placement : 'none';
	}

	/**
	 * Menu items for the languages.
	 *
	 * @param string $item_class  Class of each <li>.
	 * @param string $link_class  Class of each <a>.
	 * @param string $label_class Class of the text span ('' for none).
	 * @return string
	 */
	public static function menu_items( $item_class, $link_class, $label_class = '' ) {
		$display = (string) WPM_Settings::get( 'switcher_display' );
		$display = in_array( $display, array( 'code', 'name' ), true ) ? $display : 'code';
		$items   = self::get_items( (bool) WPM_Settings::get( 'switcher_hide_current' ), false );
		$html    = '';
		foreach ( $items as $item ) {
			// Menus do not load the block styles: codes are written in capitals.
			$text = 'name' === $display ? esc_html( $item['label'] ) : esc_html( strtoupper( $item['code'] ) );
			if ( $label_class ) {
				$text = '<span class="' . esc_attr( $label_class ) . '">' . $text . '</span>';
			}
			$url   = $item['current'] ? WPM_Url_Handler::instance()->get_language_url( $item['code'] ) : $item['url'];
			$html .= sprintf(
				'<li class="%1$s wpm-menu-language wpm-menu-language-%2$s%3$s"><a class="%4$s wpm-lang-link" href="%5$s" hreflang="%6$s" lang="%6$s"%7$s%8$s>%9$s</a></li>',
				esc_attr( $item_class ),
				esc_attr( $item['code'] ),
				$item['current'] ? ' wpm-active current-menu-item' : '',
				esc_attr( $link_class ),
				esc_url( $url ),
				esc_attr( $item['hreflang'] ),
				$item['current'] ? ' aria-current="true"' : '',
				'code' === $display ? ' aria-label="' . esc_attr( sprintf( '%1$s (%2$s)', strtoupper( $item['code'] ), $item['label'] ) ) . '"' : '',
				$text
			);
		}
		return $html;
	}

	/**
	 * Whether a header template part of the active theme already contains a
	 * Language Switcher block: the menu placement would show it twice, so it
	 * steps aside. Cached until a template part is saved or the theme changes.
	 *
	 * @return bool
	 */
	public static function theme_header_has_block() {
		$key    = 'wpm_header_switcher_' . get_stylesheet();
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}
		$found = false;
		if ( function_exists( 'get_block_templates' ) && wp_is_block_theme() ) {
			// Themes do not always declare the header area: the slug counts too.
			foreach ( get_block_templates( array(), 'wp_template_part' ) as $part ) {
				$is_header = 'header' === $part->area || false !== strpos( (string) $part->slug, 'header' );
				if ( $is_header && false !== strpos( (string) $part->content, 'wp:wp-multilingual/language-switcher' ) ) {
					$found = true;
					break;
				}
			}
		}
		set_transient( $key, $found ? 'yes' : 'no', DAY_IN_SECONDS );
		return $found;
	}

	/**
	 * Forgets the header check (a template part was saved, the theme changed).
	 */
	public static function forget_header_check() {
		delete_transient( 'wpm_header_switcher_' . get_stylesheet() );
	}

	/**
	 * Whether a navigation menu was chosen for the switcher. A menu that has
	 * language versions counts with all of them.
	 *
	 * @param int $ref wp_navigation post ID.
	 * @return bool
	 */
	public static function is_chosen_navigation( $ref ) {
		$chosen = array_map( 'intval', (array) WPM_Settings::get( 'switcher_menus' ) );
		if ( ! $ref || ! $chosen ) {
			return false;
		}
		if ( in_array( $ref, $chosen, true ) ) {
			return true;
		}
		$versions = get_option( 'wpm_menus', array() );
		$versions = is_array( $versions ) ? array_map( 'intval', $versions ) : array();
		return in_array( $ref, $versions, true ) && (bool) array_intersect( $chosen, $versions );
	}

	/**
	 * Navigation block: languages after the last item of the menu.
	 *
	 * @param string $content Rendered block.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function add_to_navigation_block( $content, $block ) {
		if ( 'menu' !== self::placement() || false !== strpos( $content, 'wpm-menu-language' ) || self::theme_header_has_block() ) {
			return $content;
		}
		$ref = isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0;
		if ( ! self::is_chosen_navigation( $ref ) ) {
			return $content;
		}
		// The outermost list of the block closes last (submenus close inside it).
		$end = strrpos( $content, '</ul>' );
		if ( false === $end ) {
			return $content;
		}
		$items = self::menu_items( 'wp-block-navigation-item', 'wp-block-navigation-item__content', 'wp-block-navigation-item__label' );
		return substr_replace( $content, $items, $end, 0 );
	}

	/**
	 * Classic menus (wp_nav_menu) in the chosen theme locations.
	 *
	 * @param string   $items Menu items HTML.
	 * @param stdClass $args  wp_nav_menu() arguments.
	 * @return string
	 */
	public static function add_to_classic_menu( $items, $args ) {
		if ( 'menu' !== self::placement() || empty( $args->theme_location ) ) {
			return $items;
		}
		if ( ! in_array( $args->theme_location, (array) WPM_Settings::get( 'switcher_locations' ), true ) ) {
			return $items;
		}
		return $items . self::menu_items( 'menu-item', '' );
	}

	/**
	 * Floating dropdown in a corner of the screen.
	 */
	public static function render_floating() {
		if ( 'floating' !== self::placement() || is_feed() || is_embed() ) {
			return;
		}
		$position = 'left' === WPM_Settings::get( 'switcher_floating_position' ) ? 'left' : 'right';
		$html     = self::render(
			array(
				'design'    => 'dropdown',
				'display'   => 'name' === WPM_Settings::get( 'switcher_display' ) ? 'name' : 'both',
				'showIcon'  => true,
				'menuAlign' => $position,
				'menuTheme' => 'dark' === WPM_Settings::get( 'switcher_floating_theme' ) ? 'dark' : 'light',
			),
			'wpm-floating wpm-floating--' . $position . ' wpm-floating--' . ( 'dark' === WPM_Settings::get( 'switcher_floating_theme' ) ? 'dark' : 'light' )
		);
		if ( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render().
		}
	}
}
