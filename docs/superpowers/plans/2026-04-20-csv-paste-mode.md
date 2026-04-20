# CSV Paste Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second CSV input mode ("Paste CSV Data") as equivalent alternative to the existing file-upload mode, selectable via a dropdown, with a custom Divi 5 settings field that offers both an inline textarea and a modal editor.

**Architecture:** A new `csvSourceMode` select attribute governs which input field is active. A new `csvContent` attribute holds pasted CSV text, rendered via a custom Divi 5 field component (`dcsve/csv-content-editor`) built on `@divi/modal`. The shared `CsvParser` is refactored so pure text parsing (`parseCsvText`) is reusable from both a URL source (`parseUrl`) and a direct string source (`parseString`). REST endpoint accepts the existing `csv_url` via GET and the new `csv_content` via POST (nonce + `edit_posts` capability). Frontend render callback dispatches by mode.

**Tech Stack:** PHP 8.0+ (WordPress plugin, Divi 5 Module API), TypeScript/React (Visual Builder), SCSS (styles), PHPUnit 10 for unit tests on the parser.

**Spec reference:** `docs/superpowers/specs/2026-04-20-csv-paste-mode-design.md`

---

## File Structure

**New files:**
- `phpunit.xml.dist` — PHPUnit config
- `tests/phpunit/bootstrap.php` — test bootstrap (no WP dependency)
- `tests/phpunit/unit/CsvParserTest.php` — unit tests for pure parsing functions
- `src/components/csv-events-module/csv-content-editor/index.tsx` — custom field
- `src/components/csv-events-module/csv-content-editor/modal.tsx` — modal component
- `src/components/csv-events-module/csv-content-editor/styles.scss` — editor styles
- `src/components/csv-events-module/csv-content-editor/constants.ts` — shared constants (size limits)

**Modified files:**
- `composer.json` — add phpunit dev dep
- `includes/CsvParser.php` — refactor: extract `parseCsvText`, add `parseString`, rename `parse` → `parseUrl`
- `includes/RestApi.php` — accept `csv_content` via POST, security, size validation
- `modules-json/csv-events-module/module.json` — new `csvSourceMode` + `csvContent` attributes, `show` condition on `csvSource`
- `modules-json/csv-events-module/module-default-render-attributes.json` — defaults for new attrs
- `src/components/csv-events-module/types.ts` — new interface fields
- `src/components/csv-events-module/edit.tsx` — mode-aware fetch (GET/POST)
- `src/components/csv-events-module/module.scss` — import editor styles
- `src/index.ts` — register custom field via Divi filter
- `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php` — mode dispatch

---

## Task Dependencies

- **Task 1 → Task 2** (parser tests need framework)
- **Task 2 → Task 3** (REST uses parser API)
- **Task 2 → Task 9** (renderer uses parser API)
- **Task 3 → Task 8** (edit.tsx uses REST)
- **Task 4 → Task 5 → Task 6 → Task 7 → Task 8** (JSON/types/component/registration/wiring)
- **Task 10 runs last** (E2E verification)

Tasks 2/3/9 (PHP track) and 4–8 (frontend track) can be parallelized after Task 1.

---

## Task 1: PHPUnit Setup for Parser Tests

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/phpunit/bootstrap.php`
- Create: `tests/phpunit/unit/CsvParserTest.php` (empty skeleton)

**Rationale:** Only the pure CSV-parsing logic gets unit-tested — no WordPress bootstrap needed. Everything that touches `get_transient`, `wp_upload_dir`, or `attachment_url_to_postid` is verified manually in the Divi Builder (Task 10).

- [ ] **Step 1: Add PHPUnit to composer dev deps**

Edit `composer.json` to add `require-dev`:

```json
{
    "name": "divi-simple-event-list/divi-csv-events",
    "description": "Divi 5 module to display events from a CSV file.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "DiviCsvEvents\\": "modules/",
            "DiviCsvEvents\\Includes\\": "includes/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "DiviCsvEvents\\Tests\\": "tests/phpunit/"
        }
    },
    "scripts": {
        "test": "phpunit --testdox"
    }
}
```

Run: `composer update --dev`
Expected: phpunit/phpunit installed under `vendor/`.

- [ ] **Step 2: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/phpunit/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="defects"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/phpunit/unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create tests/phpunit/bootstrap.php**

```php
<?php
/**
 * PHPUnit bootstrap for pure-PHP unit tests. No WordPress runtime.
 */

// Prevent Direct Access guards from killing test bootstrap.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
```

- [ ] **Step 4: Create empty test skeleton**

`tests/phpunit/unit/CsvParserTest.php`:

```php
<?php
namespace DiviCsvEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CsvParserTest extends TestCase {
    public function test_framework_runs(): void {
        $this->assertTrue( true );
    }
}
```

- [ ] **Step 5: Verify framework runs**

Run: `composer test`
Expected: `OK (1 test, 1 assertion)`.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/
git commit -m "Add PHPUnit setup for parser unit tests"
```

---

## Task 2: Refactor CsvParser — Extract parseCsvText, Add parseString, Rename parse → parseUrl

**Files:**
- Modify: `includes/CsvParser.php`
- Modify: `tests/phpunit/unit/CsvParserTest.php`

**Goal:** Isolate the pure text-to-events logic so it can be reused from a string source. The existing URL-based entry point keeps its caching + file-loading behavior, just renamed.

