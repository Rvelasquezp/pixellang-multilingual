<?php
/**
 * Alternative text and caption of media per language.
 *
 * Images are shared by all languages (no duplicate files). The native
 * fields of the Media Library hold the default language; each other
 * language gets an "Alternative text" and a "Caption" field in the media
 * modal and on the attachment screen, stored in the `_wpm_media` meta
 * (lang => [ alt, caption ]).
 *
 * On the front end, whatever reads the Media Library follows the language
 * of the page: the `_wp_attachment_image_alt` meta (featured images, theme
 * cards and carousels built with wp_get_attachment_image(), Open Graph of
 * SEO plugins) and wp_get_attachment_caption(). An empty field falls back
 * to the default language.
 *
 * Image blocks inside a page keep their own alt and caption in the content:
 * a translation is a separate page, so they are translated in its editor.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Media {

	const META = '_wpm_media';

	public static function init() {
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_alt' ), 10, 4 );
		add_filter( 'wp_get_attachment_caption', array( __CLASS__, 'filter_caption' ), 10, 2 );
		add_action( 'admin_head-post.php', array( __CLASS__, 'edit_screen_styles' ) );
	}

	/**
	 * Attachment screen: the language fields as wide as the native ones.
	 */
	public static function edit_screen_styles() {
		$screen = get_current_screen();
		if ( $screen && 'attachment' === $screen->post_type ) {
			echo '<style>.compat-attachment-fields{width:100%;max-width:810px}.compat-attachment-fields th{padding-bottom:4px;text-align:left}.compat-attachment-fields input.text,.compat-attachment-fields textarea{width:100%}</style>';
		}
	}

	/**
	 * Languages that get extra fields (all but the default one).
	 *
	 * @return array slug => config
	 */
	private static function other_languages() {
		$manager = WPM_Language_Manager::instance();
		$langs   = $manager->get_all();
		unset( $langs[ $manager->get_default() ] );
		return $langs;
	}

	/**
	 * Texts of an image in a language: alt, caption ('' when not translated).
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function get( $id, $lang ) {
		// Read the raw meta: the alt filter below must not run on itself.
		$all = get_post_meta( $id, self::META, true );
		return array(
			'alt'     => isset( $all[ $lang ]['alt'] ) ? (string) $all[ $lang ]['alt'] : '',
			'caption' => isset( $all[ $lang ]['caption'] ) ? (string) $all[ $lang ]['caption'] : '',
		);
	}

	/**
	 * Stores the texts of an image in a language.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $lang Language code.
	 * @param array  $data alt, caption.
	 */
	public static function set( $id, $lang, array $data ) {
		$all = get_post_meta( $id, self::META, true );
		$all = is_array( $all ) ? $all : array();
		$alt     = isset( $data['alt'] ) ? sanitize_text_field( $data['alt'] ) : '';
		$caption = isset( $data['caption'] ) ? wp_kses_post( $data['caption'] ) : '';
		if ( '' === $alt && '' === $caption ) {
			unset( $all[ $lang ] );
		} else {
			$all[ $lang ] = array(
				'alt'     => $alt,
				'caption' => $caption,
			);
		}
		if ( $all ) {
			update_post_meta( $id, self::META, $all );
		} else {
			delete_post_meta( $id, self::META );
		}
	}

	/**
	 * Fields in the media modal and the attachment screen.
	 *
	 * @param array   $fields Form fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public static function fields( $fields, $post ) {
		if ( ! $post || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return $fields;
		}
		foreach ( self::other_languages() as $slug => $cfg ) {
			$texts = self::get( $post->ID, $slug );
			$fields[ 'wpm_alt_' . $slug ] = array(
				/* translators: %s: language name. */
				'label' => sprintf( __( 'Alternative text (%s)', 'pixellang-multilingual' ), $cfg['label'] ),
				'input' => 'text',
				'value' => $texts['alt'],
				/* translators: %s: language name. */
				'helps' => sprintf( __( 'Empty: the default language text is used on %s pages.', 'pixellang-multilingual' ), $cfg['label'] ),
			);
			$fields[ 'wpm_caption_' . $slug ] = array(
				/* translators: %s: language name. */
				'label' => sprintf( __( 'Caption (%s)', 'pixellang-multilingual' ), $cfg['label'] ),
				'input' => 'textarea',
				'value' => $texts['caption'],
			);
		}
		return $fields;
	}

	/**
	 * Saves the fields (media modal and attachment screen).
	 *
	 * @param array $post       Attachment data.
	 * @param array $attachment Submitted fields.
	 * @return array
	 */
	public static function save( $post, $attachment ) {
		if ( empty( $post['ID'] ) || ! current_user_can( 'edit_post', (int) $post['ID'] ) ) {
			return $post;
		}
		foreach ( array_keys( self::other_languages() ) as $slug ) {
			if ( ! array_key_exists( 'wpm_alt_' . $slug, $attachment ) && ! array_key_exists( 'wpm_caption_' . $slug, $attachment ) ) {
				continue;
			}
			self::set(
				(int) $post['ID'],
				$slug,
				array(
					'alt'     => isset( $attachment[ 'wpm_alt_' . $slug ] ) ? wp_unslash( $attachment[ 'wpm_alt_' . $slug ] ) : '',
					'caption' => isset( $attachment[ 'wpm_caption_' . $slug ] ) ? wp_unslash( $attachment[ 'wpm_caption_' . $slug ] ) : '',
				)
			);
		}
		return $post;
	}

	/**
	 * Language whose texts apply, or '' to keep the stored ones.
	 *
	 * @return string
	 */
	private static function front_language() {
		$manager = WPM_Language_Manager::instance();
		if ( ! $manager->is_front_request() ) {
			return '';
		}
		$lang = $manager->get_current();
		return $lang === $manager->get_default() ? '' : $lang;
	}

	/**
	 * _wp_attachment_image_alt of the current language.
	 *
	 * @param mixed  $value    Short-circuit value (null).
	 * @param int    $id       Object ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Single value.
	 * @return mixed
	 */
	public static function filter_alt( $value, $id, $meta_key, $single ) {
		if ( '_wp_attachment_image_alt' !== $meta_key || null !== $value ) {
			return $value;
		}
		$lang = self::front_language();
		if ( ! $lang ) {
			return $value;
		}
		$alt = self::get( (int) $id, $lang )['alt'];
		if ( '' === $alt ) {
			return $value;
		}
		return $single ? $alt : array( $alt );
	}

	/**
	 * Caption of the current language.
	 *
	 * @param string $caption Caption.
	 * @param int    $id      Attachment ID.
	 * @return string
	 */
	public static function filter_caption( $caption, $id ) {
		$lang = self::front_language();
		if ( ! $lang ) {
			return $caption;
		}
		$own = self::get( (int) $id, $lang )['caption'];
		return '' !== $own ? $own : $caption;
	}
}
