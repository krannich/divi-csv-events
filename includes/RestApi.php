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
				'validate_callback' => __NAMESPACE__ . '\\validate_csv_url',
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
 * Validate that csv_url points to a CSV file in the WordPress Media Library.
 *
 * Prevents arbitrary file disclosure by ensuring the URL resolves to a known
 * attachment with a .csv extension.
 *
 * @since 1.0.0
 *
 * @param string          $value   The csv_url parameter value.
 * @param \WP_REST_Request $request REST request.
 * @param string          $param   Parameter name.
 *
 * @return true|\WP_Error
 */
function validate_csv_url( $value, $request, $param ) {
	if ( empty( $value ) ) {
		return new \WP_Error(
			'rest_invalid_param',
			__( 'The csv_url parameter is required.', 'divi-csv-events' ),
			[ 'status' => 400 ]
		);
	}

	// Must have a .csv extension.
	$path = wp_parse_url( $value, PHP_URL_PATH );
	if ( ! $path || '.csv' !== strtolower( substr( $path, -4 ) ) ) {
		return new \WP_Error(
			'rest_invalid_param',
			__( 'Only .csv files are allowed.', 'divi-csv-events' ),
			[ 'status' => 400 ]
		);
	}

	// Must be a known Media Library attachment.
	$attachment_id = attachment_url_to_postid( $value );
	if ( ! $attachment_id ) {
		return new \WP_Error(
			'rest_invalid_param',
			__( 'The CSV file must be uploaded to the WordPress Media Library.', 'divi-csv-events' ),
			[ 'status' => 400 ]
		);
	}

	return true;
}

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
