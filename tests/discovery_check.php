<?php

declare(strict_types=1);

/**
 * Prüfroutine für die Zuordnung gemeldeter Modellnamen.
 *
 * Die Discovery bekommt den Modellnamen aus der UPnP-Beschreibung des Geräts.
 * Der steht dort so, wie er auf dem Gehäuse steht - und das ist in Europa
 * nicht der Name, unter dem der Katalog das Gerät führt:
 *
 *   gemeldet (echt, 05.10.2026 von 192.168.178.33:60006 abgerufen):
 *       <manufacturer>Denon</manufacturer>
 *       <modelName>Denon AVC-X3800H</modelName>
 *   im Katalog:
 *       AVR-X3800H
 *
 * Zwei Unterschiede auf einmal: der vorangestellte Herstellername und AVC
 * statt AVR. Bis Build 103 verglich die Discovery wörtlich und ordnete
 * deshalb keinem europäischen Denon ein Modell zu (Forum t/40873, #762).
 *
 * Geprüft wird die gemeinsame Zuordnung in AVRs:
 *   - der Katalog ist einheitlich benannt und die Vergleichsformen sind eindeutig
 *   - echte gemeldete Namen treffen den richtigen Katalogeintrag
 *   - ein unbekanntes Modell und der falsche Hersteller treffen nichts
 *   - ein in Bestandsanlagen gespeicherter Altname liefert weiter Fähigkeiten
 *   - die Discovery benutzt diese Zuordnung, statt eine zweite zu führen
 *
 * Läuft ohne Symcon-Kernel, ohne Netz und ohne die Hersteller-Protokolle
 * (tests/symcon_stubs.php) - also auch in der CI.
 *
 * Aufruf: php tests/discovery_check.php
 * Exit-Code 1 bei Abweichungen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);

require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';

// ---- Prüfgerüst ------------------------------------------------------------

final class Pruefung
{
    private static string $abschnitt = '';
    private static int $bestanden    = 0;
    /** @var list<string> */
    private static array $fehler = [];

    public static function abschnitt(string $titel): void
    {
        self::$abschnitt = $titel;
        echo "\n==== $titel ====\n";
    }

    public static function ist(string $fall, bool $erfuellt, string $befund = ''): void
    {
        if ($erfuellt) {
            self::$bestanden++;
            echo "  OK    $fall\n";
            return;
        }

        self::$fehler[] = self::$abschnitt . ' / ' . $fall . ($befund === '' ? '' : ': ' . $befund);
        echo "  FEHL  $fall\n";
        if ($befund !== '') {
            echo '        ', str_replace("\n", "\n        ", $befund), "\n";
        }
    }

    public static function bilanz(): int
    {
        $anzahl = count(self::$fehler);
        echo "\n", str_repeat('-', 70), "\n";
        printf("%d Prüfungen, %d Fehler\n", self::$bestanden + $anzahl, $anzahl);

        if ($anzahl === 0) {
            echo "OK: Gemeldete Modellnamen werden zugeordnet wie zugesichert.\n";
            return 0;
        }

        fwrite(STDERR, "\nFEHLER:\n");
        foreach (self::$fehler as $fehler) {
            fwrite(STDERR, '  - ' . $fehler . "\n");
        }
        return 1;
    }
}

// ---- Nähte zur Implementierung ---------------------------------------------

/**
 * Vergleichsform eines Modellnamens.
 *
 * Nutzt AVRs::canonicalModelName(), sobald es die gibt. Fehlt die Funktion
 * (Stand vor dem Fix), bildet der Ersatz die alte Semantik nach: es wurde
 * wörtlich verglichen, also ist die Vergleichsform der Name selbst.
 */
function vergleichsform(string $name): string
{
    if (method_exists(AVRs::class, 'canonicalModelName')) {
        return AVRs::canonicalModelName($name);
    }
    return $name;
}

