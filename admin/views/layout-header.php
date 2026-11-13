<?php
/**
 * Shared header of the plugin screens: brand bar, navigation, notices.
 *
 * @package PixelLang_Multilingual
 *
 * @var string $slug    Current screen slug.
 * @var array  $screens Screens definition.
 */

defined( 'ABSPATH' ) || exit;

$wpm_notices = WPM_Admin::notices();
$wpm_notice  = isset( $_GET['wpm_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap wpm-app">
	<h1 class="screen-reader-text"><?php echo esc_html( $screens[ $slug ][0] . ' ‹ PixelLang Multilingual' ); ?></h1>
	<!-- WP moves admin notices after the first heading: keep this marker. -->
	<hr class="wp-header-end" />

	<header class="wpm-topbar">
		<div class="wpm-topbar__brand">
			<span class="wpm-topbar__logo"><?php echo WPM_Icons::mark( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			<span class="wpm-topbar__name">PixelLang <span>Multilingual</span></span>
			<span class="wpm-topbar__version">v<?php echo esc_html( WPM_VERSION ); ?></span>
		</div>
		<nav class="wpm-nav" aria-label="<?php esc_attr_e( 'PixelLang sections', 'pixellang-multilingual' ); ?>">
			<?php
			foreach ( $screens as $wpm_nav_slug => $wpm_nav ) :
				if ( ! current_user_can( $wpm_nav[1] ) ) {
					continue;
				}
				$wpm_is_current = $wpm_nav_slug === $slug;
				?>
				<a class="wpm-nav__link<?php echo $wpm_is_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( WPM_Admin::url( $wpm_nav_slug ) ); ?>"<?php echo $wpm_is_current ? ' aria-current="page"' : ''; ?>>
					<?php WPM_Icons::render( $wpm_nav[3], 16 ); ?>
					<span><?php echo esc_html( $wpm_nav[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>

	<main class="wpm-main" id="wpm-main">
		<?php
		if ( isset( $wpm_notices[ $wpm_notice ] ) ) {
			WPM_Admin::notice( $wpm_notices[ $wpm_notice ][0], $wpm_notices[ $wpm_notice ][1] );
		}
