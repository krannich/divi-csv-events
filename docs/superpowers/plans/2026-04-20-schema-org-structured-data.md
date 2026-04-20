# Schema.org Structured Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emit Schema.org JSON-LD Event data for rendered events so that search engines and LLMs can consume the event information; add optional CSV address column, time-range syntax with overnight support, and module-level organizer settings.

**Architecture:** A new pure `SchemaBuilder` class turns the server-rendered event array + organizer settings + site timezone into a valid JSON-LD string. The `CsvParser` is extended to accept a 6th `Address/Adresse` column and to parse range-format times (`HH:MM-HH:MM`) with automatic overnight detection. The PHP render callback reads three new module settings (`organizerName`, `organizerUrl`, `schemaEnabled`), dispatches to `SchemaBuilder`, and emits the result as a third `<script type="application/ld+json">` tag inside the module HTML. The Visual Builder preview doesn't render schema (irrelevant for editor context) but mirrors the time-range display logic so what the editor sees matches the frontend.

**Tech Stack:** PHP 8.0+ (WordPress plugin), PHPUnit 10, TypeScript/React (Divi 5 Visual Builder).

**Spec reference:** `docs/superpowers/specs/2026-04-20-schema-org-structured-data-design.md`
**Base branch:** `main` (use a feature branch `feat/v1.2-schema-org`)

---

## File Structure

**New files:**
- `includes/SchemaBuilder.php` — pure function class, turns events → JSON-LD string
- `tests/phpunit/unit/SchemaBuilderTest.php` — unit tests for SchemaBuilder

**Modified files:**
- `includes/CsvParser.php` — accept 6th column, parse time ranges
- `tests/phpunit/unit/CsvParserTest.php` — new tests for 6th column + time-range
- `src/components/csv-events-module/module.json` — add `organizerName`, `organizerUrl`, `schemaEnabled` to `eventSettings.items`
- `src/components/csv-events-module/module-default-render-attributes.json` — add defaults for the three new settings
- `src/components/csv-events-module/types.ts` — add `startTime`, `endTime`, `address` to `CsvEvent`, add settings fields
- `src/components/csv-events-module/edit.tsx` — time display uses `start_time`/`end_time`
- `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php` — read new settings, dispatch to SchemaBuilder, update time display helpers
- `divi-csv-events.php` — version 1.1.0 → 1.2.0, `DCSVE_VERSION` constant
- `package.json` — version bump
- `PROJECT_CONTEXT.md` — document CSV spec evolution + new features

---

## Task Dependencies

- **Task 1 → Task 2** (SchemaBuilder tests assume the new event-shape from CsvParser)
- **Task 1 → Task 6** (RenderCallback reads `start_time`/`end_time`/`address`)
- **Task 2 → Task 6** (RenderCallback calls SchemaBuilder)
- **Task 3 → Task 4 → Task 5 → Task 6** (JSON → types → edit.tsx → render)
- **Task 7 (docs) and Task 8 (release)** run last

Tasks 1, 3 can be parallelized. Task 2 depends only on Task 1's new event shape contract. Tasks 4-5-6 chain sequentially.

---

## Task 1: Extend CsvParser — 6th column + time-range parsing

**Files:**
- Modify: `includes/CsvParser.php`
- Modify: `tests/phpunit/unit/CsvParserTest.php`

**Goal:** The parser now:
1. Accepts both 5- and 6-column headers (English and German variants)
2. Reads a 6th `Address`/`Adresse` column into event key `address` (default `''`)
3. Parses the `time` column into `start_time` + `end_time`:
   - `HH:MM-HH:MM` → both set, overnight detected by string comparison `end_time < start_time`
   - `HH:MM` → `start_time = HH:MM`, `end_time = ''`
   - `''` → both empty
4. Keeps the original `time` string in the event (for backward compatibility and display fallback)

