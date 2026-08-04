# IPSymconDenon — Projekt-Hinweise

Bibliothek „Denon/Marantz AV Receiver" (Store: „Denon & Marantz", fonzo.ipsymcondenon)
mit 6 Modulen auf Basis von `IPSModuleStrict`. Ursprünglich von Fonzo (Upstream:
Wolbolar/IPSymconDenon), gepflegt von bumaas.

## Struktur

- `Denon AVR HTTP/`, `Denon AVR Telnet/` — Geräte-Module; erben von `AVRModule`
  (in `DenonClass.php`), registrieren keine eigenen Variablen.
- `Denon Discovery/` — Konfigurator (SSDP-Suche).
- `Denon HTTP IO/` — I/O-Modul (HTTP-Polling), `Denon Splitter HTTP/`,
  `Denon Splitter Telnet/` — Splitter.
- `DenonClass.php` (Repo-Wurzel) — **Aggregator**: lädt `AVRModels.php` + alle
  Klassen unter `libs/` in der ursprünglichen Deklarationsreihenfolge (statische
  Initialisierer werden lazy aufgelöst — Reihenfolge nicht „reparieren").
- `libs/` — die eigentliche Klassenbibliothek: `AVRModule.php` (Basisklasse:
  Variablen-Registrierung, Presentations, Formularteile, RequestAction-Kern,
  `sendMappedValue(Name)`-Helfer, Zonen-Prädikate), `DENONIPSProfiles.php`
  (Katalog der ~174 Variablendefinitionen inkl. `$order` und Capability-
  Filterung), `DENON_API_Commands.php`, `DENON_StatusHTML.php`,
  `DenonAVRCP_API_Data.php`, `DENONIPSVarType.php`, `DENON_HTTP_Interface.php`.
- `DenonAVR.php` / `MarantzAVR.php` — Modellklassen (je Modell Zonen/Fähigkeiten),
  `AVRModels.php` — Capabilities-Register (`AVRs`, `AVR`).
- `library.json` — Version/Build/Date; Build-Konvention siehe globale CLAUDE.md.

## Darstellungen (Presentations)

Variablen bekommen ihre Darstellung zentral über `AVRModule::GetVariablePresentation()`
(`DenonClass.php`) mit `VARIABLE_PRESENTATION_*`-GUIDs — **keine Legacy-Profile**
(`IPS_CreateVariableProfile` darf nicht wieder eingeführt werden). Associations werden
modellabhängig gefiltert (`updateProfileAccordingToCaps()`) bzw. dynamisch aus der
AVR-XML gebaut (`SetInputSources()`).

## Texte pflegen

Die Formulare werden überwiegend **dynamisch** in PHP gebaut (`GetConfigurationForm()`
in den module.php, gemeinsame Teile in `DenonClass.php`); nur `Denon HTTP IO` hat eine
statische form.json.

1. Captions/Labels immer **englisch** als Schlüssel im Code bzw. in form.json.
2. Deutsche Übersetzung in die `locale.json` des jeweiligen Moduls; Texte aus
   `DenonClass.php`-Formularteilen müssen in **beiden** AVR-Modulen übersetzt sein.
3. `C:\php\php tests/check_locale.php` muss grün sein (läuft auch in der CI);
   das Skript prüft auch `'caption' => '…'`-Literale im PHP-Code.

**Bewusste Ausnahme:** die ~174 zusammengesetzten Checkbox-Captions
`$caption . ' (' . $command . ')'` (`AVRModule::getTypeItem()`) bleiben englisch —
Composite-Strings sind über locale.json nicht übersetzbar.

## Tests

- `tests/check_locale.php` — Übersetzungs-Vollständigkeit (siehe „Texte pflegen").
- `tests/golden_regression.php` — **Golden-File-Regressionstest** (läuft ohne Kernel/
  Netz über `tests/symcon_stubs.php`): friert Capabilities aller 107 Modelle,
  Profilkatalog, Presentations, Variablen-Registrierung, alle ~150 Telnet-Wrapper-
  Buffer und die Konfigurationsformulare als `tests/golden/*.json` ein.
  - Prüfen: `C:\php\php tests/golden_regression.php` (auch in der CI).
  - `--update` **nur** nach bewusst gewollter Verhaltensänderung ausführen und die
    Golden-Diffs im Commit reviewen — nie um einen roten Test „wegzudrücken".
  - `--dump <Modell>` schreibt Voll-Dumps nach `tests/dump/` (gitignored) zum
    Diff-Debugging bei reinen sha256-Abweichungen.
  - Die ursprünglich mit Build 76 eingefrorenen Altfehler wurden in **Build 77**
    gegen die Goldens gefixt (CVFDR/CVSDL-Sendeprefixe, ToneCTRL-Ident-Mismatch,
    `$order`-Duplikate, `RegisterVariables_OLD`-Fatal im HTTP-Modul — letzterer
    seither per `registration_http.json` mit abgesichert). Die Goldens spiegeln
    das korrigierte Verhalten.

## Bekannte offene Punkte (bewusst zurückgestellt)

- `Denon AVR HTTP` bindet `FormExpertParameters()` nicht ein — die Property
  `WriteDebugInformationToLogfile` ist dort per Formular unerreichbar (Telnet hat sie).
- Keine Aufräum-Routine für verwaiste Alt-Profile aus Bestandsinstallationen
  (vor der Presentations-Umstellung angelegt).
- Spec-Konformitäts-Check gegen die Hersteller-Protokoll-Excels unter
  `X:\Denon_Marantz` (u. a. `Marantz_FY23-CY2022_AV_CINEMA_PROTOCOL_V04.xlsx`)
  steht noch aus — Skript soll lokal lesen, Excels nicht committen.

## Support-Kontext

Fehlerberichte kommen aus dem Symcon-Forum (community.symcon.de); Fixes als Beta
über `master` in den Store, Forum-Antworten auf Deutsch.
