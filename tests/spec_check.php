<?php

declare(strict_types=1);

/**
 * Spec-Konformitäts-Check gegen die Hersteller-Protokoll-Excels.
 *
 * Liest die xlsx-Protokolldateien (lokal unter DENON_SPEC_DIR bzw.
 * X:\Denon_Marantz - sie werden NICHT committet) und vergleicht je Modell:
 *
 *  Richtung A (Modul -> Spec): jeder Kommando-Ident aus den Capability-
 *    Bereichen des Modells - OK / LAUT SPEC NICHT UNTERSTÜTZT / KEINE SPEC-ZEILE.
 *  Richtung B (Spec -> Modul): im Protokoll als unterstützt markierte
 *    Kommandos, die kein Capability-Ident des Modells abdeckt.
 *
 * Hinweise:
 *  - Wertebereiche (z. B. MV 00-98) werden nicht geprüft.
 *  - Richtung B vergleicht gegen die Capabilities (was das Modul für das
 *    Modell als Variablen/Formulare anbietet); die generischen Wrapper
 *    (DAVRT_*) sind unabhängig davon aufrufbar.
 *  - Die alten binären .xls-Protokolle (FY16-FY21 Marantz u. a.) können
 *    ohne Fremdbibliothek nicht gelesen werden und bleiben ungeprüft.
 *
 * Aufruf: C:\php\php -d extension=zip tests/spec_check.php [--model <Name>] [--details]
 * Exit-Code: 0 (informativer Report, kein CI-Gate); 1 nur bei Umgebungsfehlern.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ext-zip fehlt - Aufruf mit: php -d extension=zip tests/spec_check.php\n");
    exit(1);
}

$specDir = getenv('DENON_SPEC_DIR') ?: 'X:/Denon_Marantz';
if (!is_dir($specDir)) {
    echo "Spec-Verzeichnis $specDir nicht vorhanden - übersprungen.\n";
    exit(0);
}

$modelFilter = null;
$details     = in_array('--details', $argv, true);
if (($i = array_search('--model', $argv, true)) !== false && isset($argv[$i + 1])) {
    $modelFilter = $argv[$i + 1];
}

require_once __DIR__ . '/symcon_stubs.php';
require_once dirname(__DIR__) . '/DenonClass.php';

set_error_handler(static function (int $errno, string $errstr): bool {
    return true; // trigger_error aus getCapabilities() nicht ausgeben
});

const CAPABILITY_AREAS = [
    'AVRInfos', 'PowerFunctions', 'InputSettings', 'SurroundMode', 'CV_Commands',
    'PS_Commands', 'VS_Commands', 'PV_Commands', 'SystemControl_Commands',
    'Tuner_Control', 'Zone_Commands'
];

// ---- xlsx-Leser ------------------------------------------------------------

function readSharedStrings(ZipArchive $zip): array
{
    $shared = [];
    $xmlRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlRaw === false) {
        return $shared;
    }
    foreach (simplexml_load_string($xmlRaw)->si as $si) {
        if (isset($si->t)) {
            $shared[] = (string)$si->t;
        } else {
            $txt = '';
            foreach ($si->r as $r) {
                $txt .= (string)$r->t;
            }
            $shared[] = $txt;
        }
    }
    return $shared;
}

/** @return array<int, array<string, string>> Zeilennummer => [Spaltenbuchstabe => Wert] */
function readSheetRows(ZipArchive $zip, string $sheetPath, array $shared): array
{
    $rows = [];
    $raw  = $zip->getFromName($sheetPath);
    if ($raw === false) {
        return $rows;
    }
    foreach (simplexml_load_string($raw)->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/\d+/', '', (string)$c['r']);
            $v   = isset($c->v) ? (string)$c->v : (isset($c->is->t) ? (string)$c->is->t : '');
            if ((string)$c['t'] === 's') {
                $v = $shared[(int)$v] ?? '';
            }
            if (trim($v) !== '') {
                $cells[$col] = trim($v);
            }
        }
        if ($cells !== []) {
            $rows[(int)$row['r']] = $cells;
        }
    }
    return $rows;
}

