<?php
/**
 * Finds translatable strings in the files of a theme or plugin.
 *
 * Read only: files are parsed, never modified. PHP files go through the
 * PHP tokenizer (reliable with quotes, comments and nesting); JavaScript
 * files are read for wp.i18n calls with a regular expression. Only literal
 * arguments are kept: __( $variable ) cannot be known before it runs (the
 * discovery mode records those while browsing).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_String_Scanner {

	const MAX_FILES = 4000;
	const MAX_SIZE  = 1048576;

	/**
	 * gettext functions => positions of text, plural, context, domain.
	 *
	 * @var array
	 */
	private static $functions = array(
		'__'                   => array( 'text' => 0, 'domain' => 1 ),
		'_e'                   => array( 'text' => 0, 'domain' => 1 ),
		'translate'            => array( 'text' => 0, 'domain' => 1 ),
		'esc_html__'           => array( 'text' => 0, 'domain' => 1 ),
		'esc_html_e'           => array( 'text' => 0, 'domain' => 1 ),
		'esc_attr__'           => array( 'text' => 0, 'domain' => 1 ),
		'esc_attr_e'           => array( 'text' => 0, 'domain' => 1 ),
		'_x'                   => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
		'_ex'                  => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
		'esc_html_x'           => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
		'esc_attr_x'           => array( 'text' => 0, 'context' => 1, 'domain' => 2 ),
		'_n'                   => array( 'text' => 0, 'plural' => 1, 'domain' => 3 ),
		'_nx'                  => array( 'text' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ),
		'_n_noop'              => array( 'text' => 0, 'plural' => 1, 'domain' => 2 ),
		'_nx_noop'             => array( 'text' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3 ),
	);

	/**
	 * Components that can be scanned: "type:slug" => [ label, path ].
	 *
	 * @return array
	 */
	public static function components() {
		$out   = array();
		$theme = wp_get_theme();
		$out[ 'theme:' . $theme->get_stylesheet() ] = array(
			'label' => sprintf( '%s (%s)', $theme->get( 'Name' ), __( 'theme', 'pixellang-multilingual' ) ),
			'path'  => $theme->get_stylesheet_directory(),
		);
		if ( $theme->parent() ) {
			$parent = $theme->parent();
			$out[ 'theme:' . $parent->get_stylesheet() ] = array(
				'label' => sprintf( '%s (%s)', $parent->get( 'Name' ), __( 'parent theme', 'pixellang-multilingual' ) ),
				'path'  => $parent->get_stylesheet_directory(),
			);
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			$slug = dirname( $file );
			if ( '.' === $slug || basename( WPM_DIR ) === $slug || ! isset( $plugins[ $file ] ) ) {
				continue;
			}
			$out[ 'plugin:' . $slug ] = array(
				'label' => sprintf( '%s (%s)', $plugins[ $file ]['Name'], __( 'plugin', 'pixellang-multilingual' ) ),
				'path'  => WP_PLUGIN_DIR . '/' . $slug,
			);
		}
		return $out;
	}

	/**
	 * Scans a component and registers its strings.
	 *
	 * @param string $component "type:slug" from components().
	 * @return array|WP_Error found, new, files.
	 */
	public static function scan( $component ) {
		$components = self::components();
		if ( ! isset( $components[ $component ] ) ) {
			return new WP_Error( 'wpm_unknown_component', __( 'Unknown theme or plugin.', 'pixellang-multilingual' ) );
		}
		list( $type, $slug ) = explode( ':', $component, 2 );
		$root                = wp_normalize_path( $components[ $component ]['path'] );
		$found               = self::scan_directory( $root );
		$before              = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . WPM_Strings::table() ); // phpcs:ignore WordPress.DB

		foreach ( $found['strings'] as $string ) {
			// One call per place: register() merges the locations.
			foreach ( $string['locations'] as $location ) {
				WPM_Strings::register(
					array_merge(
						$string,
						array(
							'source_type' => $type,
							'source_name' => $slug,
							'location'    => $location,
						)
					)
				);
			}
		}
		$after = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . WPM_Strings::table() ); // phpcs:ignore WordPress.DB
		return array(
			'found' => count( $found['strings'] ),
			'new'   => $after - $before,
			'files' => $found['files'],
		);
	}

	/**
	 * Strings of every PHP and JS file under a directory.
	 *
	 * @param string $root Directory.
	 * @return array{strings: array, files: int}
	 */
	public static function scan_directory( $root ) {
		$strings = array();
		$files   = 0;
		$skip    = array( 'node_modules', 'vendor', '.git', 'tests', 'languages' );
		if ( ! is_dir( $root ) ) {
			return array( 'strings' => array(), 'files' => 0 );
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				function ( $file ) use ( $skip ) {
					return ! ( $file->isDir() && in_array( $file->getFilename(), $skip, true ) );
				}
			)
		);
		foreach ( $iterator as $file ) {
			if ( $files >= self::MAX_FILES ) {
				break;
			}
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, array( 'php', 'js' ), true ) || $file->getSize() > self::MAX_SIZE || false !== strpos( $file->getFilename(), '.min.' ) ) {
				continue;
			}
			++$files;
			$code = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$rel  = ltrim( substr( wp_normalize_path( $file->getPathname() ), strlen( $root ) ), '/' );
			$list = 'php' === $ext ? self::parse_php( (string) $code ) : self::parse_js( (string) $code );
			foreach ( $list as $item ) {
				$key      = WPM_Strings::key( $item['domain'], $item['context'], $item['original'] );
				$location = $rel . ':' . $item['line'];
				unset( $item['line'] );
				if ( ! isset( $strings[ $key ] ) ) {
					$item['locations'] = array();
					$strings[ $key ]   = $item;
				}
				$strings[ $key ]['locations'][] = $location;
			}
		}
		return array(
			'strings' => array_values( $strings ),
			'files'   => $files,
		);
	}

	/**
	 * gettext calls with literal arguments in PHP code.
	 *
	 * @param string $code PHP source.
	 * @return array
	 */
	public static function parse_php( $code ) {
		$tokens = @token_get_all( $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- Invalid files are skipped.
		$out    = array();
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || ! isset( self::$functions[ $tokens[ $i ][1] ] ) ) {
				continue;
			}
			// Skip method calls and definitions: ->__( , ::__( , function __(.
			$prev = self::previous_significant( $tokens, $i );
			if ( $prev && ( ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) ) ) {
				continue;
			}
			$j = $i + 1;
			while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				++$j;
			}
			if ( $j >= $count || '(' !== $tokens[ $j ] ) {
				continue;
			}
			$args = self::read_args( $tokens, $j );
			$item = self::build( $tokens[ $i ][1], $args, (int) $tokens[ $i ][2] );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	private static function previous_significant( $tokens, $i ) {
		for ( $k = $i - 1; $k >= 0; $k-- ) {
			if ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return $tokens[ $k ];
		}
		return null;
	}

	/**
	 * Arguments of a call: each is the literal string, or null when not literal.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $open   Index of "(".
	 * @return array
	 */
	private static function read_args( $tokens, $open ) {
		$args    = array();
		$depth   = 0;
		$current = array();
		$count   = count( $tokens );
		for ( $k = $open; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t || '{' === $t ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $t || ']' === $t || '}' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					$args[] = self::literal( $current );
					break;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$args[]  = self::literal( $current );
				$current = array();
				continue;
			}
			if ( ! ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
				$current[] = $t;
			}
		}
		return $args;
	}

	/**
	 * Value of an argument made of one string literal, else null.
	 *
	 * @param array $parts Tokens of the argument.
	 * @return string|null
	 */
	private static function literal( array $parts ) {
		if ( 1 !== count( $parts ) || ! is_array( $parts[0] ) || T_CONSTANT_ENCAPSED_STRING !== $parts[0][0] ) {
			return null;
		}
		$raw   = $parts[0][1];
		$quote = $raw[0];
		$body  = substr( $raw, 1, -1 );
		if ( "'" === $quote ) {
			return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
		}
		if ( false !== strpos( $body, '$' ) ) {
			return null; // Interpolated: not a fixed string.
		}
		return stripcslashes( $body );
	}

	private static function build( $function, array $args, $line ) {
		$map  = self::$functions[ $function ];
		$text = $args[ $map['text'] ] ?? null;
		if ( null === $text || '' === $text ) {
			return null;
		}
		$domain = isset( $map['domain'] ) ? ( $args[ $map['domain'] ] ?? null ) : null;
		if ( ! $domain || in_array( $domain, array( 'default' ), true ) ) {
			return null; // Core strings are translated by WordPress itself.
		}
		return array(
			'original'        => $text,
			'original_plural' => isset( $map['plural'] ) ? (string) ( $args[ $map['plural'] ] ?? '' ) : '',
			'context'         => isset( $map['context'] ) ? (string) ( $args[ $map['context'] ] ?? '' ) : '',
			'domain'          => $domain,
			'line'            => $line,
		);
	}

	/**
	 * wp.i18n calls in JavaScript: __( 'Text', 'domain' ) and _x( 'Text', 'ctx', 'domain' ).
	 *
	 * @param string $code JS source.
	 * @return array
	 */
	public static function parse_js( $code ) {
		$out = array();
		$str = '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")';
		$re  = '/(?<![\w$.])(?:wp\.i18n\.)?(__|_x)\(\s*' . $str . '\s*,\s*' . $str . '(?:\s*,\s*' . $str . ')?\s*\)/';
		if ( ! preg_match_all( $re, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}
		foreach ( $matches as $m ) {
			$values = array();
			for ( $g = 2; $g <= 7; $g += 2 ) {
				if ( isset( $m[ $g ] ) && -1 !== $m[ $g ][1] && '' !== $m[ $g ][0] ) {
					$values[] = stripcslashes( $m[ $g ][0] );
				} elseif ( isset( $m[ $g + 1 ] ) && -1 !== $m[ $g + 1 ][1] ) {
					$values[] = stripcslashes( $m[ $g + 1 ][0] );
				}
			}
			$is_x = '_x' === $m[1][0];
			if ( ( $is_x && count( $values ) < 3 ) || ( ! $is_x && count( $values ) < 2 ) ) {
				continue;
			}
			$out[] = array(
				'original'        => $values[0],
				'original_plural' => '',
				'context'         => $is_x ? $values[1] : '',
				'domain'          => $is_x ? $values[2] : $values[1],
				'line'            => substr_count( substr( $code, 0, $m[0][1] ), "\n" ) + 1,
			);
		}
		return $out;
	}
}
