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

- `tests/receivepath_check.php` — **Verhaltensprüfung des Telnet-Empfangspfads**
  (ohne Kernel, Netz und Herstellerdateien, daher in der CI wirksam). Die
  Golden-Suite deckt den Sendeweg ab; `ReceiveData` steht dort sogar auf der
  Ausschlussliste. Geprüft wird `DenonSplitterTelnet::ReceiveData()` samt
  `DenonAVRCP_API_Data::GetCommandResponse()`: Zuordnung eines sauberen Stapels
  (Idents **und** Werte), die Zusicherung „jeder Katalogeintrag hat ein
  `ValueMapping`" über alle 112 Modelle, und der Fragmentpuffer in allen vier
  Fällen (zusammensetzen, frisches Fragment behalten, überlanges und
  überaltertes verwerfen).
  - Prüfen: `C:\php\php tests/receivepath_check.php`.
  - Der Harnisch setzt die Testuhr über die Naht `currentTime()`; die Alterung
    ist damit deterministisch prüfbar, ohne auf die echte Uhr zu warten.
  - Keine Golden Files: jede Zusicherung steht für sich und sagt, was richtig
    wäre. Ein Snapshot hätte hier nur das Ist-Verhalten eingefroren.

- `tests/httppath_check.php` — **Verhaltensprüfung des HTTP-Pfads** (`Denon HTTP
  IO`, `DENON_StatusHTML`; ohne Kernel und Netz, in der CI). Drei Abschnitte:
  Semaphorenbilanz (kein `IPS_SemaphoreLeave` ohne gehaltene Sperre — der Stub
  führt dafür Buch wie der Kernel), Fehlerursache (`getPrevious()` gesetzt,
  Originalmeldung im Text, plus eine Quelltextprüfung über **alle** drei
  `throw`-Stellen) und das Abruf-Timeout über die Naht `httpContext()`.
  - Prüfen: `C:\php\php tests/httppath_check.php`.
  - Kein Abschnitt löst einen echten HTTP-Abruf aus: der Fehlerpfad wird über
    einen Harnisch erreicht, dessen `GetInputVarMapping()` wirft.

- `tests/capability_diff.php` — **Vorher-Nachher-Vergleich** im Klartext, für das
  Review von Capability-Änderungen:
  `C:\php\php tests/capability_diff.php --from <Ref|Datei> [--to <Ref|Datei>]`.
  Holt den Vorher-Stand aus `tests/golden/capabilities.json` des Refs; kennt der
  Ref die Datei noch nicht, wird ersatzweise ein temporärer `git worktree`
  aufgemacht. Informativ (Exit 0), Exit 1 nur bei Umgebungsfehlern.

## Robustheit im Empfangs- und HTTP-Pfad (2.30 build 97/98)

Fünf Fehler — **keine** fehlenden Features —, die genau dann wirken, wenn
ohnehin etwas klemmt (Gerät aus, Netz weg). Sie sind mit **build 97** (Telnet)
und **build 98** (HTTP) behoben. Beide Pfade haben seither ein Regressionsnetz,
das vorher fehlte: die Golden-Suite deckt nur den Sendeweg ab, `ReceiveData`
steht dort sogar auf der Ausschlussliste. Fehler dieser Art fielen also erst
beim Anwender auf — zum Vergleich hat `Denon Splitter Telnet/module.php` 110
Commits, rund 50 davon mit einem Fix-Betreff.

**build 97 — Telnet-Empfangspfad** (`tests/receivepath_check.php`, 11 Zusicherungen):

- **Fragmentpuffer ohne Verfall.** `ReceiveData()` puffert Pakete, die nicht auf
  `\r` enden; ein abgerissenes Telegramm blieb dauerhaft liegen und wurde jeder
  folgenden Antwort vorangestellt — die Instanz war bis zum Neustart still taub.
  Jetzt verfallen Bruchstücke über `FRAGMENT_MAX_LENGTH` (4096 Byte) und älter
  als `FRAGMENT_MAX_AGE` (30 s) mit einer Warnung. 4096 Byte sind rund das
  Fünffache eines vollständigen Statusabrufs; die Grenze greift nur im
  Fehlerfall. `protected function currentTime(): int` ist die Naht, an der die
  Prüfroutine die Uhr stellt.
- **Null-Rückgabe.** `GetCommandResponse()` überspringt eine Antwort ohne
  `ValueMapping`, statt den ganzen Stapel zu verwerfen; Rückgabetyp jetzt
  `array` statt `?array`. **Erreichbar war das `return null` allerdings nie** —
  `GetVariableProfileMapping()` setzt `ValueMapping` ausnahmslos, über alle 112
  Modelle und 174 Katalogeinträge nachgemessen. Der Fix ist Absicherung, kein
  reparierter Absturz; die Zusicherung selbst steht seither als Prüfung in der
  Routine.

