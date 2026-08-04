<?php

declare(strict_types=1);

/**
 * Golden-File-Regressionstest fuer die Denon/Marantz-Kernlogik.
 *
 * Friert das Ist-Verhalten (inkl. bekannter Altfehler) als Golden Files unter
 * tests/golden/ ein und vergleicht jede erneute Erzeugung strukturell dagegen.
 * Bereiche:
 *   models        - Capabilities aller AVR-Modelle (Digest je Modell)
 *   profiles      - Profilkatalog + Profil-Mapping je Modell (Digest)
 *   presentations - Presentation-Arrays aller Profile je Modell (Digest,
 *                   Voll-Dump fuer Referenzmodelle)
 *   registration  - aufgezeichnete Variablen-Registrierung je (Modell, Zone)
 *   wrappers      - gesendete Buffer aller Telnet-Befehlsmethoden
 *   forms         - GetConfigurationForm je (Modell, Zone) (Digest + 1 Voll-Dump)
 *
 * trigger_error-Meldungen werden je Snapshot mit eingefroren (Fehlerkanal).
 * Laeuft ohne IP-Symcon-Kernel und ohne Netzwerk (tests/symcon_stubs.php).
 *
 * Aufruf: php tests/golden_regression.php            (pruefen)
 *         php tests/golden_regression.php --update   (Golden Files neu schreiben)
 *         php tests/golden_regression.php --dump <Modell> (Voll-Dumps nach tests/dump/)
 * Exit-Code 1 bei Abweichungen (fuer die CI), sonst 0.
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

// trigger_error-Meldungen einsammeln statt ausgeben (auch E_USER_ERROR darf
// nicht abbrechen - der Fehlerkanal ist Teil des eingefrorenen Verhaltens)
$GLOBALS['capturedErrors'] = [];
set_error_handler(static function (int $errno, string $errstr): bool {
    if (!(error_reporting() & $errno)) {
        return false; // per @ unterdrueckt
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

// Referenzmodelle/-kombinationen (Namen sind Keys von AVRs::getAllAVRs());
// Voll-Dumps weiterer Modelle bei Bedarf per --dump <Modell>
const PRESENTATION_FULL_MODELS = ['AVR-X3800H'];
const COMBOS = [
    ['label' => 'X3800H_main',  'manufacturer' => 1, 'model' => 'AVR-X3800H',     'zone' => 0],
    ['label' => 'X3800H_zone2', 'manufacturer' => 1, 'model' => 'AVR-X3800H',     'zone' => 1],
    ['label' => 'X3000_main',   'manufacturer' => 1, 'model' => 'AVR-X3000',      'zone' => 0],
    ['label' => 'SR6015_main',  'manufacturer' => 2, 'model' => 'Marantz-SR6015', 'zone' => 0],
    ['label' => 'DRA-N5_main',  'manufacturer' => 1, 'model' => 'DRA-N5',         'zone' => 0],
];
const FORMS_FULL_LABEL = 'X3800H_main';

function manufacturerName(int $manufacturer): string
{
    return $manufacturer === 2 ? DENONIPSProfiles::ManufacturerMarantz : DENONIPSProfiles::ManufacturerDenon;
}

function newTelnetHarness(array $combo): TelnetHarness
{
    $harness = new TelnetHarness(0);
    $harness->Create();
    $caps = AVRs::getAllAVRs()[$combo['model']];
    $harness->setPropertyForTest('manufacturer', $combo['manufacturer']);
    $harness->setPropertyForTest($combo['manufacturer'] === 2 ? 'AVRTypeMarantz' : 'AVRTypeDenon', $caps['internalID']);
    $harness->setPropertyForTest('Zone', $combo['zone']);
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

    // models: Capabilities-Digest je Modell (inkl. Konsistenz-Check-Fehlern)
    $models = [];
    foreach ($allModels as $model) {
        takeErrors();
        $caps           = AVRs::getCapabilities($model);
        $models[$model] = ['sha256' => digest($caps), 'errors' => takeErrors()];
    }
    $files['models.json'] = $models;

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

    // registration: aufgezeichnete Variablen-Registrierung je Kombination
    $registration = [];
    foreach (COMBOS as $combo) {
        $harness = newTelnetHarness($combo);
        $method  = new ReflectionMethod(DenonAVRTelnet::class, 'ValidateConfiguration');
        $method->invoke($harness, manufacturerName($combo['manufacturer']), $combo['model']);
        $registration[$combo['label']] = ['recorded' => $harness->recorded, 'errors' => takeErrors()];
    }
    $files['registration.json'] = $registration;

    // wrappers: alle oeffentlichen Telnet-Befehlsmethoden mit Beispielwerten
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
            $wrappers[$method->getName()] = 'uebersprungen: nicht unterstuetzte Parametertypen';
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

    // forms: GetConfigurationForm je Kombination
    $forms = [];
    foreach (COMBOS as $combo) {
        $harness = newTelnetHarness($combo);
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
// JSON-Zahl -80, die beim Einlesen der Golden-Datei als int zurueckkommt.
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
        echo 'OK (' . count($actual) . " Eintraege)\n\n";
        continue;
    }
    $fail = true;
    foreach ($actual as $key => $value) {
        if (!array_key_exists($key, $golden)) {
            echo "  NEU (nicht in Golden-Datei): $key\n";
        } elseif ($golden[$key] !== $value) {
            echo "  ABWEICHUNG: $key\n";
            echo '    erwartet: ' . canonical($golden[$key]) . "\n";
            echo '    erhalten: ' . canonical($value) . "\n";
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

echo "OK: Alle Bereiche unveraendert.\n";
