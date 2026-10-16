<?php
/**
 * Tracking_Manager helpers that run without WordPress.
 *
 * @package LPManager_Pro
 */

use LPManagerPro\Tracking_Manager;
use PHPUnit\Framework\TestCase;

// Pass-through double: these tests cover stripping and length, not URL escaping.
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return str_replace( ' ', '%20', (string) $url );
	}
}

if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( $data, $scheme = 'auth' ) {
		return hash_hmac( 'md5', $data, 'fake-test-salt-' . $scheme );
	}
}

// In-memory object cache; tests switch it on with $GLOBALS['lpmanager_pro_test_object_cache'].
$GLOBALS['lpmanager_pro_test_object_cache'] = false;
$GLOBALS['lpmanager_pro_test_cache']        = array();

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache() {
		return $GLOBALS['lpmanager_pro_test_object_cache'];
	}
}

if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
		if ( isset( $GLOBALS['lpmanager_pro_test_cache'][ $group ][ $key ] ) ) {
			return false;
		}
		$GLOBALS['lpmanager_pro_test_cache'][ $group ][ $key ] = $data;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_incr' ) ) {
	function wp_cache_incr( $key, $offset = 1, $group = '' ) {
		if ( ! isset( $GLOBALS['lpmanager_pro_test_cache'][ $group ][ $key ] ) ) {
			return false;
		}
		$GLOBALS['lpmanager_pro_test_cache'][ $group ][ $key ] += $offset;
		return $GLOBALS['lpmanager_pro_test_cache'][ $group ][ $key ];
	}
}

require_once dirname( __DIR__ ) . '/includes/Tracking_Manager.php';

class TrackingManagerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['lpmanager_pro_test_object_cache'] = false;
		$GLOBALS['lpmanager_pro_test_cache']        = array();
	}

	public function test_clean_href_strips_query_string_and_fragment() {
		$this->assertSame( 'https://example.com/pricing', Tracking_Manager::clean_href( 'https://example.com/pricing?email=a@example.com&token=abc#plans' ) );
		$this->assertSame( 'https://example.com/pricing', Tracking_Manager::clean_href( 'https://example.com/pricing#plans' ) );
		$this->assertSame( 'mailto:sales@example.com', Tracking_Manager::clean_href( 'mailto:sales@example.com?subject=Quote' ) );
	}

	public function test_clean_href_keeps_phone_links() {
		$this->assertSame( 'tel:+15551234567', Tracking_Manager::clean_href( 'tel:+15551234567' ) );
	}

	public function test_clean_href_caps_length_after_escaping() {
		$long = 'https://example.com/' . str_repeat( 'a b', 300 );

		$this->assertSame( Tracking_Manager::MAX_HREF_LENGTH, strlen( Tracking_Manager::clean_href( $long ) ) );
	}

	public function test_clean_href_handles_non_strings() {
		$this->assertSame( '', Tracking_Manager::clean_href( null ) );
	}

	public function test_clean_link_rows_strips_and_merges_rows_for_the_same_link() {
		$stored = array(
			12 => array(
				'a' => array( 'count' => 3, 'href' => 'https://example.com/offer?utm_source=ad', 'label' => 'Get the offer' ),
				'b' => array( 'count' => 2, 'href' => 'https://example.com/offer#top', 'label' => 'Get the offer' ),
				'c' => array( 'count' => 5, 'href' => 'tel:+15551234567', 'label' => 'Call now' ),
			),
			'junk' => 'not-an-array',
		);

		$cleaned = Tracking_Manager::clean_link_rows( $stored );

		$this->assertSame( array( 12 ), array_keys( $cleaned ) );
		$this->assertCount( 2, $cleaned[12] );
		$rows = array_values( $cleaned[12] );
		$this->assertSame( array( 'count' => 5, 'href' => 'https://example.com/offer', 'label' => 'Get the offer' ), $rows[0] );
		$this->assertSame( array( 'count' => 5, 'href' => 'tel:+15551234567', 'label' => 'Call now' ), $rows[1] );
	}

	public function test_clean_link_rows_is_idempotent() {
		$once = Tracking_Manager::clean_link_rows(
			array( 7 => array( 'x' => array( 'count' => 1, 'href' => 'https://example.com/a?b=c', 'label' => str_repeat( 'L', 150 ) ) ) )
		);

		$this->assertSame( $once, Tracking_Manager::clean_link_rows( $once ) );
		$this->assertSame( Tracking_Manager::MAX_LABEL_LENGTH, mb_strlen( current( $once[7] )['label'] ) );
	}

	public function test_session_token_verifies_for_its_own_page_only() {
		$token = Tracking_Manager::create_session_token( 42, 1000000 );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', (string) Tracking_Manager::verify_session_token( $token, 42, 1000100 ) );
		$this->assertFalse( Tracking_Manager::verify_session_token( $token, 43, 1000100 ) );
	}

	public function test_session_token_expires_and_rejects_tampering() {
		$token = Tracking_Manager::create_session_token( 42, 1000000 );

		$this->assertFalse( Tracking_Manager::verify_session_token( $token, 42, 1000000 + Tracking_Manager::SESSION_TTL + 1 ) );
		$this->assertFalse( Tracking_Manager::verify_session_token( $token, 42, 1000000 - 120 ) );
		$this->assertFalse( Tracking_Manager::verify_session_token( substr( $token, 0, -1 ) . ( substr( $token, -1 ) === 'a' ? 'b' : 'a' ), 42, 1000100 ) );
		$this->assertFalse( Tracking_Manager::verify_session_token( str_replace( '42.', '43.', $token ), 43, 1000100 ) );
	}

	public function test_session_token_rejects_malformed_input() {
		foreach ( array( '', 'abc', '42.1000000.xyz.sig', null, array( 'x' ) ) as $bad ) {
			$this->assertFalse( Tracking_Manager::verify_session_token( $bad, 42, 1000100 ) );
		}
	}

	public function test_events_are_not_limited_without_a_persistent_object_cache() {
		$this->assertTrue( Tracking_Manager::allow_event( '0123456789abcdef', 'heatmap', 1 ) );
		$this->assertTrue( Tracking_Manager::allow_event( '0123456789abcdef', 'heatmap', 1 ) );
	}

	public function test_session_token_time_boundaries() {
		$token = Tracking_Manager::create_session_token( 42, 1000000 );

		$this->assertNotFalse( Tracking_Manager::verify_session_token( $token, 42, 1000000 + Tracking_Manager::SESSION_TTL ) );
		$this->assertFalse( Tracking_Manager::verify_session_token( $token, 42, 1000000 + Tracking_Manager::SESSION_TTL + 1 ) );

		$future = Tracking_Manager::create_session_token( 42, 1000060 );
		$this->assertNotFalse( Tracking_Manager::verify_session_token( $future, 42, 1000000 ) );
		$future = Tracking_Manager::create_session_token( 42, 1000061 );
		$this->assertFalse( Tracking_Manager::verify_session_token( $future, 42, 1000000 ) );
	}

	public function test_once_per_session_events_with_an_object_cache() {
		$GLOBALS['lpmanager_pro_test_object_cache'] = true;

		$this->assertTrue( Tracking_Manager::allow_event( 'aaaaaaaaaaaaaaaa', 'heatmap', 1 ) );
		$this->assertFalse( Tracking_Manager::allow_event( 'aaaaaaaaaaaaaaaa', 'heatmap', 1 ) );
		$this->assertTrue( Tracking_Manager::allow_event( 'bbbbbbbbbbbbbbbb', 'heatmap', 1 ) );
	}

	public function test_repeat_events_are_capped_per_session_with_an_object_cache() {
		$GLOBALS['lpmanager_pro_test_object_cache'] = true;
		$limit = Tracking_Manager::MAX_REPEAT_EVENTS_PER_SESSION;

		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertTrue( Tracking_Manager::allow_event( 'aaaaaaaaaaaaaaaa', 'click', $limit ) );
		}
		$this->assertFalse( Tracking_Manager::allow_event( 'aaaaaaaaaaaaaaaa', 'click', $limit ) );
	}

	public function test_long_labels_key_the_same_before_and_after_capping() {
		$raw    = array( 1 => array( 'r' => array( 'count' => 2, 'href' => 'https://example.com/x', 'label' => str_repeat( 'L', 150 ) ) ) );
		$capped = array( 1 => array( 'c' => array( 'count' => 3, 'href' => 'https://example.com/x', 'label' => str_repeat( 'L', Tracking_Manager::MAX_LABEL_LENGTH ) ) ) );

		$merged = Tracking_Manager::clean_link_rows( array( 1 => array_merge( $raw[1], $capped[1] ) ) );

		$this->assertCount( 1, $merged[1] );
		$this->assertSame( 5, current( $merged[1] )['count'] );
		$this->assertSame( array_keys( Tracking_Manager::clean_link_rows( $raw )[1] ), array_keys( Tracking_Manager::clean_link_rows( $capped )[1] ) );
	}
}
