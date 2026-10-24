<?php
/*
Plugin Name: Divi CSV Events
Plugin URI:  https://github.com/krannich/divi-csv-events
Description: Display events from a CSV file in four views (list, cards, table, slider) with period filters. Built for Divi 5.
Version:     1.7.5
Author:      DiviSimpleEventList
Author URI:
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: divi-csv-events
Domain Path: /languages
Update URI:  https://github.com/krannich/divi-csv-events

Divi CSV Events is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Divi CSV Events is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Divi CSV Events. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

define( 'DCSVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCSVE_URL', plugin_dir_url( __FILE__ ) );
define( 'DCSVE_JSON_PATH', DCSVE_PATH . 'modules-json/' );
define( 'DCSVE_VERSION', '1.7.5' );

/**
 * Requires Autoloader.
 */
if ( ! file_exists( DCSVE_PATH . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Divi CSV Events:</strong> ' . esc_html__( 'Please run "composer install" in the plugin directory.', 'divi-csv-events' ) . '</p></div>';
	} );
	return;
}
require DCSVE_PATH . 'vendor/autoload.php';
require DCSVE_PATH . 'modules/Modules.php';

add_action( 'init', function () {
	load_plugin_textdomain( 'divi-csv-events', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Self-hosted update checker — queries GitHub Releases for new versions.
 */
if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
	$dcsve_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/krannich/divi-csv-events/',
		__FILE__,
		'divi-csv-events'
	);
	$dcsve_update_checker->setBranch( 'main' );
	$dcsve_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * Register REST API endpoint.
 */
require DCSVE_PATH . 'includes/RestApi.php';

/**
 * Enqueue Visual Builder scripts and styles.
 *
 * @since 1.0.0
 */
function dcsve_enqueue_vb_scripts() {
	if ( et_builder_d5_enabled() && et_core_is_fb_enabled() ) {
		$plugin_dir_url = plugin_dir_url( __FILE__ );

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			[
				'name'    => 'divi-csv-events-builder-bundle-script',
				'version' => DCSVE_VERSION,
				'script'  => [
					'src'                => "{$plugin_dir_url}scripts/bundle.js",
					'deps'               => [
						'divi-module-library',
						'divi-vendor-wp-hooks',
					],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				],
			]
		);

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			[
				'name'    => 'divi-csv-events-builder-bundle-style',
				'version' => DCSVE_VERSION,
				'style'   => [
					'src'                => "{$plugin_dir_url}styles/bundle.css",
					'deps'               => [],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				],
			]
		);
	}
}
add_action( 'divi_visual_builder_assets_before_enqueue_scripts', 'dcsve_enqueue_vb_scripts' );

/**
 * Register frontend assets (enqueued on demand from render_callback).
 *
 * @since 1.0.0
 */
function dcsve_register_frontend_assets() {
	$plugin_dir_url = plugin_dir_url( __FILE__ );
	wp_register_style( 'divi-csv-events-bundle-style', "{$plugin_dir_url}styles/bundle.css", array(), DCSVE_VERSION );
	wp_register_script( 'divi-csv-events-frontend', "{$plugin_dir_url}assets/js/frontend.js", array(), DCSVE_VERSION, true );
	wp_add_inline_script(
		'divi-csv-events-frontend',
		'var dcsveRestUrl = ' . wp_json_encode( esc_url_raw( rest_url() ) ) . ';'
		. 'var dcsveI18n = ' . wp_json_encode( dcsve_frontend_i18n() ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'dcsve_register_frontend_assets' );

/**
 * Strings the frontend script needs after a client-side re-render.
 * Must use the same source strings as RenderCallbackTrait so one .po covers both.
 */
function dcsve_frontend_i18n() {
	return [
		'moreInfo'    => __( 'Weitere Informationen', 'divi-csv-events' ),
		/* translators: %s is a time or time range, e.g. "17:00" or "17:00–20:00" */
		'time'        => __( '%s Uhr', 'divi-csv-events' ),
		'noEvents'    => __( 'No events found for the selected period.', 'divi-csv-events' ),
		'prev'        => __( 'Previous', 'divi-csv-events' ),
		'next'        => __( 'Next', 'divi-csv-events' ),
		'colDate'     => __( 'Datum', 'divi-csv-events' ),
		'colTime'     => __( 'Uhrzeit', 'divi-csv-events' ),
		'colTitle'    => __( 'Veranstaltung', 'divi-csv-events' ),
		'colLocation' => __( 'Ort', 'divi-csv-events' ),
		'colDetails'  => __( 'Details', 'divi-csv-events' ),
		'print'       => __( 'Drucken', 'divi-csv-events' ),
		/* translators: %s is a date */
		'printedOn'   => __( 'Stand: %s', 'divi-csv-events' ),
		'periods'     => [
			'week'    => __( 'Woche', 'divi-csv-events' ),
			'month'   => __( 'Monat', 'divi-csv-events' ),
			'quarter' => __( 'Quartal', 'divi-csv-events' ),
			'year'    => __( 'Jahr', 'divi-csv-events' ),
			'all'     => __( 'Alle', 'divi-csv-events' ),
		],
		'months'      => [
			__( 'Januar', 'divi-csv-events' ), __( 'Februar', 'divi-csv-events' ), __( 'März', 'divi-csv-events' ),
			__( 'April', 'divi-csv-events' ), __( 'Mai', 'divi-csv-events' ), __( 'Juni', 'divi-csv-events' ),
			__( 'Juli', 'divi-csv-events' ), __( 'August', 'divi-csv-events' ), __( 'September', 'divi-csv-events' ),
			__( 'Oktober', 'divi-csv-events' ), __( 'November', 'divi-csv-events' ), __( 'Dezember', 'divi-csv-events' ),
		],
		'monthsShort' => [
			__( 'Jan', 'divi-csv-events' ), __( 'Feb', 'divi-csv-events' ), __( 'Mär', 'divi-csv-events' ),
			__( 'Apr', 'divi-csv-events' ), __( 'Mai', 'divi-csv-events' ), __( 'Jun', 'divi-csv-events' ),
			__( 'Jul', 'divi-csv-events' ), __( 'Aug', 'divi-csv-events' ), __( 'Sep', 'divi-csv-events' ),
			__( 'Okt', 'divi-csv-events' ), __( 'Nov', 'divi-csv-events' ), __( 'Dez', 'divi-csv-events' ),
		],
		'wdays'       => [
			__( 'So', 'divi-csv-events' ), __( 'Mo', 'divi-csv-events' ), __( 'Di', 'divi-csv-events' ),
			__( 'Mi', 'divi-csv-events' ), __( 'Do', 'divi-csv-events' ), __( 'Fr', 'divi-csv-events' ),
			__( 'Sa', 'divi-csv-events' ),
		],
	];
}

/**
 * Enqueue frontend assets. Called from the module's render_callback.
 *
 * @since 1.0.0
 */
function dcsve_enqueue_frontend_assets() {
	wp_enqueue_style( 'divi-csv-events-bundle-style' );
	wp_enqueue_script( 'divi-csv-events-frontend' );
}


/**
 * Allow CSV uploads in WordPress Media Library.
 *
 * @since 1.0.0
 *
 * @param array $mimes Allowed MIME types.
 * @return array
 */
function dcsve_allow_csv_upload( $mimes ) {
	$mimes['csv'] = 'text/csv';
	return $mimes;
}
add_filter( 'upload_mimes', 'dcsve_allow_csv_upload' );
