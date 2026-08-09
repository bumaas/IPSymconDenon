<?php

declare(strict_types=1);

/**
 * Golden-File-Regressionstest für die Denon/Marantz-Kernlogik.
 *
 * Friert das Ist-Verhalten (inkl. bekannter Altfehler) als Golden Files unter
 * tests/golden/ ein und vergleicht jede erneute Erzeugung strukturell dagegen.
 * Bereiche:
 *   models        - Capabilities aller AVR-Modelle (Digest je Modell)
 *   capabilities  - dieselben Capabilities im Klartext (macht Änderungen an
 *                   Modellfähigkeiten im git diff lesbar, siehe SPEC-Vererbung.md)
 *   profiles      - Profilkatalog + Profil-Mapping je Modell (Digest)
 *   presentations - Presentation-Arrays aller Profile je Modell (Digest,
 *                   Voll-Dump für Referenzmodelle)
 *   registration  - aufgezeichnete Variablen-Registrierung je (Modell, Zone)
 *   wrappers      - gesendete Buffer aller Telnet-Befehlsmethoden
 *   forms         - GetConfigurationForm je (Modell, Zone) (Digest + 1 Voll-Dump)
 *
 * trigger_error-Meldungen werden je Snapshot mit eingefroren (Fehlerkanal).
 * Läuft ohne IP-Symcon-Kernel und ohne Netzwerk (tests/symcon_stubs.php).
 *
 * Aufruf: php tests/golden_regression.php            (prüfen)
 *         php tests/golden_regression.php --update   (Golden Files neu schreiben)
 *         php tests/golden_regression.php --dump <Modell> (Voll-Dumps nach tests/dump/)
 * Exit-Code 1 bei Abweichungen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$update     = in_array('--update', $argv, true);
$dumpModels = [];
if (($i = array_search('--dump', $argv, true)) !== false) {
    $dumpModels = array_values(array_filter(array_slice($argv, $i + 1), static fn($a) => !str_starts_with($a, '--')));
}

$root      = dirname(__DIR__);
$goldenDir = __DIR__ . '/golden';
$dumpDir   = __DIR__ . '/dump';

require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';
require_once $root . '/Denon AVR Telnet/module.php';
require_once $root . '/Denon AVR HTTP/module.php';

// trigger_error-Meldungen einsammeln statt ausgeben (auch E_USER_ERROR darf
// nicht abbrechen - der Fehlerkanal ist Teil des eingefrorenen Verhaltens)
$GLOBALS['capturedErrors'] = [];
set_error_handler(static function (int $errno, string $errstr): bool {
    if (!(error_reporting() & $errno)) {
        return false; // per @ unterdrückt
    }
    $GLOBALS['capturedErrors'][] = $errstr;
    return true;
});

function takeErrors(): array
{
    $errors                    = $GLOBALS['capturedErrors'];
    $GLOBALS['capturedErrors'] = [];
    return $errors;
}

function canonical(mixed $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
}

function digest(mixed $data): string
{
    return hash('sha256', canonical($data));
}

function pretty(mixed $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

/**
 * Abweichung zweier Golden-Einträge als Text.
 *
 * Bei Einträgen mit Unterfeldern (z. B. capabilities.json: ein Bereich je Key)
 * werden nur die abweichenden Felder gezeigt - sonst stünde bei jedem
 * geänderten Modell der komplette Datensatz zweimal in der Ausgabe.
 */
function abweichungsDetails(mixed $golden, mixed $actual): string
{
    if (!is_array($golden) || !is_array($actual)) {
        return '    erwartet: ' . canonical($golden) . "\n" . '    erhalten: ' . canonical($actual) . "\n";
    }
    $text = '';
    foreach ($actual as $feld => $wert) {
        if (!array_key_exists($feld, $golden)) {
            $text .= "    $feld: neu, erhalten " . canonical($wert) . "\n";
        } elseif ($golden[$feld] !== $wert) {
            $text .= "    $feld: erwartet " . canonical($golden[$feld]) . ', erhalten ' . canonical($wert) . "\n";
        }
    }
    foreach ($golden as $feld => $wert) {
        if (!array_key_exists($feld, $actual)) {
            $text .= "    $feld: entfallen, erwartet war " . canonical($wert) . "\n";
        }
    }
    return $text;
}

