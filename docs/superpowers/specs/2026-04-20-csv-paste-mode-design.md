# CSV Paste Mode — Design Spec

**Datum:** 2026-04-20
**Status:** Approved (brainstorming phase)
**Kontext:** Divi CSV Events Module — Erweiterung der Datenquelle

## Ziel

Dem User zwei gleichwertige Wege anbieten, CSV-Daten in das Modul einzupflegen:

1. **File-Mode (bestand)** — CSV-Datei aus Media Library auswählen / hochladen
2. **Paste-Mode (neu)** — CSV-Text direkt ins Modul einfügen, inkl. komfortablem Modal-Editor für größere Listen

## Motivation

- **Use Case A** (End-User / Website-Redakteure): Manche User wollen keine Datei hochladen, sondern direkt Text eintragen. Besonders für schnelles Setup kleiner Event-Listen (5–20 Einträge).
- **Use Case B** (Komfort): Für den Testfall oder eine überschaubare Anzahl Termine ist die Datei-Pipeline (Excel → Export → Upload) übertrieben.
- **USP bleibt erhalten:** "CSV = Excel-kompatibel" → File-Mode bleibt Default und primärer Weg. Paste-Mode ist Ergänzung, kein Ersatz.

## Non-Goals

- Keine CSV-Auto-Korrektur oder Syntax-Highlighting.
- Kein "In Datei konvertieren"-Button.
- Keine Versionshistorie der pasteten Daten.
- Kein Import-Preview vor dem Speichern (Live-Preview im Builder reicht).
- Keine Migration bestehender Module — bestehende Instanzen bleiben im `file`-Modus mit bisherigem Verhalten.

## 1. Visual Builder UI

**Content-Tab → "Data"-Gruppe bekommt drei Felder (Reihenfolge):**

1. `CSV Source` — `divi/select` mit Optionen `CSV File` (Default) / `Paste CSV Data`
2. `CSV File` — `divi/upload` (wie heute), sichtbar nur bei Mode `file`
3. `CSV Data` — Custom Field `dcsve/csv-content-editor`, sichtbar nur bei Mode `paste`

**Conditional Visibility:** Über das `show`-Feature der Divi-5-Settings.

**Datenerhalt beim Moduswechsel:** Beide Felder behalten ihren Wert, auch wenn sie gerade ausgeblendet sind. Der Mode-Selector ist die alleinige Source of Truth für die Datenquelle. Kein Merge, keine Priority-Regel.

## 2. Modul-Attributes

Neue Attribute in `modules-json/csv-events-module/module.json`:

```jsonc
"csvSourceMode": {
  "type": "object",
  "default": {
    "innerContent": {
      "desktop": { "value": { "mode": "file" } }
    }
  },
  "settings": {
    "innerContent": {
      "groupType": "group-item",
      "item": {
        "groupSlug": "contentCsvSource",
        "priority": 5,
        "component": {
          "name": "divi/select",
          "type": "field",
          "props": {
            "options": {
              "file":  { "label": "CSV File" },
              "paste": { "label": "Paste CSV Data" }
            }
          }
        },
        "label": "CSV Source"
      }
    }
  }
}
```

```jsonc
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
        "show": { "csvSourceMode.innerContent.*.value.mode": "paste" },
        "component": {
          "name": "dcsve/csv-content-editor",
          "type": "field"
        },
        "label": "CSV Data"
      }
    }
  }
}
```

Das bestehende `csvSource`-Attribut bleibt unverändert, bekommt zusätzlich eine `show`-Condition auf `mode: "file"`.

**Defaults in `module-default-render-attributes.json`** werden entsprechend ergänzt.

## 3. Custom Settings-Field: Modal-Editor

**Neu:** `src/components/csv-events-module/csv-content-editor/index.tsx`

**Aufbau:**
- Label "CSV Data"
- Inline-Textarea (~10 Zeilen, monospace)
- Sekundär-Button "Edit in large window" → öffnet `<Modal>` via `@divi/modal`
- Character-Counter + Size-Indikator unten rechts: `"{bytes} / 100 KB"`

**Modal-Dialog:**
- Titel: "Edit CSV Data"
- Format-Hinweis als Subheader: `"Format: Date;Time;Title;Location;Description"`
- Großes `<textarea>` (~25 Zeilen, monospace, resizable-vertical)
- Footer: Event-Zähler + Size-Indikator links, `[ Cancel ] [ Apply ]` rechts
- Keyboard: `ESC` = Cancel, `Ctrl/Cmd+Enter` = Apply