/**
 * Katalogname zu einem gemeldeten Modellnamen, oder null.
 *
 * Nutzt AVRs::findByReportedModel(), sobald es die gibt. Der Ersatz bildet die
 * alte Semantik der Discovery nach (Denon Discovery/module.php:157-165 vor dem
 * Fix): Schleife über den Katalog, exakter Vergleich, Hersteller muss passen.
 */
function katalogname(string $hersteller, string $gemeldet): ?string
{
    if (method_exists(AVRs::class, 'findByReportedModel')) {
        return AVRs::findByReportedModel($hersteller, $gemeldet);
    }

    foreach (AVRs::getAllAVRs() as $name => $caps) {
        if (($caps['Manufacturer'] === $hersteller) && ($name === $gemeldet)) {
            return $name;
        }
    }
    return null;
}

/** interne ID eines Katalogeintrags */
function interneId(string $katalogname): ?int
{
    return AVRs::getAllAVRs()[$katalogname]['internalID'] ?? null;
}

// ---- Abschnitt 1: Katalog --------------------------------------------------

Pruefung::abschnitt('Katalog');

$alle = AVRs::getAllAVRs();

Pruefung::ist(
    'der Katalog führt 112 Modelle',
    count($alle) === 112,
    'gefunden: ' . count($alle)
);

// Der Katalog benennt die Modelle amerikanisch (AVR-...), auch die, die in
// Europa als AVC verkauft werden - etwa den AVC-X3800H. Eine einzelne
// Ausnahme würde die Zuordnung unten zum Sonderfall machen.
$avc = array_values(array_filter(array_keys($alle), static fn (string $n): bool => stripos($n, 'AVC') === 0));
Pruefung::ist(
    'kein Katalogname ist in der AVC-Schreibweise geführt',
    $avc === [],
    'Ausreißer: ' . json_encode($avc, JSON_UNESCAPED_SLASHES)
);

// Die Zuordnung vergleicht Vergleichsformen. Fielen zwei Modelle auf dieselbe,
// träfe die Suche je nach Reihenfolge das falsche.
$formen = [];
foreach (array_keys($alle) as $name) {
    $formen[vergleichsform($name)][] = $name;
}
$doppelt = array_filter($formen, static fn (array $n): bool => count($n) > 1);
Pruefung::ist(
    'die Vergleichsformen aller Modelle sind eindeutig',
    $doppelt === [],
    'mehrfach belegt: ' . json_encode($doppelt, JSON_UNESCAPED_SLASHES)
);

// ---- Abschnitt 2: gemeldete Namen ------------------------------------------

Pruefung::abschnitt('Zuordnung gemeldeter Modellnamen');

/**
 * Fälle: [Hersteller, gemeldeter Name, erwarteter Katalogname, Herkunft]
 *
 * Die beiden ersten Namen sind echte Mitschnitte, nicht nachgebaut. Die
 * weiteren prüfen Schreibweisen, die derselben Regel folgen.
 */
$faelle = [
    ['Denon', 'Denon AVC-X3800H', 'AVR-X3800H', 'UPnP-Beschreibung 192.168.178.33, 05.10.2026'],
    ['Denon', 'Denon AVC-X3900H', 'AVR-X3900H', 'Screenshot marvus, Forum t/40873 #762'],
    ['Denon', 'Denon AVR-X3900H', 'AVR-X3900H', 'amerikanische Schreibweise'],
    ['Denon', 'AVR-X3900H', 'AVR-X3900H', 'Katalogname unverändert'],
    ['Denon', 'Denon AVC-X8500H', 'AVR-X8500H', 'europäischer Name des X8500H'],
    ['Denon', 'AVC-X8500H', 'AVR-X8500H', 'bis Build 103 der Katalogname selbst'],
    ['Marantz', 'Marantz-SR6015', 'Marantz-SR6015', 'Katalogname unverändert'],
    ['Marantz', 'Marantz SR6015', 'Marantz-SR6015', 'Herstellername mit Leerzeichen'],
];

