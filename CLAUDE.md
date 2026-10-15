# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

**Divi CSV Events** (Plugin-Slug `divi-csv-events`, aktuell v1.7.2): ein Divi 5 Custom Module, das Termine aus einer CSV-Datei (oder eingefügtem CSV-Text) in vier Ansichten (Liste, Kacheln, Tabelle, Slider) mit Zeitraum-Filter rendert und optional Schema.org-JSON-LD ausgibt. Kein Custom Post Type, keine DB-Tabelle, keine externen Requests. Hintergrund, Marketplace-Positionierung und Roadmap stehen in `PROJECT_CONTEXT.md`; Design-Specs und Implementierungspläne der Features unter `docs/superpowers/`.

Der ursprüngliche Shortcode-Prototyp liegt in `reference/vereinskalender.php` (mit `reference/termine.csv`). Er ist vollständig ins Modul überführt und dient nur noch als Nachschlagewerk.

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

- `wpup` nutzt `wp-playground-cli start` (global installiert). Die Site wird pro Verzeichnis unter `~/.wordpress-playground/sites/<hash>/` gespeichert und überlebt Neustarts; `wpup --reset` setzt sie zurück. Login `admin`/`password`, PHP 8.0 wie unser Minimum (`WP_PHP=8.3 wpup` für andere Versionen). Divi muss einmalig als Theme-ZIP hochgeladen werden. Pretty Permalinks sind aus, REST-Routen also über `/?rest_route=/divi-csv-events/v1/events`.
- `composer` ist auf dieser Maschine nicht im PATH. Solange `vendor/` existiert, funktioniert `vendor/bin/phpunit` direkt.
- Es gibt kein Lint-Setup (kein ESLint, kein PHPCS). TypeScript wird von ts-loader mit `transpileOnly: true` gebaut, Typfehler brechen den Build also **nicht**. Bei Änderungen an `.ts/.tsx` lohnt ein manueller `npx tsc --noEmit`.
- Die Tests laufen ohne WordPress. `tests/phpunit/bootstrap.php` definiert nur `ABSPATH` und stubbt `wp_json_encode`. Testbar sind deshalb nur reine Klassen: `CsvParser::parseCsvText()` und `SchemaBuilder`. Alles mit `get_transient`, `wp_date`, `attachment_url_to_postid` usw. ist nicht unit-testbar.
- Die Versionsnummer steht an drei Stellen und muss synchron bleiben: `package.json` (bestimmt den ZIP-Namen), `divi-csv-events.php` Plugin-Header und `DCSVE_VERSION` (Asset-Cache-Busting, Update-Checker).

## Build-Artefakte (gitignored)

Webpack erzeugt aus `src/index.ts`:

| Output | Quelle | Wird geladen |
|---|---|---|
| `scripts/bundle.js` | alle `.ts/.tsx` | nur im Visual Builder (App-Window) |
| `styles/bundle.css` | `module.scss` | Frontend **und** Builder |
| `styles/vb-bundle.css` | `style.scss` (aktuell leer) | nur Builder, via splitChunks-Cachegroup `vb` |
| `modules-json/csv-events-module/*.json` | `module.json` + die beiden `module-default-*.json`, per CopyWebpackPlugin kopiert | PHP liest sie bei `ModuleRegistration::register_module()` |

Ohne `npm run build` fehlt `modules-json/`, und PHP kann das Modul nicht registrieren. Nach jeder Änderung an `module.json` muss neu gebaut werden, auch wenn nur PHP getestet wird.

`gulpfile.js` definiert die ZIP-Excludes (src, tests, docs, reference, Configs) und legt alle Dateien unter einen Top-Level-Ordner `divi-csv-events/`. Der Ordnername bestimmt das Plugin-Verzeichnis in WordPress und muss zum Slug des Update-Checkers passen; ohne ihn würde jede Version als eigenes Plugin `divi-csv-events-vX.Y.Z/` installiert. `_scaffold/` ist der ursprüngliche Klon von `elegantthemes/d5-extension-example-modules` und nur als Referenz da.

## Architektur

