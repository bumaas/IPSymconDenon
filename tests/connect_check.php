<?php

declare(strict_types=1);

/**
 * Prüfroutine für den Instanzstatus beim Verbinden der Kette.
 *
 * Anlass: Forum t/40873 #766 (BlackOrca, AVR-X2300W). Nach dem Anlegen über
 * die Discovery meldete der IO „verbunden“, der Splitter „nicht aktiv“ und
 * das Gerät „IP-Adresse ist ungültig“ (204), über Telnet wie über HTTP.
 *
 * Ursache: Der Konfigurator legt die drei Instanzen an und verbindet sie
 * danach. Gerät und Splitter prüfen ihren Status beim Anlegen, haben da noch
 * keinen Parent und reagierten bis build 107 nicht auf das spätere Verbinden
 * (FM_CONNECT). Die Registrierung auf IM_CHANGESTATUS des Parents geschah nur,
 * wenn beim Aufruf schon ein Parent da war, und beim Umhängen blieb sie am
 * alten Parent stehen.
 *
 * Geprüft wird über die Kernel-Nachbildung KernelStub in tests/symcon_stubs.php
 * (Instanzbaum, Status, Nachrichten). Ohne Kernel, Netz und Herstellerdateien,
 * daher in der CI wirksam.
 *
 * Aufruf: php tests/connect_check.php
 * Exit-Code 1 bei Abweichungen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);

require_once __DIR__ . '/symcon_stubs.php';
require_once $root . '/DenonClass.php';
require_once $root . '/Denon AVR Telnet/module.php';
require_once $root . '/Denon AVR HTTP/module.php';
require_once $root . '/Denon Splitter Telnet/module.php';
require_once $root . '/Denon Splitter HTTP/module.php';

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
            echo '        ', $befund, "\n";
        }
    }

    public static function bilanz(): int
    {
        $anzahl = count(self::$fehler);
        echo "\n", str_repeat('-', 70), "\n";
        printf("bestanden: %d, fehlgeschlagen: %d\n", self::$bestanden, $anzahl);

        if ($anzahl === 0) {
            echo "OK: Gerät und Splitter folgen dem Verbinden der Kette.\n";
            return 0;
        }

        echo "FEHLER:\n";
        foreach (self::$fehler as $f) {
            echo "  - $f\n";
        }
        return 1;
    }
}

// ---- Harnisch --------------------------------------------------------------

/** Telnet-Gerät ohne HTTP-Abrufe am Receiver (kein Netz im Test). */
/** Zählt, wie oft ein Gerät den Receiver abgefragt hätte. */
final class Abrufe
{
    public static int $anzahl = 0;
}

final class TelnetGeraet extends DenonAVRTelnet
{
    protected function GetInputsAVR(DENONIPSProfiles $DenonAVRVar): array
    {
        Abrufe::$anzahl++;
        return [];
    }

    public function GetStateHTTP(): ?array
    {
        Abrufe::$anzahl++;
        return [];
    }

    protected function UpdateVariable($data): bool
    {
        return true;
    }
}

/** HTTP-Gerät ohne Abruf der Eingangsliste am Receiver (kein Netz im Test). */
final class HttpGeraet extends DenonAVRHTTP
{
    protected function GetInputsAVR(DENONIPSProfiles $DenonAVRVar): array
    {
        Abrufe::$anzahl++;
        return [];
    }
}

// Absicherung, falls doch ein Abruf durchrutscht
ini_set('default_socket_timeout', '1');

const IO_HOST = '192.0.2.10'; // Dokumentationsnetz (RFC 5737), antwortet nie
const AVR_X2300W = 29; // Index in AVRs::$AVRTypeDenon

function statusText(int $id): string
{
    return sprintf('Status %d', KernelStub::status($id));
}

/**
 * Legt eine Instanz so an, wie es der Kernel beim Konfigurator tut:
 * Create, Konfiguration, ApplyChanges, noch ohne Verbindung.
 */
function anlegen(int $id, IPSModuleStrict $modul, array $properties = []): IPSModuleStrict
{
    KernelStub::add($id, $modul);
    $modul->Create();
    foreach ($properties as $name => $wert) {
        $modul->setPropertyForTest($name, $wert);
    }
    $modul->ApplyChanges();
    return $modul;
}

const GERAET_X2300W = ['manufacturer' => 1, 'AVRTypeDenon' => AVR_X2300W, 'Zone' => 0];

