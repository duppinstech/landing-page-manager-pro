<?php
/**
 * Pro Analytics screen helpers that run without WordPress.
 *
 * @package LPManager_Pro
 */

use LPManagerPro\Analytics_Page;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === $number ? $single : $plural;
	}
}

require_once dirname( __DIR__ ) . '/includes/Analytics_Page.php';

class AnalyticsPageTest extends TestCase {

	public function test_format_duration() {
		$this->assertSame( '1 second', Analytics_Page::format_duration( 1 ) );
		$this->assertSame( '45 seconds', Analytics_Page::format_duration( 45 ) );
		$this->assertSame( '2 min 05 s', Analytics_Page::format_duration( 125 ) );
		$this->assertSame( '0 seconds', Analytics_Page::format_duration( -3 ) );
	}

	public function test_page_rows_join_sections_by_page() {
		$data = array(
			'by_page'      => array(
				array( 'id' => 5, 'title' => 'Five', 'views' => 10 ),
				array( 'id' => 6, 'title' => 'Six', 'views' => 0 ),
			),
			'engagement'   => array( array( 'post_id' => 5, 'time_avg' => 30, 'time_count' => 2 ) ),
			'interactions' => array( array( 'post_id' => 5, 'cta_view' => 3 ) ),
			'rage_clicks'  => array( array( 'post_id' => 5, 'count' => 4 ), array( 'post_id' => 99, 'count' => 1 ) ),
			'link_clicks'  => array( array( 'post_id' => 6, 'links' => array( array( 'href' => 'x', 'label' => 'X', 'count' => 1 ) ) ) ),
			'heatmap'      => array( array( 'post_id' => 5, 'grid' => array( 1, 2, 3 ) ) ),
		);

		$rows = Analytics_Page::page_rows( $data );

		$this->assertSame( array( 5, 6 ), array_keys( $rows ) );
		$this->assertSame( 30, $rows[5]['engagement']['time_avg'] );
		$this->assertSame( 3, $rows[5]['interactions']['cta_view'] );
		$this->assertSame( 4, $rows[5]['rage_clicks'] );
		$this->assertSame( 0, $rows[6]['rage_clicks'] );
		$this->assertCount( 1, $rows[6]['links'] );
		$this->assertCount( 100, $rows[5]['grid'] );
		$this->assertSame( array( 1, 2, 3, 0 ), array_slice( $rows[5]['grid'], 0, 4 ) );
		$this->assertSame( array_fill( 0, 100, 0 ), $rows[6]['grid'] );
	}
}
