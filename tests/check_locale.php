<?php

declare(strict_types=1);

/**
 * Prueft die Uebersetzungs-Vollstaendigkeit der Module dieses Repos:
 *
 *  - Jeder caption/label/suffix-Text aus form.json braucht einen de-Schluessel in locale.json.
 *  - Die Formulare werden groesstenteils dynamisch in PHP gebaut (GetConfigurationForm):
 *    daher werden zusaetzlich alle 'caption' => '...'-String-Literale aus module.php
 *    (und fuer die AVR-Module aus der gemeinsamen DenonClass.php) geprueft.
 *    Zusammengesetzte Captions (z. B. $caption . ' (' . $command . ')') sind statisch
 *    nicht aufloesbar und bleiben bewusst aussen vor.
 *  - Jeder Translate('...')-Text (module.php und form.json-Skripte, z. B. onClick)
 *    braucht ebenfalls einen de-Schluessel.
 *  - Verwaiste de-Schluessel werden nur gemeldet, nicht als Fehler gewertet
 *    (dynamische Nutzung wie zusammengesetzte Captions ist moeglich).
 *
 * Exit-Code 1 bei fehlenden Uebersetzungen (fuer die CI), sonst 0.
 * Aufruf: php tests/check_locale.php
 */

$moduleDirs = [
    'Denon AVR HTTP',
    'Denon AVR Telnet',
    'Denon Discovery',
    'Denon HTTP IO',
    'Denon Splitter HTTP',
    'Denon Splitter Telnet'
];

// PHP-Dateien, deren Formularteile in mehreren Modulen auftauchen (AVRModule in DenonClass.php)
$sharedPhpFiles = [
    'Denon AVR HTTP'   => ['DenonClass.php'],
    'Denon AVR Telnet' => ['DenonClass.php']
];

$root = dirname(__DIR__);
$fail = false;

foreach ($moduleDirs as $moduleDir) {
    $dir = $root . '/' . $moduleDir;
    echo "==== $moduleDir ====\n";

    $localeFile = $dir . '/locale.json';
    if (!is_file($localeFile)) {
        echo "keine locale.json vorhanden - uebersprungen\n\n";
        continue;
    }
    $locale = json_decode(file_get_contents($localeFile), true, 512, JSON_THROW_ON_ERROR);
    $deKeys = array_keys($locale['translations']['de'] ?? []);

    $modulePhp = file_get_contents($dir . '/module.php');
    foreach ($sharedPhpFiles[$moduleDir] ?? [] as $sharedFile) {
        $modulePhp .= "\n" . file_get_contents($root . '/' . $sharedFile);
    }

    // 1) Alle caption/label/suffix-Texte aus form.json rekursiv einsammeln
    $formTexts = [];
    $formRaw   = '';
    $formFile  = $dir . '/form.json';
    if (is_file($formFile)) {
        $formRaw = file_get_contents($formFile);
        $form    = json_decode($formRaw, true, 512, JSON_THROW_ON_ERROR);
        collectFormTexts($form, '', $formTexts);
    }

    // 2) caption-Literale aus dem dynamischen Formularbau in PHP
    $phpCaptionTexts = collectPhpCaptionTexts($modulePhp);

    // 3) Translate-Aufrufe aus module.php und aus den Skripten in form.json (z. B. onClick)
    $translateTexts = collectTranslateTexts($modulePhp . "\n" . $formRaw);

    // Fehlend: form.json-Text ohne de-Schluessel
    $missingForm = [];
    foreach ($formTexts as $text => $paths) {
        if (!in_array($text, $deKeys, true)) {
            $missingForm[$text] = $paths[0];
        }
    }

    // Fehlend: PHP-caption ohne de-Schluessel
    $missingCaption = [];
    foreach (array_keys($phpCaptionTexts) as $text) {
        if (!in_array($text, $deKeys, true)) {
            $missingCaption[] = $text;
        }
    }

    // Fehlend: Translate-Text ohne de-Schluessel
    $missingPhp = [];
    foreach (array_keys($translateTexts) as $text) {
        if (!in_array($text, $deKeys, true)) {
            $missingPhp[] = $text;
        }
    }

    // Verwaist: de-Schluessel weder als Formular-Text noch als Translate-Text
    $orphans = [];
    foreach ($deKeys as $key) {
        if (!isset($formTexts[$key]) && !isset($phpCaptionTexts[$key]) && !isset($translateTexts[$key])) {
            $inLiteral = str_contains($modulePhp, $key) || ($formRaw !== '' && str_contains($formRaw, $key));
            $orphans[] = [$key, $inLiteral ? 'kommt woertlich in module.php/form.json vor' : 'nirgends gefunden'];
        }
    }

    echo 'form.json Texte (unique): ' . count($formTexts) . "\n";
    echo 'PHP-caption-Literale:     ' . count($phpCaptionTexts) . "\n";
    echo 'locale de-Schluessel:     ' . count($deKeys) . "\n";
    echo 'Translate-Texte:          ' . count($translateTexts) . "\n";
    echo 'Sprachen in locale.json:  ' . implode(', ', array_keys($locale['translations'] ?? [])) . "\n\n";

    echo 'FEHLENDE UEBERSETZUNGEN (form.json -> kein de-Schluessel): ' . count($missingForm) . "\n";
    foreach ($missingForm as $text => $path) {
        echo "  - \"$text\"  ($path)\n";
    }
    echo 'FEHLENDE UEBERSETZUNGEN (PHP-caption -> kein de-Schluessel): ' . count($missingCaption) . "\n";
    foreach ($missingCaption as $text) {
        echo "  - \"$text\"\n";
    }
    echo 'FEHLENDE UEBERSETZUNGEN (Translate -> kein de-Schluessel): ' . count($missingPhp) . "\n";
    foreach ($missingPhp as $text) {
        echo "  - \"$text\"\n";
    }
    echo 'VERWAISTE de-SCHLUESSEL (nur Hinweis, kein Fehler): ' . count($orphans) . "\n";
    foreach ($orphans as [$key, $note]) {
        echo "  - \"$key\"  [$note]\n";
    }
    echo "\n";

    if ($missingForm !== [] || $missingCaption !== [] || $missingPhp !== []) {
        $fail = true;
    }
}