// ---- Test-Harnische --------------------------------------------------------

class AVRModuleHarness extends AVRModule
{
    public function callGetVariablePresentation(array $varDef): array|string
    {
        return $this->GetVariablePresentation($varDef);
    }
}

class TelnetHarness extends DenonAVRTelnet
{
    // kein HTTP im Test: feste, leere Inputliste statt SetInputSources()
    protected function GetInputsAVR(DENONIPSProfiles $DenonAVRVar): array
    {
        return ['Inputs' => [], 'InputMapping' => []];
    }
}

class HttpHarness extends DenonAVRHTTP
{
    protected function GetInputsAVR(DENONIPSProfiles $DenonAVRVar): array
    {
        return ['Inputs' => [], 'InputMapping' => []];
    }

    // ohne Parent-/IO-Instanz liefert SetInstanceStatus() immer false und der
    // Registrierungspfad würde nie erreicht - im Test als gültig annehmen
    protected function SetInstanceStatus(): bool
    {
        return true;
    }
}

// Referenzmodelle/-kombinationen (Namen sind Keys von AVRs::getAllAVRs());
// Voll-Dumps weiterer Modelle bei Bedarf per --dump <Modell>
const PRESENTATION_FULL_MODELS = ['AVR-X3800H'];
// 'enable': Properties, die zusätzlich zu den Voreinstellungen auf true gesetzt
// werden. Ohne das registriert die Aufzeichnung nur die wenigen per Default
// aktiven Variablen - neu aufgenommene Kommandos blieben ungeprüft.
const COMBOS = [
    ['label' => 'X3800H_main',  'manufacturer' => 1, 'model' => 'AVR-X3800H',     'zone' => 0],
    ['label' => 'X3800H_zone2', 'manufacturer' => 1, 'model' => 'AVR-X3800H',     'zone' => 1],
    ['label' => 'X3000_main',   'manufacturer' => 1, 'model' => 'AVR-X3000',      'zone' => 0],
    ['label' => 'SR6015_main',  'manufacturer' => 2, 'model' => 'Marantz-SR6015', 'zone' => 0],
    ['label' => 'DRA-N5_main',  'manufacturer' => 1, 'model' => 'DRA-N5',         'zone' => 0],
    // CY2026/CY2025: deckt die mit Build 93 aufgenommenen Kommandos ab
    ['label'  => 'X3900H_main', 'manufacturer' => 1, 'model' => 'AVR-X3900H', 'zone' => 0,
     'enable' => ['BluetoothLevel', 'ChannelLevelMonitoring', 'HDMIHotPlugTest',
                  'ChannelExpander', 'SurroundLevelCompensation', 'QuickSelect', 'SurroundMode', 'VideoSelect']],
    ['label'  => 'X3900H_zone2', 'manufacturer' => 1, 'model' => 'AVR-X3900H', 'zone' => 1,
     'enable' => ['Z2Quick']],
    ['label'  => 'AV20_main', 'manufacturer' => 2, 'model' => 'Marantz-AV20', 'zone' => 0,
     'enable' => ['BluetoothLevel', 'HDMIHotPlugTest', 'SurroundLevelCompensation',
                  'DACFilter', 'QuickSelect', 'SurroundMode', 'VideoSelect']],
];
const FORMS_FULL_LABEL = 'X3800H_main';

function manufacturerName(int $manufacturer): string
{
    return $manufacturer === 2 ? DENONIPSProfiles::ManufacturerMarantz : DENONIPSProfiles::ManufacturerDenon;
}

