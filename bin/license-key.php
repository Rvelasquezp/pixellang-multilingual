<?php
/**
 * Issues PixelLang Multilingual license keys (Agence Pixel only).
 *
 * Needs Pixel's private signing key, which is NEVER committed: by default
 * ~/.pixellang/license-signing.key (keep a copy in LastPass). Without it,
 * this script cannot create keys.
 *
 *   php bin/license-key.php --client="Centre le CAP" --domain=centrelecap.ca \
 *       [--domain=*.centrelecap.ca] [--expires=2027-12-31] [--key=/path/to/signing.key]
 *   php bin/license-key.php --check=PXL1.xxx.yyy
 *
 * "client.com" also covers www.client.com; "*.client.com" covers its
 * subdomains. Add the WP Engine staging domain if it should be licensed
 * too (sites on .local, .test and localhost never need a key).
 *
 * @package PixelLang_Multilingual
 */

// phpcs:disable

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	fwrite( STDERR, "PHP needs the sodium extension (PHP 7.2+).\n" );
	exit( 1 );
}

function pxl_b64url( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function pxl_b64url_decode( $data ) {
	$data = strtr( $data, '-_', '+/' );
	return base64_decode( $data . str_repeat( '=', ( 4 - strlen( $data ) % 4 ) % 4 ), true );
}

$opts = getopt( '', array( 'client:', 'domain:', 'expires:', 'key:', 'check:', 'public:' ) );

// Check a key against the public key of the plugin.
if ( isset( $opts['check'] ) ) {
	$source = file_get_contents( __DIR__ . '/../includes/class-license.php' );
	preg_match( "/const PUBLIC_KEY = '([^']+)'/", $source, $m );
	$public = base64_decode( isset( $opts['public'] ) ? $opts['public'] : $m[1], true );
	$parts  = explode( '.', preg_replace( '/\s+/', '', $opts['check'] ) );
	$ok     = 3 === count( $parts ) && 'PXL1' === $parts[0] && sodium_crypto_sign_verify_detached( (string) pxl_b64url_decode( $parts[2] ), $parts[0] . '.' . $parts[1], $public );
	echo $ok ? "Valid signature.\n" . pxl_b64url_decode( $parts[1] ) . "\n" : "INVALID key.\n";
	exit( $ok ? 0 : 1 );
}

if ( empty( $opts['client'] ) || empty( $opts['domain'] ) ) {
	fwrite( STDERR, "Usage: php bin/license-key.php --client=\"Name\" --domain=client.com [--domain=...] [--expires=YYYY-MM-DD] [--key=path]\n" );
	exit( 1 );
}

$key_file = isset( $opts['key'] ) ? $opts['key'] : getenv( 'HOME' ) . '/.pixellang/license-signing.key';
$secret   = is_readable( $key_file ) ? base64_decode( trim( file_get_contents( $key_file ) ), true ) : false;
if ( ! $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
	fwrite( STDERR, "Signing key not found or invalid: $key_file\n" );
	exit( 1 );
}

$domains = array_values( array_unique( array_map( 'strtolower', (array) $opts['domain'] ) ) );
$expires = isset( $opts['expires'] ) ? $opts['expires'] : null;
if ( null !== $expires && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
	fwrite( STDERR, "--expires must be YYYY-MM-DD.\n" );
	exit( 1 );
}

$payload = array(
	'c' => (string) $opts['client'],
	'd' => $domains,
	'e' => $expires,
	'i' => gmdate( 'Y-m-d' ),
	'n' => bin2hex( random_bytes( 6 ) ),
);
$body = 'PXL1.' . pxl_b64url( json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo $body . '.' . pxl_b64url( sodium_crypto_sign_detached( $body, $secret ) ) . "\n";
