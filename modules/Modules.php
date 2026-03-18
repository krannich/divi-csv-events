<?php
/**
 * Register all modules with dependency tree.
 *
 * @package DiviCsvEvents
 * @since 1.0.0
 */

namespace DiviCsvEvents;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use DiviCsvEvents\CsvEventsModule\CsvEventsModule;

add_action(
	'divi_module_library_modules_dependency_tree',
	function ( $dependency_tree ) {
		$dependency_tree->add_dependency( new CsvEventsModule() );
	}
);