if ($fail) {
    echo "FEHLER: Es fehlen Uebersetzungen (siehe oben).\n";
    exit(1);
}

echo "OK: Alle Texte sind uebersetzt.\n";

function collectFormTexts(array $node, string $path, array &$formTexts): void
{
    foreach ($node as $k => $v) {
        $p = $path . (is_int($k) ? '[' . $k . ']' : '.' . $k);
        if (in_array($k, ['caption', 'label', 'suffix'], true) && is_string($v) && $v !== '') {
            $formTexts[$v][] = $p;
        }
        if (is_array($v)) {
            collectFormTexts($v, $p, $formTexts);
        }
    }
}

/**
 * Sammelt alle 'caption' => '...'-String-Literale aus PHP-Quelltext.
 * Konkatenationen ('caption' => 'Zone ' . $Zone) werden ausgelassen,
 * da der Gesamttext statisch nicht bestimmbar ist.
 *
 * @return array<string, true> Texte als Schluessel (dedupliziert)
 */
function collectPhpCaptionTexts(string $code): array
{
    $texts = [];
    foreach (
        [
            '/[\'"]caption[\'"]\s*=>\s*\'((?:[^\'\\\\]|\\\\.)*)\'(?!\s*\.)/s',
            '/[\'"]caption[\'"]\s*=>\s*"((?:[^"\\\\]|\\\\.)*)"(?!\s*\.)/s'
        ] as $pattern
    ) {
        if (preg_match_all($pattern, $code, $matches)) {
            foreach ($matches[1] as $text) {
                if ($text !== '') {
                    $texts[stripcslashes($text)] = true;
                }
            }
        }
    }

    return $texts;
}

/**
 * Sammelt die Argumente aller Translate('...')-/Translate("...")-Aufrufe im uebergebenen Quelltext.
 *
 * @return array<string, true> Texte als Schluessel (dedupliziert)
 */
function collectTranslateTexts(string $code): array
{
    $texts = [];
    foreach (
        [
            '/->Translate\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s',
            '/->Translate\(\s*"((?:[^"\\\\]|\\\\.)*)"/s'
        ] as $pattern
    ) {
        if (preg_match_all($pattern, $code, $matches)) {
            foreach ($matches[1] as $text) {
                $texts[stripcslashes($text)] = true;
            }
        }
    }

    return $texts;
}
