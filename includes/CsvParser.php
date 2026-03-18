<?php
/**
 * CSV Parser - reads, parses and filters events from a CSV file.
 *
 * Shared between REST API endpoint and PHP frontend rendering.
 *
 * @package DiviCsvEvents\Includes
 * @since 1.0.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

class CsvParser {

	/**
	 * Parse a CSV file and return filtered events.
	 *
	 * @since 1.0.0
	 *
	 * @param string $csv_url   URL of the CSV file (Media Library or external).
	 * @param string $period    Filter period: week, month, quarter, year, all.
	 * @param int    $count     Max events to return (0 = all).
	 * @param bool   $show_past Whether to include past events.
	 *
	 * @return array Array of event arrays.
	 */
	/**
	 * Expected CSV header columns (case-insensitive).
	 */
	private static $expected_headers = [ 'date', 'time', 'title', 'location', 'description' ];

	public static function parse( $csv_url, $period = 'year', $count = 0, $show_past = false, $period_count = 1 ) {
		$result = self::load_csv( $csv_url );

		// If load_csv returned an error, pass it through.
		if ( isset( $result['error'] ) ) {
			return $result;
		}

		$events = $result;

		if ( empty( $events ) ) {
			return [];
		}

		$events = self::filter_events( $events, $period, $show_past, $period_count );

		if ( $count > 0 ) {
			$events = array_slice( $events, 0, $count );
		}

		return $events;
	}

	/**
	 * Validate CSV header row.
	 *
	 * @param array $header The header row from fgetcsv.
	 * @return string Error message or empty string if valid.
	 */
	private static function validate_header( $header ) {
		if ( ! $header || count( $header ) < 3 ) {
			return 'Invalid CSV file: header row must have at least 3 columns.';
		}

		$normalized = array_map( function ( $col ) {
			return strtolower( trim( $col ) );
		}, $header );

		$expected = self::$expected_headers;
		$missing  = [];

		// Check first 3 required columns: Date, Time, Title.
		for ( $i = 0; $i < 3; $i++ ) {
			if ( ! isset( $normalized[ $i ] ) || $normalized[ $i ] !== $expected[ $i ] ) {
				$missing[] = ucfirst( $expected[ $i ] );
			}
		}

		if ( ! empty( $missing ) ) {
			$found = implode( ';', array_map( 'trim', $header ) );
			return sprintf(
				'Invalid CSV header. Expected: Date;Time;Title;Location;Description — Found: %s',
				$found
			);
		}

		return '';
	}

	/**
	 * Load and parse a CSV file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $csv_url URL of the CSV file.
	 *
	 * @return array Raw events array, sorted chronologically.
	 */
	private static function load_csv( $csv_url ) {
		if ( empty( $csv_url ) ) {
			return [];
		}

		// Try to resolve to a local file path for Media Library uploads.
		$csv_path = self::resolve_local_path( $csv_url );

		if ( ! $csv_path || ! file_exists( $csv_path ) ) {
			return [];
		}

		$events = [];
		$handle = fopen( $csv_path, 'r' );

		if ( ! $handle ) {
			return [];
		}

		// Remove BOM if present.
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		// Read and validate header row.
		$header = fgetcsv( $handle, 0, ';' );
		if ( ! $header ) {
			fclose( $handle );
			return [];
		}

		$header_error = self::validate_header( $header );
		if ( $header_error ) {
			fclose( $handle );
			return [ 'error' => $header_error ];
		}

		while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			if ( count( $row ) < 3 ) {
				continue;
			}

			$date        = trim( $row[0] ?? '' );
			$time        = trim( $row[1] ?? '' );
			$title       = trim( $row[2] ?? '' );
			$location    = trim( $row[3] ?? '' );
			$description = trim( $row[4] ?? '' );

			if ( empty( $date ) || empty( $title ) ) {
				continue;
			}

			$events[] = [
				'date'        => $date,
				'time'        => $time,
				'title'       => $title,
				'location'    => $location,
				'description' => $description,
			];
		}

		fclose( $handle );

		// Sort chronologically.
		usort( $events, function ( $a, $b ) {
			return strcmp( $a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time'] );
		} );

		return $events;
	}

	/**
	 * Resolve a URL to a local file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url File URL.
	 *
	 * @return string|false Local file path or false.
	 */
	private static function resolve_local_path( $url ) {
		// Try to get attachment ID from URL.
		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			if ( $path && file_exists( $path ) ) {
				return $path;
			}
		}

		// Fallback: convert upload URL to path.
		$upload_dir  = wp_upload_dir();
		$upload_url  = $upload_dir['baseurl'];
		$upload_path = $upload_dir['basedir'];

		if ( str_starts_with( $url, $upload_url ) ) {
			$relative = substr( $url, strlen( $upload_url ) );
			$path     = $upload_path . $relative;
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Filter events by period and past-event setting.
	 *
	 * Uses date strings (YYYY-MM-DD) for comparison to avoid timezone issues.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $events   Events array.
	 * @param string $period   Filter period.
	 * @param bool   $show_past Whether to include past events.
	 *
	 * @return array Filtered events.
	 */
	private static function filter_events( $events, $period, $show_past, $period_count = 1 ) {
		$today = wp_date( 'Y-m-d' );

		// Filter out past events (before today).
		if ( ! $show_past ) {
			$events = array_filter( $events, function ( $e ) use ( $today ) {
				return $e['date'] >= $today;
			} );
			$events = array_values( $events );
		}

		if ( 'all' === $period ) {
			return $events;
		}

		// Calendar period boundaries with period_count support.
		$year         = (int) wp_date( 'Y' );
		$month        = (int) wp_date( 'n' );
		$period_count = max( 1, (int) $period_count );

		switch ( $period ) {
			case 'week':
				// Current week + (period_count - 1) additional weeks.
				$day_of_week = (int) wp_date( 'N' ); // 1=Mon, 7=Sun
				$monday     = wp_date( 'Y-m-d', strtotime( '-' . ( $day_of_week - 1 ) . ' days' ) );
				$end_offset = ( $period_count * 7 ) - 1;
				$end_date   = wp_date( 'Y-m-d', strtotime( $monday . ' +' . $end_offset . ' days' ) );
				$start_date = $monday;
				break;

			case 'month':
				// Current month + (period_count - 1) additional months.
				$start_date = sprintf( '%04d-%02d-01', $year, $month );
				$end_month  = $month + $period_count - 1;
				$end_year   = $year;
				if ( $end_month > 12 ) {
					$end_year  += (int) floor( ( $end_month - 1 ) / 12 );
					$end_month  = ( ( $end_month - 1 ) % 12 ) + 1;
				}
				$end_date = wp_date( 'Y-m-t', mktime( 0, 0, 0, $end_month, 1, $end_year ) );
				break;

			case 'quarter':
				// Current quarter + (period_count - 1) additional quarters.
				$q_start_month = ( (int) floor( ( $month - 1 ) / 3 ) ) * 3 + 1;
				$q_end_month   = $q_start_month + ( $period_count * 3 ) - 1;
				$q_end_year    = $year;
				if ( $q_end_month > 12 ) {
					$q_end_year  += (int) floor( ( $q_end_month - 1 ) / 12 );
					$q_end_month  = ( ( $q_end_month - 1 ) % 12 ) + 1;
				}
				$start_date = sprintf( '%04d-%02d-01', $year, $q_start_month );
				$end_date   = wp_date( 'Y-m-t', mktime( 0, 0, 0, $q_end_month, 1, $q_end_year ) );
				break;

			case 'year':
			default:
				// Current year + (period_count - 1) additional years.
				$start_date = sprintf( '%04d-01-01', $year );
				$end_date   = sprintf( '%04d-12-31', $year + $period_count - 1 );
				break;
		}

		// Apply show_past: if off, don't show events before today even if in range.
		$effective_start = $show_past ? $start_date : max( $start_date, $today );

		return array_values( array_filter( $events, function ( $e ) use ( $effective_start, $end_date ) {
			return $e['date'] >= $effective_start && $e['date'] <= $end_date;
		} ) );
	}
}
