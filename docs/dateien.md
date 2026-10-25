# Rolle jeder Datei

Nachschlagetabelle: welche Datei im Repo welche Aufgabe hat und welche PHP-/Builder-Dateien Gegenstücke zueinander sind.

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