**Public API after refactor:**
- `CsvParser::parseUrl($csv_url, $period, $count, $show_past, $period_count)` — was `parse()`. Reads file from URL, caches, returns events or `['error' => ...]`.
- `CsvParser::parseString($csv_text, $period, $count, $show_past, $period_count)` — NEW. Parses text directly, no caching of URL-specific state (content-hash cache only), returns events or `['error' => ...]`.
- `CsvParser::parseCsvText($csv_text)` — NEW, private. Pure text → raw-events array or `['error' => ...]`. No filtering, no sorting consumer-facing (but keeps internal sort for consistency).

### Step-by-step (TDD)

- [ ] **Step 1: Write failing tests for `parseCsvText`**

Replace `tests/phpunit/unit/CsvParserTest.php` with:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: All 9 tests FAIL with `Error: Call to undefined method DiviCsvEvents\Includes\CsvParser::parseCsvText()` (or similar).

- [ ] **Step 3: Extract `parseCsvText` from existing code**

Edit `includes/CsvParser.php`. Replace the class body with this (keeps existing behavior, renames `parse` → `parseUrl`, adds `parseString` + `parseCsvText`):

```php
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

        // Strip UTF-8 BOM.
        if ( str_starts_with( $csv_text, "\xEF\xBB\xBF" ) ) {
            $csv_text = substr( $csv_text, 3 );
        }

        // Normalize line endings, split into lines.
        $csv_text = str_replace( [ "\r\n", "\r" ], "\n", $csv_text );
        $lines    = explode( "\n", $csv_text );
        $lines    = array_values( array_filter( $lines, static fn( $l ) => '' !== trim( $l ) ) );

        if ( empty( $lines ) ) {
            return [];
        }

        $header = str_getcsv( array_shift( $lines ), ';' );
        $header_error = self::validate_header( $header );
        if ( '' !== $header_error ) {
            return [ 'error' => $header_error ];
        }

        $events = [];
        foreach ( $lines as $line ) {
            $row = str_getcsv( $line, ';' );
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

    /**
     * Backward-compatibility wrapper. Delegates to parseUrl().
     *
     * @deprecated 1.1.0 Use parseUrl() instead.
     */
    public static function parse( $csv_url, $period = 'year', $count = 0, $show_past = false, $period_count = 1 ) {
        return self::parseUrl( $csv_url, $period, $count, $show_past, $period_count );
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

    // --- Existing URL-based helpers (unchanged apart from delegating parseCsvText) ---

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
                $monday     = wp_date( 'Y-m-d', strtotime( '-' . ( $day_of_week - 1 ) . ' days' ) );
                $end_offset = ( $period_count * 7 ) - 1;
                $end_date   = wp_date( 'Y-m-d', strtotime( $monday . ' +' . $end_offset . ' days' ) );
                $start_date = $monday;
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
```

**Notes:**
- `parseCsvText` is now the single source of truth for text parsing.
- `parse()` is kept as a deprecated alias so any third-party code continues to work.
- `parseString()` uses a content-hash transient cache (works in both WP and test env due to `function_exists` guards).
- The old `load_csv()` helper is replaced by inlined `file_get_contents` + `parseCsvText` call inside `load_csv_cached`.

- [ ] **Step 4: Run tests — they must pass**

Run: `composer test`
Expected: `OK (9 tests, ...)`

- [ ] **Step 5: Verify existing callers still compile**

Run: `php -l includes/CsvParser.php && php -l includes/RestApi.php && php -l modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`
Expected: `No syntax errors detected` for each file.

- [ ] **Step 6: Commit**

```bash
git add includes/CsvParser.php tests/phpunit/unit/CsvParserTest.php
git commit -m "Refactor CsvParser: extract parseCsvText, add parseString, rename parse->parseUrl"
```

---

## Task 3: Extend REST Endpoint — csv_content via POST

**Files:**
- Modify: `includes/RestApi.php`

**Goal:** Support a second invocation shape:
- `GET /divi-csv-events/v1/events?csv_url=...` — existing, unchanged, public.
- `POST /divi-csv-events/v1/events` with JSON `{ csv_content, period, period_count, count, show_past }` — NEW, requires logged-in user with `edit_posts` cap + valid REST nonce.

**Size limit:** 100 KB hard limit on `csv_content`. Enforced server-side.

- [ ] **Step 1: Replace `includes/RestApi.php`**

