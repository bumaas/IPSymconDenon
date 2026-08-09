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

## Schalten: `RequestAction` statt eigener Wrapper

Symcon bietet seit 5.0 die globale Funktion `RequestAction(int $VariablenID, mixed $Wert)`;
paresy hat sie 2018 ausdrücklich als Ersatz für hardwarespezifische Schaltfunktionen
eingeführt. Die Voraussetzung erfüllt das Modul: `AVRModule` ruft `EnableAction()` für jede
schaltbare Statusvariable (`libs/AVRModule.php:461`). **Für reines Zustandsschalten sind
eigene öffentliche Wrapper damit überholt.**

- **Neue** öffentliche Funktionen nur noch, wenn `RequestAction` den Fall strukturell nicht
  abbilden kann: keine Statusvariable (z. B. Menü- und Netzwerk-Navigation), mehrere
  Parameter oder ein Rückgabewert.
- **Bestehende, funktionierende** Wrapper bleiben. Sie zu entfernen wäre ein Breaking Change
  an der Skript-API und damit ein Major-Sprung — sie sind nicht deprecated.
- **Ausnahme:** nachweislich defekte Wrapper dürfen entfernt statt repariert werden, sofern
  die Fähigkeit über `RequestAction` erreichbar bleibt. So geschehen in **2.30 build 91**
  (`CinemaEQ`, `StageWidth`, `StageHeight`, `RecSelect` — alle vier konnten nie erfolgreich
  aufgerufen werden; dazu `Dimmer` aus der eintägigen Beta 2.29 #88).
- Die Funktionsreferenz in `docs/de/README.md` und `docs/en/README.md` beginnt deshalb mit
  `RequestAction` als empfohlenem Weg; die Wrapper stehen dahinter als das, wofür sie noch
  gebraucht werden.
- Abgesichert sind alle Wrapper über `tests/golden/wrappers.json` (Sende-Buffer je Funktion) —
  ein entfallener oder umbenannter Wrapper fällt dort sofort auf.

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
  geprüft. Der Abgleich läuft über Kommando-Präfixe: `DIM` deckt pauschal alle
  Untervarianten ab (`DIM BRI`, `DIM SEL`, …), einzelne fehlende Werte sieht das
  Skript also nicht.

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
  CINEMA-Klasse). Die **DIM-Direktwahl** ist seit **2.29 build 87** umgesetzt
  (68 statt 19 Modelle). Noch offen („Paket 2/3"): fehlende Kommandos ergänzen
  (VSMONI-Direktwahl, PSIMAX-Gruppe, PSDIRAC per V03-Spec für X3800H/X4800H,
  Trigger TR1/TR2, SYREMOTE/SYPANEL-Lock) sowie neue Modelle
  (CINEMA 30, AV 10, Denon-S-Serie, X3300W, A1H, A110).
- Die Baseline der Kettenprüfung (`tests/inheritance_baseline.json`) **duldet den
  Ist-Stand**, sie bestätigt ihn nicht: 554 Einzelverluste in 82 Klassen sind
  eingefroren. Sie sind überwiegend legitim (ein günstigeres Modell darf ein
  Feature nicht haben), aber nicht einzeln geprüft.
- Die fünf binären Marantz-`.xls` (2015, FY16–FY21) überspringt
  `tests/spec_check.php` weiterhin. Sie wurden am 2026-08-08 einmalig per
  Excel-COM ausgewertet (Ergebnis in der Commit-Message zu 2.29 build 87); zwei
  von ihnen nutzen ein drittes Tabellen-Layout mit der Kommandospalte
  `Command code` statt `COMMAND`, das der Leser in `spec_check.php` nicht kennt.
- `tests/check_locale.php` hängt für die AVR-Module nur `DenonClass.php` an
  (`$sharedPhpFiles`, `:33-36`). Seit der `libs/`-Zerlegung (Build 81) ist das ein
  reiner Aggregator — die gemeinsamen `'caption'`-Literale aus `libs/AVRModule.php`
  werden nicht mehr geprüft. Der Exit-Code bleibt 0, die Abdeckung ist also still
  verloren gegangen; der Fix ist ein Einzeiler (Dateiliste erweitern), zieht aber
  vermutlich verwaiste de-Schlüssel nach sich.
- Die **Live-Verifikation der DIM-Direktwahl am Gerät** steht aus: ob der AVR nach
  `DIM x` von sich aus antwortet, ist ungeprüft. Falls nicht, `DIM` in
  `STATUS_REQUEST_AFTER_SEND` (`Denon AVR Telnet/module.php:214`) aufnehmen.
- **`ILB`** (Marantz „Illumination", Werte AUTO/BRI/DIM/DAR/OFF/SEL) ist bewusst nicht
  umgesetzt. Es ist — anders als `DIM` — echt modellabhängig (FY23: nur AV 10 und
  CINEMA 40; CY2023: nur CINEMA 30) und bräuchte deshalb eine eigene Capability.
  Ebenfalls offen gelassen: `DIM SEL` (Toggle), das nicht in eine Enumeration passt.

## Support-Kontext

Fehlerberichte kommen aus dem Symcon-Forum (community.symcon.de); Fixes als Beta
über `master` in den Store, Forum-Antworten auf Deutsch.