foreach ($faelle as [$hersteller, $gemeldet, $erwartet, $herkunft]) {
    $gefunden = katalogname($hersteller, $gemeldet);
    Pruefung::ist(
        sprintf('%s meldet "%s" -> %s', $hersteller, $gemeldet, $erwartet),
        $gefunden === $erwartet,
        sprintf('gefunden: %s (%s)', $gefunden ?? 'nichts', $herkunft)
    );
}

// Die beiden Geräte, um die es geht, müssen bei ihrer internen ID bleiben -
// die steht in den Instanzen der Anwender (Property AVRTypeDenon).
Pruefung::ist(
    'AVR-X3800H behält die interne ID 208',
    interneId('AVR-X3800H') === 208,
    'gefunden: ' . var_export(interneId('AVR-X3800H'), true)
);
Pruefung::ist(
    'AVR-X3900H behält die interne ID 210',
    interneId('AVR-X3900H') === 210,
    'gefunden: ' . var_export(interneId('AVR-X3900H'), true)
);
Pruefung::ist(
    'der X8500H behält die interne ID 39',
    interneId('AVR-X8500H') === 39,
    'gefunden: ' . var_export(interneId('AVR-X8500H'), true)
);

// Was nicht passt, darf nicht zugeordnet werden.
Pruefung::ist(
    'ein unbekanntes Modell trifft nichts',
    katalogname('Denon', 'Denon AVR-X9999H') === null,
    'gefunden: ' . (katalogname('Denon', 'Denon AVR-X9999H') ?? 'nichts')
);
Pruefung::ist(
    'ein Denon-Modell trifft unter Marantz nichts',
    katalogname('Marantz', 'Denon AVC-X3900H') === null,
    'gefunden: ' . (katalogname('Marantz', 'Denon AVC-X3900H') ?? 'nichts')
);
Pruefung::ist(
    'ein leerer Name trifft nichts',
    katalogname('Denon', '') === null,
    'gefunden: ' . (katalogname('Denon', '') ?? 'nichts')
);

// ---- Abschnitt 3: Bestandsanlagen ------------------------------------------

Pruefung::abschnitt('Bestandsanlagen');

// Der Modellname wird nicht nur angezeigt, er steht als Text in einer
// Statusvariablen des Splitters (Denon Splitter Telnet/module.php:44, gesetzt
// in SaveInputVarmapping()) und im HTTP-IO. Nach der Umbenennung steht dort
// bei einem X8500H-Anwender zunächst noch der alte Name; bis das Gerätemodul
// das Mapping neu schickt, muss er weiter zu Fähigkeiten führen.
$altnameCaps = null;
$altnameFehler = '';
try {
    $altnameCaps = AVRs::getCapabilities('AVC-X8500H');
} catch (Throwable $e) {
    $altnameFehler = get_class($e) . ': ' . $e->getMessage();
}
Pruefung::ist(
    'der gespeicherte Altname AVC-X8500H liefert weiterhin Fähigkeiten',
    is_array($altnameCaps) && isset($altnameCaps['internalID']) && $altnameCaps['internalID'] === 39,
    $altnameFehler !== '' ? $altnameFehler : 'Rückgabe: ' . gettype($altnameCaps)
);

// ---- Abschnitt 4: nur eine Zuordnung ---------------------------------------

Pruefung::abschnitt('Nur eine Zuordnung im Code');

$discovery = (string) file_get_contents($root . '/Denon Discovery/module.php');

Pruefung::ist(
    'die Discovery benutzt die gemeinsame Zuordnung aus AVRs',
    str_contains($discovery, 'AVRs::findByReportedModel'),
    'AVRs::findByReportedModel kommt in Denon Discovery/module.php nicht vor'
);
Pruefung::ist(
    'die Discovery führt keine eigene Vergleichsschleife mehr',
    !str_contains($discovery, '$AVRName === $model_name'),
    'der wörtliche Vergleich steht noch in Denon Discovery/module.php'
);

exit(Pruefung::bilanz());