```php
<?php
/**
 * REST API endpoints for CSV Events.
 *
 * GET  /divi-csv-events/v1/events?csv_url=...  (public; attachment-only)
 * POST /divi-csv-events/v1/events              (authenticated; csv_content body)
 *
 * Used by the Visual Builder for live preview.
 *
 * @package DiviCsvEvents\Includes
 * @since 1.0.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access forbidden.' );
}

const MAX_CSV_CONTENT_BYTES = 102400; // 100 KB

add_action( 'rest_api_init', function () {
    // GET route: file-mode (existing behavior).
    register_rest_route( 'divi-csv-events/v1', '/events', [
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\rest_get_events_by_url',
        'permission_callback' => '__return_true',
        'args'                => shared_filter_args() + [
            'csv_url' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => __NAMESPACE__ . '\\validate_csv_url',
            ],
        ],
    ] );

    // POST route: paste-mode (new).
    register_rest_route( 'divi-csv-events/v1', '/events', [
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\rest_get_events_by_content',
        'permission_callback' => static function () {
            return current_user_can( 'edit_posts' );
        },
        'args'                => shared_filter_args() + [
            'csv_content' => [
                'required'          => true,
                'type'              => 'string',
                // No sanitize_textarea_field — it mangles CSV semicolons/newlines.
                'validate_callback' => __NAMESPACE__ . '\\validate_csv_content',
            ],
        ],
    ] );
} );

/**
 * Shared filter-related args used by both routes.
 */
function shared_filter_args() {
    return [
        'period' => [
            'default'           => 'year',
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static function ( $value ) {
                $allowed = [ 'week', 'month', 'quarter', 'year', 'all' ];
                if ( ! in_array( $value, $allowed, true ) ) {
                    return new \WP_Error(
                        'rest_invalid_param',
                        sprintf( __( 'Invalid period. Allowed values: %s', 'divi-csv-events' ), implode( ', ', $allowed ) ),
                        [ 'status' => 400 ]
                    );
                }
                return true;
            },
        ],
        'count' => [
            'default'           => 0,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
        'show_past' => [
            'default' => false,
            'type'    => 'boolean',
        ],
        'period_count' => [
            'default'           => 1,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
    ];
}

function validate_csv_url( $value, $request, $param ) {
    if ( empty( $value ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'The csv_url parameter is required.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    $path = wp_parse_url( $value, PHP_URL_PATH );
    if ( ! $path || '.csv' !== strtolower( substr( $path, -4 ) ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'Only .csv files are allowed.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    $attachment_id = attachment_url_to_postid( $value );
    if ( ! $attachment_id ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'The CSV file must be uploaded to the WordPress Media Library.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    return true;
}

function validate_csv_content( $value, $request, $param ) {
    if ( ! is_string( $value ) || '' === trim( $value ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'The csv_content parameter is required.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    if ( strlen( $value ) > MAX_CSV_CONTENT_BYTES ) {
        return new \WP_Error(
            'rest_payload_too_large',
            sprintf(
                /* translators: %1$d current size KB, %2$d max size KB */
                __( 'CSV content is too large (%1$d KB / max %2$d KB). Please use the File upload mode.', 'divi-csv-events' ),
                (int) ceil( strlen( $value ) / 1024 ),
                (int) ( MAX_CSV_CONTENT_BYTES / 1024 )
            ),
            [ 'status' => 413 ]
        );
    }

    if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
        return new \WP_Error(
            'rest_invalid_param',
            __( 'CSV content must be valid UTF-8.', 'divi-csv-events' ),
            [ 'status' => 400 ]
        );
    }

    return true;
}

function rest_get_events_by_url( $request ) {
    $csv_url      = $request->get_param( 'csv_url' );
    $period       = $request->get_param( 'period' );
    $count        = (int) $request->get_param( 'count' );
    $show_past    = (bool) $request->get_param( 'show_past' );
    $period_count = (int) $request->get_param( 'period_count' );

    $result = CsvParser::parseUrl( $csv_url, $period, $count, $show_past, $period_count );

    if ( isset( $result['error'] ) ) {
        return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return rest_ensure_response( $result );
}

function rest_get_events_by_content( $request ) {
    $csv_content  = (string) $request->get_param( 'csv_content' );
    $period       = $request->get_param( 'period' );
    $count        = (int) $request->get_param( 'count' );
    $show_past    = (bool) $request->get_param( 'show_past' );
    $period_count = (int) $request->get_param( 'period_count' );

    $result = CsvParser::parseString( $csv_content, $period, $count, $show_past, $period_count );

    if ( isset( $result['error'] ) ) {
        return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return rest_ensure_response( $result );
}
```

- [ ] **Step 2: Syntax check**

Run: `php -l includes/RestApi.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual smoke test (run after Task 10 integration build)**

Note: Deferred to Task 10 since the POST route is only exercised through the Visual Builder.

- [ ] **Step 4: Commit**

```bash
git add includes/RestApi.php
git commit -m "Add POST /events endpoint for paste-mode CSV parsing"
```

---

## Task 4: Module JSON — Add csvSourceMode + csvContent Attributes

**Files:**
- Modify: `modules-json/csv-events-module/module.json`
- Modify: `modules-json/csv-events-module/module-default-render-attributes.json`

- [ ] **Step 1: Add `csvSourceMode` and `csvContent` attributes**

Edit `modules-json/csv-events-module/module.json`. Locate the `"csvSource"` block (around line 60). Replace the entire `"csvSource": { ... }` block + its trailing comma with:

```jsonc
    "csvSourceMode": {
      "type": "object",
      "default": {
        "innerContent": { "desktop": { "value": { "mode": "file" } } }
      },
      "settings": {
        "innerContent": {
          "groupType": "group-item",
          "item": {
            "groupSlug": "contentCsvSource",
            "priority": 5,
            "render": true,
            "subName": "mode",
            "label": "CSV Source",
            "description": "Choose how to provide event data.",
            "features": {
              "sticky": false,
              "dynamicContent": false,
              "responsive": false,
              "hover": false
            },
            "component": {
              "name": "divi/select",
              "type": "field",
              "props": {
                "options": {
                  "file":  { "label": "CSV File" },
                  "paste": { "label": "Paste CSV Data" }
                }
              }
            }
          }
        }
      }
    },
    "csvSource": {
      "type": "object",
      "settings": {
        "innerContent": {
          "groupType": "group-items",
          "items": {
            "src": {
              "groupSlug": "contentCsvSource",
              "priority": 10,
              "render": true,
              "subName": "src",
              "label": "CSV File",
              "description": "Upload or select a CSV file. Format: Date;Time;Title;Location;Description",
              "show": { "csvSourceMode.innerContent.*.value.mode": "file" },
              "features": {
                "sticky": false,
                "dynamicContent": false,
                "responsive": false,
                "hover": false
              },
              "component": {
                "name": "divi/upload",
                "type": "field",
                "props": {
                  "dataType": "text/csv",
                  "chooseText": "Select CSV File",
                  "uploadButtonText": "Upload CSV",
                  "hideMetadata": true
                }
              }
            }
          }
        }
      }
    },
    "csvContent": {
      "type": "object",
      "default": {
        "innerContent": { "desktop": { "value": { "content": "" } } }
      },
      "settings": {
        "innerContent": {
          "groupType": "group-item",
          "item": {
            "groupSlug": "contentCsvSource",
            "priority": 15,
            "render": true,
            "subName": "content",
            "label": "CSV Data",
            "description": "Paste CSV data directly. Format: Date;Time;Title;Location;Description",
            "show": { "csvSourceMode.innerContent.*.value.mode": "paste" },
            "features": {
              "sticky": false,
              "dynamicContent": false,
              "responsive": false,
              "hover": false
            },
            "component": {
              "name": "dcsve/csv-content-editor",
              "type": "field"
            }
          }
        }
      }
    },
