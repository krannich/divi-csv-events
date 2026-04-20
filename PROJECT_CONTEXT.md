# Project Context — Divi CSV Events

## Entstehung & Hintergrund

Dieses Projekt entstand aus einem konkreten Bedarf: Ein Eventkalender für den Schützenverein von 1848 Schneverdingen, der auf WordPress mit Divi 5 läuft (Hosting: webgo Starter, 1 GB RAM). Die Anforderung war ein schlanker Kalender ohne Plugin-Bloat, mit CSV als Datenquelle, damit Vereinsmitglieder Termine in Excel pflegen und hochladen können.

Der funktionierende Prototyp (`reference/vereinskalender.php`) wurde als WordPress mu-plugin mit Shortcode gebaut und deckt bereits alle Features ab. Dieser wird jetzt in ein vollwertiges Divi 5 Custom Module umgebaut, um es auf dem Divi Marketplace anzubieten.

## Marketplace-Positionierung

**Name**: Divi CSV Events (Arbeitstitel)
**Preis**: $29–49 (Einmalkauf mit 1 Jahr Updates)
**Zielgruppe**: Vereine, Gemeinden, kleine Firmen, Gastro — alle, die Events einfach pflegen wollen ohne The Events Calendar o.ä.
**USP**: 
- CSV = Excel-kompatibel, kein DB-Backend, kein Custom Post Type
- 4 Ansichten in einem Modul
- Visuell konfigurierbar im Divi 5 Builder
- Leichtgewichtig (kein jQuery, kein Framework, Vanilla JS)
- DSGVO-freundlich (keine externen Requests, keine Cookies)

## Architektur-Entscheidungen

### Warum CSV statt Custom Post Type?
- Niedrigere Einstiegshürde: CSV in Excel bearbeiten → hochladen
- Kein WP-Admin-Login nötig für Redakteure
- Portabel: CSV kann überall geöffnet, bearbeitet, archiviert werden
- Performance: Eine Datei lesen ist schneller als DB-Queries
- Keine DB-Migration bei Plugin-Deaktivierung

### Warum Semikolon als Trennzeichen?
- Excel speichert in DE-Locale standardmäßig mit Semikolon
- Komma in deutschen Texten (Beschreibungen) würde Parsing brechen
- Auto-Detection wäre fragil — lieber eine klare Konvention

### Warum REST API für den Builder?
- Divi 5 Visual Builder rendert mit React — kein PHP-Zugriff
- edit.tsx muss die Events per fetch() laden
- REST Endpoint parsed die CSV serverseitig und liefert JSON
- Caching: Transient API (5 Min) um nicht bei jeder Builder-Aktion die CSV zu parsen
- Nonce-gesichert, nur für eingeloggte Benutzer mit `edit_posts` Capability

### Frontend-Rendering: PHP, nicht JS
- Die vier Ansichten werden serverseitig in PHP gerendert (RenderCallbackTrait.php)
- JavaScript nur für: View-Switcher, Period-Filter, Slider-Navigation
- Grund: SEO-freundlich, kein Layout-Shift, funktioniert ohne JS (Graceful Degradation)
- Die JS-Logik filtert/togglet nur DOM-Elemente die bereits gerendert sind

### CSS-Architektur
- Prefix `.dcsve-` für alle Klassen
- Keine externen Dependencies (kein Bootstrap, kein Tailwind)
- CSS Custom Properties für Theme-Integration (`--dcsve-accent`, `--dcsve-radius` etc.)
- Divi's eigene Spacing/Typography-Settings werden respektiert
- Dark Mode: Nicht nötig (Divi-Themes kontrollieren das selbst)

## Die vier Ansichten im Detail

### Liste (`view="list"`)
- Gruppiert nach Monat
- Pro Event: Datum (Wochentag, Tag. Monat), Uhrzeit, Titel (fett), Ort + Beschreibung
- Trennlinien zwischen Events
- Kompakteste Darstellung, gut für Sidebar

### Kacheln (`view="cards"`)
- Gruppiert nach Monat
- CSS Grid: `repeat(auto-fill, minmax(220px, 1fr))`
- Pro Kachel: Datums-Badge (Tag + Monat in Akzentfarbe), Titel, Zeit + Ort, Beschreibung (einzeilig, ellipsis)
- Hover: Border-Highlight

### Tabelle (`view="table"`)
- Klassische Tabelle mit Spalten: Datum, Uhrzeit, Veranstaltung, Ort, Details
- Monatstrennzeilen als Zwischenüberschriften
- Responsive: Horizontal scrollbar auf Mobile
- Gut für Ausdrucke

### Slider (`view="slider"`)
- Horizontaler Scroll-Track mit Snap
- Karten ähnlich wie Kacheln-Ansicht, aber horizontal
- Desktop: Vor/Zurück-Buttons erscheinen nur auf Hover
- Mobile: Touch-Swipe nativ, keine Buttons (`@media (hover: none)`)
- Kein externes Slider-Plugin (pure CSS scroll-snap + JS scrollBy)

## Filter-Logik

### Zeitraum-Filter
- Woche: +7 Tage ab heute
- Monat: +1 Monat ab heute
- Quartal: +3 Monate ab heute
- Jahr: +1 Jahr ab heute
- Alle: Kein Zeitlimit
- Vergangene Events werden per Default ausgeblendet

