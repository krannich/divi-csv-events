# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

**Divi CSV Events** (Plugin-Slug `divi-csv-events`, aktuell v1.7.5): ein Divi 5 Custom Module, das Termine aus einer CSV-Datei (oder eingefügtem CSV-Text) in vier Ansichten (Liste, Kacheln, Tabelle, Slider) mit Zeitraum-Filter rendert und optional Schema.org-JSON-LD ausgibt. Kein Custom Post Type, keine DB-Tabelle, keine externen Requests. Hintergrund, Marketplace-Positionierung und Roadmap stehen in `PROJECT_CONTEXT.md`; Design-Specs und Implementierungspläne der Features unter `docs/superpowers/`.

Der ursprüngliche Shortcode-Prototyp liegt in `reference/vereinskalender.php` (mit `reference/termine.csv`). Er ist vollständig ins Modul überführt und dient nur noch als Nachschlagewerk.

## Zweck und Auslieferung

- Zweck: Termine aus CSV in Divi-5-Seiten anzeigen, entstanden für den Schützenverein Schneverdingen, geplant für den Divi Marketplace (`PROJECT_CONTEXT.md`).
- Auslieferung: kein Server-Deploy. Release-ZIP aus `npm run zip` als Asset eines GitHub-Release von `krannich/divi-csv-events`; installierte Seiten holen Updates über den plugin-update-checker (`divi-csv-events.php`, Branch `main`, `enableReleaseAssets()`). Ablauf unter „Release (Kurzform)“.
- Live-Seiten, auf denen das Plugin läuft: offen – bei Dennis nachfragen.
- Wer: Releases nur nach Freigabe durch Dennis.

## Kommandos

```bash
npm install                 # JS-Toolchain
composer install            # PHP-Autoloader + plugin-update-checker + PHPUnit

npm run start               # Webpack im Watch-Modus (NODE_ENV=development)
npm run build               # Production-Build → scripts/, styles/, modules-json/
npm run zip                 # build + composer --no-dev + gulp zip → divi-csv-events-v<version>.zip

wpup                        # lokales WordPress (Playground, SQLite) mit diesem Plugin auf http://127.0.0.1:8881
wpdown                      # … wieder stoppen. zsh-Funktionen aus ~/.zprofile, im Bash-Tool: zsh -lc 'wpup'

composer test               # PHPUnit mit --testdox (alle Unit-Tests)
vendor/bin/phpunit --testdox                                         # dasselbe ohne composer im PATH
vendor/bin/phpunit --filter test_parses_german_headers               # einzelner Test
vendor/bin/phpunit tests/phpunit/unit/SchemaBuilderTest.php          # eine Testdatei
```

- Lokales WordPress mit `wpup` (Login `admin`/`password`, REST über `/?rest_route=/divi-csv-events/v1/events`), Details in `docs/entwicklung.md`.
- `composer` und `wp` (WP-CLI) sind per Homebrew installiert. `vendor/bin/phpunit` funktioniert, sobald `composer install` gelaufen ist.
- Es gibt kein Lint-Setup (kein ESLint, kein PHPCS). TypeScript wird von ts-loader mit `transpileOnly: true` gebaut, Typfehler brechen den Build also **nicht**. Bei Änderungen an `.ts/.tsx` lohnt ein manueller `npx tsc --noEmit`.
- Die Tests laufen ohne WordPress. `tests/phpunit/bootstrap.php` definiert nur `ABSPATH` und stubbt `wp_json_encode`. Testbar sind deshalb nur reine Klassen: `CsvParser::parseCsvText()` und `SchemaBuilder`. Alles mit `get_transient`, `wp_date`, `attachment_url_to_postid` usw. ist nicht unit-testbar.
- Die Versionsnummer steht an drei Stellen und muss synchron bleiben: `package.json` (bestimmt den ZIP-Namen), `divi-csv-events.php` Plugin-Header und `DCSVE_VERSION` (Asset-Cache-Busting, Update-Checker).

## Architektur (Kurzfassung)

Das Modul existiert zweimal, einmal in PHP fürs Frontend und einmal in React für den Visual Builder. Beide teilen sich `module.json` (Attribute, Settings-Felder, CSS-Selektoren) und dieselben CSS-Klassen (`dcsve_csv_events__*`, gestylt in `module.scss`). Die vier View-Renderer existieren sogar **dreimal**: in `RenderCallbackTrait.php` (Server-HTML), in `edit.tsx` (React-Preview) und in `assets/js/frontend.js` (Client-Rerender nach Filterklick). Wer Markup einer View ändert, muss alle drei Stellen anfassen.