function newHarness(string $class, array $combo): IPSModuleStrict
{
    $harness = new $class(0);
    $harness->Create();
    $caps = AVRs::getAllAVRs()[$combo['model']];
    $harness->setPropertyForTest('manufacturer', $combo['manufacturer']);
    $harness->setPropertyForTest($combo['manufacturer'] === 2 ? 'AVRTypeMarantz' : 'AVRTypeDenon', $caps['internalID']);
    $harness->setPropertyForTest('Zone', $combo['zone']);
    foreach ($combo['enable'] ?? [] as $property) {
        $harness->setPropertyForTest($property, true);
    }
    $harness->resetRecorded();
    takeErrors();
    return $harness;
}

// ---- Bereiche --------------------------------------------------------------

function presentationsForModel(string $model): array
{
    $profileManager = new DENONIPSProfiles($model);
    $harness        = new AVRModuleHarness(0);
    $full           = [];
    foreach (array_keys($profileManager->GetAllProfilesSortedByPos()) as $configId) {
        $config = $profileManager->GetVariableConfig($configId);
        if ($config === false) {
            $full[$configId] = ['config' => false];
            continue;
        }
        $full[$configId] = [
            'config'       => $config,
            'presentation' => $harness->callGetVariablePresentation($config)
        ];
    }
    return $full;
}

/**
 * Capabilities eines Modells mit sortierten Kommandolisten.
 *
 * Sortiert wird bewusst: eine reine Umsortierung im Quelltext soll keinen Diff
 * über hunderte Zeilen erzeugen. Die Reihenfolge bleibt trotzdem abgesichert -
 * der sha256 in models.json hasht die unsortierte Struktur.
 */
function sortedCapabilities(array $caps): array
{
    foreach ($caps as $key => $value) {
        if (is_array($value)) {
            sort($value);
            $caps[$key] = $value;
        }
    }
    return $caps;
}

function profilesForModel(string $model): array
{
    $profileManager = new DENONIPSProfiles($model);
    return [
        'profiles' => $profileManager->GetAllProfilesSortedByPos(),
        'mapping'  => $profileManager->GetVariableProfileMapping()
    ];
}