**Event-array shape after this task:**
```php
[
    'date'        => '2026-06-13',
    'time'        => '17:00-03:00',   // raw from CSV
    'start_time'  => '17:00',
    'end_time'    => '03:00',
    'title'       => 'Schützenfest',
    'location'    => 'Festplatz',
    'description' => 'Umzug',
    'address'     => 'Hauptstr. 1, 29640 Schneverdingen',
]
```

### Step-by-step (TDD)

- [ ] **Step 1: Add failing tests**

Append to `tests/phpunit/unit/CsvParserTest.php` inside the `CsvParserTest` class:

```php
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
```

- [ ] **Step 2: Run tests — verify they fail**

Run: `composer test`
Expected: 8 new failures/errors (existing 9 still pass).

- [ ] **Step 3: Update accepted_headers for the optional 6th column**

Edit `includes/CsvParser.php`. Find the `$accepted_headers` static array (around line 14). The 6th entry is new and OPTIONAL. Change:

```php
    private static $accepted_headers = [
        [ 'date', 'datum' ],
        [ 'time', 'uhrzeit' ],
        [ 'title', 'titel' ],
        [ 'location', 'ort' ],
        [ 'description', 'beschreibung' ],
    ];
```

to:

```php
    private static $accepted_headers = [
        [ 'date', 'datum' ],
        [ 'time', 'uhrzeit' ],
        [ 'title', 'titel' ],
        [ 'location', 'ort' ],
        [ 'description', 'beschreibung' ],
        [ 'address', 'adresse' ],   // optional 6th column (v1.2.0+)
    ];
```

- [ ] **Step 4: Update parseCsvText to read the 6th column and parse time ranges**

In `includes/CsvParser.php`, replace the `parseCsvText` method body. The row loop currently assigns 5 fields; extend to 6 and normalize `time`:

Find this block inside `parseCsvText`:

```php
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
```

Replace with:

```php
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
            $address     = trim( $row[5] ?? '' );

            if ( '' === $date || '' === $title ) {
                continue;
            }
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                continue;
            }

            [ $start_time, $end_time ] = self::parse_time_field( $time );

            $events[] = compact(
                'date', 'time', 'start_time', 'end_time',
                'title', 'location', 'description', 'address'
            );
        }
```

- [ ] **Step 5: Add the private `parse_time_field` helper**

In the same class, add this method (after `parseCsvText`, before `parse`):

```php
    /**
     * Split the raw time string into start_time and end_time.
     *
     * Accepts:
     *   "HH:MM"          → start only, end empty
     *   "HH:MM-HH:MM"    → both set; overnight detected later by caller via string compare
     *   ""               → both empty
     *   anything else    → kept as start_time, end empty (emission layer handles)
     */
    private static function parse_time_field( string $time ): array {
        $time = trim( $time );
        if ( '' === $time ) {
            return [ '', '' ];
        }

        if ( preg_match( '/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $time, $m ) ) {
            return [ self::pad_hhmm( $m[1] ), self::pad_hhmm( $m[2] ) ];
        }

        return [ $time, '' ];
    }

    /**
     * Left-pad single-digit hour: "9:00" → "09:00". Leaves "09:00" unchanged.
     */
    private static function pad_hhmm( string $v ): string {
        if ( preg_match( '/^(\d):(\d{2})$/', $v, $m ) ) {
            return '0' . $m[1] . ':' . $m[2];
        }
        return $v;
    }
```

- [ ] **Step 6: Run tests — they should all pass**

Run: `composer test`
Expected: `OK (17 tests, ...)` (9 original + 8 new).

- [ ] **Step 7: Verify existing callers still syntax-check**

