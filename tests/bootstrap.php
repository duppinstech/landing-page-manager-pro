<?php
/**
 * PHPUnit bootstrap: minimal WordPress doubles so the GitHub updater can be tested without a WordPress install.
 *
 * Everything here is fake test data. Never add real credentials or customer data.
 *
 * @package LPManager_Pro
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Test filter registry: tests set $GLOBALS['lpmanager_pro_test_filters'][ $hook ] = callable.
 */
$GLOBALS['lpmanager_pro_test_filters'] = array();

function apply_filters( $hook, $value, ...$args ) {
	if ( isset( $GLOBALS['lpmanager_pro_test_filters'][ $hook ] ) ) {
		return call_user_func( $GLOBALS['lpmanager_pro_test_filters'][ $hook ], $value, ...$args );
	}
	return $value;
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : '';
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

// ---- Doubles used by the GitHub updater tests. -------------------------------------------------

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['lpmanager_pro_test_site_options'] = array();
$GLOBALS['lpmanager_pro_test_get_queue']    = array();
$GLOBALS['lpmanager_pro_test_get_requests'] = array();
$GLOBALS['lpmanager_pro_test_hooks']        = array();

function get_site_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['lpmanager_pro_test_site_options'] ) ? $GLOBALS['lpmanager_pro_test_site_options'][ $name ] : $default_value;
}

function update_site_option( $name, $value ) {
	$GLOBALS['lpmanager_pro_test_site_options'][ $name ] = $value;
	return true;
}

function delete_site_option( $name ) {
	unset( $GLOBALS['lpmanager_pro_test_site_options'][ $name ] );
	return true;
}

/**
 * GET double: shifts the next queued response (array or WP_Error) and records the request.
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['lpmanager_pro_test_get_requests'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( empty( $GLOBALS['lpmanager_pro_test_get_queue'] ) ) {
		return new WP_Error( 'no_response_queued', 'No test response queued.' );
	}
	return array_shift( $GLOBALS['lpmanager_pro_test_get_queue'] );
}

function wp_remote_retrieve_header( $response, $header ) {
	if ( ! is_array( $response ) || empty( $response['headers'] ) ) {
		return '';
	}
	$headers = array_change_key_case( $response['headers'], CASE_LOWER );
	return isset( $headers[ strtolower( $header ) ] ) ? $headers[ strtolower( $header ) ] : '';
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['lpmanager_pro_test_hooks'][] = array( $hook, $callback );
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

/**
 * Same header matching as WordPress core's get_file_data().
 */
function get_file_data( $file, $headers ) {
	$data = (string) file_get_contents( $file, false, null, 0, 8192 ); // phpcs:ignore
	$out  = array();
	foreach ( $headers as $key => $name ) {
		$out[ $key ] = preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $data, $m ) ? trim( $m[1] ) : '';
	}
	return $out;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function wpautop( $text ) {
	return '<p>' . $text . '</p>';
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

$GLOBALS['lpmanager_pro_test_user_can'] = false;

function current_user_can( $capability ) {
	return (bool) $GLOBALS['lpmanager_pro_test_user_can'];
}

/**
 * Filesystem double recording moves.
 */
class LPManager_Pro_Test_Filesystem {
	public $moves  = array();
	public $result = true;

	public function move( $from, $to, $overwrite = false ) {
		$this->moves[] = array( $from, $to, $overwrite );
		return $this->result;
	}
}

function wp_kses_post( $html ) {
	return (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
}

require_once dirname( __DIR__ ) . '/includes/class-lpmanager-pro-github-updater.php';