function buildFiles(): array
{
    $files     = [];
    $allModels = array_keys(AVRs::getAllAVRs());

    // models:       Capabilities-Digest je Modell (inkl. Konsistenz-Check-Fehlern)
    // capabilities: dieselben Daten im Klartext, damit im Review sichtbar wird,
    //               *was* sich geändert hat - der Digest zeigt nur *dass*.
    $models       = [];
    $capabilities = [];
    foreach ($allModels as $model) {
        takeErrors();
        $caps                 = AVRs::getCapabilities($model);
        $models[$model]       = ['sha256' => digest($caps), 'errors' => takeErrors()];
        $capabilities[$model] = sortedCapabilities($caps);
    }
    $files['models.json']       = $models;
    $files['capabilities.json'] = $capabilities;

    // profiles: Katalog + Mapping je Modell
    $profiles = [];
    foreach ($allModels as $model) {
        takeErrors();
        $data             = profilesForModel($model);
        $profiles[$model] = ['count' => count($data['profiles']), 'sha256' => digest($data), 'errors' => takeErrors()];
    }
    $files['profiles.json'] = $profiles;

    // presentations: je Modell alle Profile -> Presentation
    $presentations = [];
    foreach ($allModels as $model) {
        takeErrors();
        $full                  = presentationsForModel($model);
        $presentations[$model] = ['count' => count($full), 'sha256' => digest($full), 'errors' => takeErrors()];
        if (in_array($model, PRESENTATION_FULL_MODELS, true)) {
            $files['presentations_full_' . $model . '.json'] = $full;
        }
    }
    $files['presentations.json'] = $presentations;

    // registration: aufgezeichnete Variablen-Registrierung je Kombination (Telnet)
    $registration = [];
    foreach (COMBOS as $combo) {
        $harness = newHarness(TelnetHarness::class, $combo);
        $entry   = [];
        try {
            $method = new ReflectionMethod(DenonAVRTelnet::class, 'ValidateConfiguration');
            $method->invoke($harness, manufacturerName($combo['manufacturer']), $combo['model']);
        } catch (Throwable $e) {
            $entry['exception'] = get_class($e) . ': ' . $e->getMessage();
        }
        $entry['recorded'] = $harness->recorded;
        $entry['errors']   = takeErrors();
        $registration[$combo['label']] = $entry;
    }
    $files['registration.json'] = $registration;

    // registration_http: dito für das HTTP-Modul (ValidateConfiguration ohne Parameter)
    $registrationHttp = [];
    foreach ([COMBOS[0], COMBOS[2]] as $combo) {
        $harness = newHarness(HttpHarness::class, $combo);
        $entry   = [];
        try {
            $method = new ReflectionMethod(DenonAVRHTTP::class, 'ValidateConfiguration');
            $method->invoke($harness);
        } catch (Throwable $e) {
            $entry['exception'] = get_class($e) . ': ' . $e->getMessage();
        }
        $entry['recorded'] = $harness->recorded;
        $entry['errors']   = takeErrors();
        $registrationHttp[$combo['label']] = $entry;
    }
    $files['registration_http.json'] = $registrationHttp;

    // wrappers: alle öffentlichen Telnet-Befehlsmethoden mit Beispielwerten
    $samples  = [
        'bool'   => [true, false],
        'int'    => [0, 15],
        'float'  => [-10.5, 0.0],
        'string' => ['ON', 'Auto']
    ];
    $excluded = ['Create', 'Destroy', 'ApplyChanges', 'MessageSink', 'ReceiveData', 'RequestAction',
                 'GetConfigurationForm', 'GetStateHTTP', 'setPropertyForTest', 'resetRecorded', 'Translate'];
    $wrappers = [];
    $harness  = new TelnetHarness(0);
    $harness->Create();
    $methods = array_filter(
        (new ReflectionClass(DenonAVRTelnet::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn(ReflectionMethod $m) => $m->getDeclaringClass()->getName() === DenonAVRTelnet::class
                                          && !in_array($m->getName(), $excluded, true)
    );
    usort($methods, static fn($a, $b) => strcmp($a->getName(), $b->getName()));
    foreach ($methods as $method) {
        $paramTypes = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType || !isset($samples[$type->getName()])) {
                $paramTypes = null;
                break;
            }
            $paramTypes[] = $type->getName();
        }
        if ($paramTypes === null) {
            $wrappers[$method->getName()] = 'übersprungen: nicht unterstützte Parametertypen';
            continue;
        }
        $invocations = [];
        $setCount    = $paramTypes === [] ? 1 : 2;
        for ($set = 0; $set < $setCount; $set++) {
            $args = array_map(static fn(string $t) => $samples[$t][$set], $paramTypes);
            $harness->resetRecorded();
            takeErrors();
            $invocation = ['args' => $args];
            try {
                $result = $method->invoke($harness, ...$args);
                if ($result !== null) {
                    $invocation['return'] = $result;
                }
            } catch (Throwable $e) {
                $invocation['exception'] = get_class($e) . ': ' . $e->getMessage();
            }
            $invocation['events'] = $harness->recorded;
            $invocation['errors'] = takeErrors();
            $invocations[]        = $invocation;
        }
        $wrappers[$method->getName()] = $invocations;
    }
    $files['wrappers.json'] = $wrappers;

    // requestaction: Kern + Nachführ-Requests je Ident (Telnet und HTTP)
    $raCases = [
        ['PW', true], ['MU', false], ['MV', -40.5], ['PSDYNEQ', true],
        ['PSVOLLEV', true], ['PSSP', 'FRO'], ['PSFH', true], ['PSDIM', 2],
        ['Z2POWER', true], ['Z3VOL', -20.5], ['PSTONE_CTRL', true], ['VSSC', '48P'],
        ['SI', 'CD']
    ];
    $requestAction = [];
    foreach ([['telnet', TelnetHarness::class], ['http', HttpHarness::class]] as [$variant, $class]) {
        $harness = newHarness($class, COMBOS[0]);
        $cases   = [];
        foreach ($raCases as [$ident, $value]) {
            $harness->resetRecorded();
            takeErrors();
            $case = ['ident' => $ident, 'value' => $value];
            try {
                $harness->RequestAction($ident, $value);
            } catch (Throwable $e) {
                $case['exception'] = get_class($e) . ': ' . $e->getMessage();
            }
            $case['events'] = $harness->recorded;
            $case['errors'] = takeErrors();
            $cases[]        = $case;
        }
        $requestAction[$variant] = $cases;
    }
    $files['requestaction.json'] = $requestAction;

    // forms: GetConfigurationForm je Kombination
    $forms = [];
    foreach (COMBOS as $combo) {
        $harness = newHarness(TelnetHarness::class, $combo);
        $json    = $harness->GetConfigurationForm();
        $forms[$combo['label']] = ['length' => strlen($json), 'sha256' => hash('sha256', $json), 'errors' => takeErrors()];
        if ($combo['label'] === FORMS_FULL_LABEL) {
            $files['forms_full_' . $combo['label'] . '.json'] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
    }
    $files['forms.json'] = $forms;

    return $files;
}

// ---- Lauf ------------------------------------------------------------------

$files = buildFiles();

// JSON-Roundtrip-Normalisierung: json_encode macht z. B. aus float -80.0 die
// JSON-Zahl -80, die beim Einlesen der Golden-Datei als int zurückkommt.
// Vergleich und Schreiben arbeiten deshalb einheitlich auf dekodierten Werten.
$files = json_decode(canonical($files), true, 512, JSON_THROW_ON_ERROR);

if ($dumpModels !== []) {
    if (!is_dir($dumpDir) && !mkdir($dumpDir) && !is_dir($dumpDir)) {
        fwrite(STDERR, "Kann $dumpDir nicht anlegen.\n");
        exit(1);
    }
    foreach ($dumpModels as $model) {
        if (!array_key_exists($model, AVRs::getAllAVRs())) {
            fwrite(STDERR, "Unbekanntes Modell: $model\n");
            exit(1);
        }
        file_put_contents($dumpDir . '/profiles_full_' . $model . '.json', pretty(profilesForModel($model)));
        file_put_contents($dumpDir . '/presentations_full_' . $model . '.json', pretty(presentationsForModel($model)));
        echo "Dump geschrieben: $dumpDir/{profiles,presentations}_full_$model.json\n";
    }
    exit(0);
}

if ($update) {
    if (!is_dir($goldenDir) && !mkdir($goldenDir) && !is_dir($goldenDir)) {
        fwrite(STDERR, "Kann $goldenDir nicht anlegen.\n");
        exit(1);
    }
    foreach ($files as $name => $data) {
        file_put_contents($goldenDir . '/' . $name, pretty($data));
    }
    echo 'Golden-Dateien aktualisiert: ' . count($files) . " Dateien in tests/golden/.\n";
    exit(0);
}

$fail = false;
foreach ($files as $name => $actual) {
    $path = $goldenDir . '/' . $name;
    echo "==== $name ====\n";
    if (!is_file($path)) {
        echo "GOLDEN-DATEI FEHLT\n\n";
        $fail = true;
        continue;
    }
    $golden = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if ($golden === $actual) {
        echo 'OK (' . count($actual) . " Einträge)\n\n";
        continue;
    }
    $fail = true;
    foreach ($actual as $key => $value) {
        if (!array_key_exists($key, $golden)) {
            echo "  NEU (nicht in Golden-Datei): $key\n";
        } elseif ($golden[$key] !== $value) {
            echo "  ABWEICHUNG: $key\n";
            echo abweichungsDetails($golden[$key], $value);
        }
    }
    foreach (array_keys($golden) as $key) {
        if (!array_key_exists($key, $actual)) {
            echo "  FEHLT (in Golden-Datei, aber nicht mehr vorhanden): $key\n";
        }
    }
    echo "\n";
}

if ($fail) {
    fwrite(STDERR, "FEHLER: Verhalten weicht von den Golden-Dateien ab (siehe oben).\n");
    fwrite(STDERR, "Falls die Abweichung beabsichtigt ist: php tests/golden_regression.php --update\n");
    exit(1);
}

echo "OK: Alle Bereiche unverändert.\n";
