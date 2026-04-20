<?php
/*
Plugin Name: Divi CSV Events
Plugin URI:  https://github.com/krannich/divi-csv-events
Description: Display events from a CSV file in four views (list, cards, table, slider) with period filters. Built for Divi 5.
Version:     1.2.0
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
define( 'DCSVE_VERSION', '1.2.0' );

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
	wp_add_inline_script( 'divi-csv-events-frontend', 'var dcsveRestUrl = ' . wp_json_encode( esc_url_raw( rest_url() ) ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'dcsve_register_frontend_assets' );

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
