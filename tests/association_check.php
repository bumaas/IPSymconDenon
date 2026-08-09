<?php

declare(strict_types=1);

/**
 * Prüft, ob der Profilkatalog jede Auswahl anbietet, die ein Modell beansprucht.
 *
 * Einige Profile bekommen ihre Assoziationen modellabhängig gefiltert:
 * DENONIPSProfiles::updateProfileAccordingToCaps() wirft aus der Assoziationsliste
 * alles heraus, was nicht im Capability-Array "<Ident>_SubCommands" des Modells
 * steht. Der Filter kann aber nur *wegnehmen* - was ein Modell deklariert, der
 * Katalog jedoch nicht kennt, verschwindet still und ist im WebFront nicht
 * auswählbar. Eine eingehende Antwort dazu landet nur als "*Warning*: No value
 * found for SubCommand" im Debug-Log.
 *
 * Genau so fehlten bis Build 94 der Video-Select-Quelle 'Game1' (14 Modelle),
 * '8K' (18), 'Dock' (9) und dem Surround-Modus 'Auto' (94 Modelle) die
 * Katalogeinträge, obwohl die Modelle sie deklarierten.
 *
 * Geprüft wird jedes Profil mit nicht-leerer Assoziationsliste, zu dessen Ident
 * es ein Capability-Array "<Ident>_SubCommands" gibt. Profile, deren
 * Assoziationen erst zur Laufzeit entstehen (Eingangsquellen), haben eine leere
 * Liste im Katalog und bleiben deshalb außen vor.
 *
 * Läuft ohne Symcon-Kernel, ohne Netz und ohne die Hersteller-Protokolle
 * (tests/symcon_stubs.php) - also auch in der CI.
 *
 * Aufruf: php tests/association_check.php [--details]
 * Exit-Code 1 bei Lücken (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$details = in_array('--details', $argv, true);

require_once __DIR__ . '/symcon_stubs.php';
require_once dirname(__DIR__) . '/DenonClass.php';

set_error_handler(static function (int $errno, string $errstr): bool {
    return true; // trigger_error aus getCapabilities() nicht ausgeben
});

$profiles = new DENONIPSProfiles()->GetAllProfiles();
$avrs     = AVRs::getAllAVRs();

/** @var array<string, array<string, array{profil: string, modelle: list<string>}>> */
$luecken  = [];
$geprueft = 0;

foreach ($profiles as $profilName => $profil) {
    $associations = $profil['Associations'] ?? [];
    if ($associations === []) {
        continue; // Assoziationen entstehen dynamisch (z. B. Eingangsquellen)
    }

    $ident = $profil['Ident'];
    $key   = $ident . '_SubCommands';
    $katalog = array_column($associations, 2);

    $trifftZu = false;
    foreach ($avrs as $modell => $caps) {
        if (!array_key_exists($key, $caps)) {
            continue;
        }
        $trifftZu = true;
        foreach (array_diff($caps[$key], $katalog) as $fehlend) {
            $luecken[$key][$fehlend]['profil'] = $profilName;
            $luecken[$key][$fehlend]['modelle'][] = $modell;
        }
    }
    if ($trifftZu) {
        $geprueft++;
    }
}

echo "Gefilterte Profile geprüft: $geprueft\n\n";

if ($luecken === []) {
    echo "OK: Jede deklarierte Auswahl hat einen Katalogeintrag.\n";
    exit(0);
}

$anzahl = 0;
foreach ($luecken as $key => $eintraege) {
    echo "==== $key ====\n";
    foreach ($eintraege as $fehlend => $info) {
        $anzahl++;
        printf(
            "  ohne Assoziation im Profil '%s': '%s' - deklariert von %d Modell(en)\n",
            $info['profil'],
            $fehlend,
            count($info['modelle'])
        );
        if ($details) {
            echo '     ', implode(', ', $info['modelle']), "\n";
        }
    }
    echo "\n";
}

fwrite(
    STDERR,
    "FEHLER: $anzahl Auswahl(en) werden von Modellen deklariert, fehlen aber im Profilkatalog.\n"
    . "Sie sind dadurch nicht auswählbar. Assoziation in DENONIPSProfiles ergänzen -\n"
    . "dabei nur anhängen, nie umnummerieren (die Werte stehen so in den Variablen).\n"
);
exit(1);