Das Modul existiert zweimal, einmal in PHP fürs Frontend und einmal in React für den Visual Builder. Beide teilen sich `module.json` (Attribute, Settings-Felder, CSS-Selektoren) und dieselben CSS-Klassen (`dcsve_csv_events__*`, gestylt in `module.scss`). Die vier View-Renderer existieren sogar **dreimal**: in `RenderCallbackTrait.php` (Server-HTML), in `edit.tsx` (React-Preview) und in `assets/js/frontend.js` (Client-Rerender nach Filterklick). Wer Markup einer View ändert, muss alle drei Stellen anfassen.

### Datenfluss: CSV → Ausgabe

**1. Datenquelle (Builder-Attribute).** Der Redakteur wählt in `csvSourceMode` entweder `file` (Upload-Feld `csvSource.innerContent.desktop.value.src`, Media-Library-URL) oder `paste` (Textarea `csvContent.innerContent.desktop.value.content`, max 100 KB). Die Sichtbarkeit der beiden Felder wird nicht in `module.json`, sondern in `src/components/csv-events-module/index.ts` per `visible`-Callback an die Metadaten gehängt. Filter- und Anzeigeoptionen liegen gesammelt in `eventSettings.innerContent.desktop.value` (period, periodCount, count, showPast, view, showFilter, showViewSwitcher, accentColor, organizerName, organizerUrl, schemaEnabled sowie die Display-Gruppe showWeekday, showTime, showLocation, showDescription, showLink, showMonthHeadings, showTableHeader, dateLayout, linkLabel, tableMobile, showPrint, printPosition, printView, printWeekday, printTime, printLocation, printDescription, printMonthHeadings, printLinkUrl). Toggles sind Strings `'on'`/`'off'`, nicht Booleans.

**2. Parsen und Filtern: `includes/CsvParser.php`.** Zentrale Klasse, gemeinsam für REST und Frontend-Render.
- `parseCsvText($text)`: reine Funktion. BOM strippen, Zeilenenden normalisieren, Header validieren (erste drei Spalten müssen Date/Datum, Time/Uhrzeit, Title/Titel sein, case-insensitive), Zeilen mit ungültigem Datum oder leerem Titel überspringen, `time` in `start_time`/`end_time` splitten, `end_date` nur übernehmen, wenn wohlgeformt und nach `date`, `link` nur bei absoluter http(s)-URL, chronologisch sortieren. Gibt Event-Array oder `['error' => string]` zurück.
- `parseUrl($url, ...)`: löst die URL über `attachment_url_to_postid()` oder den Upload-Basedir zu einem lokalen Pfad auf (keine externen URLs), liest die Datei, cached das **ungefilterte** Ergebnis 5 Minuten als Transient (Key aus URL + `filemtime`), dann `apply_filters()`.
- `parseString($text, ...)`: dasselbe für Paste-Mode, Transient-Key aus `md5($text)`.
- `filter_events()`: `show_past=false` entfernt Vergangenes. `period` (week/month/quarter/year/all) mit `period_count` spannt ein Kalenderfenster ab dem aktuellen Wochen-/Monats-/Quartals-/Jahresanfang auf, nicht "ab heute + n Tage". Nutzt `wp_date()`, also WP-Zeitzone. Mehrtägige Termine zählen bis `end_date`: ein Termin bleibt, solange `end_date ?: date` im Fenster bzw. nicht vor heute liegt (`last_day()`).

Ein Event ist danach ein Array mit `date`, `time`, `start_time`, `end_time`, `title`, `location`, `description`, `address`, `end_date`, `link`. Transients aus älteren Versionen können die letzten beiden Keys noch nicht haben, alle Konsumenten lesen sie deshalb mit `?? ''`.

