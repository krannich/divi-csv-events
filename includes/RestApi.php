<?php
/**
 * REST API Endpoint for CSV Events.
 *
 * Used by the Visual Builder for live preview AND by the frontend for period filter AJAX.
 *
 * @package DiviCsvEvents\Includes
 * @since 1.0.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'divi-csv-events/v1', '/events', [
		'methods'             => 'GET',
		'callback'            => __NAMESPACE__ . '\\rest_get_events',
		'permission_callback' => '__return_true',
		'args'                => [
			'csv_url'   => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			],
			'period'    => [
				'default'           => 'year',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'count'     => [
				'default'           => 0,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'show_past' => [
				'default'           => '0',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'period_count' => [
				'default'           => 1,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		],
	] );
} );

/**
 * REST API callback to return parsed CSV events as JSON.
 *
 * @since 1.0.0
 *
 * @param \WP_REST_Request $request REST request.
 *
 * @return \WP_REST_Response
 */
function rest_get_events( $request ) {
	$csv_url       = $request->get_param( 'csv_url' );
	$period        = $request->get_param( 'period' );
	$count         = (int) $request->get_param( 'count' );
	$show_past_raw = $request->get_param( 'show_past' );
	$show_past     = in_array( $show_past_raw, [ '1', 'true', 'on' ], true );

	$period_count = (int) $request->get_param( 'period_count' );
	$result       = CsvParser::parse( $csv_url, $period, $count, $show_past, $period_count );

	// If the parser returned a validation error, return it with a 400 status.
	if ( isset( $result['error'] ) ) {
		return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
	}

	return rest_ensure_response( $result );
}
