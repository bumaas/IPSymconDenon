<?php

declare(strict_types=1);

/**
 * Prüft die Vererbungsketten der Modellklassen auf still verlorene Kommandos.
 *
 * Die Fähigkeiten eines Modells stehen in statischen Array-Properties der
 * Modellklasse (AVRModels.php, DenonAVR.php, MarantzAVR.php). Einen
 * Merge-Mechanismus gibt es nicht: deklariert eine Kindklasse eine solche
 * Property neu, ersetzt sie die Elternliste vollständig und muss jedes Element
 * erneut ausschreiben. Dabei kann still ein Kommando wegfallen, das der
 * Elternteil hatte - so verlor Denon_AVR_X2700H den Display-Dimmer (DIM), und
 * Build 85 erzeugte denselben Bruch unbemerkt neu bei Denon_AVR_X4700H.
 *
 * Das Skript vergleicht je Property den effektiven Wert einer Klasse mit dem
 * ihres Elternteils. Weil rund 200 solcher Verluste Bestand sind und die
 * meisten davon legitim (ein günstigeres Modell darf ein Feature nicht haben),
 * ist nicht jeder Verlust ein Fehler: der Ist-Stand liegt als Baseline in
 * tests/inheritance_baseline.json, rot wird nur ein *neuer* Verlust.
 *
 * Läuft ohne Symcon-Kernel, ohne Netz und ohne die Hersteller-Protokolle
 * (tests/symcon_stubs.php) - anders als tests/spec_check.php also auch in der CI.
 *
 * Aufruf: php tests/inheritance_check.php                  (prüfen)
 *         php tests/inheritance_check.php --update         (Baseline neu schreiben)
 *         php tests/inheritance_check.php --command DIM    (Abdeckung eines Kommandos)
 *         php tests/inheritance_check.php --model X47 --area SystemControl_Commands
 *         php tests/inheritance_check.php --snapshot <Datei> [--root <Verzeichnis>]
 * Exit-Code 1 bei Abweichung zur Baseline (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// ---- Argumente -------------------------------------------------------------

function argValue(array $argv, string $name): ?string
{
    $i = array_search($name, $argv, true);
    return ($i !== false && isset($argv[$i + 1])) ? $argv[$i + 1] : null;
}

$update       = in_array('--update', $argv, true);
$modelFilter  = argValue($argv, '--model');
$areaFilter   = argValue($argv, '--area');
$commandQuery = argValue($argv, '--command');
$snapshotFile = argValue($argv, '--snapshot');
$rootOption   = argValue($argv, '--root');

$root         = $rootOption !== null ? rtrim($rootOption, '\\/') : dirname(__DIR__);
$baselineFile = __DIR__ . '/inheritance_baseline.json';

if (!is_file($root . '/DenonClass.php')) {
    fwrite(STDERR, "FEHLER: $root/DenonClass.php nicht gefunden - --root prüfen.\n");
    exit(1);
}

// Die Stubs kommen immer aus dem eigenen Testverzeichnis: sie sind Testharnisch,
// nicht Produktionscode, und existieren in alten Commits womöglich gar nicht.
require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';

// trigger_error aus AVRs::getCapabilities() nicht ausgeben (E_USER_ERROR darf
// den Lauf nicht abbrechen) - der Fehlerkanal ist hier nicht Prüfgegenstand.
set_error_handler(static fn(): bool => true);

/**
 * Capability-Bereich (Key in getCapabilities()) => statische Property.
 *
 * Getrennt gepflegt, weil beides auseinanderfällt: 'AVRInfos' liest $AvrInfos.
 * Gegen das stille Veralten dieser Liste schützt die Plausibilitätsprüfung
 * unten - kommt ein Bereich hinzu, bricht das Skript ab, statt ihn ungeprüft
 * zu lassen.
 */
