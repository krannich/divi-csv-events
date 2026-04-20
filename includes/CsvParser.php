<?php
/**
 * CSV Parser - reads, parses and filters events.
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

    private static $accepted_headers = [
        [ 'date', 'datum' ],
        [ 'time', 'uhrzeit' ],
        [ 'title', 'titel' ],
        [ 'location', 'ort' ],
        [ 'description', 'beschreibung' ],
    ];

    /**
     * Parse from a CSV file URL (reads file, caches, filters).
     *
     * @return array Events or ['error' => message].
     */
    public static function parseUrl( $csv_url, $period = 'year', $count = 0, $show_past = false, $period_count = 1 ) {
        $result = self::load_csv_cached( $csv_url );

        if ( isset( $result['error'] ) ) {
            return $result;
        }

        return self::apply_filters( $result, $period, $count, $show_past, $period_count );
    }

    /**
     * Parse from a CSV text string (no file I/O, content-hash caching).
     *
     * @return array Events or ['error' => message].
     */
    public static function parseString( $csv_text, $period = 'year', $count = 0, $show_past = false, $period_count = 1 ) {
        // Cache stores pre-filter events — filter params (period/count/show_past) apply on each call, so variations don't bust the hit rate.
        if ( '' === trim( (string) $csv_text ) ) {
            return [];
        }

        $cache_key = 'dcsve_csv_str_' . md5( $csv_text );
        $cached    = function_exists( 'get_transient' ) ? get_transient( $cache_key ) : false;

        if ( false === $cached ) {
            $cached = self::parseCsvText( $csv_text );
            if ( function_exists( 'set_transient' ) && ! isset( $cached['error'] ) ) {
                set_transient( $cache_key, $cached, 5 * MINUTE_IN_SECONDS );
            }
        }

        if ( isset( $cached['error'] ) ) {
            return $cached;
        }

        return self::apply_filters( $cached, $period, $count, $show_past, $period_count );
    }

    /**
     * Pure text → events. No caching, no filtering. Returns raw events or ['error' => ...].
     *
     * Used directly from unit tests and as the shared backend of parseUrl / parseString.
     */
    public static function parseCsvText( $csv_text ) {
        $csv_text = (string) $csv_text;

        if ( str_starts_with( $csv_text, "\xEF\xBB\xBF" ) ) {
            $csv_text = substr( $csv_text, 3 );
        }

        $csv_text = str_replace( [ "\r\n", "\r" ], "\n", $csv_text );
        $lines    = explode( "\n", $csv_text );
        $lines    = array_values( array_filter( $lines, static fn( $l ) => '' !== trim( $l ) ) );

        if ( empty( $lines ) ) {
            return [];
        }

        $header = str_getcsv( array_shift( $lines ), ';', '"', '\\' );
        $header_error = self::validate_header( $header );
        if ( '' !== $header_error ) {
            return [ 'error' => $header_error ];
        }

        $events = [];
        foreach ( $lines as $line ) {
            $row = str_getcsv( $line, ';', '"', '\\' );
            if ( count( $row ) < 3 ) {
                continue;
            }

            $date        = trim( $row[0] ?? '' );
            $time        = trim( $row[1] ?? '' );
            $title       = trim( $row[2] ?? '' );
            $location    = trim( $row[3] ?? '' );
            $description = trim( $row[4] ?? '' );

            if ( '' === $date || '' === $title ) {
                continue;
            }
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                continue;
            }

            $events[] = compact( 'date', 'time', 'title', 'location', 'description' );
        }

        usort( $events, static fn( $a, $b ) =>
            strcmp( $a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time'] )
        );

        return $events;
    }

    private static function validate_header( $header ) {
        if ( ! $header || count( $header ) < 3 ) {
            return 'Invalid CSV header: header row must have at least 3 columns.';
        }

        $normalized = array_map( static fn( $c ) => strtolower( trim( (string) $c ) ), $header );
        $missing    = [];

        for ( $i = 0; $i < 3; $i++ ) {
            if ( ! isset( $normalized[ $i ] ) || ! in_array( $normalized[ $i ], self::$accepted_headers[ $i ], true ) ) {
                $missing[] = implode( '/', array_map( 'ucfirst', self::$accepted_headers[ $i ] ) );
            }
        }

        if ( ! empty( $missing ) ) {
            $found = implode( ';', array_map( static fn( $c ) => trim( (string) $c ), $header ) );
            return sprintf(
                'Invalid CSV header. Expected: Date/Datum;Time/Uhrzeit;Title/Titel;Location/Ort;Description/Beschreibung — Found: %s',
                $found
            );
        }

        return '';
    }

    private static function apply_filters( array $events, $period, $count, $show_past, $period_count ) {
        if ( empty( $events ) ) {
            return [];
        }

        $events = self::filter_events( $events, $period, $show_past, $period_count );

        if ( $count > 0 ) {
            $events = array_slice( $events, 0, $count );
        }

        return $events;
    }

    // --- URL-based helpers ---

    private static function load_csv_cached( $csv_url ) {
        if ( empty( $csv_url ) ) {
            return [];
        }

        $csv_path = self::resolve_local_path( $csv_url );
        if ( ! $csv_path || ! file_exists( $csv_path ) ) {
            return [];
        }

        $cache_key = 'dcsve_csv_' . md5( $csv_url . filemtime( $csv_path ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $raw    = file_get_contents( $csv_path );
        $result = false === $raw ? [] : self::parseCsvText( $raw );

        if ( ! isset( $result['error'] ) ) {
            set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
        }

        return $result;
    }

    private static function resolve_local_path( $url ) {
        $attachment_id = attachment_url_to_postid( $url );
        if ( $attachment_id ) {
            $path = get_attached_file( $attachment_id );
            if ( $path && file_exists( $path ) ) {
                return $path;
            }
        }

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

    private static function filter_events( $events, $period, $show_past, $period_count = 1 ) {
        $today = wp_date( 'Y-m-d' );

        if ( ! $show_past ) {
            $events = array_filter( $events, static fn( $e ) => $e['date'] >= $today );
            $events = array_values( $events );
        }

        if ( 'all' === $period ) {
            return $events;
        }

        $year         = (int) wp_date( 'Y' );
        $month        = (int) wp_date( 'n' );
        $period_count = max( 1, (int) $period_count );

        switch ( $period ) {
            case 'week':
                $day_of_week = (int) wp_date( 'N' );
                $monday      = wp_date( 'Y-m-d', strtotime( '-' . ( $day_of_week - 1 ) . ' days' ) );
                $end_offset  = ( $period_count * 7 ) - 1;
                $end_date    = wp_date( 'Y-m-d', strtotime( $monday . ' +' . $end_offset . ' days' ) );
                $start_date  = $monday;
                break;
            case 'month':
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
                $start_date = sprintf( '%04d-01-01', $year );
                $end_date   = sprintf( '%04d-12-31', $year + $period_count - 1 );
                break;
        }

        $effective_start = $show_past ? $start_date : max( $start_date, $today );

        return array_values( array_filter(
            $events,
            static fn( $e ) => $e['date'] >= $effective_start && $e['date'] <= $end_date
        ) );
    }
}
