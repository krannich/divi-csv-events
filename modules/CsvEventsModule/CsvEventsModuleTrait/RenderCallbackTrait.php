<?php
/**
 * CsvEventsModule::render_callback()
 *
 * @package DiviCsvEvents\CsvEventsModule
 * @since 1.0.0
 */

namespace DiviCsvEvents\CsvEventsModule\CsvEventsModuleTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use DiviCsvEvents\CsvEventsModule\CsvEventsModule;
use DiviCsvEvents\Includes\CsvParser;
use DiviCsvEvents\Includes\SchemaBuilder;

trait RenderCallbackTrait {

	/**
	 * CSV Events module render callback for the Front-End.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $attrs    Block attributes saved by VB.
	 * @param string   $content  Block content.
	 * @param WP_Block $block    Parsed block object.
	 * @param object   $elements ModuleElements instance.
	 *
	 * @return string HTML output.
	 */
	public static function render_callback( $attrs, $content, $block, $elements ) {
		// Enqueue frontend assets only when this module is on the page.
		\dcsve_enqueue_frontend_assets();

		// Get source-mode + data from attributes.
		$source_mode  = $attrs['csvSourceMode']['innerContent']['desktop']['value']['mode'] ?? 'file';
		$csv_url      = $attrs['csvSource']['innerContent']['desktop']['value']['src'] ?? '';
		$csv_content  = $attrs['csvContent']['innerContent']['desktop']['value']['content'] ?? '';
		$settings   = $attrs['eventSettings']['innerContent']['desktop']['value'] ?? [];

		$period       = $settings['period'] ?? 'year';
		$period_count = (int) ( $settings['periodCount'] ?? 1 );
		$view         = $settings['view'] ?? '';

		$count      = (int) ( $settings['count'] ?? 0 );
		$show_past  = self::is_on( $settings['showPast'] ?? 'off' );
		$show_filter      = self::is_on( $settings['showFilter'] ?? 'on' );
		$show_view_switch = self::is_on( $settings['showViewSwitcher'] ?? 'on' );
		$accent_color_raw = $settings['accentColor'] ?? '#2e7d32';
		$accent_color     = preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $accent_color_raw ) ? $accent_color_raw : '#2e7d32';

		$organizer_name = (string) ( $settings['organizerName'] ?? '' );
		$organizer_url  = (string) ( $settings['organizerUrl']  ?? '' );
		$schema_enabled = self::is_on( $settings['schemaEnabled'] ?? 'on' );

		// Heading.
		$heading = $elements->render(
			[
				'attrName' => 'heading',
			]
		);

		// Parse CSV based on source mode.
		$events    = [];
		$csv_error = '';

		if ( 'paste' === $source_mode && ! empty( $csv_content ) ) {
			$result = CsvParser::parseString( $csv_content, $period, $count, $show_past, $period_count );
		} elseif ( 'file' === $source_mode && ! empty( $csv_url ) ) {
			$result = CsvParser::parseUrl( $csv_url, $period, $count, $show_past, $period_count );
		} else {
			$result = [];
		}

		if ( isset( $result['error'] ) ) {
			$csv_error = $result['error'];
		} elseif ( is_array( $result ) ) {
			$events = $result;
		}

		// Build inner HTML.
		$inner_html = '';