const CAPABILITY_PROPERTIES = [
    'InfoFunctions'          => 'InfoFunctions',
    'AVRInfos'               => 'AvrInfos',
    'PowerFunctions'         => 'PowerFunctions',
    'InputSettings'          => 'InputSettings',
    'SurroundMode'           => 'SurroundMode',
    'MS_SubCommands'         => 'MS_SubCommands',
    'SI_SubCommands'         => 'SI_SubCommands',
    'SV_SubCommands'         => 'SV_SubCommands',
    'CV_Commands'            => 'CV_Commands',
    'PS_Commands'            => 'PS_Commands',
    'PSSP_SubCommands'       => 'PSSP_SubCommands',
    'PSDYNVOL_SubCommands'   => 'PSDYNVOL_SubCommands',
    'MSQUICK_SubCommands'    => 'MSQUICK_SubCommands',
    'Z2QUICK_SubCommands'    => 'Z2QUICK_SubCommands',
    'VS_Commands'            => 'VS_Commands',
    'VSSC_SubCommands'       => 'VSSC_SubCommands',
    'VSSCH_SubCommands'      => 'VSSCH_SubCommands',
    'PV_Commands'            => 'PV_Commands',
    'Zone_Commands'          => 'Zone_Commands',
    'SystemControl_Commands' => 'SystemControl_Commands',
    'Tuner_Control'          => 'Tuner_Control',
];

// ---- Inventar --------------------------------------------------------------

$modelle = AVRs::getAllAVRs();

// Plausibilitätsprüfung: jeder Array-Bereich aus getCapabilities() muss oben
// stehen. Sonst prüfte das Skript still weniger, als es vorgibt.
$referenz    = $modelle[array_key_first($modelle)];
$istBereiche = array_keys(array_filter($referenz, 'is_array'));
$sollBereiche = array_keys(CAPABILITY_PROPERTIES);
sort($istBereiche);
sort($sollBereiche);
if ($istBereiche !== $sollBereiche) {
    fwrite(STDERR, "FEHLER: CAPABILITY_PROPERTIES passt nicht mehr zu AVR::getCapabilities().\n");
    fwrite(STDERR, '  nur in getCapabilities(): ' . implode(', ', array_diff($istBereiche, $sollBereiche)) . "\n");
    fwrite(STDERR, '  nur in diesem Skript:     ' . implode(', ', array_diff($sollBereiche, $istBereiche)) . "\n");
    exit(1);
}

/** Klassenname => Modellname, für die 107 registrierten Modelle. */
$modellKlassen = [];
foreach (get_declared_classes() as $klasse) {
    if (!is_subclass_of($klasse, 'AVR')) {
        continue;
    }
    $name = (new ReflectionProperty($klasse, 'Name'))->getValue();
    if (array_key_exists($name, $modelle)) {
        $modellKlassen[$klasse] = $name;
    }
}

/** Alle AVR-Klassen inkl. der beiden Marken-Basisklassen (dort kann ebenfalls etwas wegfallen). */
$alleKlassen = array_values(array_filter(get_declared_classes(), static fn($c) => is_subclass_of($c, 'AVR')));

// ---- Snapshot --------------------------------------------------------------

function sortierteCapabilities(array $caps): array
{
    foreach ($caps as $key => $value) {
        if (is_array($value)) {
            sort($value);
            $caps[$key] = $value;
        }
    }
    return $caps;
}

