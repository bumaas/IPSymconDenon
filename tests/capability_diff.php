<?php

declare(strict_types=1);

/**
 * Vergleicht die Modell-Capabilities zweier Stände im Klartext.
 *
 * tests/golden/models.json sichert je Modell nur einen sha256 - im Review sieht
 * man dadurch *dass* sich etwas geändert hat, nicht *was*. Dieses Skript zeigt
 * je Modell, welche Kommandos in welchem Bereich dazugekommen oder weggefallen
 * sind.
 *
 * Ein Stand ist entweder eine Snapshot-Datei, ein Git-Ref oder der Arbeitsbaum
 * (Vorgabe für --to). Bei einem Git-Ref wird zweistufig vorgegangen:
 *   1. git show <ref>:tests/golden/capabilities.json - reiner Textzugriff,
 *      sobald die committete Momentaufnahme im jeweiligen Commit liegt.
 *   2. Fallback: temporärer git worktree + Snapshot per Subprozess. Nötig für
 *      ältere Commits; zwei Klassensätze gleichen Namens lassen sich nicht in
 *      einem PHP-Prozess laden, deshalb zwingend ein eigener Prozess.
 *
 * Aufruf: php tests/capability_diff.php --from <Ref|Datei> [--to <Ref|Datei>]
 *                                       [--model <Teilstring>] [--area <Bereich>]
 * Beispiel: php tests/capability_diff.php --from 4e630c8
 * Exit-Code 1 nur bei Umgebungsfehlern (unbekannter Ref, Subprozess gescheitert),
 * nicht bei fachlichen Unterschieden - der Report ist informativ.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// Aus dem Repo-Wurzelverzeichnis heraus arbeiten: so geraten weder Leerzeichen
// noch Umlaute des Repo-Pfads auf die Kommandozeile (Muster aus dim_gate.php).
chdir(dirname(__DIR__));

const SNAPSHOT_IM_REPO = 'tests/golden/capabilities.json';

function argValue(array $argv, string $name): ?string
{
    $i = array_search($name, $argv, true);
    return ($i !== false && isset($argv[$i + 1])) ? $argv[$i + 1] : null;
}

$from        = argValue($argv, '--from');
$to          = argValue($argv, '--to');
$modelFilter = argValue($argv, '--model');
$areaFilter  = argValue($argv, '--area');

if ($from === null) {
    fwrite(STDERR, "FEHLER: --from fehlt.\n");
    fwrite(STDERR, "Aufruf: php tests/capability_diff.php --from <Ref|Datei> [--to <Ref|Datei>]\n");
    exit(1);
}

// ---- Stände beschaffen -----------------------------------------------------

function laufe(string $cmd): array
{
    $zeilen = [];
    $rc     = 0;
    exec($cmd . ' 2>&1', $zeilen, $rc);
    return [array_map(static fn(string $l): string => rtrim($l, "\r"), $zeilen), $rc];
}

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

/** Aktueller Arbeitsbaum - im eigenen Prozess ladbar, weil nur ein Klassensatz. */
function standArbeitsbaum(): array
{
    require_once __DIR__ . '/symcon_stubs.php';
    require_once dirname(__DIR__) . '/DenonClass.php';
    set_error_handler(static fn(): bool => true);
    $stand = [];
    foreach (AVRs::getAllAVRs() as $name => $caps) {
        $stand[$name] = sortierteCapabilities($caps);
    }
    restore_error_handler();
    return $stand;
}