		// Controls bar with pre-rendered buttons.
		if ( $show_filter || $show_view_switch ) {
			$controls_html = '';

			// Period filter buttons.
			if ( $show_filter ) {
				$periods = [
					'week'    => __( 'Woche', 'divi-csv-events' ),
					'month'   => __( 'Monat', 'divi-csv-events' ),
					'quarter' => __( 'Quartal', 'divi-csv-events' ),
					'year'    => __( 'Jahr', 'divi-csv-events' ),
					'all'     => __( 'Alle', 'divi-csv-events' ),
				];
				$periods_html = '';
				foreach ( $periods as $key => $label ) {
					$active = ( $key === $period ) ? ' dcsve_csv_events__btn--active' : '';
					$periods_html .= '<button class="dcsve_csv_events__btn' . $active . '" data-period="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
				}
				$controls_html .= '<div class="dcsve_csv_events__periods">' . $periods_html . '</div>';
			}

			// View switcher buttons.
			if ( $show_view_switch ) {
				$view_icons = [
					'list'   => '&#9776;',
					'cards'  => '&#9638;',
					'table'  => '&#9636;',
					'slider' => '&#9654;',
				];
				$view_labels = [
					'list'   => __( 'Liste', 'divi-csv-events' ),
					'cards'  => __( 'Kacheln', 'divi-csv-events' ),
					'table'  => __( 'Tabelle', 'divi-csv-events' ),
					'slider' => __( 'Slider', 'divi-csv-events' ),
				];
				$default_view = $view ?: 'list';
				$views_html   = '';
				foreach ( $view_icons as $vkey => $icon ) {
					$active = ( $default_view === $vkey ) ? ' dcsve_csv_events__btn--active' : '';
					$views_html .= '<button class="dcsve_csv_events__btn dcsve_csv_events__btn-view' . $active . '" data-view="' . esc_attr( $vkey ) . '" title="' . esc_attr( $view_labels[ $vkey ] ) . '">' . $icon . '</button>';
				}
				$controls_html .= '<div class="dcsve_csv_events__views">' . $views_html . '</div>';
			}

			$controls_html = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => 'dcsve_csv_events__controls',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $controls_html,
				]
			);

			$inner_html .= $controls_html;
		}

		// Content area.
		if ( $csv_error ) {
			$content_html = '<div class="dcsve_csv_events__warning"><strong>' . esc_html__( 'CSV structure is invalid.', 'divi-csv-events' ) . '</strong><br>' . esc_html( $csv_error ) . '</div>';
		} elseif ( empty( $events ) ) {
			$source_missing = ( 'paste' === $source_mode ) ? empty( $csv_content ) : empty( $csv_url );
			if ( $source_missing ) {
				$empty_msg = ( 'paste' === $source_mode )
					? __( 'Please paste CSV data.', 'divi-csv-events' )
					: __( 'Please upload a CSV file.', 'divi-csv-events' );
			} else {
				$empty_msg = __( 'No events found for the selected period.', 'divi-csv-events' );
			}
			$content_html = '<div class="dcsve_csv_events__empty">' . esc_html( $empty_msg ) . '</div>';
		} else {
			$content_html = self::render_events_html( $events, $view, $accent_color, $show_view_switch );
		}

		$content_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'dcsve_csv_events__content',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $content_html,
			]
		);

		$inner_html .= $content_container;

		// Events data as JSON for client-side filtering.
		$events_json = wp_json_encode( $events, JSON_HEX_TAG );
		$config_json = wp_json_encode( [
			'sourceMode'       => $source_mode,
			'csvUrl'           => $csv_url,
			'period'           => $period,
			'periodCount'      => $period_count,
			'count'            => $count,
			'showPast'         => $show_past,
			'fixedView'        => $view,
			'showFilter'       => $show_filter,
			'showViewSwitcher' => $show_view_switch,
			'accentColor'      => $accent_color,
		], JSON_HEX_TAG );

		$script_tag = '<script type="application/json" class="dcsve-data">' . $events_json . '</script>';
		$config_tag = '<script type="application/json" class="dcsve-config">' . $config_json . '</script>';

		$schema_tag = '';
		if ( $schema_enabled && ! empty( $events ) ) {
			$schema_json = SchemaBuilder::build_json_ld(
				$events,
				[ 'name' => $organizer_name, 'url' => $organizer_url ],
				wp_timezone()
			);
			if ( '' !== $schema_json ) {
				$schema_tag = '<script type="application/ld+json" class="dcsve-schema">' . $schema_json . '</script>';
			}
		}

		$inner = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'dcsve_csv_events__inner',
					'style' => '--dcsve-accent: ' . esc_attr( $accent_color ) . ';',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $heading . $inner_html . $script_tag . $config_tag . $schema_tag,
			]
		);

		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$parent_attrs = $parent->attrs ?? [];

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => [ CsvEventsModule::class, 'module_classnames' ],
				'stylesComponent'     => [ CsvEventsModule::class, 'module_styles' ],
				'scriptDataComponent' => [ CsvEventsModule::class, 'module_script_data' ],
				'parentAttrs'         => $parent_attrs,
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'children'            => [
					ElementComponents::component(
						[
							'attrs'         => $attrs['module']['decoration'] ?? [],
							'id'            => $block->parsed_block['id'],
							'orderIndex'    => $block->parsed_block['orderIndex'],
							'storeInstance' => $block->parsed_block['storeInstance'],
						]
					),
					$inner,
				],
			]
		);
	}

	/**
	 * Render events HTML for all four views (pre-rendered, JS toggles visibility).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $events           Events array.
	 * @param string $view             Fixed view or empty for all.
	 * @param string $accent_color     Accent color hex.
	 * @param bool   $show_view_switch Whether the view switcher is enabled.
	 *
	 * @return string HTML.
	 */
	private static function render_events_html( $events, $view, $accent_color, $show_view_switch ) {
		$months_de = [
			1  => __( 'Januar', 'divi-csv-events' ),
			2  => __( 'Februar', 'divi-csv-events' ),
			3  => __( 'März', 'divi-csv-events' ),
			4  => __( 'April', 'divi-csv-events' ),
			5  => __( 'Mai', 'divi-csv-events' ),
			6  => __( 'Juni', 'divi-csv-events' ),
			7  => __( 'Juli', 'divi-csv-events' ),
			8  => __( 'August', 'divi-csv-events' ),
			9  => __( 'September', 'divi-csv-events' ),
			10 => __( 'Oktober', 'divi-csv-events' ),
			11 => __( 'November', 'divi-csv-events' ),
			12 => __( 'Dezember', 'divi-csv-events' ),
		];
		$months_short = [
			1  => __( 'Jan', 'divi-csv-events' ),
			2  => __( 'Feb', 'divi-csv-events' ),
			3  => __( 'Mär', 'divi-csv-events' ),
			4  => __( 'Apr', 'divi-csv-events' ),
			5  => __( 'Mai', 'divi-csv-events' ),
			6  => __( 'Jun', 'divi-csv-events' ),
			7  => __( 'Jul', 'divi-csv-events' ),
			8  => __( 'Aug', 'divi-csv-events' ),
			9  => __( 'Sep', 'divi-csv-events' ),
			10 => __( 'Okt', 'divi-csv-events' ),
			11 => __( 'Nov', 'divi-csv-events' ),
			12 => __( 'Dez', 'divi-csv-events' ),
		];
		$wdays = [
			__( 'So', 'divi-csv-events' ),
			__( 'Mo', 'divi-csv-events' ),
			__( 'Di', 'divi-csv-events' ),
			__( 'Mi', 'divi-csv-events' ),
			__( 'Do', 'divi-csv-events' ),
			__( 'Fr', 'divi-csv-events' ),
			__( 'Sa', 'divi-csv-events' ),
		];

		// Group events by month.
		$grouped = [];
		foreach ( $events as $e ) {
			$ts    = strtotime( $e['date'] );
			$month = (int) gmdate( 'n', $ts );
			$year  = gmdate( 'Y', $ts );
			$key   = $months_de[ $month ] . ' ' . $year;
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = [];
			}
			$grouped[ $key ][] = $e;
		}

		// If view switcher is enabled, render all views (JS toggles visibility).
		// If a fixed view is set WITHOUT switcher, render only that view.
		$views_to_render = $show_view_switch ? [ 'list', 'cards', 'table', 'slider' ] : ( $view ? [ $view ] : [ 'list', 'cards', 'table', 'slider' ] );
		$default_view    = $view ?: 'list';
		$html            = '';

		foreach ( $views_to_render as $v ) {
			$display = ( $v === $default_view ) ? '' : ' style="display:none"';
			$html .= '<div class="dcsve_csv_events__view" data-view="' . esc_attr( $v ) . '"' . $display . '>';

			if ( 'list' === $v ) {
				$html .= self::render_list_view( $grouped, $wdays, $months_short );
			} elseif ( 'cards' === $v ) {
				$html .= self::render_cards_view( $grouped, $months_short );
			} elseif ( 'table' === $v ) {
				$html .= self::render_table_view( $events, $months_de, $wdays, $months_short );
			} elseif ( 'slider' === $v ) {
				$html .= self::render_slider_view( $events, $months_short );
			}

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Render list view.
	 */
	private static function render_list_view( $grouped, $wdays, $months_short ) {
		$html = '';
		foreach ( $grouped as $month_label => $events ) {
			$html .= '<div class="dcsve_csv_events__group">';
			$html .= '<div class="dcsve_csv_events__month">' . esc_html( $month_label ) . '</div>';
			foreach ( $events as $e ) {
				$ts   = strtotime( $e['date'] );
				$wday = $wdays[ (int) gmdate( 'w', $ts ) ];
				$day  = gmdate( 'j', $ts );
				$mon  = $months_short[ (int) gmdate( 'n', $ts ) ];

				$html .= '<div class="dcsve_csv_events__list-item" data-date="' . esc_attr( $e['date'] ) . '">';
				$html .= '<div class="dcsve_csv_events__list-date dcsve_csv_events__el-date">' . esc_html( $wday . ', ' . $day . '. ' . $mon . '.' );
				$time_display = self::format_time( $e );
				if ( '' !== $time_display ) {
					$html .= '<strong>' . esc_html( $time_display ) . '</strong>';
				}
				$html .= '</div>';
				$html .= '<div class="dcsve_csv_events__list-body">';
				$html .= '<div class="dcsve_csv_events__list-title dcsve_csv_events__el-title">' . esc_html( $e['title'] ) . '</div>';
				$html .= '<div class="dcsve_csv_events__list-meta dcsve_csv_events__el-meta">' . esc_html( $e['location'] ) . '</div>';
				if ( ! empty( $e['description'] ) ) {
					$html .= '<div class="dcsve_csv_events__list-desc dcsve_csv_events__el-desc">' . esc_html( $e['description'] ) . '</div>';
				}
				$html .= '</div></div>';
			}
			$html .= '</div>';
		}
		return $html;
	}

	/**
	 * Render cards view.
	 */
	private static function render_cards_view( $grouped, $months_short ) {
		$html = '';
		foreach ( $grouped as $month_label => $events ) {
			$html .= '<div class="dcsve_csv_events__group">';
			$html .= '<div class="dcsve_csv_events__month">' . esc_html( $month_label ) . '</div>';
			$html .= '<div class="dcsve_csv_events__cards-grid">';
			foreach ( $events as $e ) {
				$ts  = strtotime( $e['date'] );
				$day = gmdate( 'j', $ts );
				$mon = $months_short[ (int) gmdate( 'n', $ts ) ];

				$html .= '<div class="dcsve_csv_events__card" data-date="' . esc_attr( $e['date'] ) . '">';
				$html .= '<div class="dcsve_csv_events__card-date dcsve_csv_events__el-date">';
				$html .= '<span class="dcsve_csv_events__card-day">' . esc_html( $day ) . '</span>';
				$html .= '<span class="dcsve_csv_events__card-mon">' . esc_html( $mon ) . '</span>';
				$html .= '</div>';
				$html .= '<div class="dcsve_csv_events__card-body">';
				$html .= '<div class="dcsve_csv_events__card-title dcsve_csv_events__el-title">' . esc_html( $e['title'] ) . '</div>';
				$html .= '<div class="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">';
				$time_display = self::format_time( $e );
				if ( '' !== $time_display ) {
					$html .= esc_html( $time_display ) . ' &middot; ';
				}
				$html .= esc_html( $e['location'] );
				$html .= '</div>';
				if ( ! empty( $e['description'] ) ) {
					$html .= '<div class="dcsve_csv_events__card-desc dcsve_csv_events__el-desc">' . esc_html( $e['description'] ) . '</div>';
				}
				$html .= '</div></div>';
			}
			$html .= '</div></div>';
		}
		return $html;
	}

	/**
	 * Render table view.
	 */
	private static function render_table_view( $events, $months_de, $wdays, $months_short ) {
		$html  = '<div class="dcsve_csv_events__table-wrap">';
		$html .= '<table class="dcsve_csv_events__table">';
		$html .= '<thead><tr>';
		$html .= '<th>' . esc_html__( 'Datum', 'divi-csv-events' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Uhrzeit', 'divi-csv-events' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Veranstaltung', 'divi-csv-events' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Ort', 'divi-csv-events' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Details', 'divi-csv-events' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$last_month = '';
		foreach ( $events as $e ) {
			$ts    = strtotime( $e['date'] );
			$month = (int) gmdate( 'n', $ts );
			$year  = gmdate( 'Y', $ts );
			$m     = $months_de[ $month ] . ' ' . $year;

			if ( $m !== $last_month ) {
				$html .= '<tr class="dcsve_csv_events__table-month"><td colspan="5">' . esc_html( $m ) . '</td></tr>';
				$last_month = $m;
			}

			$wday = $wdays[ (int) gmdate( 'w', $ts ) ];
			$day  = gmdate( 'j', $ts );
			$mon  = $months_short[ (int) gmdate( 'n', $ts ) ];

			$html .= '<tr data-date="' . esc_attr( $e['date'] ) . '">';
			$html .= '<td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' . esc_html( $wday . ', ' . $day . '. ' . $mon . '.' ) . '</td>';
			$time_display = self::format_time( $e );
			$time_display = preg_replace( '/\s*Uhr$/u', '', $time_display ); // column labeled "Uhrzeit" — Uhr is redundant
			$html .= '<td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' . esc_html( $time_display ) . '</td>';
			$html .= '<td class="dcsve_csv_events__table-title dcsve_csv_events__el-title">' . esc_html( $e['title'] ) . '</td>';
			$html .= '<td class="dcsve_csv_events__el-meta">' . esc_html( $e['location'] ) . '</td>';
			$html .= '<td class="dcsve_csv_events__table-desc dcsve_csv_events__el-desc">' . esc_html( $e['description'] ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Check if a toggle value is "on".
	 * Handles both 'on'/'off' strings and true/false booleans from Divi toggle.
	 */
	private static function is_on( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return 'on' === $value || 'true' === $value || true === $value;
	}

	/**
	 * Format event time for display.
	 *
	 * "17:00" + "03:00" → "17:00–03:00 Uhr" (en-dash)
	 * "17:00" alone    → "17:00 Uhr"
	 * ""               → ""
	 */
	private static function format_time( array $event ): string {
		$start = (string) ( $event['start_time'] ?? '' );
		$end   = (string) ( $event['end_time']   ?? '' );
		if ( '' !== $start && '' !== $end ) {
			return $start . "\u{2013}" . $end . ' Uhr';
		}
		if ( '' !== $start ) {
			return $start . ' Uhr';
		}
		$time = (string) ( $event['time'] ?? '' );
		if ( '' !== $time ) {
			return $time . ' Uhr';
		}
		return '';
	}

	/**
	 * Render slider view.
	 */
	private static function render_slider_view( $events, $months_short ) {
		$slider_id = 'dcsve-slider-' . wp_unique_id();

		$html  = '<div class="dcsve_csv_events__slider-wrap">';
		$html .= '<div class="dcsve_csv_events__slider-track" id="' . esc_attr( $slider_id ) . '">';

		foreach ( $events as $e ) {
			$ts  = strtotime( $e['date'] );
			$day = gmdate( 'j', $ts );
			$mon = $months_short[ (int) gmdate( 'n', $ts ) ];

			$html .= '<div class="dcsve_csv_events__slider-card" data-date="' . esc_attr( $e['date'] ) . '">';
			$html .= '<div class="dcsve_csv_events__slider-top">';
			$html .= '<div class="dcsve_csv_events__slider-badge dcsve_csv_events__el-date">';
			$html .= '<div class="dcsve_csv_events__slider-badge-day">' . esc_html( $day ) . '</div>';
			$html .= '<div class="dcsve_csv_events__slider-badge-mon">' . esc_html( $mon ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="dcsve_csv_events__slider-title dcsve_csv_events__el-title">' . esc_html( $e['title'] ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">';
			$time_display = self::format_time( $e );
			if ( '' !== $time_display ) {
				$html .= esc_html( $time_display ) . ' &middot; ';
			}
			$html .= esc_html( $e['location'] );
			$html .= '</div>';
			if ( ! empty( $e['description'] ) ) {
				$html .= '<div class="dcsve_csv_events__slider-desc dcsve_csv_events__el-desc">' . esc_html( $e['description'] ) . '</div>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-prev" data-slider="' . esc_attr( $slider_id ) . '" aria-label="' . esc_attr__( 'Previous', 'divi-csv-events' ) . '">&lsaquo;</button>';
		$html .= '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-next" data-slider="' . esc_attr( $slider_id ) . '" aria-label="' . esc_attr__( 'Next', 'divi-csv-events' ) . '">&rsaquo;</button>';
		$html .= '</div>';

		return $html;
	}
}
