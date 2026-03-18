# Divi CSV Events — Custom Divi 5 Module

## Projekt-Überblick

Ein Divi 5 Custom Module Plugin für den Divi Marketplace. Zeigt Events aus einer CSV-Datei in vier Ansichten (Liste, Kacheln, Tabelle, Slider) mit Zeitraum-Filtern. Zielgruppe: Vereine, kleine Unternehmen, Gemeinden.

## Tech Stack

- **Divi 5 Module API** (React/TypeScript für Visual Builder, PHP für Frontend-Rendering)
- **WordPress REST API** für Builder-Live-Vorschau
- **Webpack** als Bundler
- **Composer** für PHP-Autoloading
- **Node 18+**, npm

## Scaffold-Basis

Das Projekt basiert auf der Struktur von `elegantthemes/d5-extension-example-modules` (GitHub). Das "Static Module" Example ist unser Ausgangspunkt, erweitert um einen REST-Endpoint für dynamische Daten.

## Architektur

Siehe `PROJECT_CONTEXT.md` für die vollständige Architektur, Entscheidungen und Roadmap.

## Ordnerstruktur

```
divi-csv-events/
├── src/                              # Visual Builder (React/TypeScript)
│   ├── components/
│   │   └── csv-events-module/
│   │       ├── index.ts              # Module registration
│   │       ├── module.json           # Module metadata (name, slug, icon)
│   │       ├── edit.tsx              # Builder preview rendering
│   │       ├── settings-content.tsx  # Content-Tab: CSV-Quelle, Überschrift, Period, Count, Past
│   │       ├── settings-design.tsx   # Design-Tab: View, Filter, Akzentfarbe, Typo, Spacing
│   │       ├── settings-advanced.tsx # Advanced-Tab: Custom CSS, Conditions, Visibility
│   │       ├── styles.tsx            # Dynamic CSS generation
│   │       ├── types.ts             # TypeScript interfaces
│   │       ├── module.scss          # Builder-only styles
│   │       └── style.scss           # Frontend styles (wird auch im Builder geladen)
│   ├── icons/
│   │   └── csv-events/
│   │       └── index.tsx            # Modul-Icon als SVG React Component
│   └── index.ts                     # Extension entry point
├── modules/                          # Frontend PHP rendering
│   └── CsvEventsModule/
│   │   ├── CsvEventsModuleTrait/
│   │   │   ├── RenderCallbackTrait.php
│   │   │   ├── ModuleStylesTrait.php
│   │   │   └── ModuleClassnamesTrait.php
│   │   └── CsvEventsModule.php
│   └── Modules.php                  # Module registry
├── includes/
│   ├── CsvParser.php                # CSV lesen, parsen, filtern (shared zwischen REST + Render)
│   └── RestApi.php                  # WP REST Endpoint /wp-json/divi-csv-events/v1/events
├── assets/
│   └── css/
│       └── frontend.css             # Compiled frontend styles
├── divi-csv-events.php              # Plugin bootstrap
├── composer.json
├── package.json
├── tsconfig.json
├── webpack.config.js
├── CLAUDE.md
└── PROJECT_CONTEXT.md
```

## Modul-Settings (Divi Visual Builder)

### Content-Tab
| Setting | Type | Default | Beschreibung |
|---------|------|---------|-------------|
| `csv_source` | upload / text | '' | CSV-Datei aus Media Library oder URL |
| `heading` | text | '' | Optionale Überschrift |
| `period` | select | 'year' | week, month, quarter, year, all |
| `count` | range (0-50) | 0 | Max Termine (0 = alle) |
| `show_past` | yes_no | 'off' | Vergangene Termine anzeigen |

### Design-Tab
| Setting | Type | Default | Beschreibung |
|---------|------|---------|-------------|
| `view` | select | '' | list, cards, table, slider (leer = umschaltbar) |
| `show_filter` | yes_no | 'on' | Zeitraum-Filter anzeigen |
| `show_view_switcher` | yes_no | 'on' | Ansichts-Umschalter anzeigen |
| `accent_color` | color | '#2e7d32' | Farbe für Datums-Badge |
| `title_font` | font | Divi default | Typografie für Event-Titel |
| `meta_font` | font | Divi default | Typografie für Meta (Zeit, Ort) |
| `desc_font` | font | Divi default | Typografie für Beschreibung |

## CSV-Format

```csv
Datum;Uhrzeit;Titel;Ort;Beschreibung
2026-06-13;08:00;Schützenfest Tag 1;Festplatz;Festumzug und Königsschießen
```

Alternativ mit englischen Headern:
```csv
Date;Time;Title;Location;Description
2026-06-13;08:00;Schützenfest Tag 1;Festplatz;Festumzug und Königsschießen
```

- Semikolon-getrennt (Excel DE-kompatibel)
- UTF-8, BOM wird toleriert
- Header: Deutsch (Datum;Uhrzeit;Titel;Ort;Beschreibung) oder Englisch (Date;Time;Title;Location;Description)
- Datum im Format YYYY-MM-DD
- Uhrzeit im Format HH:MM
- Beschreibung ist optional

## REST API Endpoint

```
GET /wp-json/divi-csv-events/v1/events
  ?csv_url=<attachment_url_or_external_url>
  &period=year
  &count=0
  &show_past=false
```

Response: JSON Array von Event-Objekten. Wird im Visual Builder per fetch() aufgerufen, damit edit.tsx eine Live-Vorschau rendern kann.

## Wichtige Konventionen

- **Modul-Slug**: `dcsve_csv_events` (Prefix `dcsve_` für Namespace)
- **PHP Namespace**: `DiviCsvEvents`
- **Text Domain**: `divi-csv-events`
- **Minimum WP**: 6.4
- **Minimum PHP**: 8.0
- **Minimum Divi**: 5.0

## Entwicklungs-Workflow

```bash
composer install
npm install
npm run start        # Dev mit Watch
npm run build        # Production Build
npm run zip          # Distribution ZIP für Marketplace
```

## Coding-Standards

- PHP: WordPress Coding Standards, PSR-4 Autoloading via Composer
- TypeScript: Strict mode, Divi type definitions
- CSS: BEM-ähnlich mit `.dcsve-` Prefix, keine !important
- Alle Strings internationalisierbar mit `__()` / `esc_html__()`
- Escaping: Alle Ausgaben mit `esc_html()`, `esc_attr()`, `wp_kses_post()`

## Aktueller Stand

- [x] Konzept und Architektur definiert
- [x] CSV-Parser und Render-Logik als Shortcode-Plugin fertig (funktionierender Prototyp)
- [x] Vier Ansichten implementiert (Liste, Kacheln, Tabelle, Slider)
- [x] Zeitraum-Filter und View-Switcher
- [x] Responsive + Touch-Support (Slider)
- [ ] Divi 5 Extension Scaffolding
- [ ] Module Settings (Content/Design/Advanced)
- [ ] REST API Endpoint
- [ ] Visual Builder Live Preview (edit.tsx)
- [ ] Frontend PHP Rendering
- [ ] Frontend CSS
- [ ] Production Build + ZIP
- [ ] Marketplace Listing

## Referenz-Dateien

- `reference/vereinskalender.php` — Der funktionierende Shortcode-Prototyp mit allen vier Ansichten, CSS und JS. Die gesamte Render-Logik und Styles können als Basis für das Divi-Modul übernommen werden.
- `reference/termine.csv` — Beispiel-CSV mit typischen Vereinsterminen.
