# Schema.org Structured Data — Design Spec

**Datum:** 2026-04-20
**Status:** Approved (brainstorming phase)
**Target-Release:** V1.2.0
**Kontext:** Divi CSV Events — SEO-/AI-Erweiterung: Events als JSON-LD ausgeben, damit Google Rich Results und LLMs die Termine strukturiert verstehen.

## Ziel

Auf jeder Seite, die das CSV-Events-Modul enthält, ein `<script type="application/ld+json">` mit einem Schema.org `@graph` von `Event`-Objekten ausgeben. Pro Event: Name, Start-/Endzeit, Ort (strukturiert wenn Adresse vorhanden), Beschreibung, optional Veranstalter.

## Motivation

- **SEO:** Event-Rich-Results bei Google zeigen Events prominent in Suchergebnissen an (Datum, Ort, Titel).
- **AI:** LLMs wie ChatGPT / Claude / Gemini extrahieren Termine strukturiert für Nutzer-Fragen ("Wann ist das nächste Schützenfest in Schneverdingen?").
- **Standard:** Schema.org JSON-LD ist der vom gesamten Web-Ökosystem bevorzugte Mechanismus. Reicht allein — keine Microdata / Open Graph Redundanz nötig.

## Non-Goals

- Keine `offers` / Ticketpreise (nicht in CSV).
- Keine `image` / `performer` / `organizer` als verschachtelte Objekte (Organizer nur als Name + URL auf Modul-Ebene).
- Keine automatische GPS-/Koordinaten-Auflösung.
- Keine mehrsprachige Schema-Variante.
- Keine Aggregation über mehrere Module-Instanzen hinweg.

## 1. CSV-Spec-Erweiterung

Existierende Spec: 5 Spalten `Datum;Uhrzeit;Titel;Ort;Beschreibung`.

Neue Spec: 6 Spalten, die 6. ist **optional** und backward-compatible:

```csv
Datum;Uhrzeit;Titel;Ort;Beschreibung;Adresse
2026-06-13;17:00-03:00;Schützenfest Tag 1;Festplatz;Festumzug und Königsschießen;Hauptstr. 1, 29640 Schneverdingen
2026-07-04;10:00;Parade;Hauptstraße;;Hauptstr. 1, 29640 Schneverdingen
```

- Englische Header analog: `Date;Time;Title;Location;Description;Address`
- Bestehende 5-Spalten-CSVs bleiben unverändert gültig — kein Breaking Change
- `Adresse` ist ein Freitext-Feld; Parser versucht Regex-Extraktion für strukturiertes Schema, siehe Abschnitt 4

### Uhrzeit-Erweiterung

Die `Uhrzeit`-Spalte akzeptiert ab V1.2.0 drei Formate:

| Eingabe | Start | Ende | Anzeige |
|---------|-------|------|---------|
| `08:00` | 08:00 | 11:00 (Start + 3h Default) | "08:00 Uhr" |
| `17:00-22:00` | 17:00 | 22:00 (selber Tag) | "17:00–22:00 Uhr" |
| `17:00-03:00` | 17:00 | 03:00 **Folgetag** (Overnight-Detection: end < start) | "17:00–03:00 Uhr" |
| *(leer)* | — | — | *(kein Zeit-Suffix)* |

**Default-Dauer:** 3 Stunden, hart im Code. Kein separates Modul-Setting. Redakteur kann jederzeit die Range-Syntax nutzen, wenn eine andere Dauer gewollt ist.

**Overnight-Detection:** Wenn `end_hour:end_min < start_hour:start_min` als HH:MM-String verglichen → `end_date = start_date + 1 day`. Rein numerisch, keine Dauer-Heuristik.

## 2. Modul-Settings

Neu im **Content**-Tab, Elements-Gruppe (bestehend):

| Setting | Type | Default | Beschreibung |
|---------|------|---------|-------------|
| `organizer_name` | text | `''` | Veranstaltername (z.B. "Schützenverein Schneverdingen"). Wenn leer → Schema.org `organizer` weggelassen. |
| `organizer_url` | text (URL) | `''` | Website des Veranstalters (optional). Nur eingefügt wenn `organizer_name` befüllt. |
| `schema_enabled` | toggle | `on` | Schema.org JSON-LD ausgeben. Abschaltbar wenn dieselbe Seite anderswo Event-Schemas ausgibt (Duplikat-Vermeidung). |