/**
 * Baut eine Kette Gerät → Splitter → IO in der Reihenfolge der Discovery
 * und verbindet erst danach.
 *
 * @param bool $vonUnten true: erst Splitter→IO, dann Gerät→Splitter
 */
function kette(string $geraetKlasse, string $splitterKlasse, bool $vonUnten, int $ioStatus = IS_ACTIVE): array
{
    KernelStub::reset();
    [$geraet, $splitter, $io] = [10, 20, 30];

    anlegen($geraet, new $geraetKlasse($geraet), GERAET_X2300W);
    anlegen($splitter, new $splitterKlasse($splitter));
    KernelStub::add($io, null, ['Host' => IO_HOST], $ioStatus);

    if ($vonUnten) {
        KernelStub::connect($splitter, $io);
        KernelStub::connect($geraet, $splitter);
    } else {
        KernelStub::connect($geraet, $splitter);
        KernelStub::connect($splitter, $io);
    }

    return [$geraet, $splitter, $io];
}

// ---- 0 Status nach Create ---------------------------------------------------

// Hop-by-Hop: Jede Instanz meldet sich schon in Create() „nicht aktiv“ und wird
// erst aktiv, wenn ihr Parent es ist. Sonst steht bis zum ersten ApplyChanges
// der Kernel-Standardstatus da.
Pruefung::abschnitt('0 Status direkt nach Create()');
foreach ([TelnetGeraet::class, HttpGeraet::class, DenonSplitterTelnet::class, DenonSplitterHTTP::class] as $klasse) {
    KernelStub::reset();
    KernelStub::add(10, $modul = new $klasse(10));
    $modul->Create();
    Pruefung::ist("$klasse: nicht aktiv (104)", KernelStub::status(10) === IS_INACTIVE, statusText(10));
}

// ---- 1 Discovery-Reihenfolge ----------------------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    foreach ([true => 'erst Splitter→IO, dann Gerät→Splitter', false => 'erst Gerät→Splitter, dann Splitter→IO'] as $vonUnten => $reihenfolge) {
        Pruefung::abschnitt("1 $weg: anlegen, dann verbinden ($reihenfolge)");
        [$geraet, $splitter] = kette($g, $s, (bool) $vonUnten);

        Pruefung::ist('Splitter ist aktiv', KernelStub::status($splitter) === IS_ACTIVE, statusText($splitter));
        Pruefung::ist('Gerät ist aktiv, nicht „IP-Adresse ist ungültig“', KernelStub::status($geraet) === IS_ACTIVE, statusText($geraet));
    }
}

// ---- 2 IO wird erst später aktiv --------------------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    Pruefung::abschnitt("2 $weg: IO wird erst nach dem Verbinden aktiv");
    [$geraet, $splitter, $io] = kette($g, $s, true, IS_INACTIVE);

    Pruefung::ist('Splitter ist zunächst nicht aktiv', KernelStub::status($splitter) === IS_INACTIVE, statusText($splitter));
    Pruefung::ist('Gerät ist zunächst nicht aktiv', KernelStub::status($geraet) === IS_INACTIVE, statusText($geraet));

    KernelStub::setStatus($io, IS_ACTIVE);
    Pruefung::ist('Splitter folgt dem IO', KernelStub::status($splitter) === IS_ACTIVE, statusText($splitter));
    Pruefung::ist('Gerät folgt dem Splitter', KernelStub::status($geraet) === IS_ACTIVE, statusText($geraet));
}

// ---- 3 Umhängen auf einen anderen Splitter ----------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    Pruefung::abschnitt("3 $weg: Gerät wird auf einen anderen Splitter umgehängt");
    [$geraet, $splitter, $io] = kette($g, $s, true);
    Pruefung::ist('vorher hört das Gerät auf den ersten Splitter', KernelStub::isRegistered($geraet, $splitter, IM_CHANGESTATUS));

    $splitter2 = 21;
    anlegen($splitter2, new $s($splitter2));
    KernelStub::connect($splitter2, $io);

    KernelStub::disconnect($geraet);
    KernelStub::connect($geraet, $splitter2);

    Pruefung::ist('Gerät hört auf den neuen Splitter', KernelStub::isRegistered($geraet, $splitter2, IM_CHANGESTATUS));
    Pruefung::ist('Gerät hört nicht mehr auf den alten Splitter', !KernelStub::isRegistered($geraet, $splitter, IM_CHANGESTATUS));

    KernelStub::setStatus($splitter2, IS_INACTIVE);
    Pruefung::ist('Gerät folgt dem neuen Splitter', KernelStub::status($geraet) === IS_INACTIVE, statusText($geraet));
}

