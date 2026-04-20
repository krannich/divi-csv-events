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
}