/** Snapshot aus einem Git-Ref: erst die committete Momentaufnahme, sonst Worktree. */
function standAusRef(string $ref): array
{
    [$zeilen, $rc] = laufe('git show ' . escapeshellarg($ref . ':' . SNAPSHOT_IM_REPO));
    if ($rc === 0) {
        return json_decode(implode("\n", $zeilen), true, 512, JSON_THROW_ON_ERROR);
    }

    // Fallback: der Ref kennt die Momentaufnahme noch nicht.
    [, $rc] = laufe('git rev-parse --verify ' . escapeshellarg($ref . '^{commit}'));
    if ($rc !== 0) {
        fwrite(STDERR, "FEHLER: Unbekannter Git-Ref: $ref\n");
        exit(1);
    }

    $kennung  = preg_replace('/[^A-Za-z0-9]/', '_', $ref);
    $worktree = rtrim(sys_get_temp_dir(), '\\/') . '/denon_caps_' . $kennung;
    $ziel     = __DIR__ . '/dump/capabilities_' . $kennung . '.json';

    if (!is_dir(__DIR__ . '/dump') && !mkdir(__DIR__ . '/dump') && !is_dir(__DIR__ . '/dump')) {
        fwrite(STDERR, "FEHLER: tests/dump/ konnte nicht angelegt werden.\n");
        exit(1);
    }

    fwrite(STDERR, "Hinweis: $ref enthält " . SNAPSHOT_IM_REPO . " noch nicht - erzeuge Snapshot über einen temporären Worktree.\n");

    [$aus, $rc] = laufe('git worktree add --detach ' . escapeshellarg($worktree) . ' ' . escapeshellarg($ref));
    if ($rc !== 0) {
        fwrite(STDERR, "FEHLER: git worktree add fehlgeschlagen:\n" . implode("\n", $aus) . "\n");
        fwrite(STDERR, "Aufräumen ggf. mit: git worktree prune\n");
        exit(1);
    }
    register_shutdown_function(static function () use ($worktree): void {
        laufe('git worktree remove --force ' . escapeshellarg($worktree));
    });

    // Immer das *aktuelle* Skript gegen den *alten* Baum: in alten Commits
    // existiert tests/inheritance_check.php noch gar nicht.
    [$aus, $rc] = laufe(
        escapeshellarg(PHP_BINARY) . ' tests/inheritance_check.php'
        . ' --root ' . escapeshellarg($worktree)
        . ' --snapshot ' . escapeshellarg($ziel)
    );
    if ($rc !== 0 || !is_file($ziel)) {
        fwrite(STDERR, "FEHLER: Snapshot für $ref fehlgeschlagen:\n" . implode("\n", $aus) . "\n");
        exit(1);
    }
    return json_decode((string) file_get_contents($ziel), true, 512, JSON_THROW_ON_ERROR);
}

function stand(?string $angabe): array
{
    if ($angabe === null) {
        return standArbeitsbaum();
    }
    if (is_file($angabe)) {
        return json_decode((string) file_get_contents($angabe), true, 512, JSON_THROW_ON_ERROR);
    }
    return standAusRef($angabe);
}

$vorher  = stand($from);
$nachher = stand($to);

// ---- Vergleich -------------------------------------------------------------

$titelVorher  = $from;
$titelNachher = $to ?? 'Arbeitsbaum';
echo "==== Capability-Diff: $titelVorher -> $titelNachher ====\n\n";

$geaendert = 0;
$neu       = 0;
$entfallen = 0;

foreach ($nachher as $modell => $caps) {
    if ($modelFilter !== null && stripos($modell, $modelFilter) === false) {
        continue;
    }
    if (!array_key_exists($modell, $vorher)) {
        $neu++;
        echo "$modell (neu)\n\n";
        continue;
    }

    $zeilen = [];
    foreach ($caps as $bereich => $wert) {
        if ($areaFilter !== null && stripos($bereich, $areaFilter) === false) {
            continue;
        }
        $alt = $vorher[$modell][$bereich] ?? null;
        if ($alt === $wert) {
            continue;
        }
        if (!is_array($wert) || !is_array($alt)) {
            $zeilen[] = sprintf('  %-24s %s -> %s', $bereich, json_encode($alt), json_encode($wert));
            continue;
        }
        $dazu = array_values(array_diff($wert, $alt));
        $weg  = array_values(array_diff($alt, $wert));
        $teil = [];
        foreach ($dazu as $ident) {
            $teil[] = "+$ident";
        }
        foreach ($weg as $ident) {
            $teil[] = "-$ident";
        }
        if ($teil !== []) {
            $zeilen[] = sprintf('  %-24s %s', $bereich, implode(', ', $teil));
        }
    }

    if ($zeilen !== []) {
        $geaendert++;
        echo "$modell\n", implode("\n", $zeilen), "\n\n";
    }
}

foreach (array_keys($vorher) as $modell) {
    if ($modelFilter !== null && stripos($modell, $modelFilter) === false) {
        continue;
    }
    if (!array_key_exists($modell, $nachher)) {
        $entfallen++;
        echo "$modell (entfallen)\n\n";
    }
}

printf(
    "Zusammenfassung: %d Modelle, %d geändert, %d neu, %d entfallen.\n",
    count($nachher),
    $geaendert,
    $neu,
    $entfallen
);
