# CSV-Format

Vollständige Spezifikation des CSV-Formats, das das Plugin einliest (Spalten, Header, Datums-/Zeitformate, Sonderregeln).

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