**State-Flow:**
- Inline-Textarea: `onBlur` → ruft `onChange`-Callback des Field-Props (triggert Builder-Live-Preview)
- Modal: interner Draft-State, erst bei `Apply` → `onChange` des Field-Props
- `Cancel` verwirft Draft

**Registrierung:**
- Via `addFilter` am Divi-Hook `divi.moduleLibrary.fieldLibrary.registerFields` (Standard-Weg laut `_scaffold`)
- Registration in `src/index.ts`

**Dependencies:**
- `@divi/modal` (bereits als Webpack-External im Scaffold registriert)
- Kein zusätzliches npm-Package nötig

**Styling:**
- Eigene SCSS-Datei `src/components/csv-events-module/csv-content-editor/styles.scss`
- Klassen-Prefix `.dcsve-csv-editor__`

**Non-Goals:**
- Kein Syntax-Highlighting
- Kein Auto-Save / eigener Undo-Stack (Builder hat eigenes Undo)
- Keine Live-Validierung beim Tippen (Parse-Fehler erscheinen wie bei Datei-Upload im Error-Banner)

## 4. REST API & PHP-Renderer

### Endpoint `/divi-csv-events/v1/events`

Erweiterung des bestehenden Endpoints in `includes/RestApi.php`:

| Parameter | Mode | Status |
|-----------|------|--------|
| `csv_url` | file | bestand |
| `csv_content` | paste | **neu** |
| `period`, `period_count`, `count`, `show_past` | beide | bestand |

**Method-Handling:**
- `GET` bleibt Standard (für `csv_url`-Aufrufe)
- `POST` mit JSON-Body wird zusätzlich akzeptiert (für `csv_content` — vermeidet URL-Längen-Probleme)
- Beide Methoden registriert über `register_rest_route` mit `methods: 'GET, POST'`

**Resolution-Logik:**
```php
if (!empty($params['csv_content'])) {
    $events = CsvParser::parseString($params['csv_content']);
} elseif (!empty($params['csv_url'])) {
    $events = CsvParser::parseUrl($params['csv_url']);
} else {
    return new WP_Error('no_source', 'No CSV source provided');
}
```

### CsvParser-Refactoring (`includes/CsvParser.php`)

**Bestand:** `parse($url)` liest Datei von URL und parsed.

**Umbenennung + neue Methode:**
- `parseUrl(string $url): array` — liest Datei, delegiert Parsing an `parseCsvText()`
- `parseString(string $csvText): array` — **neu**, parsed Text direkt
- `parseCsvText(string $text): array` — **neu, private**, gemeinsame Low-Level-Routine mit BOM-Entfernung, Split, Header-Mapping, Row-Validation

Bestehende Aufrufer (RenderCallback, RestApi) werden entsprechend angepasst.

### PHP-Renderer (`RenderCallbackTrait.php`)

Dispatch basierend auf `csvSourceMode.innerContent.*.value.mode`:
- `file` → `CsvParser::parseUrl($csvSource.src)`
- `paste` → `CsvParser::parseString($csvContent.content)`

Der nachfolgende Render-Code (Periodenfilter, Count-Limit, View-Auswahl) bleibt unverändert.

### Caching (Transient API, 5 Min TTL)

- File-Mode: Cache-Key aus URL + Attachment-mtime
- Paste-Mode: Cache-Key aus `md5($csvContent)` — Content-Hash als natürlicher Cache-Key (invalidiert automatisch bei Änderung)

### Builder-Preview (`edit.tsx`)

`useEffect`-Hook wird mode-aware:
- `mode === 'file'` → `fetch GET` (wie heute)
- `mode === 'paste'` → `fetch POST` mit Body `{ csv_content, period, period_count, count, show_past }`
- Debounce bleibt bei 300 ms
- `mode` und `csvContent` werden zur Dependency-Liste hinzugefügt

## 5. Validierung & Edge Cases

### Size-Limits

- **Hard-Limit:** 100 KB CSV-Content (~2000 Event-Zeilen)
- Frontend-Check im Editor: visuelles Feedback + Apply-Button sperren ab Überschreitung
- Server-Check in REST: 413-Response bei Überschreitung
- **Soft-Warning** ab 50 KB: dezenter gelber Hinweis im Counter mit Empfehlung File-Upload

