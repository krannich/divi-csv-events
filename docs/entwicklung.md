# Lokale Entwicklungsumgebung

Details zur lokalen WordPress-Testumgebung (`wpup`/WordPress Playground).

- `wpup` nutzt `wp-playground-cli start` (global installiert). Die Site wird pro Verzeichnis unter `~/.wordpress-playground/sites/<hash>/` gespeichert und überlebt Neustarts; `wpup --reset` setzt sie zurück. Login `admin`/`password`, PHP 8.0 wie unser Minimum (`WP_PHP=8.3 wpup` für andere Versionen). Divi muss einmalig als Theme-ZIP hochgeladen werden. Pretty Permalinks sind aus, REST-Routen also über `/?rest_route=/divi-csv-events/v1/events`.
