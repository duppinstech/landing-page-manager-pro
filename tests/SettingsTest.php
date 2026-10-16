<?php
/**
 * Pro Settings: bot detection, privacy signals, stored-option handling, and consent-plugin detection.
 *
 * @package LPManager_Pro
 */

use LPManagerPro\Settings;
use PHPUnit\Framework\TestCase;

// Doubles not provided by the updater bootstrap. Fake data only.
$GLOBALS['lpmanager_pro_test_options']   = array();
$GLOBALS['lpmanager_pro_test_multisite'] = false;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		return array_key_exists( $name, $GLOBALS['lpmanager_pro_test_options'] ) ? $GLOBALS['lpmanager_pro_test_options'][ $name ] : $default_value;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return $GLOBALS['lpmanager_pro_test_multisite'];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_sprintf_l' ) ) {
	function wp_sprintf_l( $pattern, $args ) {
		return implode( ', ', (array) $args );
	}
}

require_once dirname( __DIR__ ) . '/includes/Settings.php';

class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['lpmanager_pro_test_options']      = array();
		$GLOBALS['lpmanager_pro_test_site_options'] = array();
		$GLOBALS['lpmanager_pro_test_multisite']    = false;
		unset( $_SERVER['HTTP_SEC_GPC'], $_SERVER['HTTP_DNT'] );
	}

	/**
	 * @dataProvider browser_user_agents
	 */
	public function test_real_browsers_are_not_bots( $user_agent ) {
		$this->assertFalse( Settings::is_bot_user_agent( $user_agent ) );
	}

	public function browser_user_agents() {
		return array(
			'chrome'      => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' ),
			'safari ios'  => array( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1' ),
			'firefox'     => array( 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0' ),
			'cubot phone' => array( 'Mozilla/5.0 (Linux; Android 10; CUBOT X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36' ),
		);
	}

	/**
	 * @dataProvider bot_user_agents
	 */
	public function test_bots_and_missing_user_agents_are_bots( $user_agent ) {
		$this->assertTrue( Settings::is_bot_user_agent( $user_agent ) );
	}

	public function bot_user_agents() {
		return array(
			'googlebot'      => array( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ),
			'headless'       => array( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/129.0.0.0 Safari/537.36' ),
			'curl'           => array( 'curl/8.4.0' ),
			'facebook'       => array( 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' ),
			'slackbot'       => array( 'Slackbot 1.0 (+https://api.slack.com/robots)' ),
			'empty'          => array( '' ),
			'whitespace'     => array( '   ' ),
		);
	}

	public function test_privacy_signal_headers() {
		$this->assertFalse( Settings::has_privacy_signal() );

		$_SERVER['HTTP_SEC_GPC'] = '1';
		$this->assertTrue( Settings::has_privacy_signal() );

		$_SERVER['HTTP_SEC_GPC'] = '0';
		$this->assertFalse( Settings::has_privacy_signal() );

		$_SERVER['HTTP_DNT'] = '1';
		$this->assertTrue( Settings::has_privacy_signal() );
	}

	public function test_get_returns_defaults_when_nothing_is_stored() {
		$this->assertSame( Settings::DEFAULTS, Settings::get() );

		$GLOBALS['lpmanager_pro_test_options'][ Settings::OPTION ] = 'not-an-array';
		$this->assertSame( Settings::DEFAULTS, Settings::get() );
	}

	public function test_get_fills_missing_keys_from_defaults() {
		$GLOBALS['lpmanager_pro_test_options'][ Settings::OPTION ] = array( 'exclude_bots' => false );

		$settings = Settings::get();

		$this->assertFalse( $settings['exclude_bots'] );
		$this->assertTrue( $settings['exclude_editors'] );
		$this->assertFalse( $settings['require_consent'] );
	}

	public function test_sanitize_sets_every_key_and_drops_unknown_ones() {
		$clean = Settings::sanitize( array( 'exclude_editors' => '1', 'unknown' => '1' ) );

		$this->assertSame( array_keys( Settings::DEFAULTS ), array_keys( $clean ) );
		$this->assertTrue( $clean['exclude_editors'] );
		$this->assertFalse( $clean['exclude_bots'] );

		$this->assertSame( array_fill_keys( array_keys( Settings::DEFAULTS ), false ), Settings::sanitize( 'not-an-array' ) );
	}

	public function test_detects_active_consent_plugins() {
		$this->assertSame( array(), Settings::detect_consent_plugins() );

		$GLOBALS['lpmanager_pro_test_options']['active_plugins'] = array(
			'akismet/akismet.php',
			'complianz-gdpr/complianz-gpdr.php',
			'complianz-gdpr-premium/complianz-gpdr-premium.php',
		);
		$this->assertSame( array( 'Complianz' ), Settings::detect_consent_plugins() );
	}

	public function test_detects_network_active_consent_plugins() {
		$GLOBALS['lpmanager_pro_test_multisite']                                = true;
		$GLOBALS['lpmanager_pro_test_site_options']['active_sitewide_plugins'] = array( 'cookiebot/cookiebot.php' => 1700000000 );

		$this->assertSame( array( 'Cookiebot' ), Settings::detect_consent_plugins() );
	}

	public function test_no_consent_notice_when_consent_is_off_and_no_plugin_is_active() {
		$this->assertNull( Settings::get_consent_status( false ) );
	}

	public function test_warns_when_a_consent_plugin_is_active_but_consent_is_not_required() {
		$GLOBALS['lpmanager_pro_test_options']['active_plugins'] = array( 'cookie-law-info/cookie-law-info.php' );

		$status = Settings::get_consent_status( false );

		$this->assertSame( 'notice-warning', $status['class'] );
		$this->assertStringContainsString( 'CookieYes', $status['message'] );
	}

	public function test_consent_required_without_the_consent_api_warns_that_nothing_is_tracked() {
		// wp_has_consent() is not defined in this test run, so the WP Consent API counts as inactive.
		$status = Settings::get_consent_status( true );

		$this->assertSame( 'notice-warning', $status['class'] );
		$this->assertStringContainsString( 'Nothing is tracked', $status['message'] );
	}
}
