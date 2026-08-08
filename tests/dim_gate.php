<?php

declare(strict_types=1);

/**
 * Gate für das DIM-Kommando (Display-Helligkeit) eines Modells.
 *
 * Ruft tests/spec_check.php für genau ein Modell auf und durchsucht dessen
 * Abschnitt „IM PROTOKOLL, IM MODUL FEHLEND" nach dem Kommando DIM.
 * Anders als spec_check.php selbst (rein informativ, immer Exit 0) ist das
 * hier ein echter Prüfschritt:
 *
 *  - DIM wird als fehlend gemeldet -> Exit-Code 1
 *  - DIM taucht dort nicht mehr auf -> Exit-Code 0
 *
 * Ohne Spec-Verzeichnis (Env DENON_SPEC_DIR, Vorgabe X:/Denon_Marantz) wird
 * sauber übersprungen (Exit-Code 0) — genau wie spec_check.php.
 *
 * Hinweis zur Aussagekraft: spec_check.php gruppiert fehlende Kommandos auf
 * das Basis-Kommando, „DIM" steht dort also für die ganze Gruppe
 * (DIM BRI/DIM/DAR/OFF). Ein grünes Gate heißt „DIM ist als Kommando
 * abgedeckt", nicht „jede Unterfunktion ist einzeln geprüft".
 *
 * Aufruf: php tests/dim_gate.php [--model <Name>]
 *         --model ist wie bei spec_check.php ein Teilstring-Filter auf den
 *         Modellnamen (Vorgabe: AVR-X4500H). Marantz-Modelle heißen ohne
 *         Leerzeichen, z. B. Marantz-CINEMA50.
 * Exit-Code 1 solange DIM fehlt, sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

const DIM_GATE_DEFAULT_MODEL = 'AVR-X4500H';

$model = DIM_GATE_DEFAULT_MODEL;
if (($i = array_search('--model', $argv, true)) !== false && isset($argv[$i + 1])) {
    $model = $argv[$i + 1];
}

// Aus dem Repo-Wurzelverzeichnis heraus mit relativem Pfad aufrufen: so landen
// weder Leerzeichen noch Umlaute des Repo-Pfads auf der Kommandozeile.
chdir(dirname(__DIR__));

$cmd = escapeshellarg(PHP_BINARY)
    . ' -d extension=zip tests/spec_check.php --model ' . escapeshellarg($model)
    . ' 2>&1';

$lines = [];
$rc    = 0;
exec($cmd, $lines, $rc);
$lines  = array_map(static fn (string $l): string => rtrim($l, "\r"), $lines);
$output = implode("\n", $lines);

echo "==== DIM-Gate: $model ====\n";

if ($rc !== 0) {
    fwrite(STDERR, "FEHLER: spec_check.php endete mit Exit-Code $rc:\n$output\n");
    exit(1);
}

if (str_contains($output, 'Spec-Verzeichnis') && str_contains($output, 'nicht vorhanden')) {
    echo trim($output), "\n";
    echo "Übersprungen: ohne Spec-Dateien ist DIM nicht prüfbar.\n";
    exit(0);
}

// ---- Report auswerten ------------------------------------------------------
// Ein Modell kann mehrere Blöcke haben (je Spec-Datei, die es abdeckt).
// Wert je Block: das Spec-Beispiel der fehlenden DIM-Zeile, sonst null.

/** @var array<string, string|null> $blocks */
$blocks  = [];
$current = null;

foreach ($lines as $line) {
    if (preg_match('/^==== (.+?)  \(Spec: /', $line, $m) === 1) {
        $current = $m[1];
        if (!array_key_exists($current, $blocks)) {
            $blocks[$current] = null;
        }
        continue;
    }
    if ($current === null) {
        continue;
    }
    if (str_starts_with($line, 'Spec-Modelle ohne Modul-Pendant:')) {
        $current = null;   // ab hier folgt nur noch der globale Anhang
        continue;
    }
    // Nur die 4-fach eingerückten Einträge der Fehlend-Liste; die Verankerung
    // schließt PSDIM und die --details-OK-Liste aus.
    if (preg_match('/^ {4}- DIM: (.+)$/', $line, $m) === 1) {
        $blocks[$current] = $m[1];
    }
}

if ($blocks === []) {
    fwrite(STDERR, "FEHLER: Kein Spec-Block für Modell \"$model\" gefunden — Modellname prüfen.\n");
    exit(1);
}

foreach ($blocks as $blockModel => $example) {
    echo $example === null
        ? "  $blockModel: DIM abgedeckt\n"
        : "  $blockModel: DIM fehlt (Spec-Beispiel: $example)\n";
}

$missing = array_keys(array_filter($blocks, static fn (?string $e): bool => $e !== null));

if ($missing !== []) {
    fwrite(STDERR, 'FEHLER: DIM fehlt laut Spec-Check bei: ' . implode(', ', $missing) . "\n");
    fwrite(STDERR, "Abhilfe: DENON_API_Commands::DIM in \$SystemControl_Commands der Modellklasse\n");
    fwrite(STDERR, "ergänzen (DenonAVR.php / MarantzAVR.php), danach Golden-Regression aktualisieren.\n");
    exit(1);
}

echo "OK: DIM ist laut Spec-Check abgedeckt.\n";
