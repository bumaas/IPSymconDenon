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
  Netz über `tests/symcon_stubs.php`): friert Capabilities aller 112 Modelle,
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

- `tests/association_check.php` — **Assoziations-Vollständigkeit** (ohne Kernel/
  Netz, daher in der CI wirksam). Einige Profile lassen ihre Assoziationen von
  `updateProfileAccordingToCaps()` modellabhängig filtern; der Filter kann nur
  wegnehmen. Was ein Modell in `<Ident>_SubCommands` deklariert, der Katalog aber
  nicht kennt, verschwindet **still** und ist nicht auswählbar. Genau so fehlten
  bis Build 94 `Game1` (14 Modelle), `8K` (18) und `Dock` (9) beim Video Select
  sowie `Auto` (94 Modelle) beim Surround Mode.
  - Prüfen: `C:\php\php tests/association_check.php`, `--details` listet die
    betroffenen Modelle.
  - Beim Ergänzen einer Assoziation **nur anhängen, nie umnummerieren** — der
    Wert steht so in den Variablen der Bestandsinstallationen.

- `tests/capability_diff.php` — **Vorher-Nachher-Vergleich** im Klartext, für das
  Review von Capability-Änderungen:
  `C:\php\php tests/capability_diff.php --from <Ref|Datei> [--to <Ref|Datei>]`.
  Holt den Vorher-Stand aus `tests/golden/capabilities.json` des Refs; kennt der
  Ref die Datei noch nicht, wird ersatzweise ein temporärer `git worktree`
  aufgemacht. Informativ (Exit 0), Exit 1 nur bei Umgebungsfehlern.

## Offene Defekte im Empfangs- und HTTP-Pfad

Anders als die Punkte im nächsten Abschnitt sind das **Fehler, keine fehlenden
Features**. Sie wirken genau dann, wenn ohnehin etwas klemmt (Gerät aus, Netz
weg), und sind alle im Code nachgelesen (zuletzt geprüft: 2.30 build 94).
Sinnvoll als **ein eigener Build „Robustheit"**, nicht mit funktionalen
Änderungen gemischt.

1. **Null-Dereferenzierung im Telnet-Splitter.**
   `DenonAVRCP_API_Data::GetCommandResponse()` ist `?array` und liefert `null`,
   wenn einem Katalogeintrag das `ValueMapping` fehlt (einziges `return null`,
   im `default`-Zweig). `DenonSplitterTelnet::ReceiveData()` greift unmittelbar
   danach ungeprüft mit `$SetCommand['SurroundDisplay']` und
   `count($SetCommand['Data'])` zu — unter PHP 8 ist `count(null)` ein
   **TypeError** und bricht den Empfangspfad ab.
2. **Fragmentpuffer ohne Verfall.** `DenonSplitterTelnet::ReceiveData()` puffert
   Pakete, die nicht auf `\r` enden, über `GetBuffer`/`SetBuffer` — ohne Alterung
   und ohne Längenbegrenzung. Ein abgerissenes Telegramm bleibt dauerhaft liegen
   und wird jeder folgenden Antwort vorangestellt; die Instanz „vergiftet" sich
   still bis zum Neustart.
3. **Semaphore wird freigegeben, ohne sie zu halten.** In `Denon HTTP IO` rufen
   `GetStatus()` und zweimal `SendCommand()` auch im Zweig „Lock fehlgeschlagen"
   `unlock()` auf, und `unlock()` ruft bedingungslos `IPS_SemaphoreLeave` — ein
   Tick gibt damit die Sperre eines **anderen**, noch laufenden Ticks frei. Bei
   10-Sekunden-Timer und sechs blockierenden HTTP-Abrufen ist Überlappung der
   Regelfall.
4. **Die Fehlerursache wird weggeworfen.** `GetStatus()` fängt `Exception $exc`
   und wirft stattdessen `new Exception('SendJson failed')` — Meldung, Datei und
   Zeile der echten Ursache sind weg. Gleiches Muster in `SendCommand()`
   (`file_get_contents failed`, `GetStatus failed`).
5. **Keine Timeouts an den HTTP-Abrufen.** `DENON_StatusHTML::fetchXml()` nutzt
   `@file_get_contents($url)` ohne Stream-Context. Bei totem Host blockiert jeder
   der sechs Endpunkte bis `default_socket_timeout` (Standard 60 s) → bis zu
   ~6 Minuten pro Zyklus, und das innerhalb der Semaphore des Aufrufers.

