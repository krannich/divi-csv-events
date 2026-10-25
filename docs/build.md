# Build-Artefakte und ZIP-Paketierung

Was Webpack und Gulp erzeugen, wo es geladen wird und warum der ZIP-Aufbau für den Update-Checker wichtig ist.

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