**build 98 — HTTP-Pfad** (`tests/httppath_check.php`, 10 Zusicherungen):

- **Semaphore ohne Besitz freigegeben.** `GetStatus()` und zweimal
  `SendCommand()` riefen auch im Zweig „Lock fehlgeschlagen" `unlock()`, und
  `unlock()` ruft bedingungslos `IPS_SemaphoreLeave` — ein Tick gab damit die
  Sperre eines **anderen**, noch laufenden Ticks frei. Bei 10-Sekunden-Timer und
  sechs blockierenden Abrufen war Überlappung der Regelfall. Die drei Aufrufe
  sind entfallen. `SemaphoreStub` in `tests/symcon_stubs.php` führt dafür
  dasselbe Konto wie der Kernel und meldet jedes `Leave` ohne `Enter`.
- **Fehlerursache verworfen.** Die drei `throw new Exception('… failed')`
  reichen jetzt Meldung **und** Ursache durch (`… . $exc->getMessage(), 0, $exc`).
- **Keine Timeouts.** `fetchXml()` bekommt den Stream-Context aus
  `protected function httpContext()` mit `HTTP_TIMEOUT = 2.0`; die Option
  `timeout` des http-Wrappers begrenzt Verbindungsaufbau *und* Lesen. Statt bis
  zu 6 × 60 s pro Zyklus (innerhalb der Semaphore des Aufrufers) sind es
  höchstens 6 × 2 s. **Kein Early-Abort:** ein 404 einer nicht vorhandenen Zone
  ist normal und darf die übrigen Endpunkte nicht abschneiden (so steht es im
  Kommentar über `fetchXml()`); die 12 s liegen daher weiterhin über dem
  10-Sekunden-Timer.

**Zwei Prüfdateien statt einer.** Der ursprüngliche Plan sah eine Datei mit vier
Abschnitten vor. Sie wäre nach dem ersten Commit rot gewesen — deshalb je eine
Datei pro Pfad, jede zusammen mit ihrem Fix. Beide entstanden **vor** dem Fix und
waren nachweislich rot (receivepath: 2 von 11, httppath: 6 von 10); ein Test, der
nie rot war, ist wertlos. `golden_regression` rührte sich bei keinem der beiden
Commits — der Sendeweg ist nicht angefasst.

**Weiterhin offen:** ein Golden über `GetCommandResponse()` mit einem Korpus
**echter** Telnet-Antwortzeilen wäre die wirksamste Ergänzung — dafür braucht es
einen Mitschnitt aus dem Splitter-Debug, keine erfundenen Daten. Bewusst nicht
Teil der beiden Builds waren außerdem: Logger-Trait, Attribut-Migration und
additiver Capability-Mechanismus; Retry oder Backoff im HTTP-Polling; die
`FormExpertParameters()`-Lücke und die Aufräum-Routine für verwaiste Alt-Profile.

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
- `tests/check_locale.php` hängt für die AVR-Module nur `DenonClass.php` an
  (`$sharedPhpFiles`, `:33-36`). Seit der `libs/`-Zerlegung (Build 81) ist das ein
  reiner Aggregator — die gemeinsamen `'caption'`-Literale aus `libs/AVRModule.php`
  werden nicht mehr geprüft. Der Exit-Code bleibt 0, die Abdeckung ist also still
  verloren gegangen; der Fix ist ein Einzeiler (Dateiliste erweitern), zieht aber
  vermutlich verwaiste de-Schlüssel nach sich.
- Die **Live-Verifikation der DIM-Direktwahl** ist erledigt (2026-08-08 am
  AVR-X3800H): der Receiver antwortet nach `DIM x` **von sich aus**. `DIM` gehört
  deshalb **nicht** in `STATUS_REQUEST_AFTER_SEND` — ein Eintrag dort kostete je
  Schaltvorgang 30 ms Wartezeit und ein überflüssiges Telegramm. Belegt ist
  bislang nur der X3800H; ein nicht nachgeführter Dimmer bei einem anderen Modell
  wäre ein modellspezifischer Befund, kein Widerspruch.
- **`ILB`** (Marantz „Illumination", Werte AUTO/BRI/DIM/DAR/OFF/SEL) ist bewusst nicht
  umgesetzt. Es ist — anders als `DIM` — echt modellabhängig (FY23: nur AV 10 und
  CINEMA 40; CY2023: nur CINEMA 30) und bräuchte deshalb eine eigene Capability.
  Ebenfalls offen gelassen: `DIM SEL` (Toggle), das nicht in eine Enumeration passt.

## Support-Kontext

Fehlerberichte kommen aus dem Symcon-Forum (community.symcon.de); Fixes als Beta
über `master` in den Store, Forum-Antworten auf Deutsch.