**Warum das hier steht und nicht im Code:** Der Empfangspfad hat **kein**
Regressionsnetz — die Golden-Suite deckt den Sendeweg ab, `ReceiveData` steht
dort sogar auf der Ausschlussliste. Gerade dort ist die Fix-Dichte am höchsten:
`Denon Splitter Telnet/module.php` hat 110 Commits, davon rund 50 mit einem
Fix-Betreff. Fehler fallen dort also erst beim Anwender auf. Der wirksamste
flankierende Test wäre ein Golden über `GetCommandResponse()` mit einem Korpus
**echter** Telnet-Antwortzeilen — dafür braucht es einen Mitschnitt aus dem
Splitter-Debug, keine erfundenen Daten.

### Umsetzungsplan

Vorabklärung (2026-08-09): Der Empfangspfad ist mit der vorhandenen
Stub-Infrastruktur **testbar**. `DenonSplitterTelnet::ReceiveData()` ruft nur
`GetBuffer`, `SetBuffer`, `GetValue`, `SendDataToChildren`, `SendDebug` und
`Logger_Dbg` — alles vorhanden. Es fehlen genau zwei Dinge: `Get/SetBuffer` sind
in `tests/symcon_stubs.php` **No-Ops** (SetBuffer verwirft, GetBuffer liefert
immer `''`), und `IPS_SemaphoreEnter`/`IPS_SemaphoreLeave` sind gar nicht
gestubbt.

Zwei Commits, jeder für sich grün. Die Prüfroutine entsteht jeweils **vor** dem
Fix (lokal rot) und landet im selben Commit, damit die CI an keinem Punkt der
Historie rot ist.

**Erster Commit — Telnet-Empfangspfad (Defekte 1+2):**
- `tests/symcon_stubs.php`: `Get/SetBuffer` bekommen echten Speicher pro Instanz.
- `tests/receivepath_check.php` (neu): Abschnitt 1, siehe unten.
- `libs/DenonAVRCP_API_Data.php`: das `return null` wird zu `continue` — eine
  Antwort ohne `ValueMapping` überspringt *sich selbst* statt den ganzen Stapel;
  Rückgabetyp `?array` → `array`. Bewusst **nicht** „gib eine leere Struktur
  zurück" (schluckt still alles) und **nicht** nur ein Guard im Splitter
  (kuriert das Symptom).
- `Denon Splitter Telnet/module.php`: Fragmentpuffer mit Längenobergrenze und
  Alterung, Verwerfen mit Warnung; `protected function currentTime(): int` als
  Test-Naht (Muster: `GetInputsAVR`-Override im Golden-Harness).
- `.github/workflows/check.yml`, `CLAUDE.md`, `library.json`.

**Zweiter Commit — HTTP-Pfad (Defekte 3+4+5):**
- `tests/symcon_stubs.php`: zählende Semaphoren-Stubs, `IPS_SemaphoreEnter` per
  Testschalter auf `false` zwingbar.
- `Denon HTTP IO/module.php`: die drei `unlock()`-Aufrufe im Zweig „Lock
  fehlgeschlagen" entfallen; `throw new Exception('… failed', 0, $exc)` samt
  Originalmeldung im Text an allen drei Stellen.
- `libs/DENON_StatusHTML.php`: `fetchXml()` mit Stream-Context, Timeout als
  Klassenkonstante, gebaut in `protected function httpContext()` — die Naht, an
  der die Prüfroutine ohne Netz ansetzt.

**Nur Timeout, kein Early-Abort.** Eine ältere Notiz schlug vor, nach dem ersten
Fehlschlag abzubrechen; der Kommentar über `fetchXml()` sagt ausdrücklich das
Gegenteil (ein 404 einer nicht vorhandenen Zone ist normal). Der Worst Case sinkt
damit auf 6 × Timeout — bei 2 s also 12 s und weiterhin über dem 10-s-Timer. Das
gehört in die Commit-Message, nicht weggerundet.

**Prüfroutine `tests/receivepath_check.php`** (ohne Kernel, Netz und
Herstellerdateien; Exit 1 bei Abweichung, CI-tauglich):
1. *Telnet-Empfangspfad* über einen `SplitterHarness` mit gebastelten
   `{"Buffer":"<hex>"}`-Payloads: sauberer Stapel liefert die erwarteten Idents;
   eine Antwort ohne `ValueMapping` lässt die übrigen durch; ein unvollständiges
   Telegramm plus Reststück ergibt genau eine Antwort; ein Fragment jenseits der
   Längengrenze wird verworfen; ein überaltertes Fragment ebenso (deterministisch
   über `currentTime()`, nicht über die echte Uhr).