```

**Note on `show` syntax:** The `show` key is Divi 5's standard conditional-display pattern seen in built-in modules. If on first load the conditional doesn't kick in (i.e. both fields remain visible), fall back at implementation time to a no-conditional version (labels still communicate intent clearly) and document this in the spec's Offene Punkte.

- [ ] **Step 2: Add defaults in render-attributes JSON**

Edit `modules-json/csv-events-module/module-default-render-attributes.json`. If the file is `{}` (or similar empty), replace with:

```json
{
  "csvSourceMode": {
    "innerContent": { "desktop": { "value": { "mode": "file" } } }
  },
  "csvContent": {
    "innerContent": { "desktop": { "value": { "content": "" } } }
  }
}
```

If the file already has keys, merge these two keys preserving existing content.

- [ ] **Step 3: Validate JSON**

Run:
```
node -e "JSON.parse(require('fs').readFileSync('modules-json/csv-events-module/module.json','utf8'))"
node -e "JSON.parse(require('fs').readFileSync('modules-json/csv-events-module/module-default-render-attributes.json','utf8'))"
```
Expected: no output (parse succeeds).

- [ ] **Step 4: Commit**

```bash
git add modules-json/csv-events-module/module.json modules-json/csv-events-module/module-default-render-attributes.json
git commit -m "Add csvSourceMode and csvContent attributes to module JSON"
```

---

## Task 5: TypeScript Types

**Files:**
- Modify: `src/components/csv-events-module/types.ts`

- [ ] **Step 1: Add `CsvSourceModeValue`, `CsvContentValue`, update `CsvEventsModuleAttrs`**

Insert after the existing `EventSettingsValue` interface (around line 38) and update the `CsvEventsModuleAttrs` interface. Full patched file:

```typescript
// WordPress REST API settings on window.
declare global {
  interface Window {
    wpApiSettings?: { root: string; nonce: string };
  }
}

// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface CsvEventsModuleCssAttr extends Module.Css.AttributeValue {
  heading?: string;
  controls?: string;
  content?: string;
}

export type CsvEventsModuleCssGroupAttr = FormatBreakpointStateAttr<CsvEventsModuleCssAttr>;

export type CsvSourceMode = 'file' | 'paste';

export interface CsvSourceModeValue {
  mode?: CsvSourceMode;
}

export interface CsvSourceValue {
  src?: string;
}

export interface CsvContentValue {
  content?: string;
}

export interface EventSettingsValue {
  period?: string;
  periodCount?: string;
  count?: string;
  showPast?: string;
  view?: string;
  showFilter?: string;
  showViewSwitcher?: string;
  accentColor?: string;
}

export interface CsvEventsModuleAttrs extends InternalAttrs {
  css?: CsvEventsModuleCssGroupAttr;

  module?: {
    meta?: Element.Meta.Attributes;
    advanced?: {
      link?: Element.Advanced.Link.Attributes;
      htmlAttributes?: Element.Advanced.IdClasses.Attributes;
      text?: Element.Advanced.Text.Attributes;
    };
    decoration?: Element.Decoration.PickedAttributes<
      'animation' |
      'background' |
      'border' |
      'boxShadow' |
      'disabledOn' |
      'filters' |
      'overflow' |
      'position' |
      'scroll' |
      'sizing' |
      'spacing' |
      'sticky' |
      'transform' |
      'transition' |
      'zIndex'
    > & {
      attributes?: any;
    };
  };

  // CSV source mode selector (file | paste)
  csvSourceMode?: {
    innerContent?: FormatBreakpointStateAttr<CsvSourceModeValue>;
  };

  // CSV source URL (upload field, active when mode=file)
  csvSource?: {
    innerContent?: FormatBreakpointStateAttr<CsvSourceValue>;
  };

  // CSV pasted content (textarea + modal, active when mode=paste)
  csvContent?: {
    innerContent?: FormatBreakpointStateAttr<CsvContentValue>;
  };

  // Heading
  heading?: Element.Types.Title.Attributes;

