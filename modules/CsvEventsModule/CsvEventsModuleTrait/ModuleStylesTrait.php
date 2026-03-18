<?php
/**
 * CsvEventsModule::module_styles().
 *
 * @package DiviCsvEvents\CsvEventsModule
 * @since 1.0.0
 */

namespace DiviCsvEvents\CsvEventsModule\CsvEventsModuleTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use DiviCsvEvents\CsvEventsModule\CsvEventsModule;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * CSV Events Module style components.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Style arguments.
	 */
	public static function module_styles( $args ) {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),

					// Heading.
					$elements->style(
						[
							'attrName' => 'heading',
						]
					),

					// Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => CsvEventsModule::custom_css(),
						]
					),
				],
			]
		);
	}
}