**3a. Frontend-Render: `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php`.** Divi ruft `render_callback($attrs, $content, $block, $elements)` beim Seitenaufbau. Ablauf:
1. `dcsve_enqueue_frontend_assets()` lädt CSS und `frontend.js` nur, wenn das Modul auf der Seite ist.
2. Attribute lesen, `CsvParser::parseString()` oder `parseUrl()` aufrufen.
3. Controls-Bar mit Period-Buttons (`data-period`), View-Switcher-Buttons (`data-view`) und optional dem Druck-Button (`__btn-print`, `showPrint`; `printPosition` top/bottom/both, unten als `__print--bottom` nach dem Content-Container) als echtes HTML bauen.
4. `render_events_html()`: Events nach Monat gruppieren (Startdatum) und **alle vier Views vorrendern**, wenn der View-Switcher an ist (nicht aktive per `style="display:none"`). Ohne Switcher nur die feste View. Datum in Liste/Tabelle rendert `render_date()` als drei Spans `__d-wday`, `__d-day`, `__d-mon` (Font-Gruppen „Date: Weekday“, „Date: Day Number“, „Date: Month“ im Design-Tab; „Date Text (all parts)“ färbt den ganzen Block inkl. Uhrzeit und Badge). Wichtig: Datumsteile, Uhrzeit und Badge-Zahlen haben im SCSS **keine eigene Farbe** (`color: inherit`), die Hierarchie entsteht über `opacity`. Nur so erreicht die Divi-Farbe von „Date Text“ alle Kinder; eine explizite Farbe am Kind würde sie schlucken. Inline: „Mi, 16. Sep.“ bzw. „Di, 22. Sep. – Fr, 25. Sep.“. Gestapelt (`dateLayout=stacked`, Modifier `dcsve_csv_events--date-stacked` am Inner-Wrapper, Rest ist CSS): ein `__d-block` pro Datum mit den Zeilen „Mo“ / „16“ / „Sep“ ohne Satzzeichen; bei Enddatum zwei Blöcke nebeneinander mit `__d-sep` (Gedankenstrich, per Flexbox vertikal zentriert). Der Flex-Container ist der innere Wrapper `__d-stack`, nie das `<td>`: eine Tabellenzelle mit `display:flex` verliert ihre Rahmen-Verschmelzung (doppelte Linien). `tableMobile` („Table Layout“, `scroll`|`split`|`stack`; `split` = Datum links, Rest daneben gestapelt per CSS-Grid auf der `<tr>`; Datumsspalte fest `var(--dcsve-date-col, 9em)`, weil `max-content` pro Zeile neu berechnet würde) ist das einzige **responsive** Feld in `eventSettings`: Divi speichert Abweichungen unter `innerContent.tablet.value.tableMobile` bzw. `.phone.…`, PHP (`table_layout_classes()`) und `edit.tsx` lesen mit Vererbung Desktop → Tablet → Phone und setzen je Band einen Modifier `--table-{stack|split}-{desktop|tablet|phone}`. Das SCSS wendet eine Mixin in Divis Bändern an (≥981, 768–980, ≤767 px). Beim Filter-Rerender bleibt der Wrapper samt Klassen stehen, das JS braucht den Wert nicht. `showWeekday=off` lässt den Wochentag-Span weg. Kacheln/Slider nutzen `badge_parts()` („13–15“ über „Jun“, bei Monatswechsel „13. Jun –“ über „2. Jul“) und tragen `__d-day`/`__d-mon` am Badge, der Layout-Schalter wirkt dort nicht. `render_link()` hängt bei gesetztem `link` einen Button `.dcsve_csv_events__link` an (`target="_blank"`, `rel="noopener noreferrer"`); Beschriftung aus `linkLabel`, leer = `__( 'Weitere Informationen' )`. Der Button hat im Design-Tab eigene Divi-Gruppen „Link Button Text/Background/Border/Spacing/Box Shadow“ über das Attribut `linkBtn` (Selektor `.dcsve_csv_events__link`, Decorations mit `important`), das SCSS liefert nur den Outline-Standard in Akzentfarbe. `render_location()` setzt vor den Ort ein Inline-SVG `.dcsve_csv_events__pin`. Die sechs Display-Flags werden als `$display`-Array durch alle View-Renderer gereicht (`$ctx['display']`): Zeit, Ort, Beschreibung und Link blenden das jeweilige Element aus, in der Tabelle die ganze Spalte; „Details“ existiert nur, wenn Beschreibung oder Link aktiv ist, `colspan` der Monatszeilen wird daraus berechnet. Datum und Titel sind nie abschaltbar.
5. Zwei `<script type="application/json">`-Tags anhängen: `.dcsve-data` (die gefilterten Events) und `.dcsve-config` (Filterparameter, csvUrl, fixedView, display, printView, heading als Klartext usw.) für das Frontend-JS.
6. Optional `<script type="application/ld+json" class="dcsve-schema">` aus `SchemaBuilder::build_json_ld()`.
7. Alles in `Module::render()` einwickeln, mit `module_classnames`, `module_styles`, `module_script_data` aus den anderen Traits. `--dcsve-accent` wird als Inline-CSS-Variable am Inner-Wrapper gesetzt.

