<?php
/**
 * PHPUnit bootstrap for pure-PHP unit tests. No WordPress runtime.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
