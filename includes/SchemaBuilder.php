<?php
/**
 * Builds Schema.org JSON-LD for events.
 *
 * Pure — no WordPress dependencies beyond wp_json_encode (stubbed in tests).
 *
 * @package DiviCsvEvents\Includes
 * @since 1.2.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access forbidden.' );
}

class SchemaBuilder {

    private const DEFAULT_DURATION_MINUTES = 180; // 3h fallback when only start_time is given

    /**
     * Build a JSON-LD <script> body for the given events.
     *
     * Returns '' when $events is empty (caller should skip the <script> tag).
     *
     * @param array          $events    Event arrays as produced by CsvParser::parseCsvText.
     * @param array          $organizer ['name' => string, 'url' => string]
     * @param \DateTimeZone  $tz        Timezone used to format start/end dates.
     */
    public static function build_json_ld( array $events, array $organizer, \DateTimeZone $tz ): string {
        if ( empty( $events ) ) {
            return '';
        }

        $graph = [];
        foreach ( $events as $event ) {
            $graph[] = self::build_event( $event, $organizer, $tz );
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        return (string) wp_json_encode( $payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    private static function build_event( array $event, array $organizer, \DateTimeZone $tz ): array {
        $out = [
            '@type'               => 'Event',
            'name'                => (string) ( $event['title'] ?? '' ),
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        $dates = self::compute_dates(
            (string) ( $event['date']       ?? '' ),
            (string) ( $event['start_time'] ?? '' ),
            (string) ( $event['end_time']   ?? '' ),
            $tz
        );
        $out['startDate'] = $dates['start'];
        if ( null !== $dates['end'] ) {
            $out['endDate'] = $dates['end'];
        }

        $location = self::build_location(
            (string) ( $event['location'] ?? '' ),
            (string) ( $event['address']  ?? '' )
        );
        if ( null !== $location ) {
            $out['location'] = $location;
        }

        $description = (string) ( $event['description'] ?? '' );
        if ( '' !== $description ) {
            $out['description'] = $description;
        }

        $org = self::build_organizer( $organizer );
        if ( null !== $org ) {
            $out['organizer'] = $org;
        }

        return $out;
    }

    /**
     * @return array{start:string, end:string|null}
     */
    private static function compute_dates( string $date, string $start_time, string $end_time, \DateTimeZone $tz ): array {
        // If date is not a valid calendar date, fall back to the raw string as startDate.
        if ( ! self::is_valid_date( $date ) ) {
            return [ 'start' => $date, 'end' => null ];
        }

        // No valid HH:MM start_time → date-only start, no end.
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) ) {
            return [ 'start' => $date, 'end' => null ];
        }

        $start = self::datetime_iso( $date, $start_time, $tz );

        if ( preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
            $end_date = $date;
            if ( $end_time < $start_time ) {
                // Overnight — roll end to next day.
                $end_date = self::add_one_day( $date );
            }
            $end = self::datetime_iso( $end_date, $end_time, $tz );
            return [ 'start' => $start, 'end' => $end ];
        }

        // Single time → default 3h duration.
        $dt = self::make_datetime( $date, $start_time, $tz );
        $dt->modify( '+' . self::DEFAULT_DURATION_MINUTES . ' minutes' );
        return [ 'start' => $start, 'end' => $dt->format( 'c' ) ];
    }

    private static function make_datetime( string $date, string $time, \DateTimeZone $tz ): \DateTime {
        return new \DateTime( "{$date} {$time}:00", $tz );
    }

    private static function datetime_iso( string $date, string $time, \DateTimeZone $tz ): string {
        return self::make_datetime( $date, $time, $tz )->format( 'c' );
    }

    private static function add_one_day( string $date ): string {
        $dt = new \DateTimeImmutable( $date );
        return $dt->add( new \DateInterval( 'P1D' ) )->format( 'Y-m-d' );
    }

    /**
     * @return array|null Place object, or null when no location info.
     */
    private static function build_location( string $name, string $address_raw ): ?array {
        if ( '' === $name ) {
            return null;
        }

        $place = [
            '@type' => 'Place',
            'name'  => $name,
        ];

        if ( '' !== $address_raw ) {
            $place['address'] = self::build_address( $address_raw );
        }

        return $place;
    }

    private static function build_address( string $raw ): array {
        $addr = [ '@type' => 'PostalAddress' ];

        if ( preg_match( '/^(.+?),\s*(\d{5})\s+(.+)$/', $raw, $m ) ) {
            $addr['streetAddress']   = trim( $m[1] );
            $addr['postalCode']      = $m[2];
            $addr['addressLocality'] = trim( $m[3] );
        } else {
            $addr['streetAddress'] = trim( $raw );
        }

        $addr['addressCountry'] = 'DE';
        return $addr;
    }

    /**
     * @return array|null Organization object, or null when name is empty.
     */
    private static function build_organizer( array $organizer ): ?array {
        $name = trim( (string) ( $organizer['name'] ?? '' ) );
        if ( '' === $name ) {
            return null;
        }

        $out = [
            '@type' => 'Organization',
            'name'  => $name,
        ];

        $url = trim( (string) ( $organizer['url'] ?? '' ) );
        if ( '' !== $url && false !== filter_var( $url, FILTER_VALIDATE_URL ) ) {
            $out['url'] = $url;
        }

        return $out;
    }

    /**
     * Check that a YYYY-MM-DD string is both well-formed and a real calendar date.
     */
    private static function is_valid_date( string $date ): bool {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
            return false;
        }
        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
    }
}
