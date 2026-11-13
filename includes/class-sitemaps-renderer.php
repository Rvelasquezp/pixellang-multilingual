<?php
/**
 * Core sitemap renderer that also writes <xhtml:link> alternates.
 *
 * Identical to WP_Sitemaps_Renderer::get_sitemap_xml() except that the
 * urlset declares the xhtml namespace and entries may carry a
 * `wpm_alternates` list (hreflang => URL), set by WPM_Sitemaps.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Sitemaps_Renderer extends WP_Sitemaps_Renderer {

	/**
	 * @param array $url_list Entries.
	 * @return string|false
	 */
	public function get_sitemap_xml( $url_list ) {
		$urlset = new SimpleXMLElement(
			sprintf(
				'%1$s%2$s%3$s',
				'<?xml version="1.0" encoding="UTF-8" ?>',
				$this->stylesheet,
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="' . WPM_Sitemaps::XHTML_NS . '" />'
			)
		);

		foreach ( $url_list as $url_item ) {
			$url = $urlset->addChild( 'url' );
			foreach ( $url_item as $name => $value ) {
				if ( 'loc' === $name ) {
					$url->addChild( $name, esc_url( $value ) );
				} elseif ( in_array( $name, array( 'lastmod', 'changefreq', 'priority' ), true ) ) {
					$url->addChild( $name, esc_xml( $value ) );
				} elseif ( 'wpm_alternates' === $name && is_array( $value ) ) {
					foreach ( $value as $hreflang => $href ) {
						$link = $url->addChild( 'xhtml:link', null, WPM_Sitemaps::XHTML_NS );
						$link->addAttribute( 'rel', 'alternate' );
						$link->addAttribute( 'hreflang', (string) $hreflang );
						$link->addAttribute( 'href', esc_url_raw( $href ) );
					}
				} else {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: %s: List of element names. */
							esc_html__( 'Fields other than %s are not currently supported for sitemaps.', 'pixellang-multilingual' ),
							'loc,lastmod,changefreq,priority'
						),
						'5.5.0'
					);
				}
			}
		}

		return $urlset->asXML();
	}
}