2. *Semaphorenbilanz*: `IPS_SemaphoreEnter` liefert `false`, `GetStatus()` und
   `SendCommand()` laufen in den Fehlzweig, `Leave`-Zähler muss `Enter`-Zähler
   entsprechen. Braucht keine Produktivänderung, um prüfbar zu werden.
3. *Fehlerursache*: geworfene Exception hat `getPrevious()` gesetzt und die
   Originalmeldung im Text.
4. *Timeout*: `httpContext()` liefert einen Context mit endlichem `timeout`
   unterhalb der Obergrenze, geprüft per `stream_context_get_options()`.

**Ausdrücklich nicht Teil davon:** das Golden über echte Telnet-Mitschnitte
(braucht einen Mitschnitt, erfundene Zeilen frieren nur die eigene Erwartung
ein); Logger-Trait, Attribut-Migration und additiver Capability-Mechanismus;
alles am Sendeweg, am Profilkatalog und an Capabilities (keine neuen Kommandos
oder Modelle); neue oder geänderte öffentliche Funktionen; Retry, Backoff oder
ein anderer Timer im HTTP-Polling; die `FormExpertParameters()`-Lücke und die
Aufräum-Routine für verwaiste Alt-Profile.

**Nachweise bei der Umsetzung:** dass die neue Routine vor dem jeweiligen Fix
wirklich rot ist (ein Test, der nie rot war, ist wertlos), und dass sich
`golden_regression` **nicht** rührt — Block A fasst den Sendeweg nicht an.

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
  Trigger TR1/TR2/TR3, SYREMOTE/SYPANEL-Lock) sowie neue Modelle
  (CINEMA 30, AV 10, Denon-S-Serie, X3300W, A1H, A110).
- Aus den Specs **Denon CY2026 V02** und **Marantz CY2025 V04** sind die Modelle
  seit **2.30 build 92** und die Kommandos `BTLEV`, `CLM`, `SYHPT`, `PSCEX`,
  `PSSURLEV`, `PSDACFIL`, Quick Select 6 seit **2.30 build 93** umgesetzt.
  Aus diesen beiden Specs stehen noch aus:
  - `ILB` (Illumination, 6 Werte) bei AV 20/AV 30 — bislang in keiner Liste.
  - `PSDACFIL` für **CINEMA 30** und **AV 10** — beide Modelle kennt das Modul
    nicht (siehe Modell-Liste oben).
  - `CVTTR` bei AV 20/AV 30 (die CINEMA 40 hat dieselbe Lücke).
- Quick Select 6 wird über die Capability-Arrays `MSQUICK_SubCommands` und
  `Z2QUICK_SubCommands` gefiltert (Muster: `PSDYNVOL_SubCommands`). Zone 3
  bleibt bewusst ungefiltert bei 0–5, weil kein Modell dort eine sechste Auswahl
  hat. Wer eine Capability dieser Art ergänzt, muss sie an drei Stellen
  eintragen: `class AVR`, `AVR::getCapabilities()` und `CAPABILITY_PROPERTIES`
  in `tests/inheritance_check.php` — der Prüfer schlägt sonst zu Recht Alarm.
  Namensfalle: der Array**name** folgt dem Profil-Ident (`Z2QUICK_SubCommands`),
  der **Inhalt** den Assoziationen — und die sind in allen Zonen die
  `MSQUICK*`-Konstanten.