**3b. Client-Interaktion: `assets/js/frontend.js`.** Vanilla JS, kein Framework. Liest `.dcsve-config` (inkl. `display`-Flags, die `displayFlags()` mit Default `true` auflöst), hängt Click-Handler an die Buttons. View-Switcher togglet nur `display` der vorgerenderten `.dcsve_csv_events__view`-Container. Ein Klick auf einen Period-Button holt im File-Mode per `fetch` GET `/wp-json/divi-csv-events/v1/events?csv_url=...` neue Events. Im Paste-Mode gibt es keine öffentliche Route, deshalb legt PHP dort die **ungefilterte** Liste in `.dcsve-data` ab und `filterEvents()` im JS bildet die Zeitraum-Logik von `CsvParser::filter_events()` nach (Browser-Datum statt WP-Zeitzone). In beiden Fällen werden alle Views clientseitig neu gerendert (`innerHTML`). Die REST-Basis-URL kommt aus `window.dcsveRestUrl`, alle sichtbaren Strings (Monate, Wochentage, Zeit-Suffix, Button-Label, Leerzustand) aus `window.dcsveI18n`; beides injiziert `divi-csv-events.php` als Inline-Script, die Strings kommen aus `dcsve_frontend_i18n()` und laufen so über dieselbe `.po` wie das PHP-Markup. Slider-Navigation ist `scrollBy` auf einem CSS-scroll-snap-Track. **Druck:** `wrap._dcsveEvents` hält immer die gerade angezeigten Events (`initialEvents()` beim Start, danach jeder Filterklick). `printEvents()` baut mit `buildPrintDocument(config, events, period, stylesheetHref)` ein eigenständiges HTML-Dokument (verlinkt `bundle.css` über die Script-Handle-ID `divi-csv-events-bundle-style-css`, plus eingebettetes A4-Print-CSS), öffnet es per `window.open` und das Dokument ruft nach `load` selbst `window.print()`. Vergangene Termine werden immer entfernt, die Ansicht kommt aus `printView` (table/list/cards), die Felder aus `config.printDisplay` (eigene Toggles, unabhängig von der Bildschirmansicht; Links nur als Klartext-URL über `printLinkUrl`, dafür hat `renderLink()` den Modus `d.linkAsUrl`), Überschrift aus `config.heading`, Untertitel aus Zeitraum-Label und „Stand: Datum“. `buildPrintDocument` ist bewusst eine reine Funktion, damit sie in Node testbar ist.

**3c. Builder-Preview: `src/components/csv-events-module/edit.tsx`.** React-Komponente, die Divi im Builder rendert. Da im Builder kein PHP läuft, holt sie die Events per `fetch` aus der REST API (debounced 300 ms, AbortController bei Attributänderung): GET mit `csv_url` im File-Mode, POST mit `csv_content` und `X-WP-Nonce` im Paste-Mode. Die Nonce kommt aus `window.wpApiSettings` bzw. `window.parent.wpApiSettings` (Builder läuft im iframe). Zeigt Loading-, Fehler- und Leerzustand und rendert nur die in `view` gewählte Ansicht. Die Buttons in der Preview sind `<span>`s ohne Handler, da Interaktion im Builder nicht nötig ist.