Position: nach den bestehenden `Elements`-Feldern (`accentColor` ist aktuell das letzte). Priority > 70.

## 3. Schema.org-Output-Struktur

Ein einzelnes `<script type="application/ld+json">`-Element am Ende des Module-HTMLs (nach den bestehenden `dcsve-data` / `dcsve-config` JSON-Scripts):

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Event",
      "name": "Schützenfest Tag 1",
      "startDate": "2026-06-13T17:00:00+02:00",
      "endDate": "2026-06-14T03:00:00+02:00",
      "eventStatus": "https://schema.org/EventScheduled",
      "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
      "location": {
        "@type": "Place",
        "name": "Festplatz",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "Hauptstr. 1",
          "postalCode": "29640",
          "addressLocality": "Schneverdingen",
          "addressCountry": "DE"
        }
      },
      "description": "Festumzug und Königsschießen",
      "organizer": {
        "@type": "Organization",
        "name": "Schützenverein Schneverdingen",
        "url": "https://schuetzenverein-schneverdingen.de/"
      }
    }
  ]
}
```

### Conditional Fields

| Feld | Enthalten wenn… |
|------|-----------------|
| `name` | immer (Pflichtfeld) |
| `startDate` | immer (Pflichtfeld; Datum allein wenn keine Uhrzeit, sonst ISO-8601 mit Zeitzone) |
| `endDate` | wenn Uhrzeit gepflegt ist (sonst weggelassen) |
| `eventStatus` | immer, hart auf `EventScheduled` |
| `eventAttendanceMode` | immer, hart auf `OfflineEventAttendanceMode` |
| `location.name` | immer wenn CSV-`Ort` befüllt |
| `location.address` | nur wenn CSV-`Adresse` befüllt |
| `description` | nur wenn CSV-`Beschreibung` befüllt |
| `organizer` | nur wenn Modul-`organizer_name` befüllt |
| `organizer.url` | nur wenn `organizer_url` befüllt und valid |

### Events-Scope

Ausgegeben werden **genau die Events, die serverseitig gerendert werden** — also die durch `$period`, `$period_count`, `$count`, `$show_past` gefilterte Liste. Schema und sichtbarer Content stimmen damit immer überein. Google straft Divergenz ab.

### Single JSON-LD Block

Alle Events in einem `@graph`-Array in **einem** `<script>`-Element. Cleaner als ein Script pro Event, und kein Parser-Limit bei vielen Events.

### Zeitzone

`startDate` / `endDate` werden mit WP-Site-Timezone (`wp_timezone()`) in ISO-8601 mit Offset ausgegeben: `2026-06-13T17:00:00+02:00`. Bei nur-Datum (keine Uhrzeit): `"startDate": "2026-06-13"` ohne Zeit.

## 4. Adress-Parsing

Die CSV-`Adresse`-Spalte ist Freitext. Beim Schema-Aufbau versuchen wir zu strukturieren:

**Regex:** `/^(.+?),\s*(\d{5})\s+(.+)$/`

| Match | Ergebnis |
|-------|----------|
| `"Hauptstr. 1, 29640 Schneverdingen"` matcht | `streetAddress: "Hauptstr. 1"`, `postalCode: "29640"`, `addressLocality: "Schneverdingen"`, `addressCountry: "DE"` |
| `"Irgendwas ohne PLZ"` matcht nicht | Fallback: `streetAddress: "Irgendwas ohne PLZ"`, `addressCountry: "DE"` |

`addressCountry` hart auf `"DE"` für V1.2.0. Könnte später als Modul-Setting kommen — aktuell YAGNI, Zielmarkt ist DE.

## 5. Architektur & Dateien

### Neue Datei: `includes/SchemaBuilder.php`

Zentrale Logik: Event-Array + Modul-Settings → JSON-LD-String.

```php
namespace DiviCsvEvents\Includes;

class SchemaBuilder {
    public static function build_json_ld(
        array $events,
        array $organizer,       // ['name' => ..., 'url' => ...]
        \DateTimeZone $tz
    ): string;
    