- Die Spec-Marke `@10` heißt **unterstützt, sofern entsprechend konfiguriert**
  („Requires Amp assign = Zone2"), nicht „nicht unterstützt". Beim AVR-S980H
  trägt die gesamte Zone 2 diese Marke, auch die längst angebotenen Quick
  Selects 1–5 — sie deshalb nicht als Ausschlussgrund lesen.
- `SYHPT` (HDMI Hot Plug Test) ist eine reine Aktion: keine Statusabfrage
  (deshalb im `GetStates()`-Ausschluss des Telnet-Moduls), und die Quittung
  `SYHPT OK` steht in der Ignorierliste von `DenonAVRCP_API_Data`. Achtung bei
  künftigen `SY`-Kommandos: `SY` (Remote Lock) ist Präfix von `SYHPT` — sobald
  es ein `SY`-Profil gibt, muss `SYHPT` im Katalog davor stehen (wie
  `PSDEL`/`PSDELAY`).
- Die neuen Modelle erben ein `Tuner_Control`, das ihre Spec als nicht
  unterstützt markiert (`TM`, `TMAN`, `TPAN`) — genau wie die CINEMA 40, an der
  sie hängen. Ein leeres `Tuner_Control` gibt es bei **keinem** der Modelle,
  der Pfad ist also ungetestet; die Frage „welche Modelle haben wirklich einen
  Tuner?" gehört in einen eigenen Durchgang.
- Aus dem Code-Review zu Build 94 bewusst zurückgestellt:
  - **Kein additiver Mechanismus für Capability-Arrays.** Jede Neudeklaration
    schreibt die Elternliste ab (allein Build 93 rund 370 kopierte Zeilen). Ein
    `$PS_Commands_add`, das `AVR::getCapabilities()` mit der Elternliste
    zusammenführt, würde die ganze Fehlerklasse abräumen, die
    `tests/inheritance_check.php` heute nur nachträglich meldet. Großer,
    modellübergreifender Umbau — eigener Durchgang.
  - **`SYHPT` als Zustandsvariable modelliert.** Die Variable bekommt nie einen
    Wert zurück (keine Statusabfrage, Quittung ignoriert) und steht nach dem
    Neuladen wieder auf „High". Das ist dieselbe Bauart wie `ptNavigation` (MN),
    also konsistent, aber nicht schön. Ein `'noStatusRequest' => true` im
    Profilkatalog, das `GetStates()` auswertet, würde die Ausnahme an einer
    Stelle bündeln statt in drei Dateien (Katalogkommentar, Ident-Blacklist im
    Telnet-Modul, Ignorierliste im Splitter).
  - **`PSCLV`, `PSMODE`, `VSASP`** stehen bei X3800H/X3900H/X2900H in den
    Capabilities, obwohl die jeweilige Spec sie nicht (X3800H: ausdrücklich als
    nicht unterstützt) führt — geerbter Altbestand, gleiche Klasse wie der
    Tuner-Punkt oben.
  - **`MSQUICK1MEMORY`–`MSQUICK6MEMORY`** und `MSQUICKSTATE` sind unbenutzt.
    Entweder Quick-Select-Memory als Profil umsetzen oder den Block entfernen.
- Aus dem Code-Review zu Build 89 (Struktur, kein Defekt):
  - `Logger_Err`/`Logger_Warn` liegen byte-identisch in `libs/AVRModule.php`,
    `Denon Splitter Telnet` und `Denon HTTP IO` — ein `trait` in `libs/` (über den
    `DenonClass.php`-Aggregator geladen) würde das für alle sechs Module auflösen.
  - `InputMapping` und `AVRType` sind internes Buchhaltungswissen; der
    idiomatische Container wäre `RegisterAttributeString` (unsichtbar per
    Konstruktion, ganz ohne `IPS_SetHidden`). `Denon Splitter Telnet` registriert
    dieselben zwei Idents zudem **unversteckt** — die Bibliothek verhält sich also
    widersprüchlich. Umbau mit Migrationsbedarf.
  - `RegisterHiddenVariableString()` im HTTP-IO ist nicht testbar: die Stubs in
    `tests/symcon_stubs.php` kennen weder `IPS_GetObjectIDByIdent` noch
    `IPS_SetHidden`, und die Golden-Suite fährt `DenonAVRHTTP`, nicht
    `DenonAVRIOHTTP`.
- Die Baseline der Kettenprüfung (`tests/inheritance_baseline.json`) **duldet den
  Ist-Stand**, sie bestätigt ihn nicht: 556 Einzelverluste in 83 Klassen sind
  eingefroren. Sie sind überwiegend legitim (ein günstigeres Modell darf ein
  Feature nicht haben), aber nicht einzeln geprüft.
- Die fünf binären Marantz-`.xls` (2015, FY16–FY21) überspringt
  `tests/spec_check.php` weiterhin. Sie wurden am 2026-08-08 einmalig per
  Excel-COM ausgewertet (Ergebnis in der Commit-Message zu 2.29 build 87); zwei
  von ihnen nutzen ein drittes Tabellen-Layout mit der Kommandospalte
  `Command code` statt `COMMAND`, das der Leser in `spec_check.php` nicht kennt.

## Support-Kontext

Fehlerberichte kommen aus dem Symcon-Forum (community.symcon.de); Fixes als Beta
über `master` in den Store, Forum-Antworten auf Deutsch.
