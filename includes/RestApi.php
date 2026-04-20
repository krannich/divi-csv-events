<?php
/**
 * REST API endpoints for CSV Events.
 *
 * GET  /divi-csv-events/v1/events?csv_url=...  (public; attachment-only)
 * POST /divi-csv-events/v1/events              (authenticated; csv_content body)
 *
 * Used by the Visual Builder for live preview.
 *
 * @package DiviCsvEvents\Includes
 * @since 1.0.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access forbidden.' );
}

const MAX_CSV_CONTENT_BYTES = 102400; // 100 KB — must match src/components/csv-events-module/csv-content-editor/constants.ts

add_action( 'rest_api_init', function () {
    // GET route: file-mode (existing behavior).
    register_rest_route( 'divi-csv-events/v1', '/events', [
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_get_events_by_url',
        'permission_callback' => '__return_true',
        'args'                => shared_filter_args() + [
            'csv_url' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => __NAMESPACE__ . '\\validate_csv_url',
            ],
        ],
    ] );

    // POST route: paste-mode (new).
    register_rest_route( 'divi-csv-events/v1', '/events', [
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_get_events_by_content',
        'permission_callback' => static function () {
            return current_user_can( 'edit_posts' );
        },
        'args'                => shared_filter_args() + [
            'csv_content' => [
                'required'          => true,
                'type'              => 'string',
                // No sanitize_textarea_field — it mangles CSV semicolons/newlines.
                'validate_callback' => __NAMESPACE__ . '\\validate_csv_content',
            ],
        ],
    ] );
} );

/**
 * Shared filter-related args used by both routes.
 */
function shared_filter_args() {
    return [
        'period' => [
            'default'           => 'year',
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static function ( $value ) {
                $allowed = [ 'week', 'month', 'quarter', 'year', 'all' ];
                if ( ! in_array( $value, $allowed, true ) ) {
                    return new \WP_Error(
                        'rest_invalid_param',
                        sprintf( __( 'Invalid period. Allowed values: %s', 'divi-csv-events' ), implode( ', ', $allowed ) ),
                        [ 'status' => 400 ]
                    );
                }
                return true;
            },
        ],
        'count' => [
            'default'           => 0,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
        'show_past' => [
            'default' => false,
            'type'    => 'boolean',
        ],
        'period_count' => [
            'default'           => 1,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
    ];
}

function validate_csv_url( $value, $request, $param ) {
    if ( empty( $value ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'The csv_url parameter is required.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    $path = wp_parse_url( $value, PHP_URL_PATH );
    if ( ! $path || '.csv' !== strtolower( substr( $path, -4 ) ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'Only .csv files are allowed.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

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

function validate_csv_content( $value, $request, $param ) {
    if ( ! is_string( $value ) || '' === trim( $value ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'The csv_content parameter is required.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    if ( strlen( $value ) > MAX_CSV_CONTENT_BYTES ) {
        return new \WP_Error(
            'rest_payload_too_large',
            sprintf(
                /* translators: %1$d current size KB, %2$d max size KB */
                __( 'CSV content is too large (%1$d KB / max %2$d KB). Please use the File upload mode.', 'divi-csv-events' ),
                (int) ceil( strlen( $value ) / 1024 ),
                (int) ( MAX_CSV_CONTENT_BYTES / 1024 )
            ),
            [ 'status' => 413 ]
        );
    }

    if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'CSV content must be valid UTF-8.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    return true;
}

function rest_get_events_by_url( $request ) {
    $csv_url      = $request->get_param( 'csv_url' );
    $period       = $request->get_param( 'period' );
    $count        = (int) $request->get_param( 'count' );
    $show_past    = (bool) $request->get_param( 'show_past' );
    $period_count = (int) $request->get_param( 'period_count' );

    $result = CsvParser::parseUrl( $csv_url, $period, $count, $show_past, $period_count );

    if ( isset( $result['error'] ) ) {
        return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return rest_ensure_response( $result );
}

function rest_get_events_by_content( $request ) {
    $csv_content  = (string) $request->get_param( 'csv_content' );
    $period       = $request->get_param( 'period' );
    $count        = (int) $request->get_param( 'count' );
    $show_past    = (bool) $request->get_param( 'show_past' );
    $period_count = (int) $request->get_param( 'period_count' );

    $result = CsvParser::parseString( $csv_content, $period, $count, $show_past, $period_count );

    if ( isset( $result['error'] ) ) {
        return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return rest_ensure_response( $result );
}