**4. REST API: `includes/RestApi.php`.** Zwei Routen auf `divi-csv-events/v1/events`:
- GET, öffentlich (`permission_callback` `__return_true`), Parameter `csv_url`. Akzeptiert nur `.csv`-URLs, die einem Media-Library-Attachment entsprechen. Wird von Builder **und** Frontend-JS genutzt.
- POST, nur `edit_posts`, Parameter `csv_content` (max `MAX_CSV_CONTENT_BYTES` = 100 KB, UTF-8-geprüft, absichtlich ohne `sanitize_textarea_field`, weil das Semikolons und Zeilenumbrüche zerstört). Nur der Builder nutzt sie.
Beide teilen `shared_filter_args()` (period, count, show_past, period_count) und delegieren an `CsvParser`. Fehler kommen als `{ "error": "..." }` mit HTTP 400.

**5. Strukturierte Daten: `includes/SchemaBuilder.php`.** Reine Klasse ohne WP-Abhängigkeit außer `wp_json_encode`. Baut aus den **gleichen gefilterten Events** ein `@graph` von `Event`-Objekten. Zeitlogik ohne `end_date`: Range → `startDate`/`endDate`, Ende < Start → Folgetag; nur Startzeit → +3 h; keine Zeit → nur Datum. Mit `end_date`: `endDate` ist Enddatum plus `end_time` (falls Range), sonst nur das Enddatum; die Overnight- und 3-h-Regeln greifen dann nicht. `link` wird als `url` ausgegeben. Adresse per Regex `Straße, PLZ Ort` in `PostalAddress` zerlegt, `addressCountry` fest `DE`. `JSON_HEX_TAG` verhindert `</script>`-Breakout.

### Divi-5-Registrierung

- **PHP:** `divi-csv-events.php` lädt Autoloader und `modules/Modules.php`. Dieses hängt sich an `divi_module_library_modules_dependency_tree` und fügt `CsvEventsModule` hinzu. Dessen `load()` ruft auf `init` `ModuleRegistration::register_module()` mit dem `modules-json/`-Pfad und dem `render_callback` auf. Der Blockname ist `dcsve/csv-events`.
- **JS:** `src/index.ts` registriert das Modul auf `divi.moduleLibrary.registerModuleLibraryStore.after` via `registerModule(metadata, {defaultAttrs, defaultPrintedStyleAttrs, placeholderContent, renderers: {edit}})`. `src/module-icons.ts` hängt das Icon aus `src/icons/csv-events/` per Filter `divi.iconLibrary.icon.map` ein.
- Das Builder-Bundle wird in `divi-csv-events.php` über `PackageBuildManager::register_package_build()` nur ins App-Window geladen (`enqueue_top_window: false`).

### Design-Settings und Styles

Es gibt keine handgeschriebenen `settings-*.tsx`. Alle Felder sind deklarativ in `module.json`. Die Design-Typografie läuft über Divi-native Font-Decoration-Gruppen pro Attribut (`heading`, `monthHeadingText`, `dateText`, `weekdayText`, `dayText`, `monthText`, `titleText`, `metaText`, `descText`, `filterBtn`, `linkBtn`), jeweils mit einem `selector` wie `{{selector}} .dcsve_csv_events__el-date`. Die Renderer setzen deshalb neben den BEM-Klassen zusätzliche Hook-Klassen `dcsve_csv_events__el-month` (Monats-Gruppierung in Liste/Kacheln und die Monatszeile der Tabelle), `__el-date`, `__el-title`, `__el-meta`, `__el-desc`. „Heading Text“ betrifft nur die Modul-Überschrift `__heading`, nicht die Monatsgruppen. `styles.tsx` (Builder) und `ModuleStylesTrait.php` (Frontend) listen dieselben Attribute für `elements->style()` auf und müssen parallel gepflegt werden. Custom-CSS-Felder (`heading`, `controls`, `content`) sind in `module.json` definiert; `custom-css.ts` und `CustomCssTrait.php` lesen sie für beide Seiten.

## Rolle jeder Datei

