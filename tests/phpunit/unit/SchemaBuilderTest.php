<?php
namespace DiviCsvEvents\Tests\Unit;

use DiviCsvEvents\Includes\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase {

    private \DateTimeZone $berlin;

    protected function setUp(): void {
        $this->berlin = new \DateTimeZone( 'Europe/Berlin' );
    }

    private function minimalEvent( array $overrides = [] ): array {
        return array_merge( [
            'date'        => '2026-06-13',
            'time'        => '17:00',
            'start_time'  => '17:00',
            'end_time'    => '',
            'title'       => 'Festival',
            'location'    => 'Town Hall',
            'description' => '',
            'address'     => '',
        ], $overrides );
    }

    private function decode( string $json ): array {
        $this->assertIsString( $json );
        $this->assertNotSame( '', $json );
        $data = json_decode( $json, true );
        $this->assertIsArray( $data );
        return $data;
    }

    public function test_empty_events_returns_empty_string(): void {
        $this->assertSame( '', SchemaBuilder::build_json_ld( [], [], $this->berlin ) );
    }

    public function test_minimal_event_has_required_fields(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent() ],
            [],
            $this->berlin
        );

        $data = $this->decode( $json );
        $this->assertSame( 'https://schema.org', $data['@context'] );
        $this->assertCount( 1, $data['@graph'] );

        $event = $data['@graph'][0];
        $this->assertSame( 'Event',   $event['@type'] );
        $this->assertSame( 'Festival', $event['name'] );
        $this->assertSame( '2026-06-13T17:00:00+02:00', $event['startDate'] );
        $this->assertSame( 'Town Hall', $event['location']['name'] );
        $this->assertSame( 'https://schema.org/EventScheduled', $event['eventStatus'] );
        $this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', $event['eventAttendanceMode'] );
    }

    public function test_single_time_adds_default_three_hour_end(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'start_time' => '17:00', 'end_time' => '' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-06-13T17:00:00+02:00', $event['startDate'] );
        $this->assertSame( '2026-06-13T20:00:00+02:00', $event['endDate'] );
    }

    public function test_time_range_same_day(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'start_time' => '17:00', 'end_time' => '22:00' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-06-13T17:00:00+02:00', $event['startDate'] );
        $this->assertSame( '2026-06-13T22:00:00+02:00', $event['endDate'] );
    }

    public function test_overnight_time_range_rolls_end_to_next_day(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'start_time' => '17:00', 'end_time' => '03:00' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-06-13T17:00:00+02:00', $event['startDate'] );
        $this->assertSame( '2026-06-14T03:00:00+02:00', $event['endDate'] );
    }

    public function test_no_time_emits_date_only_start_and_no_end(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'time' => '', 'start_time' => '', 'end_time' => '' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-06-13', $event['startDate'] );
        $this->assertArrayNotHasKey( 'endDate', $event );
    }

    public function test_invalid_start_time_falls_back_to_date_only(): void {
        // CsvParser preserves garbage in start_time if input is non-HH:MM.
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'time' => 'abc', 'start_time' => 'abc', 'end_time' => '' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-06-13', $event['startDate'] );
        $this->assertArrayNotHasKey( 'endDate', $event );
    }

    public function test_invalid_calendar_date_falls_back_to_date_only(): void {
        // 2026-02-30 does not exist; PHP's DateTime would silently roll to 2026-03-02.
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'date' => '2026-02-30' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( '2026-02-30', $event['startDate'] );
        $this->assertArrayNotHasKey( 'endDate', $event );
    }

    public function test_address_with_postal_code_is_structured(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'address' => 'Hauptstr. 1, 29640 Schneverdingen' ] ) ],
            [],
            $this->berlin
        );
        $addr = $this->decode( $json )['@graph'][0]['location']['address'];

        $this->assertSame( 'PostalAddress',     $addr['@type'] );
        $this->assertSame( 'Hauptstr. 1',       $addr['streetAddress'] );
        $this->assertSame( '29640',             $addr['postalCode'] );
        $this->assertSame( 'Schneverdingen',    $addr['addressLocality'] );
        $this->assertSame( 'DE',                $addr['addressCountry'] );
    }

    public function test_address_without_postal_falls_back_to_street(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'address' => 'Auf dem Berg' ] ) ],
            [],
            $this->berlin
        );
        $addr = $this->decode( $json )['@graph'][0]['location']['address'];

        $this->assertSame( 'Auf dem Berg', $addr['streetAddress'] );
        $this->assertSame( 'DE',           $addr['addressCountry'] );
        $this->assertArrayNotHasKey( 'postalCode',       $addr );
        $this->assertArrayNotHasKey( 'addressLocality',  $addr );
    }

    public function test_no_address_omits_address_key(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'address' => '' ] ) ],
            [],
            $this->berlin
        );
        $location = $this->decode( $json )['@graph'][0]['location'];

        $this->assertSame( 'Town Hall', $location['name'] );
        $this->assertArrayNotHasKey( 'address', $location );
    }

    public function test_empty_location_name_omits_location(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'location' => '', 'address' => '' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertArrayNotHasKey( 'location', $event );
    }

    public function test_description_included_when_set(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'description' => 'Royal parade' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertSame( 'Royal parade', $event['description'] );
    }

    public function test_description_omitted_when_empty(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'description' => '' ] ) ],
            [],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertArrayNotHasKey( 'description', $event );
    }

    public function test_organizer_included_when_name_set(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent() ],
            [ 'name' => 'Schützenverein', 'url' => 'https://sv-example.de/' ],
            $this->berlin
        );
        $org = $this->decode( $json )['@graph'][0]['organizer'];

        $this->assertSame( 'Organization',         $org['@type'] );
        $this->assertSame( 'Schützenverein',       $org['name'] );
        $this->assertSame( 'https://sv-example.de/', $org['url'] );
    }

    public function test_organizer_omitted_when_name_empty(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent() ],
            [ 'name' => '', 'url' => 'https://sv-example.de/' ],
            $this->berlin
        );
        $event = $this->decode( $json )['@graph'][0];

        $this->assertArrayNotHasKey( 'organizer', $event );
    }

    public function test_organizer_url_omitted_when_invalid(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent() ],
            [ 'name' => 'Club', 'url' => 'not-a-url' ],
            $this->berlin
        );
        $org = $this->decode( $json )['@graph'][0]['organizer'];

        $this->assertSame( 'Club', $org['name'] );
        $this->assertArrayNotHasKey( 'url', $org );
    }

    public function test_utc_timezone_produces_z_or_plus_zero_offset(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent() ],
            [],
            new \DateTimeZone( 'UTC' )
        );
        $event = $this->decode( $json )['@graph'][0];

        // Accept either +00:00 or Z
        $this->assertMatchesRegularExpression(
            '/^2026-06-13T17:00:00(\+00:00|Z)$/',
            $event['startDate']
        );
    }

    public function test_script_tag_in_data_is_hex_escaped_to_prevent_breakout(): void {
        $json = SchemaBuilder::build_json_ld(
            [ $this->minimalEvent( [ 'title' => '</script><script>alert(1)</script>' ] ) ],
            [],
            $this->berlin
        );

        // Raw </script> must NOT appear — JSON_HEX_TAG replaces < with \u003C
        $this->assertStringNotContainsString( '</script>', $json );
        $this->assertStringNotContainsString( '<script', $json );

        // But the content must still round-trip correctly through json_decode.
        $decoded = json_decode( $json, true );
        $this->assertSame( '</script><script>alert(1)</script>', $decoded['@graph'][0]['name'] );
    }

    public function test_multiple_events_each_serialized(): void {
        $events = [
            $this->minimalEvent( [ 'title' => 'A', 'date' => '2026-06-13' ] ),
            $this->minimalEvent( [ 'title' => 'B', 'date' => '2026-07-14' ] ),
        ];

        $json = SchemaBuilder::build_json_ld( $events, [], $this->berlin );
        $graph = $this->decode( $json )['@graph'];

        $this->assertCount( 2, $graph );
        $this->assertSame( 'A', $graph[0]['name'] );
        $this->assertSame( 'B', $graph[1]['name'] );
    }
}
