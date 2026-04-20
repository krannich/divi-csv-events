<?php
namespace DiviCsvEvents\Tests\Unit;

use DiviCsvEvents\Includes\CsvParser;
use PHPUnit\Framework\TestCase;

final class CsvParserTest extends TestCase {

    public function test_parses_minimal_valid_csv_with_english_headers(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;08:00;Festival;Townhall;Annual gathering\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( '2026-06-13', $events[0]['date'] );
        $this->assertSame( '08:00',      $events[0]['time'] );
        $this->assertSame( 'Festival',   $events[0]['title'] );
        $this->assertSame( 'Townhall',   $events[0]['location'] );
        $this->assertSame( 'Annual gathering', $events[0]['description'] );
    }

    public function test_parses_german_headers(): void {
        $csv = "Datum;Uhrzeit;Titel;Ort;Beschreibung\n"
             . "2026-06-13;08:00;Schützenfest;Festplatz;Umzug\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'Schützenfest', $events[0]['title'] );
    }

    public function test_strips_utf8_bom(): void {
        $csv = "\xEF\xBB\xBFDate;Time;Title;Location;Description\n"
             . "2026-06-13;08:00;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'X', $events[0]['title'] );
    }

    public function test_returns_error_on_missing_required_header(): void {
        $csv = "Foo;Bar;Baz\n2026-06-13;08:00;X\n";

        $result = CsvParser::parseCsvText( $csv );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'Invalid CSV header', $result['error'] );
    }

    public function test_skips_rows_with_invalid_date(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "not-a-date;08:00;Bad;;\n"
             . "2026-06-13;08:00;Good;;\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'Good', $events[0]['title'] );
    }

    public function test_skips_rows_missing_date_or_title(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . ";08:00;NoDate;;\n"
             . "2026-06-13;08:00;;;\n"
             . "2026-06-14;08:00;Valid;;\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'Valid', $events[0]['title'] );
    }

    public function test_sorts_events_chronologically(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-08-01;12:00;C;;\n"
             . "2026-06-13;08:00;A;;\n"
             . "2026-07-04;14:00;B;;\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertSame( ['A', 'B', 'C'], array_column( $events, 'title' ) );
    }

    public function test_returns_empty_array_for_empty_input(): void {
        $result = CsvParser::parseCsvText( '' );
        $this->assertSame( [], $result );
    }

    public function test_handles_crlf_line_endings(): void {
        $csv = "Date;Time;Title;Location;Description\r\n"
             . "2026-06-13;08:00;X;Y;Z\r\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
    }

    public function test_parses_6_column_csv_with_address(): void {
        $csv = "Date;Time;Title;Location;Description;Address\n"
             . "2026-06-13;08:00;Festival;Hall;Desc;Hauptstr. 1, 29640 Schneverdingen\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'Hauptstr. 1, 29640 Schneverdingen', $events[0]['address'] );
    }

    public function test_parses_6_column_csv_with_german_headers(): void {
        $csv = "Datum;Uhrzeit;Titel;Ort;Beschreibung;Adresse\n"
             . "2026-06-13;08:00;Fest;Halle;Beschr;Am Markt 5, 29640 Schneverdingen\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'Am Markt 5, 29640 Schneverdingen', $events[0]['address'] );
    }

    public function test_5_column_csv_still_works_address_empty(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;08:00;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertArrayHasKey( 'address', $events[0] );
        $this->assertSame( '', $events[0]['address'] );
    }

    public function test_time_single_produces_start_only(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;08:00;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertSame( '08:00', $events[0]['start_time'] );
        $this->assertSame( '',      $events[0]['end_time'] );
    }

    public function test_time_range_splits_start_and_end(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;17:00-22:00;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertSame( '17:00', $events[0]['start_time'] );
        $this->assertSame( '22:00', $events[0]['end_time'] );
        $this->assertSame( '17:00-22:00', $events[0]['time'] );
    }

    public function test_time_range_overnight_still_produces_both_times(): void {
        // Parser does not compute the next-day date; SchemaBuilder does.
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;17:00-03:00;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertSame( '17:00', $events[0]['start_time'] );
        $this->assertSame( '03:00', $events[0]['end_time'] );
    }

    public function test_time_empty_both_empty(): void {
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertSame( '', $events[0]['start_time'] );
        $this->assertSame( '', $events[0]['end_time'] );
    }

    public function test_time_invalid_string_kept_as_start(): void {
        // Non-HH:MM input is preserved in start_time without crashing.
        // Schema emission layer decides whether to use it.
        $csv = "Date;Time;Title;Location;Description\n"
             . "2026-06-13;abc;X;Y;Z\n";

        $events = CsvParser::parseCsvText( $csv );

        $this->assertCount( 1, $events );
        $this->assertSame( 'abc', $events[0]['start_time'] );
        $this->assertSame( '',    $events[0]['end_time'] );
    }
}