Run: `php -l includes/CsvParser.php && php -l includes/RestApi.php && php -l modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 8: Commit**

```bash
git add includes/CsvParser.php tests/phpunit/unit/CsvParserTest.php
git commit -m "Extend CsvParser: optional Address column + time-range parsing"
```

---

## Task 2: Create SchemaBuilder (TDD)

**Files:**
- Create: `includes/SchemaBuilder.php`
- Create: `tests/phpunit/unit/SchemaBuilderTest.php`

**Goal:** A pure class that takes `(array $events, array $organizer, \DateTimeZone $tz)` and returns a JSON-LD string representing a Schema.org `@graph` of `Event` objects. No WordPress functions inside — fully testable in isolation.

**Public API:**
```php
class SchemaBuilder {
    public static function build_json_ld(
        array $events,
        array $organizer,     // ['name' => string, 'url' => string]
        \DateTimeZone $tz
    ): string;
}
```

Returns `''` when `$events` is empty (caller should not emit a `<script>` tag for an empty array).

### Step-by-step (TDD)

- [ ] **Step 1: Write failing tests first**

Create `tests/phpunit/unit/SchemaBuilderTest.php`:

```php
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
```

- [ ] **Step 2: Run tests — they should all fail (class doesn't exist yet)**

Run: `composer test`
Expected: multiple failures, all complaining about `DiviCsvEvents\Includes\SchemaBuilder` not found.

- [ ] **Step 3: Create the SchemaBuilder class**

Create `includes/SchemaBuilder.php`:

```php
<?php
/**
 * Builds Schema.org JSON-LD for events.
 *
 * Pure — no WordPress dependencies. Testable in isolation.
 *
 * @package DiviCsvEvents\Includes
 * @since 1.2.0
 */

namespace DiviCsvEvents\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access forbidden.' );
}

class SchemaBuilder {

    private const DEFAULT_DURATION_MINUTES = 180; // 3h fallback when only start_time given