| Datei | Rolle |
|---|---|
| `divi-csv-events.php` | Plugin-Bootstrap: Konstanten (`DCSVE_PATH`, `DCSVE_JSON_PATH`, `DCSVE_VERSION`), Autoloader, Textdomain aus `languages/`, Update-Checker (GitHub Releases via `yahnis-elsts/plugin-update-checker`), Builder-Bundle-Registrierung, Frontend-Asset-Registrierung, Inline-Script mit `dcsveRestUrl` und `dcsveI18n` (`dcsve_frontend_i18n()`), CSV-Upload in Media Library erlauben |
| `includes/CsvParser.php` | CSV lesen, validieren, parsen, cachen, filtern. Einziger Ort mit Parsing-Logik |
| `includes/RestApi.php` | GET/POST `/divi-csv-events/v1/events` für Builder-Preview und Frontend-Filter |
| `includes/SchemaBuilder.php` | Schema.org-JSON-LD aus Event-Array |
| `modules/Modules.php` | Registriert `CsvEventsModule` im Divi-Dependency-Tree |
| `modules/CsvEventsModule/CsvEventsModule.php` | Modulklasse, bündelt die Traits, registriert `render_callback` auf `init` |
| `…/CsvEventsModuleTrait/RenderCallbackTrait.php` | Gesamtes Frontend-HTML inkl. vier View-Renderer, Data-/Config-/Schema-Script-Tags |
| `…/CsvEventsModuleTrait/ModuleStylesTrait.php` | Divi-Style-Output für alle Font-Gruppen + Custom CSS (Frontend-Gegenstück zu `styles.tsx`) |
| `…/CsvEventsModuleTrait/ModuleClassnamesTrait.php` | Text-Option-Klassen am Modul-Wrapper (Gegenstück zu `module-classnames.ts`) |
| `…/CsvEventsModuleTrait/ModuleScriptDataTrait.php` | Divi-Script-Data für Animation/Link/Sticky (Gegenstück zu `module-script-data.tsx`) |
| `…/CsvEventsModuleTrait/CustomCssTrait.php` | Liest `customCssFields` aus dem registrierten Block-Type |
| `assets/js/frontend.js` | Frontend-Interaktion: Period-Filter per REST, View-Switch, Slider-Navigation, Client-Rerender |
| `src/index.ts` | Extension-Entry, Modulregistrierung im Builder |
| `src/module-icons.ts`, `src/icons/` | Modul-Icon (SVG als React-Komponente) im Icon-Library-Filter |
| `src/types.ts` | Store-Typen für den Blocknamen `dcsve/csv-events` |
| `src/components/csv-events-module/module.json` | Single Source of Truth für Attribute, Settings-Felder, Selektoren, Custom-CSS-Felder. Content-Tab-Gruppen: Data (Quelle), Elements (Filter, View, Farbe, Schema), Display (Sichtbarkeits-Toggles). Wird nach `modules-json/` kopiert |
| `…/module-default-render-attributes.json` | Default-Attribute beim Einfügen (Text-Color light, h3, alle `eventSettings`-Defaults) |
| `…/module-default-printed-style-attributes.json` | Defaults, die als CSS gedruckt werden |
| `…/index.ts` | Modul-Definition, hängt `visible`-Callbacks für File-/Paste-Felder an die Metadaten, importiert SCSS |
| `…/edit.tsx` | Builder-Preview: REST-Fetch + React-Renderer für die vier Views |
| `…/styles.tsx` | Style-Komponente für den Builder |
| `…/types.ts` | `CsvEventsModuleAttrs` (Attribut-Shape), `CsvEvent` (REST-Antwort), `wpApiSettings`-Window-Typ |
| `…/custom-css.ts`, `…/module-classnames.ts`, `…/module-script-data.tsx`, `…/placeholder-content.ts` | Builder-Gegenstücke zu den PHP-Traits bzw. Placeholder beim Einfügen |
| `…/module.scss` | **Alle** Styles für Frontend und Builder (→ `styles/bundle.css`) |
| `…/style.scss` | Builder-only Styles, aktuell leer (→ `styles/vb-bundle.css`) |
| `webpack.config.js` | Externals-Mapping auf `window.divi.*` / `window.vendor.*`, CSS-Split, JSON-Copy |
| `gulpfile.js` | ZIP-Paketierung mit Excludes |
| `phpunit.xml.dist`, `tests/phpunit/` | Unit-Tests für `CsvParser` und `SchemaBuilder` |
| `languages/` | `divi-csv-events.pot` (Template) und `divi-csv-events-en_US.po/.mo`. Quellstrings sind deutsch, en_US übersetzt sie. Nach neuen `__()`-Aufrufen `.pot`/`.po` ergänzen und `.mo` mit `msgfmt -o languages/divi-csv-events-en_US.mo languages/divi-csv-events-en_US.po` neu bauen. Kein wp-cli auf dieser Maschine |
| `PROJECT_CONTEXT.md` | Entstehung, Architektur-Entscheidungen, Roadmap |
| `docs/superpowers/` | Design-Specs und Pläne (Paste-Mode, Schema.org) |
| `reference/` | Shortcode-Prototyp und Beispiel-CSV |

