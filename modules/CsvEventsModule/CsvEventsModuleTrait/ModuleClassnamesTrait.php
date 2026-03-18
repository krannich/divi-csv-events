<?php
/**
 * CsvEventsModule::module_classnames().
 *
 * @package DiviCsvEvents\CsvEventsModule
 * @since 1.0.0
 */

namespace DiviCsvEvents\CsvEventsModule\CsvEventsModuleTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	/**
	 * Module classnames function for CSV Events module.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments.
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] );

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}
	}
}