// ---- 4 Trennen ----------------------------------------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    Pruefung::abschnitt("4 $weg: Splitter wird vom IO getrennt");
    [$geraet, $splitter, $io] = kette($g, $s, true);

    KernelStub::disconnect($splitter);
    Pruefung::ist('Splitter ist nicht mehr aktiv', KernelStub::status($splitter) === IS_INACTIVE, statusText($splitter));
    Pruefung::ist('Splitter hört nicht mehr auf den IO', !KernelStub::isRegistered($splitter, $io, IM_CHANGESTATUS));
    // Hop-by-Hop: Das Gerät spiegelt den Splitter. Die Adresse prüft der IO,
    // ein 204 „IP-Adresse ist ungültig“ am Gerät wäre hier irreführend.
    Pruefung::ist('Gerät ist nicht aktiv (104), nicht 204', KernelStub::status($geraet) === IS_INACTIVE, statusText($geraet));
}

// ---- 5 Gerät ohne Splitter ----------------------------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    Pruefung::abschnitt("5 $weg: Gerät ohne Splitter");
    KernelStub::reset();
    anlegen(10, new $g(10), GERAET_X2300W);
    Pruefung::ist('frisch angelegt ohne Splitter: nicht aktiv (104), nicht 204', KernelStub::status(10) === IS_INACTIVE, statusText(10));

    [$geraet] = kette($g, $s, true);
    KernelStub::disconnect($geraet);
    Pruefung::ist('vom Splitter getrennt: nicht aktiv (104), nicht 204', KernelStub::status($geraet) === IS_INACTIVE, statusText($geraet));
}

// ---- 6 Kernel-Neustart --------------------------------------------------------

foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class]] as $weg => [$g, $s]) {
    Pruefung::abschnitt("6 $weg: Kernel-Neustart");
    [$geraet, $splitter] = kette($g, $s, true);

    KernelStub::neustart();
    Pruefung::ist('Splitter ist nach dem Neustart aktiv', KernelStub::status($splitter) === IS_ACTIVE, statusText($splitter));
    Pruefung::ist('Gerät ist nach dem Neustart aktiv', KernelStub::status($geraet) === IS_ACTIVE, statusText($geraet));
}

// ---- 7 Abrufe am Receiver ------------------------------------------------------

// Jede Verbindungsnachricht führt zu ApplyChanges. Teuer (Variablen, Abruf am
// Receiver) ist das nur mit aktivem Parent; Trennen und ein inaktiver IO
// dürfen den Receiver nicht abfragen (Code-Review zu build 108, Befund 10).
foreach (['Telnet' => [TelnetGeraet::class, DenonSplitterTelnet::class, 2], 'HTTP' => [HttpGeraet::class, DenonSplitterHTTP::class, 1]] as $weg => [$g, $s, $jeLauf]) {
    Pruefung::abschnitt("7 $weg: Abrufe am Receiver");
    [$geraet, $splitter, $io] = kette($g, $s, true);

    Abrufe::$anzahl = 0;
    KernelStub::setStatus($io, IS_INACTIVE);
    Pruefung::ist('IO wird inaktiv: kein Abruf', Abrufe::$anzahl === 0, Abrufe::$anzahl . ' Abrufe');

    Abrufe::$anzahl = 0;
    KernelStub::setStatus($io, IS_ACTIVE);
    Pruefung::ist('IO wird wieder aktiv: genau ein Lauf', Abrufe::$anzahl === $jeLauf, Abrufe::$anzahl . ' Abrufe');

    Abrufe::$anzahl = 0;
    KernelStub::disconnect($geraet);
    Pruefung::ist('Gerät getrennt: kein Abruf', Abrufe::$anzahl === 0, Abrufe::$anzahl . ' Abrufe');
}

// ---- 8 Keine Warnungen ---------------------------------------------------------

// Der Error-Handler oben fängt Warnungen und trigger_error ab, damit sie die
// Ausgabe nicht zerreißen. Durchgehen lassen darf die Prüfung sie nicht.
Pruefung::abschnitt('8 Keine Warnungen oder Notices in allen Abschnitten');
Pruefung::ist(
    'keine Meldung aufgelaufen',
    $GLOBALS['meldungen'] === [],
    implode(' | ', array_unique($GLOBALS['meldungen']))
);

exit(Pruefung::bilanz());
