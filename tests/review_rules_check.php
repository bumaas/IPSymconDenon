<?php

declare(strict_types=1);

/**
 * Prüft die Auflagen aus dem abgelehnten Stable-Review.
 *
 * Eine frühere Stable-Einreichung (Commit fd951c2653) wurde vom Symcon-Review
 * abgelehnt. Der Wortlaut steht nur im Feld 'remark' des Store-Datensatzes,
 * nicht im Repo:
 *
 *   "Im HTTP IO, DenonClass und Denon Splitter Telnet steckt noch mindestens ein
 *    IPS_LogMessage, das müsste raus. Du kannst gerne alternativ
 *    $this->LogMessage verwenden.
 *    Im Denon Splitter Telnet setzt du in ApplyChanges via IPS_SetHidden die
 *    Sichtbarkeit von zwei Variablen. Die Sichtbarkeit ist allerdings in der
 *    Hoheit des Benutzers. Du kannst gerne initial einen Wert vorgeben, aber
 *    nicht für existierende Variablen anpassen. Du müsstest also prüfen, ob du
 *    die Variablen neu erstellst und nur in dem Fall die Sichtbarkeit setzen."
 *
 * Beide Punkte sind behoben (Builds 89/90). Damit sie es bleiben, hängt die
 * Prüfung nicht länger an einer Notiz „vor der Einreichung mal greppen" - eine
 * erneute Ablehnung kostet einen ganzen Review-Zyklus.
 *
 * Geprüft wird der Modulcode ohne tests/ (dort darf der Kernel-Stub die
 * Funktionen selbstverständlich definieren). Kommentare werden vorher entfernt,
 * ein auskommentierter Aufruf ist also kein Befund.
 *
 * Aufruf: php tests/review_rules_check.php [--details]
 * Exit-Code 1 bei Verstößen (für die CI), sonst 0.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$details = in_array('--details', $argv, true);
$root    = dirname(__DIR__);

/** Framework-Hooks, in denen die Sichtbarkeit nie gesetzt werden darf. */
const HOOKS = ['Create', 'ApplyChanges'];

/** Belege dafür, dass eine Funktion die Existenz prüft, bevor sie versteckt. */
const EXISTENZ_PRUEFUNGEN = ['IPS_GetObjectIDByIdent', 'IPS_ObjectExists'];

/**
 * Quelltext ohne Kommentare - Grundlage aller Suchen.
 */
function codeOhneKommentare(string $code): string
{
    $out = '';
    foreach (token_get_all($code) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $out .= str_repeat("\n", substr_count($token[1], "\n"));
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }
    return $out;
}

/**
 * Alle Funktionen einer Datei mit Name, Zeile und Rumpf.
 *
 * @return list<array{name: string, line: int, body: string}>
 */
function funktionen(string $code): array
{
    $tokens = token_get_all($code);
    $anzahl = count($tokens);
    $gefunden = [];

    for ($i = 0; $i < $anzahl; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        $zeile = $tokens[$i][2];
        $name  = '(anonym)';

        $j = $i + 1;
        while ($j < $anzahl) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $text;
                break;
            }
            if ($text === '(') { // anonyme Funktion oder Pfeilfunktion
                break;
            }
            $j++;
        }

        $tiefe = 0;
        $begonnen = false;
        $rumpf = '';
        for ($k = $j; $k < $anzahl; $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];

            if ($text === '{') {
                $tiefe++;
                $begonnen = true;
            } elseif ($text === '}') {
                $tiefe--;
            } elseif (!$begonnen && $text === ';') {
                break; // abstrakte Methode / Interface
            }

            if ($begonnen) {
                $rumpf .= $text;
            }
            if ($begonnen && $tiefe === 0) {
                break;
            }
        }

        $gefunden[] = ['name' => $name, 'line' => $zeile, 'body' => $rumpf];
    }

    return $gefunden;
}

/** @return list<string> alle PHP-Dateien des Modulcodes, relativ zur Wurzel */
function modulDateien(string $root): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $eintrag): bool {
                $name = $eintrag->getFilename();
                if ($eintrag->isDir()) {
                    return !in_array($name, ['.git', '.github', 'tests', 'node_modules', 'vendor'], true);
                }
                return str_ends_with($name, '.php');
            }
        )
    );

    $dateien = [];
    foreach ($iterator as $datei) {
        $dateien[] = str_replace('\\', '/', substr($datei->getPathname(), strlen($root) + 1));
    }
    sort($dateien);

    return $dateien;
}

$dateien  = modulDateien($root);
$verstoesse = [];

foreach ($dateien as $relativ) {
    $code = codeOhneKommentare(file_get_contents($root . '/' . $relativ));

    // Auflage 1: kein IPS_LogMessage im Modulcode
    foreach (explode("\n", $code) as $nr => $zeile) {
        if (str_contains($zeile, 'IPS_LogMessage')) {
            $verstoesse[] = sprintf(
                '%s:%d — IPS_LogMessage; das Review verlangt $this->LogMessage',
                $relativ,
                $nr + 1
            );
        }
    }

    // Auflage 2: IPS_SetHidden nur beim erstmaligen Anlegen
    foreach (funktionen($code) as $funktion) {
        if (!str_contains($funktion['body'], 'IPS_SetHidden')) {
            continue;
        }

        if (in_array($funktion['name'], HOOKS, true)) {
            $verstoesse[] = sprintf(
                '%s:%d — IPS_SetHidden direkt in %s(); die Sichtbarkeit gehört dem Anwender',
                $relativ,
                $funktion['line'],
                $funktion['name']
            );
            continue;
        }

        $geprueft = false;
        foreach (EXISTENZ_PRUEFUNGEN as $beleg) {
            if (str_contains($funktion['body'], $beleg)) {
                $geprueft = true;
                break;
            }
        }

        if (!$geprueft) {
            $verstoesse[] = sprintf(
                '%s:%d — IPS_SetHidden in %s() ohne Neuanlage-Prüfung (%s)',
                $relativ,
                $funktion['line'],
                $funktion['name'],
                implode(' oder ', EXISTENZ_PRUEFUNGEN)
            );
        } elseif ($details) {
            printf("  ok: %s:%d %s() prüft vor dem Verstecken auf Existenz\n", $relativ, $funktion['line'], $funktion['name']);
        }
    }
}

printf("Modul-Dateien geprüft: %d\n\n", count($dateien));

if ($verstoesse === []) {
    echo "OK: Die Auflagen des Stable-Reviews sind eingehalten.\n";
    exit(0);
}

foreach ($verstoesse as $verstoss) {
    echo '  ', $verstoss, "\n";
}

fwrite(
    STDERR,
    "\nFEHLER: " . count($verstoesse) . " Verstoß(e) gegen die Auflagen des Stable-Reviews.\n"
    . "Eine Stable-Einreichung würde daran erneut scheitern; der Wortlaut des Reviews\n"
    . "steht im Kopf dieser Datei.\n"
);
exit(1);
