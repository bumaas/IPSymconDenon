<?php

declare(strict_types=1);

/**
 * Prüfroutine für den Telnet-Empfangspfad.
 *
 * Der Sendeweg ist durch tests/golden_regression.php abgedeckt, der Empfangsweg
 * war es bis Build 97 gar nicht - ausgerechnet dort, wo die Fix-Dichte am
 * höchsten ist. Diese Routine schließt die Lücke. Sie prüft Verhalten, nicht
 * Schnappschüsse: jede Zusicherung steht für sich und sagt, was richtig wäre.
 *
 * Geprüft wird DenonSplitterTelnet::ReceiveData() samt der von ihr benutzten
 * Auswertung DenonAVRCP_API_Data::GetCommandResponse():
 *   - ein vollständiger Stapel wird vollständig und richtig zugeordnet
 *   - jeder Katalogeintrag hat ein ValueMapping (die Zusicherung, auf der die
 *     Auswertung fußt)
 *   - der Fragmentpuffer setzt zerrissene Telegramme wieder zusammen, verwirft
 *     aber überlange und überalterte Bruchstücke
 *
 * Läuft ohne Symcon-Kernel, ohne Netz und ohne die Hersteller-Protokolle
 * (tests/symcon_stubs.php) - also auch in der CI.
 *
 * Aufruf: php tests/receivepath_check.php
 * Exit-Code 1 bei Abweichungen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);

require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';
require_once $root . '/Denon Splitter Telnet/module.php';

// trigger_error-Meldungen einsammeln statt ausgeben - sie gehören zum
// Normalbetrieb (unbekannte Antworten) und sind hier nicht das Prüfkriterium.
$GLOBALS['meldungen'] = [];
set_error_handler(static function (int $errno, string $errstr): bool {
    $GLOBALS['meldungen'][] = $errstr;
    return true;
});

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
        printf("bestanden: %d, fehlgeschlagen: %d\n", self::$bestanden, $anzahl);

        if ($anzahl === 0) {
            echo "OK: Der Telnet-Empfangspfad verhält sich wie zugesichert.\n";
            return 0;
        }

        fwrite(STDERR, "\nFEHLER:\n");
        foreach (self::$fehler as $fehler) {
            fwrite(STDERR, '  - ' . $fehler . "\n");
        }
        return 1;
    }
}

// ---- Test-Harnisch ---------------------------------------------------------

/**
 * Splitter mit den zwei Auskünften, die ReceiveData() sonst aus Statusvariablen
 * holt, einem festhaltenden SendDataToChildren() und einer Testuhr.
 */
class SplitterHarness extends DenonSplitterTelnet
{
    public const string MODELL = 'AVR-X3800H';

    /** @var list<array> an die Geräteinstanzen weitergereichte Nachrichten */
    public array $gesendet = [];

    /** Testuhr für den Verfall des Fragmentpuffers (Naht: currentTime()) */
    public int $jetzt = 1_700_000_000;

    protected function GetValue(string $Ident): mixed
    {
        return match ($Ident) {
            'AVRType'      => self::MODELL,
            'InputMapping' => json_encode(
                ['AVRType' => self::MODELL, 'Inputs' => [['Source' => 'CD'], ['Source' => 'TUNER']]],
                JSON_THROW_ON_ERROR
            ),
            default        => '',
        };
    }

    protected function SendDataToChildren(string $Data): array|false
    {
        $this->gesendet[] = json_decode($Data, true, 512, JSON_THROW_ON_ERROR);
        return [];
    }

    protected function currentTime(): int
    {
        return $this->jetzt;
    }
}

// ---- Hilfen ----------------------------------------------------------------