- `includes/CsvParser.php`: einziger Ort mit Parsing-, Cache- (Transient 5 Min) und Filterlogik, gemeinsam für REST und Frontend.
- `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`: Frontend-HTML, rendert bei aktivem View-Switcher alle vier Views vor, hängt `.dcsve-data`/`.dcsve-config`/Schema-Script-Tags an.
- `assets/js/frontend.js`: Vanilla JS für View-Switch, Period-Filter (File-Mode per REST, Paste-Mode clientseitig), Slider, Druckansicht.
- `src/components/csv-events-module/edit.tsx`: Builder-Preview, holt Events per REST (GET File-Mode, POST mit Nonce im Paste-Mode).
- `includes/RestApi.php`: GET (öffentlich, nur Media-Library-`.csv`) und POST (`edit_posts`, max 100 KB) auf `divi-csv-events/v1/events`.
- `includes/SchemaBuilder.php`: Schema.org-JSON-LD aus denselben gefilterten Events.
- `module.json` ist Single Source of Truth für Attribute und Settings; `styles.tsx`/`ModuleStylesTrait.php` und die übrigen Builder-/PHP-Gegenstücke parallel pflegen.

Build-Artefakte (`scripts/`, `styles/`, `modules-json/`) sind gitignored.
Ohne `npm run build` fehlt `modules-json/`, und PHP kann das Modul nicht registrieren. Nach jeder Änderung an `module.json` muss neu gebaut werden, auch wenn nur PHP getestet wird.

CSV: Semikolon, UTF-8, Spalten `Datum;Uhrzeit;Titel;Ort;Beschreibung;Adresse;Enddatum;Link` (nur die ersten drei Pflicht, positional), Datum `YYYY-MM-DD`. Vollständig in `docs/csv-format.md`.

## Release (Kurzform)

1. Version an allen drei Stellen erhöhen (siehe oben), ggf. `.pot`/`.po`/`.mo` aktualisieren.
2. `npm run zip` → `divi-csv-events-v<version>.zip` mit Top-Level-Ordner `divi-csv-events/` (muss zum Update-Checker-Slug passen, siehe `docs/build.md`).
3. Ausgeliefert wird über GitHub Releases von `krannich/divi-csv-events` (Update-Checker auf Branch `main`, ZIP als Release-Asset).
4. Kein Live-Deploy, keine Tags, keine Releases ohne ausdrückliche Freigabe von Dennis.

## Konventionen

- Modul-Slug/Blockname `dcsve/csv-events`, CSS-Klassen `dcsve_csv_events__*` (BEM-artig, keine `!important`), PHP-Namespace `DiviCsvEvents`, Text Domain `divi-csv-events`
- Minimum: WP 6.4, PHP 8.0, Divi 5.0, Node 18
- PHP folgt WordPress Coding Standards (Tabs), `includes/` nutzt 4 Spaces. Alle Ausgaben escapen, alle Strings über `__()`
- UI-Labels in `module.json` sind englisch, die Frontend-Strings (Monatsnamen, Buttons, Tabellenköpfe) sind deutsche Quellstrings hinter `__()`
- Neue Filter- oder Anzeigeoptionen gehören in `eventSettings` in `module.json` **und** in `module-default-render-attributes.json` sowie `types.ts`, dann in `RenderCallbackTrait.php` (`$display` bzw. `$settings`), `edit.tsx` (`DisplayFlags`) und `frontend.js` (`config.display`). Alle drei Renderer müssen dieselbe Sichtbarkeitslogik haben
- Früher lag das Projekt in iCloud Drive (heute `~/Developer/Web/divi-csv-events`, kein iCloud mehr). Wenn Composer dort Verzeichnisse entfernte und neu anlegte (`npm run zip` wechselt zwischen no-dev und dev), hinterließ iCloud leere Ordner wie `vendor/phpunit/phpunit 2/` oder `… 3/` und teils auch leere Originalnamen. `gulpfile.js` packt deshalb mit `nodir: true` nur Dateien, leere Ordner können nicht ins ZIP geraten. Aufräumen auf der Platte: `find vendor -type d -empty -name "* [0-9]" -delete`
- Neue CSV-Spalten: `$accepted_headers` und `compact()` in `CsvParser.php`, `CsvEvent` in `types.ts`, dann die drei Renderer und ggf. `SchemaBuilder.php`. Bestehende CSVs mit weniger Spalten müssen weiter funktionieren

## Weiterführende Doku

- `docs/architektur.md`: lesen, bevor du Datenfluss, Rendering, REST API, Schema.org, Druckansicht, Divi-Registrierung oder Design-Settings änderst.
- `docs/dateien.md`: lesen, wenn du wissen musst, welche Datei wofür zuständig ist oder welches PHP-/Builder-Gegenstück mitzuändern ist.
- `docs/build.md`: lesen bei Änderungen an Webpack, Gulp, ZIP-Paketierung oder Build-Output.
- `docs/csv-format.md`: lesen bei Änderungen am CSV-Parsing oder neuen CSV-Spalten.
- `docs/entwicklung.md`: lesen, wenn du die lokale WordPress-Playground-Umgebung (`wpup`) einrichtest oder zurücksetzt.
- `PROJECT_CONTEXT.md`: lesen für Entstehung, Architektur-Entscheidungen, Marketplace-Positionierung und Roadmap.
- `docs/superpowers/`: lesen, wenn du an Paste-Mode oder Schema.org arbeitest (Design-Specs und Implementierungspläne).