function pretty(mixed $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

if ($snapshotFile !== null) {
    $snapshot = [];
    foreach ($modelle as $name => $caps) {
        $snapshot[$name] = sortierteCapabilities($caps);
    }
    if (file_put_contents($snapshotFile, pretty($snapshot)) === false) {
        fwrite(STDERR, "FEHLER: Snapshot konnte nicht geschrieben werden: $snapshotFile\n");
        exit(1);
    }
    echo 'Snapshot geschrieben: ', $snapshotFile, ' (', count($snapshot), " Modelle)\n";
    exit(0);
}

// ---- Kettenanalyse ---------------------------------------------------------

/**
 * Fundstelle der Property-Deklaration als "Datei:Zeile" (relativ zum Repo).
 */
function fundstelle(string $klasse, string $property): string
{
    $rc    = new ReflectionClass($klasse);
    $datei = $rc->getFileName();
    if ($datei === false) {
        return '?';
    }
    $zeilen = file($datei);
    if ($zeilen === false) {
        return basename($datei);
    }
    $muster = '/^\s*public\s+static\s+\S+\s+\$' . preg_quote($property, '/') . '\s*=/';
    for ($i = $rc->getStartLine() - 1; $i < $rc->getEndLine() && $i < count($zeilen); $i++) {
        if (preg_match($muster, $zeilen[$i]) === 1) {
            return basename($datei) . ':' . ($i + 1);
        }
    }
    return basename($datei);
}

/** Statischen Property-Wert einer Klasse holen, oder null wenn nicht nutzbar. */
function propertyWert(string $klasse, string $property): ?array
{
    if (!property_exists($klasse, $property)) {
        return null;
    }
    $rp = new ReflectionProperty($klasse, $property);
    if (!$rp->isStatic() || !$rp->isInitialized()) {
        return null;
    }
    $wert = $rp->getValue();
    return is_array($wert) ? $wert : null;
}

/** Befunde: Klasse => ['model','parent','lost' => [Bereich => Idents]] */
$befunde = [];
foreach ($alleKlassen as $klasse) {
    $eltern = get_parent_class($klasse);
    if ($eltern === false) {
        continue;
    }
    $verlust = [];
    $gewinn  = [];
    foreach (CAPABILITY_PROPERTIES as $bereich => $property) {
        $rp = property_exists($klasse, $property) ? new ReflectionProperty($klasse, $property) : null;
        if ($rp === null || $rp->getDeclaringClass()->getName() !== $klasse) {
            continue;   // geerbt - kein eigenes Risiko
        }
        $kindWert   = propertyWert($klasse, $property);
        $elternWert = propertyWert($eltern, $property);
        if ($kindWert === null || $elternWert === null) {
            continue;
        }
        $fehlt = array_values(array_diff($elternWert, $kindWert));
        if ($fehlt !== []) {
            $verlust[$bereich] = $fehlt;
            $gewinn[$bereich]  = array_values(array_diff($kindWert, $elternWert));
        }
    }
    if ($verlust !== []) {
        $befunde[$klasse] = [
            'model'  => $modellKlassen[$klasse] ?? null,
            'parent' => $eltern,
            'lost'   => $verlust,
            'gained' => $gewinn,   // nur für den Report, nicht für die Baseline
        ];
    }
}

/** Modelle, die eine Bereichsliste von genau dieser Klasse erben. */
function betroffeneModelle(string $klasse, string $property, array $modellKlassen): array
{
    $treffer = [];
    foreach ($modellKlassen as $kandidat => $name) {
        if ($kandidat !== $klasse && !is_subclass_of($kandidat, $klasse)) {
            continue;
        }
        if ((new ReflectionProperty($kandidat, $property))->getDeclaringClass()->getName() === $klasse) {
            $treffer[] = $name;
        }
    }
    return $treffer;
}

// ---- Abdeckungssicht für ein Kommando ---------------------------------------

if ($commandQuery !== null) {
    echo "==== Abdeckung des Kommandos $commandQuery ====\n\n";

    $gruppen = [];
    $fuehren = 0;
    foreach ($modellKlassen as $klasse => $name) {
        foreach (CAPABILITY_PROPERTIES as $bereich => $property) {
            if (!in_array($commandQuery, $modelle[$name][$bereich], true)) {
                continue;
            }
            $fuehren++;
            $quelle = (new ReflectionProperty($klasse, $property))->getDeclaringClass()->getName();
            $gruppen[$bereich][$quelle][] = $name;
        }
    }

    if ($gruppen === []) {
        echo "Kein Modell führt dieses Kommando.\n";
    }
    foreach ($gruppen as $bereich => $quellen) {
        echo "$bereich - $fuehren von ", count($modellKlassen), " Modellen:\n";
        foreach ($quellen as $quelle => $namen) {
            printf("  %-28s %-22s %s\n", $quelle, fundstelle($quelle, CAPABILITY_PROPERTIES[$bereich]), implode(', ', $namen));
        }
        echo "\n";
    }

    echo "Kettenunterbrechungen (Elternteil führt es, Kind nicht):\n";
    $brueche = 0;
    foreach ($befunde as $klasse => $befund) {
        foreach ($befund['lost'] as $bereich => $idents) {
            if (!in_array($commandQuery, $idents, true)) {
                continue;
            }
            $brueche++;
            $betroffen = betroffeneModelle($klasse, CAPABILITY_PROPERTIES[$bereich], $modellKlassen);
            printf("  %-28s erbt von %-28s %s\n", $klasse, $befund['parent'], fundstelle($klasse, CAPABILITY_PROPERTIES[$bereich]));
            printf("      betrifft %d Modelle: %s\n", count($betroffen), implode(', ', $betroffen));
        }
    }
    if ($brueche === 0) {
        echo "  keine\n";
    }
    exit(0);
}

// ---- Report ----------------------------------------------------------------

$verlusteGesamt = 0;
foreach ($befunde as $befund) {
    foreach ($befund['lost'] as $idents) {
        $verlusteGesamt += count($idents);
    }
}

printf("==== Vererbungsketten: %d Modelle, %d Bereiche ====\n\n", count($modellKlassen), count(CAPABILITY_PROPERTIES));

$angezeigt = 0;
foreach ($befunde as $klasse => $befund) {
    if ($modelFilter !== null && stripos($klasse, $modelFilter) === false
        && ($befund['model'] === null || stripos($befund['model'], $modelFilter) === false)) {
        continue;
    }
    $bereiche = $areaFilter !== null
        ? array_filter($befund['lost'], static fn($k) => stripos($k, $areaFilter) !== false, ARRAY_FILTER_USE_KEY)
        : $befund['lost'];
    if ($bereiche === []) {
        continue;
    }
    $angezeigt++;
    printf("%s%s erbt von %s\n", $klasse, $befund['model'] !== null ? " ({$befund['model']})" : ' (Basisklasse)', $befund['parent']);
    foreach ($bereiche as $bereich => $idents) {
        $property  = CAPABILITY_PROPERTIES[$bereich];
        $betroffen = betroffeneModelle($klasse, $property, $modellKlassen);
        printf("  %-24s %s\n", $bereich, fundstelle($klasse, $property));
        printf("    verliert: %s\n", implode(', ', array_map(static fn($i) => "'$i'", $idents)));
        if ($befund['gained'][$bereich] !== []) {
            printf("    gewinnt:  %s\n", implode(', ', array_map(static fn($i) => "'$i'", $befund['gained'][$bereich])));
        }
        printf("    betrifft %d Modelle%s\n", count($betroffen), $betroffen === [] ? '' : ': ' . implode(', ', $betroffen));
    }
    echo "\n";
}

if ($modelFilter !== null || $areaFilter !== null) {
    printf("Angezeigt: %d von %d Klassen mit Verlust (Filter aktiv).\n", $angezeigt, count($befunde));
}
printf("Summe: %d verlorene Kommandos in %d Klassen.\n\n", $verlusteGesamt, count($befunde));

// ---- Baseline --------------------------------------------------------------

/** Vergleichsschlüssel "Klasse::Bereich::Ident" über alle Befunde. */
function tripel(array $befunde): array
{
    $out = [];
    foreach ($befunde as $klasse => $befund) {
        foreach ($befund['lost'] as $bereich => $idents) {
            foreach ($idents as $ident) {
                $out[] = "$klasse::$bereich::$ident";
            }
        }
    }
    sort($out);
    return $out;
}

$baselineDaten = [];
foreach ($befunde as $klasse => $befund) {
    $baselineDaten[$klasse] = [
        'model'  => $befund['model'],
        'parent' => $befund['parent'],
        'lost'   => $befund['lost'],
    ];
}

if ($update) {
    if (file_put_contents($baselineFile, pretty($baselineDaten)) === false) {
        fwrite(STDERR, "FEHLER: Baseline konnte nicht geschrieben werden: $baselineFile\n");
        exit(1);
    }
    printf("Baseline aktualisiert: %s (%d Klassen, %d Verluste).\n", basename($baselineFile), count($baselineDaten), $verlusteGesamt);
    exit(0);
}

echo "==== Abgleich mit ", basename($baselineFile), " ====\n";

if (!is_file($baselineFile)) {
    fwrite(STDERR, "FEHLER: Baseline fehlt: $baselineFile\n");
    fwrite(STDERR, "Erstmalig anlegen mit: php tests/inheritance_check.php --update\n");
    exit(1);
}

$baseline = json_decode((string) file_get_contents($baselineFile), true, 512, JSON_THROW_ON_ERROR);
$jetzt    = tripel($befunde);
$frueher  = tripel($baseline);

$neu       = array_values(array_diff($jetzt, $frueher));
$entfallen = array_values(array_diff($frueher, $jetzt));

foreach ($neu as $eintrag) {
    echo "  NEU: $eintrag\n";
}
foreach ($entfallen as $eintrag) {
    echo "  ENTFALLEN: $eintrag\n";
}

if ($neu !== [] || $entfallen !== []) {
    fwrite(STDERR, "FEHLER: Vererbungsketten weichen von " . basename($baselineFile) . " ab (siehe oben).\n");
    fwrite(STDERR, "NEU bedeutet: eine Klasse lässt ein Kommando des Elternteils still weg - prüfen, ob das gewollt ist.\n");
    fwrite(STDERR, "Falls beabsichtigt: php tests/inheritance_check.php --update (Baseline-Diff im Commit reviewen).\n");
    exit(1);
}

printf("OK: %d bekannt, 0 neu, 0 entfallen.\n", count($jetzt));
