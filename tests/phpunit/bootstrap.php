<?php
/**
 * PHPUnit bootstrap for pure-PHP unit tests. No WordPress runtime,
 * but a couple of WP helpers are stubbed so pure utility classes
 * using them remain testable in isolation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string|false {
        return json_encode( $data, $options, $depth );
    }
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
