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
- Seit 1.7.4 gleicher Kartenaufbau wie Kacheln: Badge links, rechts Titel, darunter Uhrzeit/Ort, Beschreibung, Button
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

## Enddatum und Link (v1.3.0+)

Zwei weitere optionale CSV-Spalten, positional nach der Adresse:

- **Spalte 7 `Enddatum` / `End Date`:** mehrtägige Termine. Ein Termin verschwindet erst, wenn das Enddatum vorbei ist (`CsvParser::last_day()` in beiden Filtern, gespiegelt in `frontend.js`). Anzeige als Zeitraum in Liste/Tabelle, als Bereichs-Badge in Kacheln/Slider. Schema.org `endDate` = Enddatum (+ `end_time` bei Range). Ungültige oder nicht nach dem Start liegende Werte werden beim Parsen verworfen.
- **Spalte 8 `Link` / `URL`:** externe URL, nur absolute `http(s)://`-Adressen. Ausgabe als Button „Weitere Informationen“ (`.dcsve_csv_events__link`, Akzentfarbe, `target="_blank"`) in allen vier Views, Schema.org `url`.

Außerdem seit 1.3.0: `languages/` mit `.pot` und `en_US`-Übersetzung, `load_plugin_textdomain()`, und das Frontend-JS bekommt alle sichtbaren Strings aus PHP über `dcsveI18n`, statt sie hart deutsch zu halten.

## Anzeige-Optionen (v1.4.0+)

Content-Tab-Gruppe „Display“ mit sechs Toggles (alle Default an): Zeit, Ort, Beschreibung, Link-Button, Monatsüberschriften, Tabellenkopf. Sie wirken in allen vier Views, wo das Element vorkommt; in der Tabelle verschwindet die jeweilige Spalte. „Details“ gibt es nur, wenn Beschreibung oder Link aktiv ist. Ausgeblendet wird konsequent, auch wenn die CSV Daten hätte. Datum und Titel bleiben immer sichtbar. Vor dem Ort steht eine Stecknadel als Inline-SVG (`currentColor`, kein Emoji).

## Datum-Darstellung (v1.5.0+)

- **Wochentag-Toggle** (`showWeekday`) in der Display-Gruppe, wirkt in Liste und Tabelle.
- **Getrennte Typografie:** Das Datum besteht aus drei Spans (Wochentag, Tag, Monat) mit eigenen Divi-Font-Gruppen „Weekday Text“, „Day Text“, „Month Text“. „Date Text“ bleibt die Basis für den ganzen Datumsblock. Kacheln und Slider tragen dieselben Klassen am Badge.
- **Datum-Layout** (`dateLayout`: `inline` | `stacked`), nur Liste und Tabelle. Gestapelt zeigt pro Datum einen Block „Mo / 16 / Sep“, der Tag standardmäßig größer und fett. Bei Enddatum stehen zwei Blöcke nebeneinander mit vertikal zentriertem Strich (v1.5.1). Die Badges in Kacheln und Slider bleiben unverändert.

## Link-Button (v1.6.0+)

Eigenes Divi-Element `linkBtn` mit den Design-Gruppen Text, Background, Border (inkl. Radius), Spacing und Box Shadow, jeweils mit Hover und Responsive. Standard bleibt der Outline-Button in Akzentfarbe. Die Beschriftung ist zentral pro Modul überschreibbar („Link Button Text“ in der Display-Gruppe); leer nutzt den übersetzten Standard. Bewusst keine CSV-Spalte für den Text: zentral ist pflegeleichter und übersetzbar.

## Tabelle auf Mobilgeräten (v1.6.1+)

Feld „Table Layout“ in der Display-Gruppe: „Table“ (Default, scrollt bei Bedarf horizontal), „Date left, rest stacked“ (Datum als linke Spalte, übrige Zellen daneben untereinander, seit 1.6.3, gedacht für Tablet) oder „Stacked columns“. Seit 1.6.2 responsiv: pro Divi-Viewport einstellbar, mit Vererbung nach unten, also z. B. Tabelle am Desktop und ab Tablet gestapelt. Gestapelt stehen alle Zellen einer Zeile untereinander, die Kopfzeile verschwindet, Monatszeilen bleiben als Trenner. Reines CSS über Modifier-Klassen je Breakpoint-Band, kein Markup-Unterschied.

## Druckansicht (v1.7.0+)

Display-Gruppe: „Show Print Button“ (Default aus), „Print Button Position“ (oben bei den Filtern, unten unter den Terminen, oder beides) und „Print View“ (Tabelle, Liste, Kacheln; Default Tabelle). Der Button öffnet ein neues Fenster mit der **aktuell gefilterten Auswahl**, vergangene Termine werden immer entfernt, und startet den Browser-Druckdialog. Kein PDF-Export. Das Druckdokument lädt das Plugin-CSS und ergänzt A4-Print-CSS (11 px Grundschrift, `@page A4 15mm`, kein Umbruch innerhalb einer Zeile/Karte, Link-Buttons ausgeblendet, Farben schwarz). Überschrift des Moduls als Titel, darunter Zeitraum und „Stand: Datum“. Welche Felder gedruckt werden, steuern eigene Toggles („Print: Site Logo/Site Title/Weekday/Time/Location/Description/Month Headings/Table Header/Link URL“), unabhängig von den Bildschirm-Toggles; Links erscheinen im Druck als Klartext-URL, standardmäßig aus.

Offen: Wie „nächstes Jahr“ eingebunden wird. Heute zeigt „Jahr“ das Kalenderjahr ab heute; `periodCount` 2 deckt dieses und nächstes Jahr ab, als Filter-Chip gibt es das noch nicht.

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
