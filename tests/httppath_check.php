<?php

declare(strict_types=1);

/**
 * Prüfroutine für den HTTP-Pfad (Denon HTTP IO, DENON_StatusHTML).
 *
 * Gegenstück zu tests/receivepath_check.php, das den Telnet-Empfangspfad
 * abdeckt. Beide prüfen Verhalten, nicht Schnappschüsse: jede Zusicherung steht
 * für sich und sagt, was richtig wäre.
 *
 * Abschnitte:
 *   1 Semaphorenbilanz - kein IPS_SemaphoreLeave ohne gehaltene Sperre. Ein Tick
 *     gab bis Build 97 im Zweig "Lock fehlgeschlagen" die Sperre eines anderen,
 *     noch laufenden Ticks frei; bei 10-Sekunden-Timer und sechs blockierenden
 *     Abrufen ist Überlappung der Regelfall.
 *   2 Fehlerursache - die geworfene Exception behält Meldung, Datei und Zeile
 *     der echten Ursache.
 *   3 Timeout - die Abrufe blockieren nicht bis default_socket_timeout.
 *
 * Läuft ohne Symcon-Kernel, ohne Netz und ohne die Hersteller-Protokolle
 * (tests/symcon_stubs.php) - also auch in der CI. Kein Abschnitt löst einen
 * echten HTTP-Abruf aus.
 *
 * Aufruf: php tests/httppath_check.php
 * Exit-Code 1 bei Abweichungen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);

require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';
require_once $root . '/Denon HTTP IO/module.php';

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
            echo "OK: Der HTTP-Pfad verhält sich wie zugesichert.\n";
            return 0;
        }

        fwrite(STDERR, "\nFEHLER:\n");
        foreach (self::$fehler as $fehler) {
            fwrite(STDERR, '  - ' . $fehler . "\n");
        }
        return 1;
    }
}

// ---- Test-Harnische --------------------------------------------------------

/** HTTP-IO im Auslieferungszustand - für die Semaphorenbilanz. */
class HttpIoHarness extends DenonAVRIOHTTP
{
}

/**
 * HTTP-IO, bei dem der Abruf zuverlässig scheitert: GetInputVarMapping() ist die
 * erste Anweisung im try-Block, die eine Exception werfen kann, ohne dass dafür
 * ein Netzzugriff nötig wäre.
 */
class HttpIoFehlerHarness extends DenonAVRIOHTTP
{
    public const string URSACHE = 'InputMapping kann nicht gelesen werden';

    public function GetInputVarMapping()
    {
        throw new RuntimeException(self::URSACHE);
    }
}

class StatusHtmlHarness extends DENON_StatusHTML
{
    public function callHttpContext()
    {
        return $this->httpContext();
    }
}

function unbalanciert(): string
{
    return count(SemaphoreStub::$unbalanced) . ' Leave ohne Enter: '
        . implode(', ', array_unique(SemaphoreStub::$unbalanced));
}

// ---- 1 Semaphorenbilanz ----------------------------------------------------

Pruefung::abschnitt('1 Semaphorenbilanz');

$io = new HttpIoHarness(0);
$io->Create();

SemaphoreStub::reset(failEnter: true);
try {
    $io->GetStatus();
} catch (Throwable) {
    // für die Bilanz unerheblich
}
Pruefung::ist(
    'GetStatus() gibt keine fremde Sperre frei, wenn der Lock scheitert',
    SemaphoreStub::$unbalanced === [],
    unbalanciert()
);

SemaphoreStub::reset(failEnter: true);
try {
    $io->ForwardDatastring(json_encode(['Buffer' => 'MV50'], JSON_THROW_ON_ERROR));
} catch (Throwable) {
    // ForwardDatastring protokolliert selbst und wirft nicht weiter
}
Pruefung::ist(
    'SendCommand() gibt keine fremde Sperre frei, wenn der Lock scheitert',
    SemaphoreStub::$unbalanced === [],
    unbalanciert()
);