### Malformed CSV

- Bestehende Parser-Fehlerbehandlung wird wiederverwendet (REST liefert `error`-Response, `edit.tsx` zeigt `error`-State — existiert bereits)
- Paste-Mode zusätzlich: Zeilen-Referenz in Fehlermeldung ("Zeile 3: Ungültiges Datumsformat") sofern Parser das hergibt — minimaler Parser-Ausbau falls nötig

### Empty States

| Situation | Meldung |
|-----------|---------|
| `mode=file` + keine Datei | Bestehende Meldung "Bitte CSV-Datei hochladen" |
| `mode=paste` + leerer Content | **neu** "Bitte CSV-Daten einfügen (Content-Tab → CSV Data)" |
| `mode=paste` + Content ohne valide Zeilen | "Keine gültigen Events gefunden" |

### Sicherheit

- Kein `sanitize_textarea_field()` auf `csv_content` (würde Zeilenumbrüche/Semikolons zerstören)
- Stattdessen: `mb_check_encoding($content, 'UTF-8')` erzwingen, Länge prüfen, Parser operiert auf rohem String
- REST-Endpoint bleibt nonce-geschützt + `edit_posts` Capability
- Keine externen Requests im Paste-Mode → kein SSRF-Risiko

### i18n

Alle neuen Strings via `__()` / `esc_html__()` in Text-Domain `divi-csv-events`:

- "CSV Source", "CSV File", "Paste CSV Data"
- "CSV Data", "Edit in large window"
- "Cancel", "Apply"
- "Bitte CSV-Daten einfügen (Content-Tab → CSV Data)"
- "CSV zu groß (%s KB / max 100 KB). Bitte Datei-Upload verwenden."
- "Hinweis: Größere CSV-Daten — für Performance evtl. Datei-Upload erwägen."

## 6. Betroffene Dateien

| Datei | Änderung |
|-------|----------|
| `modules-json/csv-events-module/module.json` | neue Attribute `csvSourceMode` + `csvContent`, Conditional Visibility auf bestehendem `csvSource` |
| `modules-json/csv-events-module/module-default-render-attributes.json` | Defaults für neue Attribute |
| `src/components/csv-events-module/csv-content-editor/index.tsx` | **neu** — Custom Field + Modal |
| `src/components/csv-events-module/csv-content-editor/styles.scss` | **neu** — Editor/Modal Styles |
| `src/components/csv-events-module/types.ts` | neue Interface-Felder (`csvSourceMode`, `csvContent`) |
| `src/components/csv-events-module/edit.tsx` | Mode-Handling im Fetch-`useEffect`, POST bei Paste |
| `src/index.ts` | Custom-Field-Registration via `addFilter` |
| `includes/CsvParser.php` | Refactoring — `parseUrl()`, `parseString()`, `parseCsvText()` (private) |
| `includes/RestApi.php` | POST-Support, `csv_content` Parameter, Size-Validation |
| `modules/CsvEventsModule/CsvEventsModuleTrait/RenderCallbackTrait.php` | Mode-Dispatch |

## 7. Marketplace-Auswirkungen

- README erweitern: "Zwei Quell-Modi — Datei-Upload oder direkter Paste"
- Screenshot für Paste-Mode ergänzen (Marketplace-Listing-Seite)
- USP "Excel-kompatibel" bleibt unangetastet: File ist Default, Paste ist Ergänzung
- Keine Breaking Changes für bestehende Installationen: Default-Modus ist `file` und verhält sich wie bisher

## Offene Punkte (während Implementierung zu verifizieren)

- **Exaktes `show`-Condition-Syntax** in Divi 5 Module-Settings — der im Spec verwendete Pfad `csvSourceMode.innerContent.*.value.mode` ist eine begründete Annahme basierend auf dem Attribute-Layout. Falls Divi 5 ein anderes Schema (z.B. Funktionsreferenz oder Sub-Path-Ausdruck) erwartet, wird während der Implementierung entsprechend angepasst. Fallback: Beide Felder sichtbar lassen, Labels/Helptexte machen den aktiven Modus klar.
- **Custom-Field-Registration** — `addFilter('divi.moduleLibrary.fieldLibrary.registerFields', ...)` ist der Standard-Weg laut Scaffold; finale Signatur wird gegen das Divi-5-API während Implementierung geprüft.