### Interaktion
- Filter-Buttons rendern alle als HTML, JS togglet Sichtbarkeit
- Kein AJAX-Reload — alles client-seitig
- URL-Parameter wären Phase 2 (Deep-Linking auf gefilterte Ansicht)

## Divi 5 Module API — Technische Details

### Module Registration (module.json)
```json
{
  "name": "CSV Events",
  "slug": "dcsve_csv_events",
  "category": "module",
  "icon": "csv-events",
  "d4Equivalent": null,
  "hasStyles": true,
  "parentModule": null,
  "childModule": null
}
```

### Settings-Struktur (settings-content.tsx)
Divi 5 nutzt ein deklaratives Settings-Format:
- `upload` field für CSV (Media Library)
- `text` field für externe CSV-URL
- `select` für Period (week/month/quarter/year/all)
- `range` für Count (0-50)
- `yes_no` für show_past
- `text` für heading

### Frontend-Rendering (CsvEventsModule.php)
- Extends `Divi\Module\Module` (Divi 5 base class)
- Nutzt Traits für Render, Styles, Classnames
- `render_callback()` ist der zentrale Render-Entry-Point
- Ruft `CsvParser::parseUrl()` (Datei-Modus) oder `CsvParser::parseString()` (Paste-Modus) auf und rendert HTML basierend auf den Settings

### Visual Builder Preview (edit.tsx)
- React Component, rendert im Builder
- Fetcht Events via REST API bei Mount und Settings-Änderung
- Zeigt Loading-State während Fetch
- Rendert dieselben vier Ansichten wie das PHP-Frontend (als React Components)
- Muss nicht 1:1 identisch sein, aber visuell nah dran

## Referenz: Shortcode-Prototyp

Die Datei `reference/vereinskalender.php` enthält den vollständig funktionierenden Prototyp mit:
- CSV-Parser mit BOM-Handling und Semikolon-Delimiter
- Vier Render-Funktionen (renderList, renderCards, renderTable, renderSlider)
- Zeitraum-Filterung
- Responsive CSS
- Touch-Support
- Hover-basierte Slider-Navigation

Diese Logik wird aufgeteilt in:
- `CsvParser.php` (PHP-Parsing)
- `RenderCallbackTrait.php` (HTML-Ausgabe)
- `frontend.css` (Styles)
- `edit.tsx` (Builder-Preview als React-Portierung der Render-Logik)

## Schema.org Structured Data (v1.2.0+)

Events werden als JSON-LD (`<script type="application/ld+json">`) am Ende des Module-HTMLs ausgegeben, mit einem Schema.org `@graph` von `Event`-Objekten. Google Rich Results und LLMs (ChatGPT/Claude/Gemini) konsumieren diese Daten strukturiert.

- **Scope:** Genau die server-gerenderte Event-Liste (Period/Count/show_past-Filter respektiert). Keine Divergenz zwischen sichtbarem Content und Schema.
- **Organizer:** Optional pro Modul (`organizerName` + `organizerUrl` in den Settings). Wird als `Organization` emittiert wenn Name gesetzt.
- **Address-Parsing:** Regex `/^(.+?),\s*(\d{5})\s+(.+)$/` extrahiert Straße/PLZ/Stadt; Fallback auf `streetAddress`. `addressCountry` hart auf `DE`.
- **Zeit-Logik:** `HH:MM-HH:MM` → Range mit Overnight-Detection (Ende < Start → Folgetag). Einzelzeit ohne Range → +3h Default-Dauer. Keine Uhrzeit → nur Datum.
- **Toggle:** Über `schemaEnabled` (Default on) abschaltbar, falls dieselbe Seite anderswo Event-Schemas ausgibt.
- **XSS-Schutz:** `JSON_HEX_TAG` in der Encodierung verhindert `</script>`-Breakout bei böswilligen Event-Strings.
- **Implementierung:** `includes/SchemaBuilder.php` (pure Klasse, 18 Unit-Tests).

## Roadmap

### Phase 1 — MVP (Marketplace Launch)
- Divi 5 Module mit allen vier Ansichten
- Content + Design Settings im Visual Builder
- CSV Upload via Media Library
- REST API für Builder Preview
- Frontend PHP Rendering
- Responsive CSS
- README + Screenshots für Marketplace

### Phase 2 — Erweiterungen
- Google Sheet URL als CSV-Quelle
- Kategorie-Spalte mit Filter-Chips
- iCal-Export Button
- "Zum Kalender hinzufügen" Links (Google Calendar, Apple, Outlook)
- URL-Parameter für Deep-Linking auf Filter
- Divi 4 Backward-Compatibility Modul

### Phase 3 — Premium
- Wiederkehrende Termine (RRULE-Syntax oder einfache Wiederholung)
- Countdown-Widget zum nächsten Event (eigenständiges Mini-Modul)
- ~~Schema.org Event Markup (JSON-LD)~~ → in v1.2.0 umgesetzt
- Mehrsprachigkeit (Polylang/WPML-kompatibel)
- Custom Fields / zusätzliche CSV-Spalten