// ---- 2 Fehlerursache -------------------------------------------------------

Pruefung::abschnitt('2 Fehlerursache');

SemaphoreStub::reset();
$geworfen = null;
try {
    new HttpIoFehlerHarness(0)->GetStatus();
} catch (Throwable $t) {
    $geworfen = $t;
}

Pruefung::ist('GetStatus() meldet den Fehlschlag überhaupt', $geworfen !== null);
Pruefung::ist(
    'die geworfene Exception behält ihre Ursache (getPrevious)',
    $geworfen?->getPrevious() !== null,
    'getPrevious(): ' . ($geworfen?->getPrevious() === null ? 'null' : $geworfen->getPrevious()::class)
);
Pruefung::ist(
    'die Originalmeldung steht im Text',
    $geworfen !== null && str_contains($geworfen->getMessage(), HttpIoFehlerHarness::URSACHE),
    'Meldung: ' . ($geworfen?->getMessage() ?? '-')
);
Pruefung::ist(
    'die gehaltene Sperre wird dabei genau einmal freigegeben',
    SemaphoreStub::$unbalanced === [] && array_filter(SemaphoreStub::$held) === [],
    unbalanciert() . ', noch gehalten: ' . json_encode(array_filter(SemaphoreStub::$held))
);

// Quelltextprüfung: die beiden übrigen Wurfstellen liegen in SendCommand() und
// wären nur über einen echten HTTP-Abruf erreichbar. Dass auch sie die Ursache
// durchreichen, lässt sich billig am Quelltext festmachen.
$quelle = file_get_contents($root . '/Denon HTTP IO/module.php');
preg_match_all('/throw new Exception\((.+?)\);/s', $quelle, $treffer);
$ohneUrsache = array_values(array_filter($treffer[1], static fn(string $args) => !str_contains($args, '$exc')));
Pruefung::ist(
    'alle throw-Stellen in Denon HTTP IO reichen die Ursache durch',
    $treffer[1] !== [] && $ohneUrsache === [],
    count($ohneUrsache) . ' von ' . count($treffer[1]) . ': ' . implode(' | ', $ohneUrsache)
);

// ---- 3 Timeout der HTTP-Abrufe ---------------------------------------------

Pruefung::abschnitt('3 Timeout der HTTP-Abrufe');

// getStates() fragt sechs Endpunkte nacheinander ab und bricht dabei bewusst
// nicht früh ab (ein 404 einer nicht vorhandenen Zone ist normal). Mehr als 5 s
// je Abruf machen den Zyklus bei totem Host wieder unbrauchbar - der Timer läuft
// alle 10 s.
const TIMEOUT_OBERGRENZE = 5.0;

if (!method_exists(DENON_StatusHTML::class, 'httpContext')) {
    Pruefung::ist(
        'DENON_StatusHTML::httpContext() existiert',
        false,
        'fetchXml() ruft file_get_contents() ohne Stream-Context - der Abruf läuft in default_socket_timeout ('
        . ini_get('default_socket_timeout') . ' s) je Endpunkt'
    );
} else {
    $optionen = stream_context_get_options(new StatusHtmlHarness()->callHttpContext());
    $timeout  = $optionen['http']['timeout'] ?? null;

    Pruefung::ist('der Context setzt ein Timeout', $timeout !== null, json_encode($optionen));
    Pruefung::ist(
        'das Timeout ist endlich und höchstens ' . TIMEOUT_OBERGRENZE . ' s',
        is_numeric($timeout) && $timeout > 0 && $timeout <= TIMEOUT_OBERGRENZE,
        'timeout: ' . var_export($timeout, true)
    );
    Pruefung::ist(
        'fetchXml() benutzt den Context auch',
        str_contains(
            file_get_contents($root . '/libs/DENON_StatusHTML.php'),
            'file_get_contents($url, false, $this->httpContext())'
        ),
        'ein Context, den niemand übergibt, wirkt nicht'
    );
}

exit(Pruefung::bilanz());