function payload(string $roh): string
{
    return json_encode(
        ['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => bin2hex($roh)],
        JSON_THROW_ON_ERROR
    );
}

/** @return string|null Fehlermeldung, falls der Empfang abgebrochen ist */
function empfange(SplitterHarness $harness, string $roh): ?string
{
    try {
        $harness->ReceiveData(payload($roh));
        return null;
    } catch (Throwable $t) {
        return $t::class . ': ' . $t->getMessage();
    }
}

/** Idents der zuletzt weitergereichten Nachricht */
function idents(SplitterHarness $harness): array
{
    $letzte = end($harness->gesendet);
    return $letzte === false ? [] : array_keys($letzte['Buffer']['Data']);
}

// ---- 1 Zuordnung -----------------------------------------------------------

Pruefung::abschnitt('1 Zuordnung der Antworten');

$harness = new SplitterHarness(0);
$fehler  = empfange($harness, "MV50\rMUOFF\rPWON\r");
Pruefung::ist('sauberer Stapel bricht nicht ab', $fehler === null, (string) $fehler);
Pruefung::ist(
    'sauberer Stapel liefert genau eine Nachricht',
    count($harness->gesendet) === 1,
    'Nachrichten: ' . count($harness->gesendet)
);
Pruefung::ist(
    'sauberer Stapel liefert die Idents MV, MU, PW',
    idents($harness) === ['MV', 'MU', 'PW'],
    'erhalten: ' . json_encode(idents($harness))
);
$daten = end($harness->gesendet)['Buffer']['Data'] ?? [];
Pruefung::ist(
    'die Werte stimmen (MV50 = -30 dB, MUOFF = false, PWON = true)',
    ($daten['MV']['Value'] ?? null) === -30 && ($daten['MU']['Value'] ?? null) === false && ($daten['PW']['Value'] ?? null) === true,
    json_encode(array_map(static fn($e) => $e['Value'], $daten))
);

// GetCommandResponse() lief früher bei einem Katalogeintrag ohne 'ValueMapping'
// in ein 'return null' und riss damit den ganzen Stapel mit; der Aufrufer lief
// anschließend in count(null). Erreichbar war das nie, weil
// GetVariableProfileMapping() 'ValueMapping' ausnahmslos setzt - genau diese
// Zusicherung wird hier festgehalten. Fällt sie, überspringt die Auswertung seit
// Build 97 nur noch die betroffene Antwort.
$verletzungen = [];
foreach (array_keys(AVRs::getAllAVRs()) as $modell) {
    foreach (new DENONIPSProfiles($modell)->GetVariableProfileMapping() as $command => $item) {
        if (!array_key_exists('ValueMapping', $item)) {
            $verletzungen[] = $modell . '/' . $command;
        }
    }
}
Pruefung::ist(
    'jeder Katalogeintrag aller Modelle hat ein ValueMapping',
    $verletzungen === [],
    count($verletzungen) . ' Ausnahmen, u. a. ' . implode(', ', array_slice($verletzungen, 0, 5))
);

// ---- 2 Fragmentpuffer ------------------------------------------------------

Pruefung::abschnitt('2 Fragmentpuffer');

// Ein zerrissenes Telegramm wird mit dem Reststück wieder zusammengesetzt.
$harness = new SplitterHarness(0);
empfange($harness, "MV50\rPW");
Pruefung::ist(
    'unvollständiges Telegramm wird zurückgehalten',
    $harness->gesendet === [],
    'Nachrichten: ' . count($harness->gesendet)
);
empfange($harness, "ON\r");
Pruefung::ist(
    'Fragment und Reststück ergeben genau eine Nachricht',
    count($harness->gesendet) === 1,
    'Nachrichten: ' . count($harness->gesendet)
);
Pruefung::ist(
    'die zusammengesetzte Nachricht enthält MV und PW',
    idents($harness) === ['MV', 'PW'],
    'erhalten: ' . json_encode(idents($harness))
);

// Ein Fragment, das keine Antwort mehr werden kann, muss verfallen - sonst wird
// es jeder folgenden Antwort vorangestellt und die Instanz bleibt taub.
// 100 KiB liegen weit über jeder sinnvollen Obergrenze; eine Telnet-Antwort des
// AVR ist wenige Dutzend Zeichen lang.
$harness = new SplitterHarness(0);
empfange($harness, str_repeat('X', 100_000));
empfange($harness, "MV50\r");
Pruefung::ist(
    'überlanges Fragment wird verworfen, die nächste Antwort kommt durch',
    count($harness->gesendet) === 1 && idents($harness) === ['MV'],
    'Nachrichten: ' . count($harness->gesendet) . ', Idents: ' . json_encode(idents($harness))
);

// Dasselbe für ein Fragment, das lange genug liegen geblieben ist. Die Zeit
// kommt aus der Testuhr, nicht aus der echten Uhr.
$harness = new SplitterHarness(0);
empfange($harness, 'PW');
$harness->jetzt += 3600; // jede sinnvolle Verfallszeit liegt darunter
empfange($harness, "MV50\r");
Pruefung::ist(
    'überaltertes Fragment wird verworfen, die nächste Antwort kommt durch',
    count($harness->gesendet) === 1 && idents($harness) === ['MV'],
    'Nachrichten: ' . count($harness->gesendet) . ', Idents: ' . json_encode(idents($harness))
);

// Ein Fragment, das noch frisch ist, darf nicht verfallen.
$harness = new SplitterHarness(0);
empfange($harness, "MV5");
$harness->jetzt += 1;
empfange($harness, "0\r");
Pruefung::ist(
    'frisches Fragment überlebt und wird vervollständigt',
    count($harness->gesendet) === 1 && idents($harness) === ['MV'],
    'Nachrichten: ' . count($harness->gesendet) . ', Idents: ' . json_encode(idents($harness))
);

exit(Pruefung::bilanz());