    private static function build_event(array $event, array $organizer, \DateTimeZone $tz): array;
    private static function build_location(string $name, string $address_raw): array;
    private static function parse_address(string $raw): array;
    private static function format_iso_datetime(string $date, string $time, \DateTimeZone $tz): string;
    private static function compute_end_datetime(string $date, string $start_time, string $end_time, \DateTimeZone $tz): string;
}
```

Alle Methoden pure / testbar. Klasse unit-getestet in PHPUnit (neben CsvParser).

### Änderungen an `includes/CsvParser.php`

**Erweiterung von `parseCsvText`:**
- Akzeptiert 5- und 6-Spalten-Header (beide Header-Sprachen)
- Liest 6. Spalte als `address` in das Event-Array
- Parst `time`-Spalte in `start_time` + `end_time`:
  - Wenn Range (`HH:MM-HH:MM`) → split + Overnight-Detection über String-Vergleich
  - Wenn Single (`HH:MM`) → `start_time = HH:MM`, `end_time = ''` (SchemaBuilder fügt Default-3h hinzu)
  - Wenn leer → beide leer
- `time` bleibt im Event-Array (für Backward-Compat + Anzeige-Logik), zusätzlich `start_time`, `end_time`, `address`

Event-Array-Shape nach V1.2.0:
```php
[
    'date'        => '2026-06-13',
    'time'        => '17:00-03:00',   // Original-String aus CSV, für Anzeige
    'start_time'  => '17:00',
    'end_time'    => '03:00',         // '' wenn kein Range und kein Default
    'title'       => '…',
    'location'    => '…',
    'description' => '…',
    'address'     => 'Hauptstr. 1, 29640 Schneverdingen',  // '' wenn leer
]
```

### Änderungen an `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`

- Liest `organizer_name`, `organizer_url`, `schema_enabled` aus `eventSettings`
- Ruft `SchemaBuilder::build_json_ld()` auf, hängt Ergebnis als `<script type="application/ld+json">` ans Inner-HTML — nur wenn `schema_enabled` und mindestens ein Event vorhanden
- Zeit-Anzeige wird aus `start_time` / `end_time` generiert statt aus `time` (für Range-Display):
  - Range: `HH:MM–HH:MM Uhr`
  - Single: `HH:MM Uhr`
  - Leer: keine Zeit-Ausgabe

### Änderungen an `src/components/csv-events-module/edit.tsx`

- Zeit-Anzeige-Logik analog zum PHP-Render auf Basis `start_time` / `end_time`
- Keine Schema.org-Ausgabe im Builder-Preview (Schema ist nur fürs Frontend relevant — Builder-Preview ist für Redakteur, nicht für Crawler)

### Änderungen an `src/components/csv-events-module/types.ts`

- `CsvEvent` bekommt `start_time`, `end_time`, `address`
- `EventSettingsValue` bekommt `organizerName`, `organizerUrl`, `schemaEnabled`

### Änderungen an `modules-json/*/module.json`

- 3 neue Felder in `eventSettings.items`: `organizerName`, `organizerUrl`, `schemaEnabled`
- Defaults in `module-default-render-attributes.json`

### Änderungen an `includes/RestApi.php`

Kein Code-Change, aber: Wenn der Parser mit V1.2.0 die Event-Shape ändert (`start_time`, `end_time`, `address`), liefert REST automatisch diese Felder mit aus. Builder-Preview sieht sie direkt.

## 6. Testing

### Unit-Tests (PHPUnit)

**`CsvParserTest`** — neue Tests:
- Parst 6-Spalten-CSV (beide Header-Sprachen)
- Parst 5-Spalten-CSV weiterhin (Backward-Compat)
- `time = "17:00"` → `start_time=17:00, end_time=''`
- `time = "17:00-22:00"` → `start_time=17:00, end_time=22:00`
- `time = "17:00-03:00"` → `start_time=17:00, end_time=03:00` (Overnight — Event selbst weiß nicht von Datumsübergang, das ist Builder-Logik)
- `time = ""` → beide leer
- Ungültige Range (`"25:00-27:00"`) → wie bisher: Zeile nicht geskippt, rohe Strings drin, Schema-Ebene entscheidet dann

**`SchemaBuilderTest`** — neu:
- Minimal-Event (nur Name + Datum) → valides JSON-LD
- Event mit allen Feldern → alle Properties korrekt gesetzt
- Adress-Parsing mit PLZ → strukturiert
- Adress-Parsing ohne PLZ → Fallback auf `streetAddress`
- Organizer befüllt → organizer-Feld enthalten
- Organizer leer → organizer-Feld weggelassen
- Overnight-Event (`17:00-03:00`) → `endDate` ist Folgetag
- Leere `time` → kein `endDate`, `startDate` als Datum-String
- Range-Time → endDate korrekt am selben Tag
- Timezone-Respect: Event-Daten mit `Europe/Berlin` vs. `UTC` erzeugen unterschiedliche Offsets
- JSON-Output validiert gegen JSON-Schema-Form (`@context`, `@graph[].@type === 'Event'`)

### Manual Verification (Task-10-Äquivalent)

1. CSV mit 6. Spalte + Range-Times laden
2. Frontend-Source-Code: `<script type="application/ld+json">` vorhanden, parsebar
3. Google Rich Results Test (https://search.google.com/test/rich-results) → grün
4. Schema.org Markup Validator (https://validator.schema.org/) → keine Errors
5. Toggle aus → Script nicht ausgegeben
6. Organizer gesetzt → `organizer`-Feld enthalten
7. Overnight-Event: `endDate` zeigt Folgetag
8. Frontend-Zeit-Anzeige: "17:00–03:00 Uhr"

## 7. Edge Cases & Fehlerverhalten

| Szenario | Verhalten |
|----------|-----------|
| Event ohne Ort | `location` weggelassen — Schema bleibt valide, Google zeigt evtl. kein Rich Result |
| Event ohne Uhrzeit | `startDate = "YYYY-MM-DD"` (Datum-only), kein `endDate` |
| Ungültige Range (Start == End) | Als Single-Time behandeln: `end_time = start + 3h` |
| Ungültige Range (`"abc-def"`) | Parser behandelt als Single-Time, Originalstring in `time`, `start_time` = Rohstring, Schema emittiert das Event ohne `endDate` (kein Crash) |
| `organizer_url` ist keine valide URL | `url` weggelassen, Name bleibt |
| 0 Events nach Filter | Script nicht ausgegeben (leerer `@graph` wäre noise) |
| `schema_enabled = off` | Kein Script-Tag im Output |

## 8. Migration & Backward Compatibility

- Kein Breaking Change für bestehende User
- Bestehende 5-Spalten-CSVs: bleiben gültig, Schema wird mit `location.name` aber ohne `address` ausgegeben
- Bestehende Modul-Instanzen: `schema_enabled` Default `on`, `organizer_*` leer — Schema läuft ohne Nutzerinteraktion an, aber ohne Organizer
- PHP-API von `CsvParser` erweitert, nicht geändert — keine Brüche für andere Konsumenten

## 9. Offene Punkte für Implementierung

- **Rich Results Validierung** gegen echte Google-Tools ist Teil der manuellen Verifikation, kein automatisierter Test
- Wenn Google in Zukunft weitere Pflicht-Felder einführt, reaktiv nachziehen
- `addressCountry` hart auf `DE` — bei Internationalisierung später als Setting ergänzen

## 10. Betroffene Dateien (Summary)

**Neu:**
- `includes/SchemaBuilder.php`
- `tests/phpunit/unit/SchemaBuilderTest.php`

**Modifiziert:**
- `includes/CsvParser.php` — 6. Spalte + Time-Range-Parsing
- `tests/phpunit/unit/CsvParserTest.php` — neue Tests für 6. Spalte + Range-Times
- `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php` — JSON-LD einbetten + Time-Display aus start/end
- `src/components/csv-events-module/module.json` — 3 neue Settings (`organizerName`, `organizerUrl`, `schemaEnabled`)
- `src/components/csv-events-module/module-default-render-attributes.json` — Defaults
- `src/components/csv-events-module/types.ts` — neue Event-Felder + Settings-Felder
- `src/components/csv-events-module/edit.tsx` — Time-Display-Logik (Range-Support)
- `divi-csv-events.php` — Version 1.1.0 → 1.2.0
- `package.json` — Version Bump
- `PROJECT_CONTEXT.md` — CSV-Spec und neue Features dokumentieren

## 11. Release

- Git-Tag `v1.2.0` auf main nach Merge
- `npm run zip` → `divi-csv-events-v1.2.0.zip`
- `gh release create v1.2.0 ...` mit Release-Notes zu SEO / Structured Data / CSV-Spec-Erweiterung
- Plugin-Update-Checker propagiert das Update an alle Installationen