/** @return list<array{name: string, path: string}> */
function listSheets(ZipArchive $zip): array
{
    $map = [];
    foreach (simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $rel) {
        $map[(string)$rel['Id']] = 'xl/' . ltrim((string)$rel['Target'], '/');
    }
    $sheets = [];
    foreach (simplexml_load_string($zip->getFromName('xl/workbook.xml'))->sheets->sheet as $s) {
        $rid      = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $sheets[] = ['name' => (string)$s['name'], 'path' => $map[$rid] ?? ''];
    }
    return $sheets;
}

function colIndex(string $letters): int
{
    $n = 0;
    foreach (str_split($letters) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n;
}

/**
 * Findet Kopfzeile + Spaltenrollen. Zwei bekannte Layouts:
 *  A (Marantz/Denon FY23): Kopfzeile mit 'COMMAND' + 'Command example',
 *    Modellnamen in derselben Zeile (eine Spalte je Modell).
 *  B (ältere Denon-Dateien): Kopfzeile mit 'COMMAND' + 'example',
 *    Modellnamen in der Zeile darüber, Regionen-Teilspalten (NA/EU/...) je Modell.
 *
 * Rückgabe: ['header' => Zeilennr, 'cmd' => Spalte, 'param' => Spalte,
 * 'example' => Spalte, 'models' => list<['name' =>, 'cols' => list<Spalte>]>]
 */
function findHeader(array $rows): ?array
{
    $rowNums = array_keys($rows);
    foreach ($rowNums as $i => $rowNum) {
        $cells  = $rows[$rowNum];
        $cmdCol = $exampleCol = $paramCol = null;
        $layout = null;
        foreach ($cells as $col => $v) {
            if ($v === 'COMMAND') {
                $cmdCol = $col;
            } elseif (stripos($v, 'Command example') === 0) {
                $exampleCol = $col;
                $layout     = 'A';
            } elseif (strcasecmp($v, 'example') === 0) {
                $exampleCol = $col;
                $layout     = 'B';
            } elseif (stripos($v, 'Parameter') === 0) {
                $paramCol = $col;
            }
        }
        if ($cmdCol === null || $exampleCol === null) {
            continue;
        }

        $lastCtrl = colIndex($exampleCol);
        foreach ($cells as $col => $v) {
            if (stripos($v, 'Response') === 0) {
                $lastCtrl = max($lastCtrl, colIndex($col));
            }
        }

        if ($layout === 'A') {
            $models = [];
            foreach ($cells as $col => $v) {
                if (colIndex($col) > $lastCtrl && stripos($v, 'Notes') === false) {
                    $models[] = ['name' => $v, 'cols' => [$col]];
                }
            }
            if ($models !== []) {
                return ['header' => $rowNum, 'cmd' => $cmdCol, 'param' => $paramCol, 'example' => $exampleCol, 'models' => $models];
            }
            continue;
        }

        // Layout B: Modellnamen in der Zeile darüber, Spans bis zum nächsten Namen
        if ($i === 0) {
            continue;
        }
        $nameCells = [];
        foreach ($rows[$rowNums[$i - 1]] as $col => $v) {
            if (colIndex($col) > $lastCtrl) {
                $nameCells[colIndex($col)] = ['name' => $v, 'col' => $col];
            }
        }
        if ($nameCells === []) {
            continue;
        }
        ksort($nameCells);
        $maxMarkCol = $lastCtrl;
        foreach ($cells as $col => $v) {
            $maxMarkCol = max($maxMarkCol, colIndex($col));
        }
        $starts = array_keys($nameCells);
        $models = [];
        foreach ($starts as $k => $start) {
            $end  = ($starts[$k + 1] ?? $maxMarkCol + 1) - 1;
            $cols = [];
            for ($ci = $start; $ci <= $end; $ci++) {
                $letters = '';
                $n       = $ci;
                while ($n > 0) {
                    $letters = chr(65 + (($n - 1) % 26)) . $letters;
                    $n       = intdiv($n - 1, 26);
                }
                $cols[] = $letters;
            }
            $models[] = ['name' => $nameCells[$start]['name'], 'cols' => $cols];
        }
        return ['header' => $rowNum, 'cmd' => $cmdCol, 'param' => $paramCol, 'example' => $exampleCol, 'models' => $models];
    }
    return null;
}

// ---- Modell-Zuordnung ------------------------------------------------------

function normalizeModel(string $name): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
}