## CSV-Format

```csv
Datum;Uhrzeit;Titel;Ort;Beschreibung;Adresse;Enddatum;Link
2026-06-13;17:00-03:00;Schützenfest;Festplatz;Festumzug und Königsschießen;Hauptstr. 1, 29640 Schneverdingen;2026-06-15;https://verein.example/schuetzenfest
```

- Semikolon-getrennt (Excel DE), UTF-8, BOM toleriert, CRLF toleriert
- Header deutsch oder englisch (Date/Datum;Time/Uhrzeit;Title/Titel;Location/Ort;Description/Beschreibung;Address/Adresse;End Date/Enddatum;Link/URL), nur die ersten drei sind Pflicht. Spalten sind positional, ein Header wird nicht umsortiert
- Datum `YYYY-MM-DD`, Uhrzeit `HH:MM` oder `HH:MM-HH:MM`; Ende < Start gilt als Overnight (nur ohne Enddatum)
- Spalte 6 (Adresse, v1.2.0) wird nur für Schema.org genutzt
- Spalte 7 (Enddatum, v1.3.0) muss `YYYY-MM-DD` und nach dem Startdatum sein, sonst wird sie ignoriert. Bei Range-Uhrzeit gilt `end_time` am Enddatum
- Spalte 8 (Link, v1.3.0) nur als absolute `http(s)://`-URL, alles andere wird verworfen

## Konventionen

- Modul-Slug/Blockname `dcsve/csv-events`, CSS-Klassen `dcsve_csv_events__*` (BEM-artig, keine `!important`), PHP-Namespace `DiviCsvEvents`, Text Domain `divi-csv-events`
- Minimum: WP 6.4, PHP 8.0, Divi 5.0, Node 18
- PHP folgt WordPress Coding Standards (Tabs), `includes/` nutzt 4 Spaces. Alle Ausgaben escapen, alle Strings über `__()`
- UI-Labels in `module.json` sind englisch, die Frontend-Strings (Monatsnamen, Buttons, Tabellenköpfe) sind deutsche Quellstrings hinter `__()`
- Neue Filter- oder Anzeigeoptionen gehören in `eventSettings` in `module.json` **und** in `module-default-render-attributes.json` sowie `types.ts`, dann in `RenderCallbackTrait.php` (`$display` bzw. `$settings`), `edit.tsx` (`DisplayFlags`) und `frontend.js` (`config.display`). Alle drei Renderer müssen dieselbe Sichtbarkeitslogik haben
- Das Projekt liegt in iCloud Drive. Wenn Composer Verzeichnisse entfernt und neu anlegt (`npm run zip` wechselt zwischen no-dev und dev), hinterlässt iCloud leere Ordner wie `vendor/phpunit/phpunit 2/` oder `… 3/` und teils auch leere Originalnamen. `gulpfile.js` packt deshalb mit `nodir: true` nur Dateien, leere Ordner können nicht ins ZIP geraten. Aufräumen auf der Platte: `find vendor -type d -empty -name "* [0-9]" -delete`
- Neue CSV-Spalten: `$accepted_headers` und `compact()` in `CsvParser.php`, `CsvEvent` in `types.ts`, dann die drei Renderer und ggf. `SchemaBuilder.php`. Bestehende CSVs mit weniger Spalten müssen weiter funktionieren
