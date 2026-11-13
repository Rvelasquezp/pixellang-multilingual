<?php
/**
 * License: status of this site and the key issued by Agence Pixel.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

$wpm_status  = WPM_License::status();
$wpm_state   = $wpm_status['state'];
$wpm_payload = $wpm_status['payload'];
$wpm_key     = (string) get_option( WPM_License::OPTION, '' );
$wpm_tone    = array(
	'valid'       => 'success',
	'expiring'    => 'warning',
	'development' => 'info',
	'missing'     => 'warning',
	'invalid'     => 'danger',
	'domain'      => 'danger',
	'expired'     => 'danger',
);
$wpm_icon    = in_array( $wpm_state, array( 'valid', 'development' ), true ) ? 'check-circle' : ( 'expiring' === $wpm_state ? 'alert' : 'shield' );
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'License', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'PixelLang Multilingual is reserved for Agence Pixel clients. Each site gets a key for its domain.', 'pixellang-multilingual' ); ?></p>
	</div>
</div>

<section class="wpm-card wpm-license is-<?php echo esc_attr( $wpm_tone[ $wpm_state ] ); ?>" aria-labelledby="wpm-license-state">
	<div class="wpm-license__head">
		<span class="wpm-license__icon"><?php WPM_Icons::render( $wpm_icon, 22 ); ?></span>
		<div>
			<h3 class="wpm-license__state" id="wpm-license-state"><?php echo esc_html( WPM_License::label( $wpm_state ) ); ?></h3>
			<p class="wpm-license__text"><?php echo esc_html( WPM_License::message() ); ?></p>
		</div>
	</div>
	<dl class="wpm-license__facts">
		<div>
			<dt><?php esc_html_e( 'This site', 'pixellang-multilingual' ); ?></dt>
			<dd><code><?php echo esc_html( WPM_License::site_host() ); ?></code></dd>
		</div>
		<?php if ( $wpm_payload ) : ?>
			<div>
				<dt><?php esc_html_e( 'Client', 'pixellang-multilingual' ); ?></dt>
				<dd><?php echo esc_html( isset( $wpm_payload['c'] ) ? $wpm_payload['c'] : '' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Domains', 'pixellang-multilingual' ); ?></dt>
				<dd><?php echo esc_html( implode( ', ', $wpm_payload['d'] ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Expires', 'pixellang-multilingual' ); ?></dt>
				<dd><?php echo esc_html( ! empty( $wpm_payload['e'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $wpm_payload['e'] ) ) : __( 'Never', 'pixellang-multilingual' ) ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>
</section>

<form class="wpm-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="wpm_license" />
	<?php wp_nonce_field( 'wpm_license' ); ?>
	<div class="wpm-card__head">
		<h3 class="wpm-card__title"><?php echo esc_html( $wpm_key ? __( 'Replace the license key', 'pixellang-multilingual' ) : __( 'Add the license key', 'pixellang-multilingual' ) ); ?></h3>
	</div>
	<?php if ( $wpm_key ) : ?>
		<p class="wpm-card__intro">
			<?php esc_html_e( 'Current key:', 'pixellang-multilingual' ); ?>
			<code class="wpm-license__masked"><?php echo esc_html( substr( $wpm_key, 0, 9 ) . '…' . substr( $wpm_key, -6 ) ); ?></code>
		</p>
	<?php endif; ?>
	<label class="wpm-field">
		<span class="wpm-field__label"><?php esc_html_e( 'License key', 'pixellang-multilingual' ); ?></span>
		<textarea class="wpm-input wpm-license__input" name="license_key" rows="3" spellcheck="false" autocomplete="off" placeholder="PXL1.…" aria-describedby="wpm-license-help"></textarea>
	</label>
	<p class="wpm-help" id="wpm-license-help"><?php WPM_Icons::render( 'info', 16 ); ?><span><?php esc_html_e( 'Agence Pixel sends the key with the site. It is checked on this server, nothing is sent anywhere. Development sites (.local, .test, localhost) need no key.', 'pixellang-multilingual' ); ?></span></p>
	<div class="wpm-card__foot">
		<?php if ( $wpm_key ) : ?>
			<button type="submit" name="remove" value="1" class="wpm-btn wpm-btn--ghost wpm-btn--danger-text" formnovalidate><?php esc_html_e( 'Remove the key', 'pixellang-multilingual' ); ?></button>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<button type="submit" class="wpm-btn wpm-btn--primary"><?php esc_html_e( 'Save the key', 'pixellang-multilingual' ); ?></button>
	</div>
</form>