/** Spec-Spaltenname (ggf. mehrzeilig) -> Modul-Modellname oder null */
function mapSpecModel(string $specName, array $moduleNorms): ?string
{
    foreach (preg_split('/\R/', $specName) as $line) {
        $norm = normalizeModel($line);
        if ($norm === '') {
            continue;
        }
        foreach ($moduleNorms as $moduleName => $moduleNorm) {
            if ($moduleNorm === $norm || str_ends_with($moduleNorm, $norm)) {
                return $moduleName;
            }
        }
    }
    return null;
}

// ---- Vergleich -------------------------------------------------------------

// Wire-Präfix wie AVRModule::GetAPICommandFromIdent()
function wirePrefix(string $ident): string
{
    return match (true) {
        in_array($ident, ['Z2POWER', 'Z2INPUT', 'Z2VOL'], true) => 'Z2',
        in_array($ident, ['Z3POWER', 'Z3INPUT', 'Z3VOL'], true) => 'Z3',
        $ident === 'PVPICT'                                     => 'PV',
        default                                                 => str_replace('_', ' ', $ident),
    };
}

function prefixMatchesExample(string $example, string $prefix): bool
{
    return $prefix !== '' && str_starts_with($example, $prefix);
}

// ---- Hauptlauf -------------------------------------------------------------

$files = array_merge(
    glob($specDir . '/Denon/Protokolle/*.xlsx') ?: [],
    glob($specDir . '/Marantz/*.xlsx') ?: []
);
sort($files);

$skippedXls = array_merge(
    glob($specDir . '/Denon/Protokolle/*.xls') ?: [],
    glob($specDir . '/Marantz/*.xls') ?: []
);

$moduleNorms = [];
foreach (array_keys(AVRs::getAllAVRs()) as $name) {
    $moduleNorms[$name] = normalizeModel($name);
}

$summary        = [];
$unmatchedSpecs = [];

