<?php
/**
 * CsvEventsModule::custom_css().
 *
 * @package DiviCsvEvents\CsvEventsModule
 * @since 1.0.0
 */

namespace DiviCsvEvents\CsvEventsModule\CsvEventsModuleTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait CustomCssTrait {

	/**
	 * Custom CSS fields.
	 *
	 * @since 1.0.0
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'dcsve/csv-events' )->customCssFields;
	}
}
