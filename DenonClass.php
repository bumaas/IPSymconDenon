<?php

declare(strict_types=1);

/**
 * Aggregator der Denon/Marantz-Klassenbibliothek.
 *
 * Die Klassen liegen einzeln unter libs/; diese Datei laedt alles in der
 * urspruenglichen Deklarationsreihenfolge. Die Module binden weiterhin nur
 * DenonClass.php ein. Statische Initialisierer (z. B. DENON_API_Commands-
 * Referenzen in den Modellklassen) werden von PHP lazy aufgeloest - es genuegt,
 * dass am Ende alles geladen ist; an der Reihenfolge nichts "reparieren".
 */

require_once __DIR__ . '/AVRModels.php';
require_once __DIR__ . '/libs/AVRModule.php';
require_once __DIR__ . '/libs/DENONIPSVarType.php';
require_once __DIR__ . '/libs/DENONIPSProfiles.php';
require_once __DIR__ . '/libs/DENON_StatusHTML.php';
require_once __DIR__ . '/libs/DENON_HTTP_Interface.php';
require_once __DIR__ . '/libs/DENON_API_Commands.php';
require_once __DIR__ . '/libs/DenonAVRCP_API_Data.php';