foreach ($files as $file) {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        echo basename($file), ": nicht lesbar - übersprungen\n";
        continue;
    }
    $shared = readSharedStrings($zip);

    // Command-Sheet suchen: erst nach Namen, dann per Kopfzeilen-Scan
    $header = null;
    $rows   = [];
    $sheets = listSheets($zip);
    usort($sheets, static fn($a, $b) => (int)(stripos($b['name'], 'command list') !== false) - (int)(stripos($a['name'], 'command list') !== false));
    foreach ($sheets as $sheet) {
        $candidate = readSheetRows($zip, $sheet['path'], $shared);
        $h         = findHeader($candidate);
        if ($h !== null) {
            $header = $h;
            $rows   = $candidate;
            break;
        }
    }
    $zip->close();

    if ($header === null) {
        echo basename($file), ": kein Command-Sheet erkannt - übersprungen\n";
        continue;
    }

    // Datenzeilen extrahieren (Carry-down des Basiskommandos)
    $entries = [];
    $baseCmd = '';
    foreach ($rows as $rowNum => $cells) {
        if ($rowNum <= $header['header']) {
            continue;
        }
        if (isset($cells[$header['cmd']])) {
            $baseCmd = $cells[$header['cmd']];
        }
        $example = $cells[$header['example']] ?? '';
        if ($example === '' || !str_contains($example, '<CR>')) {
            continue;
        }
        $example = str_replace('<CR>', '', $example);
        $marks   = [];
        foreach ($header['models'] as $mi => $model) {
            $supported = $unsupported = false;
            foreach ($model['cols'] as $col) {
                $mark = $cells[$col] ?? '';
                if (in_array($mark, ['X', 'x', '○', 'O', '✔', '✓'], true)) {
                    $supported = true;
                } elseif (str_starts_with($mark, '---') || $mark === '—') {
                    $unsupported = true;
                }
            }
            if ($supported) {
                $marks[$mi] = true;
            } elseif ($unsupported) {
                $marks[$mi] = false;
            }
        }
        $param     = $header['param'] !== null ? ($cells[$header['param']] ?? '') : '';
        $entries[] = ['base' => $baseCmd, 'param' => $param, 'example' => $example, 'marks' => $marks];
    }

    foreach ($header['models'] as $mi => $model) {
        $specName    = $model['name'];
        $moduleModel = mapSpecModel($specName, $moduleNorms);
        if ($moduleModel === null) {
            $unmatchedSpecs[] = str_replace(["\r", "\n"], ' / ', $specName) . ' (' . basename($file) . ')';
            continue;
        }
        if ($modelFilter !== null && stripos($moduleModel, $modelFilter) === false) {
            continue;
        }

        // Modul-Kommandos des Modells
        $caps   = AVRs::getCapabilities($moduleModel);
        $idents = [];
        foreach (CAPABILITY_AREAS as $area) {
            foreach ($caps[$area] ?? [] as $ident) {
                if (is_string($ident) && $ident !== '') {
                    $idents[$ident] = wirePrefix($ident);
                }
            }
        }

        // Richtung A
        $ok = $unsupported = $noRow = [];
        foreach ($idents as $ident => $prefix) {
            $foundSupported = $foundRow = false;
            foreach ($entries as $e) {
                if (!prefixMatchesExample($e['example'], $prefix)) {
                    continue;
                }
                if (!array_key_exists($mi, $e['marks'])) {
                    continue;
                }
                $foundRow = true;
                if ($e['marks'][$mi]) {
                    $foundSupported = true;
                    break;
                }
            }
            if ($foundSupported) {
                $ok[] = $ident;
            } elseif ($foundRow) {
                $unsupported[] = $ident;
            } else {
                $noRow[] = $ident;
            }
        }

        // Richtung B: unterstützte Spec-Kommandos ohne Modul-Abdeckung
        $missingGroups = [];
        foreach ($entries as $e) {
            if (($e['marks'][$mi] ?? false) !== true) {
                continue;
            }
            $covered = false;
            foreach ($idents as $prefix) {
                if (prefixMatchesExample($e['example'], $prefix)) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                continue;
            }
            $token = strtok($e['param'], " \n");
            $key   = ($token !== false && $token !== '' && str_starts_with($e['example'], $e['base'] . $token))
                ? $e['base'] . $token : $e['base'];
            $missingGroups[$key] ??= $e['example'];
        }

        $summary[] = [
            'file'        => basename($file),
            'spec'        => str_replace(["\r", "\n"], ' / ', $specName),
            'model'       => $moduleModel,
            'ok'          => $ok,
            'unsupported' => $unsupported,
            'noRow'       => $noRow,
            'missing'     => $missingGroups
        ];
    }
}

// ---- Report ----------------------------------------------------------------

foreach ($summary as $s) {
    echo "==== {$s['model']}  (Spec: {$s['spec']}, Datei: {$s['file']}) ====\n";
    echo 'Modul-Kommandos: OK=', count($s['ok']),
        ', LAUT SPEC NICHT UNTERSTÜTZT=', count($s['unsupported']),
        ', KEINE SPEC-ZEILE=', count($s['noRow']),
        ' | Spec-Kommandos ohne Modul-Abdeckung: ', count($s['missing']), "\n";
    if ($s['unsupported'] !== []) {
        echo "  LAUT SPEC NICHT UNTERSTÜTZT: ", implode(', ', $s['unsupported']), "\n";
    }
    if ($s['noRow'] !== []) {
        echo "  KEINE SPEC-ZEILE (Namensabweichung/Nicht-Wire-Ident): ", implode(', ', $s['noRow']), "\n";
    }
    if ($s['missing'] !== []) {
        echo "  IM PROTOKOLL, IM MODUL FEHLEND (Kommando: Beispiel):\n";
        foreach ($s['missing'] as $key => $example) {
            echo "    - $key: $example\n";
        }
    }
    if ($details && $s['ok'] !== []) {
        echo "  OK: ", implode(', ', $s['ok']), "\n";
    }
    echo "\n";
}

echo "Spec-Modelle ohne Modul-Pendant: ", ($unmatchedSpecs === [] ? '-' : implode('; ', array_unique($unmatchedSpecs))), "\n";
echo "Nicht geprüft (binäres .xls): ", ($skippedXls === [] ? '-' : implode(', ', array_map('basename', $skippedXls))), "\n";
echo "\nHinweis: Report ist informativ (Exit 0); Wertebereiche werden nicht geprüft.\n";
