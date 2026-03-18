<?php
/**
 * Module: CSV Events Module class.
 *
 * @package DiviCsvEvents\CsvEventsModule
 * @since 1.0.0
 */

namespace DiviCsvEvents\CsvEventsModule;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * CsvEventsModule handles Front-End rendering and module registration.
 *
 * @since 1.0.0
 */
class CsvEventsModule implements DependencyInterface {
	use CsvEventsModuleTrait\RenderCallbackTrait;
	use CsvEventsModuleTrait\ModuleClassnamesTrait;
	use CsvEventsModuleTrait\ModuleStylesTrait;
	use CsvEventsModuleTrait\ModuleScriptDataTrait;

	/**
	 * Loads CsvEventsModule and registers Front-End render callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = DCSVE_JSON_PATH . 'csv-events-module/';

		add_action(
			'init',
			function() use ( $module_json_folder_path ) {
				ModuleRegistration::register_module(
					$module_json_folder_path,
					[
						'render_callback' => [ CsvEventsModule::class, 'render_callback' ],
					]
				);
			}
		);
	}
}