  // Font decoration elements
  dateText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  titleText?: { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  metaText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  descText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  filterBtn?: { decoration?: { font?: Element.Decoration.Font.Attributes; }; };

  eventSettings?: {
    innerContent?: FormatBreakpointStateAttr<EventSettingsValue>;
  };
}

export type CsvEventsModuleEditProps = ModuleEditProps<CsvEventsModuleAttrs>;

export interface CsvEvent {
  date: string;
  time: string;
  title: string;
  location: string;
  description: string;
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors (or at least no new errors from this file).

- [ ] **Step 3: Commit**

```bash
git add src/components/csv-events-module/types.ts
git commit -m "Add TypeScript types for csvSourceMode and csvContent"
```

---

## Task 6: Custom Settings Field `dcsve/csv-content-editor`

**Files:**
- Create: `src/components/csv-events-module/csv-content-editor/constants.ts`
- Create: `src/components/csv-events-module/csv-content-editor/modal.tsx`
- Create: `src/components/csv-events-module/csv-content-editor/index.tsx`
- Create: `src/components/csv-events-module/csv-content-editor/styles.scss`
- Modify: `src/components/csv-events-module/module.scss`

**Component contract:** Receives `value: { content?: string }`, `onChange(newValue)`, `label`. Renders inline `<textarea>` + "Edit in large window" button. Button opens a `<Modal>` from `@divi/modal` with a big textarea and Cancel/Apply.

### Step-by-step

- [ ] **Step 1: Create `constants.ts`**

```typescript
export const MAX_CSV_CONTENT_BYTES = 102400; // 100 KB — must match includes/RestApi.php
export const SOFT_WARN_BYTES       = 51200;  // 50 KB — show soft warning above this
export const INLINE_TEXTAREA_ROWS  = 10;
export const MODAL_TEXTAREA_ROWS   = 25;
```

- [ ] **Step 2: Create `modal.tsx`**

```tsx
import React, { ReactElement, useEffect, useRef, useState } from 'react';
import { Modal } from '@divi/modal';
import { __ } from '@wordpress/i18n';

import {
  MAX_CSV_CONTENT_BYTES,
  SOFT_WARN_BYTES,
  MODAL_TEXTAREA_ROWS,
} from './constants';

interface Props {
  initialValue: string;
  onApply: (v: string) => void;
  onClose: () => void;
}

export const CsvContentModal = ({ initialValue, onApply, onClose }: Props): ReactElement => {
  const [draft, setDraft] = useState(initialValue);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    textareaRef.current?.focus();
  }, []);

  const bytes = new Blob([draft]).size;
  const lines = draft ? draft.split('\n').filter(l => l.trim() !== '').length : 0;
  const approxEvents = Math.max(0, lines - 1); // minus header
  const overLimit = bytes > MAX_CSV_CONTENT_BYTES;
  const softWarn  = !overLimit && bytes > SOFT_WARN_BYTES;

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      onClose();
    } else if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      if (!overLimit) {
        onApply(draft);
      }
    }
  };

  return (
    <Modal
      title={__('Edit CSV Data', 'divi-csv-events')}
      onClose={onClose}
      className="dcsve-csv-editor__modal"
    >
      <div className="dcsve-csv-editor__modal-body">
        <p className="dcsve-csv-editor__modal-hint">
          {__('Format: Date;Time;Title;Location;Description', 'divi-csv-events')}
        </p>
        <textarea
          ref={textareaRef}
          className="dcsve-csv-editor__modal-textarea"
          rows={MODAL_TEXTAREA_ROWS}
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={handleKeyDown}
          spellCheck={false}
          placeholder={'Date;Time;Title;Location;Description\n2026-06-13;08:00;Festival;Townhall;Annual gathering'}
        />
        <div className="dcsve-csv-editor__footer">
          <div className={`dcsve-csv-editor__counter${overLimit ? ' is-over' : softWarn ? ' is-warn' : ''}`}>
            {approxEvents} {__('events', 'divi-csv-events')}
            {' · '}
            {(bytes / 1024).toFixed(1)} KB / {(MAX_CSV_CONTENT_BYTES / 1024).toFixed(0)} KB
            {softWarn && (
              <span className="dcsve-csv-editor__warn-text">
                {' — '}
                {__('Large CSV — consider file upload for performance.', 'divi-csv-events')}
              </span>
            )}
            {overLimit && (
              <span className="dcsve-csv-editor__warn-text">
                {' — '}
                {__('Exceeds 100 KB limit. Please use file upload.', 'divi-csv-events')}
              </span>
            )}
          </div>
          <div className="dcsve-csv-editor__modal-actions">
            <button type="button" className="dcsve-csv-editor__btn" onClick={onClose}>
              {__('Cancel', 'divi-csv-events')}
            </button>
            <button
              type="button"
              className="dcsve-csv-editor__btn dcsve-csv-editor__btn--primary"
              disabled={overLimit}
              onClick={() => onApply(draft)}
            >
              {__('Apply', 'divi-csv-events')}
            </button>
          </div>
        </div>
      </div>
    </Modal>
  );
};
```

- [ ] **Step 3: Create `index.tsx` (the custom field)**

```tsx
import React, { ReactElement, useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { CsvContentModal } from './modal';
import {
  MAX_CSV_CONTENT_BYTES,
  SOFT_WARN_BYTES,
  INLINE_TEXTAREA_ROWS,
} from './constants';

interface CsvContentEditorProps {
  value?: { content?: string };
  onChange?: (value: { content: string }) => void;
  label?: string;
  description?: string;
}

export const CsvContentEditor = ({
  value,
  onChange,
  label,
  description,
}: CsvContentEditorProps): ReactElement => {
  const current = value?.content ?? '';
  const [draft, setDraft] = useState(current);
  const [showModal, setShowModal] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Sync local draft if parent replaces value externally and textarea is not focused.
  useEffect(() => {
    const focused = document.activeElement === textareaRef.current;
    if (!focused && !showModal && current !== draft) {
      setDraft(current);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [current, showModal]);

  const bytes = new Blob([draft]).size;
  const overLimit = bytes > MAX_CSV_CONTENT_BYTES;
  const softWarn  = !overLimit && bytes > SOFT_WARN_BYTES;

  const commit = (next: string) => {
    setDraft(next);
    onChange?.({ content: next });
  };

  return (
    <div className="dcsve-csv-editor">
      {label && (
        <label className="dcsve-csv-editor__label">{label}</label>
      )}
      {description && (
        <p className="dcsve-csv-editor__desc">{description}</p>
      )}

      <textarea
        ref={textareaRef}
        className="dcsve-csv-editor__inline"
        rows={INLINE_TEXTAREA_ROWS}
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        onBlur={() => {
          if (draft !== current) {
            commit(draft);
          }
        }}
        spellCheck={false}
        placeholder={'Date;Time;Title;Location;Description\n2026-06-13;08:00;Festival;Townhall;Annual gathering'}
      />

      <div className="dcsve-csv-editor__inline-footer">
        <button
          type="button"
          className="dcsve-csv-editor__btn"
          onClick={() => setShowModal(true)}
        >
          {__('Edit in large window', 'divi-csv-events')}
        </button>
        <div className={`dcsve-csv-editor__counter${overLimit ? ' is-over' : softWarn ? ' is-warn' : ''}`}>
          {(bytes / 1024).toFixed(1)} KB / {(MAX_CSV_CONTENT_BYTES / 1024).toFixed(0)} KB
        </div>
      </div>

      {showModal && (
        <CsvContentModal
          initialValue={draft}
          onApply={(v) => {
            commit(v);
            setShowModal(false);
          }}
          onClose={() => setShowModal(false)}
        />
      )}
    </div>
  );
};
```

- [ ] **Step 4: Create `styles.scss`**

```scss
.dcsve-csv-editor {
  display: flex;
  flex-direction: column;
  gap: 6px;

  &__label {
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  &__desc {
    font-size: 12px;
    opacity: 0.75;
    margin: 0 0 4px 0;
  }

  &__inline {
    width: 100%;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    line-height: 1.4;
    padding: 8px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.25);
    color: inherit;
    border-radius: 4px;
    resize: vertical;
  }

  &__inline-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 4px;
  }

  &__btn {
    appearance: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: transparent;
    color: inherit;
    padding: 6px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;

    &:hover { background: rgba(255, 255, 255, 0.05); }

    &--primary {
      background: #4c6fff;
      border-color: #4c6fff;
      color: #fff;
      &:hover { background: #6382ff; }
      &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
    }
  }

  &__counter {
    font-size: 11px;
    opacity: 0.75;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;

    &.is-warn { color: #e6a23c; opacity: 1; }
    &.is-over { color: #f56c6c; opacity: 1; font-weight: 600; }
  }

  &__warn-text {
    font-family: inherit;
  }

  // --- Modal internal layout ---
  &__modal {
    .dcsve-csv-editor__modal-body {
      display: flex;
      flex-direction: column;
      gap: 10px;
      padding: 16px;
      min-width: 640px;
    }
  }

  &__modal-hint {
    font-size: 12px;
    opacity: 0.75;
    margin: 0;
  }

  &__modal-textarea {
    width: 100%;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.25);
    color: inherit;
    border-radius: 4px;
    resize: vertical;
    min-height: 360px;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  &__modal-actions {
    display: flex;
    gap: 8px;
  }
}
```

- [ ] **Step 5: Import stylesheet**

Edit `src/components/csv-events-module/module.scss`. Add at the top:

```scss
@import './csv-content-editor/styles.scss';
```

- [ ] **Step 6: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors (if `@divi/modal` types unavailable, suppress with a minimal ambient declaration in `types.ts` under `declare module '@divi/modal' { export const Modal: React.FC<any>; }` — add only if tsc complains).

- [ ] **Step 7: Commit**

```bash
git add src/components/csv-events-module/csv-content-editor/ src/components/csv-events-module/module.scss
git commit -m "Add CsvContentEditor custom field with modal editor"
```

---

## Task 7: Register Custom Field with Divi

**Files:**
- Modify: `src/index.ts`

**Goal:** Register `dcsve/csv-content-editor` so Divi's module-settings renderer can instantiate it when it encounters `"component": { "name": "dcsve/csv-content-editor" }`.

- [ ] **Step 1: Register via `addFilter`**

Replace `src/index.ts` with:

```typescript
import { omit } from 'lodash';

import { addAction } from '@wordpress/hooks';
import { addFilter } from '@wordpress/hooks';

import { registerModule } from '@divi/module-library';

import { csvEventsModule } from './components/csv-events-module';
import { CsvContentEditor } from './components/csv-events-module/csv-content-editor';

import './module-icons';

// Register the custom settings field used by `csvContent` attribute.
addFilter(
  'divi.moduleLibrary.fieldLibrary.registerFields',
  'diviCsvEvents/csvContentEditor',
  (fields: Record<string, unknown>) => ({
    ...fields,
    'dcsve/csv-content-editor': {
      component: CsvContentEditor,
    },
  })
);

// Register the module itself.
addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'diviCsvEvents', () => {
  registerModule(csvEventsModule.metadata, omit(csvEventsModule, 'metadata'));
});
```

**Note:** The exact filter name and shape come from the Divi 5 Scaffold (`_scaffold` directory). If Divi 5 at runtime expects a different shape (e.g. a field-class instead of a raw component), adjust the export and wrapping — confirm by checking the scaffold's custom-field registration example during implementation.

- [ ] **Step 2: Build the bundle**

Run: `npm run build`
Expected: successful build, no TypeScript errors, `assets/` updated.

- [ ] **Step 3: Commit**

```bash
git add src/index.ts
git commit -m "Register dcsve/csv-content-editor custom field"
```

---

## Task 8: Update `edit.tsx` — Mode-Aware Fetch

**Files:**
- Modify: `src/components/csv-events-module/edit.tsx`

**Goal:** When mode is `paste`, fetch via POST with `csv_content` in JSON body. When mode is `file`, keep the existing GET flow.

- [ ] **Step 1: Update attribute reads and the fetch effect**

In `edit.tsx`, locate the "Read attributes" block (around lines 213–223) and replace:

```tsx
  // Read attributes.
  const csvSrc      = attrs?.csvSource?.innerContent?.desktop?.value?.src || '';
  const settings    = attrs?.eventSettings?.innerContent?.desktop?.value || {};
```

with:

```tsx
  // Read attributes.
  const sourceMode  = (attrs?.csvSourceMode?.innerContent?.desktop?.value?.mode) || 'file';
  const csvSrc      = attrs?.csvSource?.innerContent?.desktop?.value?.src || '';
  const csvContent  = attrs?.csvContent?.innerContent?.desktop?.value?.content || '';
  const settings    = attrs?.eventSettings?.innerContent?.desktop?.value || {};
```

- [ ] **Step 2: Replace the fetch `useEffect`**

Locate the existing `useEffect` that fetches events (around lines 228–300) and replace the entire block with:

```tsx
  useEffect(() => {
    const hasSource = sourceMode === 'file' ? !!csvSrc : !!csvContent;
    if (!hasSource) {
      setEvents([]);
      setError('');
      return;
    }

    const debounceTimer = setTimeout(() => {
      if (fetchAbortRef.current) {
        fetchAbortRef.current.abort();
      }

      fetchAbortRef.current = new AbortController();
      setLoading(true);
      setError('');

      const wpApiSettings = window.wpApiSettings
        || window.parent?.wpApiSettings
        || { root: '/wp-json/', nonce: '' };

      const baseUrl = wpApiSettings.root + 'divi-csv-events/v1/events';

      const filterPayload = {
        period,
        period_count: String(periodCount),
        count:        String(count),
        show_past:    showPast ? 'true' : 'false',
      };

      let requestInit: RequestInit;
      let url: string;

      if (sourceMode === 'paste') {
        url = baseUrl;
        requestInit = {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   wpApiSettings.nonce || '',
          },
          body: JSON.stringify({
            csv_content: csvContent,
            ...filterPayload,
          }),
          signal: fetchAbortRef.current.signal,
        };
      } else {
        const params = new URLSearchParams({
          csv_url: csvSrc,
          ...filterPayload,
        });
        url = baseUrl + '?' + params.toString();
        requestInit = {
          method: 'GET',
          headers: { 'X-WP-Nonce': wpApiSettings.nonce || '' },
          signal: fetchAbortRef.current.signal,
        };
      }

      fetch(url, requestInit)
        .then(res => res.json().then(data => {
          if (!res.ok && data?.error) throw new Error(data.error);
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return data;
        }))
        .then(data => {
          if (data && data.error) {
            setError(data.error);
            setEvents([]);
          } else {
            setEvents(Array.isArray(data) ? data : []);
            setError('');
          }
          setLoading(false);
        })
        .catch(err => {
          if (err.name !== 'AbortError') {
            setError(err.message);
            setLoading(false);
          }
        });
    }, 300);

    return () => {
      clearTimeout(debounceTimer);
      if (fetchAbortRef.current) {
        fetchAbortRef.current.abort();
      }
    };
  }, [sourceMode, csvSrc, csvContent, period, periodCount, count, showPast]);
```

- [ ] **Step 3: Update empty-state messages**

Locate the empty-state blocks (around lines 378–395). Replace:

```tsx
          {!loading && !csvSrc && (
            <div className="dcsve_csv_events__empty">
              {__('Please upload a CSV file in Content > CSV Source.', 'divi-csv-events')}
            </div>
          )}
```

with:

```tsx
          {!loading && sourceMode === 'file' && !csvSrc && (
            <div className="dcsve_csv_events__empty">
              {__('Please upload a CSV file in Content > CSV Source.', 'divi-csv-events')}
            </div>
          )}

          {!loading && sourceMode === 'paste' && !csvContent && (
            <div className="dcsve_csv_events__empty">
              {__('Please paste CSV data in Content > CSV Data.', 'divi-csv-events')}
            </div>
          )}
```

And update the next block:

```tsx
          {!loading && csvSrc && error && (
```
to:
```tsx
          {!loading && (sourceMode === 'file' ? csvSrc : csvContent) && error && (
```

And:
```tsx
          {!loading && csvSrc && !error && events.length === 0 && (
```
to:
```tsx
          {!loading && (sourceMode === 'file' ? csvSrc : csvContent) && !error && events.length === 0 && (
```

- [ ] **Step 4: Type-check and build**

Run: `npx tsc --noEmit && npm run build`
Expected: both succeed with no errors.

- [ ] **Step 5: Commit**

```bash
git add src/components/csv-events-module/edit.tsx
git commit -m "Make builder preview mode-aware (file vs paste)"
```

---

## Task 9: Update RenderCallbackTrait — Mode Dispatch

**Files:**
- Modify: `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`

- [ ] **Step 1: Dispatch by mode in render_callback**

In `render_callback()` (around lines 40–73), replace:

```php
		// Get settings from attributes.
		$csv_url    = $attrs['csvSource']['innerContent']['desktop']['value']['src'] ?? '';
```

with:

```php
		// Get source-mode + data from attributes.
		$source_mode  = $attrs['csvSourceMode']['innerContent']['desktop']['value']['mode'] ?? 'file';
		$csv_url      = $attrs['csvSource']['innerContent']['desktop']['value']['src'] ?? '';
		$csv_content  = $attrs['csvContent']['innerContent']['desktop']['value']['content'] ?? '';
```

Then replace the existing parse block (around lines 64–73):

```php
		// Parse CSV with server-side filtering (period + count).
		$events    = [];
		$csv_error = '';
		if ( ! empty( $csv_url ) ) {
			$result = CsvParser::parse( $csv_url, $period, $count, $show_past, $period_count );
			if ( isset( $result['error'] ) ) {
				$csv_error = $result['error'];
			} else {
				$events = $result;
			}
		}
```

with:

```php
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
```

- [ ] **Step 2: Update the empty-state message to mention mode**

Locate (around line 140):

```php
			} elseif ( empty( $events ) ) {
				$content_html = '<div class="dcsve_csv_events__empty">' . esc_html__( 'No events found. Please upload a CSV file.', 'divi-csv-events' ) . '</div>';
			}
```

Replace with:

```php
			} elseif ( empty( $events ) ) {
				$empty_msg = ( 'paste' === $source_mode )
					? __( 'No events found. Please paste CSV data.', 'divi-csv-events' )
					: __( 'No events found. Please upload a CSV file.', 'divi-csv-events' );
				$content_html = '<div class="dcsve_csv_events__empty">' . esc_html( $empty_msg ) . '</div>';
			}
```

- [ ] **Step 3: Adjust embedded config JSON**

In the same file, locate the `$config_json` array (around line 160):

```php
		$config_json = wp_json_encode( [
			'csvUrl'           => $csv_url,
```

Add a `sourceMode` key at the top (used by frontend JS if needed for debugging — otherwise harmless):

```php
		$config_json = wp_json_encode( [
			'sourceMode'       => $source_mode,
			'csvUrl'           => $csv_url,
```

- [ ] **Step 4: Syntax-check PHP**

Run: `php -l modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php
git commit -m "Dispatch CSV parsing by source mode in render callback"
```

---

## Task 10: End-to-End Verification in Divi Builder

This task has NO code changes — it verifies everything wired together.

**Prerequisite:** A WordPress test site with Divi 5 active, the plugin symlinked or installed, and a test CSV file in the Media Library.

- [ ] **Step 1: Build the bundle**

Run: `npm run build`
Expected: success.

- [ ] **Step 2: Unit tests still pass**

Run: `composer test`
Expected: `OK (9 tests, ...)`.

- [ ] **Step 3: Activate plugin and open a page with the CSV Events module in Visual Builder**

- [ ] **Step 4: Verify File mode (default, unchanged behavior)**

- CSV Source dropdown shows `CSV File` (default).
- CSV File upload field is visible.
- CSV Data field is NOT visible.
- Upload a CSV — events render as before.

- [ ] **Step 5: Switch to Paste mode**

- Change CSV Source dropdown to `Paste CSV Data`.
- CSV File field disappears.
- CSV Data field (custom editor) appears with inline textarea.
- Empty state shows "Please paste CSV data in Content > CSV Data."

- [ ] **Step 6: Paste CSV and verify live preview**

- Paste:
  ```
  Date;Time;Title;Location;Description
  2026-06-13;08:00;Festival;Townhall;Annual gathering
  2026-07-04;10:00;Parade;Main Street;Fourth of July
  ```
- Blur the textarea.
- Builder preview re-renders with two events.

- [ ] **Step 7: Modal editor**

- Click "Edit in large window".
- Modal opens with current content in a larger textarea.
- Edit, click Apply. Preview updates.
- Re-open modal, edit, click Cancel. Preview does NOT update (change discarded).

- [ ] **Step 8: Size-limit**

- Paste >100 KB of text into the modal.
- Apply button is disabled; counter shows red.
- Close modal. Preview stays on last valid state.

- [ ] **Step 9: Mode round-trip preservation**

- Switch mode File → Paste → File. Verify the CSV URL from File mode is still set.
- Switch mode Paste → File → Paste. Verify the pasted content is still set.

- [ ] **Step 10: Save and view frontend**

- Save the page.
- View the page logged-out (frontend render).
- Events render correctly in paste-mode (PHP render_callback used parseString).

- [ ] **Step 11: REST endpoint manual smoke test (optional)**

With nonce and user logged in (from browser dev-tools):

```bash
# File mode (GET, public)
curl "http://localhost/wp-json/divi-csv-events/v1/events?csv_url=..."

# Paste mode (POST, authenticated; copy nonce from wp_localize_script)
curl -X POST "http://localhost/wp-json/divi-csv-events/v1/events" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"csv_content":"Date;Time;Title;Location;Description\n2026-06-13;08:00;X;Y;Z","period":"year"}'

# Paste mode without nonce (should 401/403)
curl -X POST "http://localhost/wp-json/divi-csv-events/v1/events" \
  -H "Content-Type: application/json" \
  -d '{"csv_content":"..."}'
```

- [ ] **Step 12: Final commit / tag**

No code changes expected — if any polish was needed during verification, commit each change with a focused message.

```bash
# Sanity — make sure nothing is uncommitted that should be:
git status
```

Expected: `nothing to commit, working tree clean`.

---

## Post-Implementation Notes

- If the `show` conditional syntax doesn't work as written (Divi 5 fallback uncertainty noted in the spec), both `csvSource` and `csvContent` will remain visible regardless of mode. The parser-side dispatch still honors the mode correctly — it's only a UI-polish regression, not a functional one. Fix by checking the exact `show` shape in the `_scaffold` examples during Task 4 execution.
- If `@divi/modal` has no TypeScript types available in the plugin's `@divi/types` package, add a minimal ambient declaration. Runtime behavior is unaffected.
- The `sourceMode` key added to the embedded frontend config JSON is a debug helper — frontend filter JS does NOT need it for current functionality.
