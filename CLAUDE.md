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
  - Der Bereich `capabilities.json` hält dieselben Capabilities zusätzlich im
    **Klartext** (sortierte Listen). `models.json` zeigt nur *dass* sich etwas
    geändert hat, `capabilities.json` macht im `git diff` sichtbar *was* — beide
    entstehen im selben Lauf und können nicht auseinanderlaufen.
  - Die ursprünglich mit Build 76 eingefrorenen Altfehler wurden in **Build 77**
    gegen die Goldens gefixt (CVFDR/CVSDL-Sendeprefixe, ToneCTRL-Ident-Mismatch,
    `$order`-Duplikate, `RegisterVariables_OLD`-Fatal im HTTP-Modul — letzterer
    seither per `registration_http.json` mit abgesichert). Die Goldens spiegeln
    das korrigierte Verhalten.

- `tests/spec_check.php` — **Spec-Konformitäts-Check** gegen die Hersteller-
  Protokoll-Excels (lokal unter `X:\Denon_Marantz` bzw. Env `DENON_SPEC_DIR`,
  nicht committet; ohne Pfad sauberer Skip). Aufruf:
  `C:\php\php -d extension=zip tests/spec_check.php [--model <Name>] [--details]`.
  Informativer Report (Exit 0): Richtung A Modul→Spec (OK / laut Spec nicht
  unterstützt / keine Spec-Zeile), Richtung B Spec→Modul (fehlende Kommandos).
  Wertebereiche und die alten binären `.xls` (Marantz FY16–FY21) werden nicht
  geprüft.

- `tests/inheritance_check.php` — **Kettenprüfung** (ohne Kernel/Netz/Spec-Dateien,
  daher als einziges Werkzeug dieser Art auch in der CI wirksam). Die
  Capability-Arrays der Modellklassen kennen **keinen Merge**: eine Neudeklaration
  ersetzt die Elternliste vollständig, wodurch still ein Kommando wegfallen kann
  (so verlor `Denon_AVR_X2700H` den Dimmer `DIM`, und Build 85 erzeugte denselben
  Bruch neu bei `Denon_AVR_X4700H`).
  - Prüfen: `C:\php\php tests/inheritance_check.php` (auch in der CI).
  - Rund 200 Verluste sind Bestand und meist legitim; sie liegen als **Baseline**
    in `tests/inheritance_baseline.json`. Rot wird nur ein *neuer* Verlust.
  - `--update` nur nach bewusster Prüfung; den Baseline-Diff im Commit reviewen.
  - `--command DIM` zeigt die Abdeckung eines Kommandos samt Deklarationsstellen
    und Erbengemeinschaften — die Sicht, die man zum Ergänzen von Capabilities
    braucht. `--model` / `--area` filtern die Anzeige.

- `tests/capability_diff.php` — **Vorher-Nachher-Vergleich** im Klartext, für das
  Review von Capability-Änderungen:
  `C:\php\php tests/capability_diff.php --from <Ref|Datei> [--to <Ref|Datei>]`.
  Holt den Vorher-Stand aus `tests/golden/capabilities.json` des Refs; kennt der
  Ref die Datei noch nicht, wird ersatzweise ein temporärer `git worktree`
  aufgemacht. Informativ (Exit 0), Exit 1 nur bei Umgebungsfehlern.

## Bekannte offene Punkte (bewusst zurückgestellt)

- `Denon AVR HTTP` bindet `FormExpertParameters()` nicht ein — die Property
  `WriteDebugInformationToLogfile` ist dort per Formular unerreichbar (Telnet hat sie).
- Keine Aufräum-Routine für verwaiste Alt-Profile aus Bestandsinstallationen
  (vor der Presentations-Umstellung angelegt).
- Befunde des Spec-Checks (tests/spec_check.php, Stand 2026-08): Die
  Zone-3-/PV-Video-Bereinigung („Paket 1") ist seit **2.28 build 84** umgesetzt
  (16 Modelle, Overrides an den Ketten-Köpfen X2400H/X3400H bzw. je
  CINEMA-Klasse). Noch offen („Paket 2/3"): fehlende Kommandos ergänzen
  (VSMONI-Direktwahl, PSIMAX-Gruppe, PSDIRAC per V03-Spec für X3800H/X4800H,
  Trigger TR1/TR2, DIM-Direktwahl, SYREMOTE/SYPANEL-Lock) sowie neue Modelle
  (CINEMA 30, AV 10, Denon-S-Serie, X3300W, A1H, A110).
- Die Baseline der Kettenprüfung (`tests/inheritance_baseline.json`) **duldet den
  Ist-Stand**, sie bestätigt ihn nicht: 556 Einzelverluste in 83 Klassen sind
  eingefroren, darunter die beiden bekannten DIM-Brüche `Denon_AVR_X2700H`
  (`DenonAVR.php:1045`) und `Denon_AVR_X4700H` (`DenonAVR.php:2417`). Sie
  verschwinden aus der Baseline, sobald der DIM-Fix aus `SPEC-Vererbung.md`
  bzw. `SPEC.md` umgesetzt wird. Planungsgrundlage für beides:
  `SPEC-Vererbung.md` (Werkzeuge) und `SPEC.md` (fachlicher Fix).

## Support-Kontext

Fehlerberichte kommen aus dem Symcon-Forum (community.symcon.de); Fixes als Beta
über `master` in den Store, Forum-Antworten auf Deutsch.