    /**
     * Build a JSON-LD `<script>` body for the given events.
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

        return (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
        // No valid start_time → use date-only ISO form, no end.
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
     * @return array|null  Place object, or null when no location info at all.
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
}
```

- [ ] **Step 4: Make `wp_json_encode` available under test**

The class uses `wp_json_encode` which is a WordPress function. For unit tests we need a shim. Edit `tests/phpunit/bootstrap.php`:

Replace its contents with:

```php
<?php
/**
 * PHPUnit bootstrap for pure-PHP unit tests. No WordPress runtime,
 * but a couple of WP helpers are stubbed so pure utility classes
 * using them remain testable in isolation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string|false {
        return json_encode( $data, $options, $depth );
    }
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
```

- [ ] **Step 5: Run tests — all should pass**

Run: `composer test`
Expected: `OK (N tests, ...)` with all CsvParser + all SchemaBuilder tests green.

If any test fails, read the assertion message carefully, fix the implementation, rerun. Do not alter tests.

- [ ] **Step 6: Syntax-check**

Run: `php -l includes/SchemaBuilder.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add includes/SchemaBuilder.php tests/phpunit/unit/SchemaBuilderTest.php tests/phpunit/bootstrap.php
git commit -m "Add SchemaBuilder for Schema.org JSON-LD output"
```

---

## Task 3: Module JSON — new settings (organizerName, organizerUrl, schemaEnabled)

**Files:**
- Modify: `src/components/csv-events-module/module.json`
- Modify: `src/components/csv-events-module/module-default-render-attributes.json`

**Goal:** Three new items in `eventSettings.settings.innerContent.items`.

- [ ] **Step 1: Add new items to `eventSettings`**

In `src/components/csv-events-module/module.json`, locate the `eventSettings` block (around line 335) and its `items` object containing `period`, `periodCount`, `count`, `showPast`, `view`, `showFilter`, `showViewSwitcher`, `accentColor`. After the closing `}` of the `accentColor` item (still inside `items`, before `items` closes), add:

```json
            "organizerName": {
              "groupSlug": "contentEventSettings",
              "priority": 80,
              "render": true,
              "subName": "organizerName",
              "label": "Organizer Name",
              "description": "Name of the organization hosting these events (for Schema.org structured data). Leave empty to omit organizer from schema.",
              "features": {
                "sticky": false,
                "responsive": false,
                "hover": false,
                "dynamicContent": false
              },
              "component": {
                "name": "divi/text",
                "type": "field"
              }
            },
            "organizerUrl": {
              "groupSlug": "contentEventSettings",
              "priority": 85,
              "render": true,
              "subName": "organizerUrl",
              "label": "Organizer URL",
              "description": "Website of the organizer (optional, used in Schema.org output).",
              "features": {
                "sticky": false,
                "responsive": false,
                "hover": false,
                "dynamicContent": false
              },
              "component": {
                "name": "divi/text",
                "type": "field"
              }
            },
            "schemaEnabled": {
              "groupSlug": "contentEventSettings",
              "priority": 90,
              "render": true,
              "subName": "schemaEnabled",
              "label": "Output Schema.org Data",
              "description": "Embed event data as JSON-LD for search engines and AI. Disable if the page already outputs event schema elsewhere.",
              "features": {
                "sticky": false,
                "responsive": false,
                "hover": false,
                "dynamicContent": false
              },
              "component": {
                "name": "divi/toggle",
                "type": "field"
              }
            }
```

Make sure to put a comma after the closing `}` of `accentColor` so the JSON stays valid.

- [ ] **Step 2: Update the `eventSettings.default` block**

In the same file, also in the `eventSettings` block, find the `default.innerContent.desktop.value` object and extend it with the three new defaults:

Current:
```json
      "default": {
        "innerContent": {
          "desktop": {
            "value": {
              "period": "year",
              "periodCount": "1",
              "count": "0",
              "showPast": "off",
              "view": "list",
              "showFilter": "on",
              "showViewSwitcher": "on",
              "accentColor": "#2e7d32"
            }
          }
        }
      },
```

Change to:
```json
      "default": {
        "innerContent": {
          "desktop": {
            "value": {
              "period": "year",
              "periodCount": "1",
              "count": "0",
              "showPast": "off",
              "view": "list",
              "showFilter": "on",
              "showViewSwitcher": "on",
              "accentColor": "#2e7d32",
              "organizerName": "",
              "organizerUrl": "",
              "schemaEnabled": "on"
            }
          }
        }
      },
```

- [ ] **Step 3: Update `module-default-render-attributes.json`**

In `src/components/csv-events-module/module-default-render-attributes.json`, locate the `eventSettings.innerContent.desktop.value` object and add the same three fields at the end:

```json
  "eventSettings": {
    "innerContent": {
      "desktop": {
        "value": {
          "period": "year",
          "periodCount": "1",
          "count": "0",
          "showPast": "off",
          "view": "list",
          "showFilter": "on",
          "showViewSwitcher": "on",
          "accentColor": "#2e7d32",
          "organizerName": "",
          "organizerUrl": "",
          "schemaEnabled": "on"
        }
      }
    }
  }
```

- [ ] **Step 4: Validate JSON**

Run:
```bash
node -e "JSON.parse(require('fs').readFileSync('src/components/csv-events-module/module.json','utf8'))"
node -e "JSON.parse(require('fs').readFileSync('src/components/csv-events-module/module-default-render-attributes.json','utf8'))"
```
Expected: no output, exit 0.

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: success, 0 errors.

- [ ] **Step 6: Commit**

```bash
git add src/components/csv-events-module/module.json src/components/csv-events-module/module-default-render-attributes.json
git commit -m "Add organizerName, organizerUrl, schemaEnabled to module settings"
```

---

## Task 4: TypeScript types

**Files:**
- Modify: `src/components/csv-events-module/types.ts`

**Goal:** Add `startTime`, `endTime`, `address` to `CsvEvent`; add `organizerName`, `organizerUrl`, `schemaEnabled` to `EventSettingsValue`.

Note on naming: the REST API returns the event array with snake_case keys (`start_time`, `end_time`) because PHP produces them that way. `CsvEvent` in the TS layer mirrors those field names verbatim to avoid a mapping step.

- [ ] **Step 1: Patch `types.ts`**

Edit `src/components/csv-events-module/types.ts`. Update the `EventSettingsValue` interface and the `CsvEvent` interface.

Find:
```typescript
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
```

Replace with:
```typescript
export interface EventSettingsValue {
  period?: string;
  periodCount?: string;
  count?: string;
  showPast?: string;
  view?: string;
  showFilter?: string;
  showViewSwitcher?: string;
  accentColor?: string;
  organizerName?: string;
  organizerUrl?: string;
  schemaEnabled?: string;
}
```

Find:
```typescript
export interface CsvEvent {
  date: string;
  time: string;
  title: string;
  location: string;
  description: string;
}
```

Replace with:
```typescript
export interface CsvEvent {
  date: string;
  time: string;
  start_time?: string;
  end_time?: string;
  title: string;
  location: string;
  description: string;
  address?: string;
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit 2>&1 | grep 'types.ts' | head`
Expected: only pre-existing errors (lines 18–20 `CsvEventsModuleCssAttr` index-signature — same as current main).

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: success.

- [ ] **Step 4: Commit**

```bash
git add src/components/csv-events-module/types.ts
git commit -m "Add start_time, end_time, address to CsvEvent type"
```

---

## Task 5: edit.tsx — time-range display in preview

**Files:**
- Modify: `src/components/csv-events-module/edit.tsx`

**Goal:** Replace the current `{e.time && <strong>{e.time} Uhr</strong>}` pattern with a helper that renders:
- `start_time` + `end_time` → `"HH:MM–HH:MM Uhr"` (en-dash)
- `start_time` only → `"HH:MM Uhr"`
- Neither → nothing

Also update the cards / slider views' inline time display that uses `${e.time} Uhr · `.

- [ ] **Step 1: Add a formatTime helper at the top of edit.tsx**

In `src/components/csv-events-module/edit.tsx`, after the `getWdays()` helper (around line 22) and before `function parseDate(...)`, add:

```tsx
function formatTime(e: CsvEvent): string {
  const start = e.start_time || '';
  const end   = e.end_time   || '';
  if (start && end) return `${start}\u2013${end} Uhr`;  // en-dash
  if (start)       return `${start} Uhr`;
  if (e.time)     return `${e.time} Uhr`;              // legacy fallback
  return '';
}
```

- [ ] **Step 2: Use formatTime in ListView**

Find in the `ListView` component:
```tsx
                  {fmtDate(d)}
                  {e.time && <strong>{e.time} Uhr</strong>}
```

Replace with:
```tsx
                  {fmtDate(d)}
                  {(() => { const t = formatTime(e); return t && <strong>{t}</strong>; })()}
```

- [ ] **Step 3: Use formatTime in CardsView**

Find in `CardsView`:
```tsx
                    <div className="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">
                      {e.time ? `${e.time} Uhr · ` : ''}{e.location}
                    </div>
```

Replace with:
```tsx
                    <div className="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">
                      {(() => { const t = formatTime(e); return t ? `${t} · ` : ''; })()}{e.location}
                    </div>
```

- [ ] **Step 4: Use formatTime in TableView**

Find in `TableView`:
```tsx
          <td className="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">{e.time}</td>
```

Replace with:
```tsx
          <td className="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">{formatTime(e).replace(/\sUhr$/, '')}</td>
```

(We strip the trailing " Uhr" because the table has a separate "Uhrzeit" column where "Uhr" would be redundant.)

- [ ] **Step 5: Use formatTime in SliderView**

Find in `SliderView`:
```tsx
              <div className="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">
                {e.time ? `${e.time} Uhr · ` : ''}{e.location}
              </div>
```

Replace with:
```tsx
              <div className="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">
                {(() => { const t = formatTime(e); return t ? `${t} · ` : ''; })()}{e.location}
              </div>
```

- [ ] **Step 6: Build and type-check**

Run: `npx tsc --noEmit 2>&1 | grep 'edit.tsx' | head`
Expected: same pre-existing errors as main (ModuleContainer props typing around lines ~340).

Run: `npm run build`
Expected: success.

- [ ] **Step 7: Commit**

```bash
git add src/components/csv-events-module/edit.tsx
git commit -m "Use start_time/end_time for range-aware time display in preview"
```

---

## Task 6: PHP render callback — integrate SchemaBuilder and range-aware time display

**Files:**
- Modify: `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`

**Goal:**
1. Read `organizerName`, `organizerUrl`, `schemaEnabled` from `eventSettings`.
2. Switch the existing time-display formatting in the four view renderers to use `start_time`/`end_time` with range support.
3. At the end of module HTML, emit a `<script type="application/ld+json">` when schema is enabled and events exist.

- [ ] **Step 1: Read the new settings**

Locate the block (around lines 45–55) where `$show_past`, `$show_filter`, etc. are read. After `$accent_color` assignment, add:

```php
		$organizer_name = (string) ( $settings['organizerName'] ?? '' );
		$organizer_url  = (string) ( $settings['organizerUrl']  ?? '' );
		$schema_enabled = self::is_on( $settings['schemaEnabled'] ?? 'on' );
```

- [ ] **Step 2: Add a `format_time` helper inside the trait**

Add this as a new `private static` method just below `is_on`:

```php
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
```

- [ ] **Step 3: Switch list view time display to format_time**

In `render_list_view`, find:
```php
				$html .= '<div class="dcsve_csv_events__list-date dcsve_csv_events__el-date">' . esc_html( $wday . ', ' . $day . '. ' . $mon . '.' );
				if ( ! empty( $e['time'] ) ) {
					$html .= '<strong>' . esc_html( $e['time'] . ' Uhr' ) . '</strong>';
				}
				$html .= '</div>';
```

Replace with:
```php
				$html .= '<div class="dcsve_csv_events__list-date dcsve_csv_events__el-date">' . esc_html( $wday . ', ' . $day . '. ' . $mon . '.' );
				$time_display = self::format_time( $e );
				if ( '' !== $time_display ) {
					$html .= '<strong>' . esc_html( $time_display ) . '</strong>';
				}
				$html .= '</div>';
```

- [ ] **Step 4: Switch cards view time display**

In `render_cards_view`, find:
```php
					$html .= '<div class="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">';
					if ( ! empty( $e['time'] ) ) {
						$html .= esc_html( $e['time'] . ' Uhr' ) . ' &middot; ';
					}
					$html .= esc_html( $e['location'] );
					$html .= '</div>';
```

Replace with:
```php
					$html .= '<div class="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">';
					$time_display = self::format_time( $e );
					if ( '' !== $time_display ) {
						$html .= esc_html( $time_display ) . ' &middot; ';
					}
					$html .= esc_html( $e['location'] );
					$html .= '</div>';
```

- [ ] **Step 5: Switch table view time display**

In `render_table_view`, find:
```php
				$html .= '<td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' . esc_html( $e['time'] ) . '</td>';
```

Replace with:
```php
				$time_display = self::format_time( $e );
				$time_display = preg_replace( '/\s*Uhr$/u', '', $time_display ); // column is labeled "Uhrzeit", Uhr is redundant
				$html .= '<td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' . esc_html( $time_display ) . '</td>';
```

- [ ] **Step 6: Switch slider view time display**

In `render_slider_view`, find:
```php
			$html .= '<div class="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">';
			if ( ! empty( $e['time'] ) ) {
				$html .= esc_html( $e['time'] . ' Uhr' ) . ' &middot; ';
			}
			$html .= esc_html( $e['location'] );
			$html .= '</div>';
```

Replace with:
```php
			$html .= '<div class="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">';
			$time_display = self::format_time( $e );
			if ( '' !== $time_display ) {
				$html .= esc_html( $time_display ) . ' &middot; ';
			}
			$html .= esc_html( $e['location'] );
			$html .= '</div>';
```

- [ ] **Step 7: Add import for SchemaBuilder**

Near the top of the file, after the existing `use DiviCsvEvents\Includes\CsvParser;` line, add:

```php
use DiviCsvEvents\Includes\SchemaBuilder;
```

- [ ] **Step 8: Emit the JSON-LD script at end of module HTML**

Locate where the two existing JSON `<script>` tags are composed (around lines 172–173):

```php
		$script_tag = '<script type="application/json" class="dcsve-data">' . $events_json . '</script>';
		$config_tag = '<script type="application/json" class="dcsve-config">' . $config_json . '</script>';
```

After those two lines, add:

```php
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
```

Then update the children composition a few lines down. Find:

```php
				'children'          => $heading . $inner_html . $script_tag . $config_tag,
```

Replace with:

```php
				'children'          => $heading . $inner_html . $script_tag . $config_tag . $schema_tag,
```

- [ ] **Step 9: Syntax-check, run PHP tests, build**

```bash
php -l modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php
composer test
npm run build
```

Expected:
- `No syntax errors detected`
- PHP tests still 17/17 green
- webpack compiles, 0 errors

- [ ] **Step 10: Commit**

```bash
git add modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php
git commit -m "Emit Schema.org JSON-LD; range-aware time display in all views"
```

---

## Task 7: Update PROJECT_CONTEXT.md

**Files:**
- Modify: `PROJECT_CONTEXT.md`

**Goal:** Document the CSV spec evolution (6th column + range times) and the Schema.org feature for future reference.

- [ ] **Step 1: Update CSV format section**

In `PROJECT_CONTEXT.md`, find the CSV-related text (around lines 120–140 or wherever the 5-column spec is described). If the file has a concrete CSV-format description, amend to include:

1. Add note about optional 6th column `Adresse`/`Address`.
2. Add note about `Uhrzeit` accepting `HH:MM` or `HH:MM-HH:MM` (with overnight detection).

Make the edit narrow — don't rewrite surrounding content. If the existing text already mentions 5 columns, adapt it to "5 or 6 columns (6th optional)".

- [ ] **Step 2: Add a Schema.org section**

Append to `PROJECT_CONTEXT.md`:

```markdown
## Schema.org Structured Data (v1.2.0+)

Events are emitted as JSON-LD (`<script type="application/ld+json">`) at the end of the module HTML, containing a Schema.org `@graph` of `Event` objects. Google Rich Results and LLMs consume this data.

- Scope: the server-rendered event set (period/count/show_past filters respected) — matches what's visible on page.
- Organizer: optional, set once per module (`organizerName`, `organizerUrl`).
- Address parsing: regex splits `"Hauptstr. 1, 29640 Schneverdingen"` into structured PostalAddress fields; `addressCountry` hardcoded to DE.
- Toggleable via `schemaEnabled` (default on).
- Time range `HH:MM-HH:MM` produces `endDate`; single time adds a 3h default duration; overnight (`17:00-03:00`) rolls end date to the next day.
- Implementation: `includes/SchemaBuilder.php`, pure, unit-tested.
```

- [ ] **Step 3: Commit**

```bash
git add PROJECT_CONTEXT.md
git commit -m "Document CSV spec evolution and Schema.org feature"
```

---

## Task 8: Version bump + release

**Files:**
- Modify: `divi-csv-events.php`
- Modify: `package.json`

**Goal:** Bump version to 1.2.0 everywhere. Build the release ZIP. Push the tag. Create the GitHub release.

- [ ] **Step 1: Bump `divi-csv-events.php` version**

Edit `divi-csv-events.php`:

Find:
```
Version:     1.1.0
```
Replace with:
```
Version:     1.2.0
```

Find:
```php
define( 'DCSVE_VERSION', '1.1.0' );
```
Replace with:
```php
define( 'DCSVE_VERSION', '1.2.0' );
```

- [ ] **Step 2: Bump `package.json` version**

Edit `package.json`:

Find:
```json
  "version": "1.1.0",
```
Replace with:
```json
  "version": "1.2.0",
```

- [ ] **Step 3: Final checks**

```bash
composer test
php -l divi-csv-events.php
npm run build
```

Expected: tests pass, no PHP syntax errors, build succeeds.

- [ ] **Step 4: Commit version bump**

```bash
git add divi-csv-events.php package.json
git commit -m "Release 1.2.0"
```

- [ ] **Step 5: Merge the feature branch into main (squash)**

```bash
git checkout main
git merge --squash feat/v1.2-schema-org
git commit -m "$(cat <<'EOF'
Add Schema.org structured data output (v1.2.0)

Events emit JSON-LD at the end of the module HTML so search engines
and LLMs can consume them. New optional CSV column Address plus time
ranges HH:MM-HH:MM with overnight detection. New module settings
for organizer name/URL and a schema toggle (default on).

SchemaBuilder is a pure class with comprehensive unit tests. PHP
renderer and React preview both use range-aware time display.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 6: Tag and push**

```bash
git tag v1.2.0
git push origin main v1.2.0
```

- [ ] **Step 7: Build release ZIP**

```bash
npm run zip
```

Expected: `divi-csv-events-v1.2.0.zip` created in project root.

- [ ] **Step 8: Create GitHub release**

```bash
gh release create v1.2.0 divi-csv-events-v1.2.0.zip \
  --title "v1.2.0 — Schema.org structured data" \
  --notes "$(cat <<'EOF'
## Features

- **Schema.org JSON-LD**: Events are now emitted as structured data so Google Rich Results and AI tools (ChatGPT, Claude, Gemini) can consume them.
- **Optional 6th CSV column ``Adresse``/``Address``**: Full postal address per event (e.g., ``Hauptstr. 1, 29640 Schneverdingen``). Parsed into structured ``PostalAddress`` for Google Rich Results.
- **Time ranges**: ``Uhrzeit`` field now accepts ``17:00-22:00`` and overnight ``17:00-03:00`` — end time automatically rolled to the next day.
- **Organizer settings** (new module settings): ``Organizer Name`` + ``Organizer URL`` are emitted as Schema.org ``Organization``.
- **Schema toggle** (default on): Turn off if the page already has event schema elsewhere.

## Compatibility

- Existing 5-column CSVs continue to work — the 6th column is optional.
- Existing single-time entries (``08:00``) produce a 3h default duration in Schema.
- No breaking changes.

## Install / Upgrade

Auto-update via WordPress, or download ``divi-csv-events-v1.2.0.zip`` below and upload manually.

## Requirements

- WordPress 6.4+
- PHP 8.0+
- Divi 5.0+
EOF
)"
```

- [ ] **Step 9: Verify**

- Browse https://github.com/krannich/divi-csv-events/releases — confirm v1.2.0 with ZIP asset.
- On a test WordPress site with v1.1.0 installed: WP Admin → Plugins → "Check for updates" → should show 1.2.0 available.
- On a test site with v1.2.0 installed: view a page with the module, View Source → look for `<script type="application/ld+json" class="dcsve-schema">` with a valid `@graph` of Event objects.
- Paste the schema block into https://validator.schema.org/ — should report 0 errors.
- Paste the page URL (or the JSON-LD) into https://search.google.com/test/rich-results — should detect Event rich results.

---

## Post-Implementation Notes

- The overnight-rollover is purely numeric (`end_time < start_time` as `HH:MM` strings). This works correctly for all sane values; a user entering `"25:00-03:00"` (invalid) falls through the range regex and is treated as single-time.
- The `formatTime` helper in edit.tsx and `format_time` in RenderCallbackTrait are deliberately separate — no DRY sharing across JS/PHP. They're simple enough that duplication is fine; a single source would require another layer.
- `addressCountry` is hardcoded `"DE"`. If an international user requests it, it becomes a module setting later.
- If Google's Rich Results test surfaces a missing-field warning after release, pin the exact field and file a small follow-up release (likely needs `image` if Google's policy changes).
