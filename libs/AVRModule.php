<?php

declare(strict_types=1);

class AVRModule extends IPSModuleStrict
{
    private const string PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE = 'WriteDebugInformationToLogfile';

    // Konstante für die Übersichtlichkeit und einfache Wartung
    private const array LEGACY_TRUE_PROPERTIES = [
        DENONIPSProfiles::ptPower,
        DENONIPSProfiles::ptMainZonePower,
        DENONIPSProfiles::ptMainMute,
        'InputSource', // Tipp: Auch hierfür eine Konstante in DENONIPSProfiles nutzen, falls möglich
        DENONIPSProfiles::ptSurroundMode,
        DENONIPSProfiles::ptMasterVolume,
        DENONIPSProfiles::ptZone2Name,
        DENONIPSProfiles::ptZone3Name,
        DENONIPSProfiles::ptZone2Power,
        DENONIPSProfiles::ptZone3Power,
        DENONIPSProfiles::ptZone2Mute,
        DENONIPSProfiles::ptZone3Mute,
        DENONIPSProfiles::ptZone2Volume,
        DENONIPSProfiles::ptZone3Volume,
        DENONIPSProfiles::ptZone2InputSource,
        DENONIPSProfiles::ptZone3InputSource,
    ];

    private const int STATUS_INST_IP_IS_INVALID                = 204; //IP-Adresse ist ungültig
    private const int STATUS_INST_NO_MANUFACTURER_SELECTED     = 210;
    private const int STATUS_INST_NO_ZONE_SELECTED             = 212;
    private const int STATUS_INST_NO_DENON_AVR_TYPE_SELECTED   = 213;
    private const int STATUS_INST_NO_MARANTZ_AVR_TYPE_SELECTED = 214;

    private const string ZERO_WIDTH_SPACE = "\u{200B}";


    protected function SetInstanceStatus(): bool
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return false;
        }

        $manufacturer = $this->ReadPropertyInteger('manufacturer');
        $zone = $this->ReadPropertyInteger('Zone');

        // 1. Validierung der Basiseigenschaften (Guard Clauses)
        if ($manufacturer === 0) {
            $this->SetStatus(self::STATUS_INST_NO_MANUFACTURER_SELECTED);
            return false;
        }

        // Hersteller-spezifische Prüfung
        if ($manufacturer === 1 && $this->ReadPropertyInteger('AVRTypeDenon') === 50) {
            $this->SetStatus(self::STATUS_INST_NO_DENON_AVR_TYPE_SELECTED);
            return false;
        }

        if ($manufacturer === 2 && $this->ReadPropertyInteger('AVRTypeMarantz') === 50) {
            $this->SetStatus(self::STATUS_INST_NO_MARANTZ_AVR_TYPE_SELECTED);
            return false;
        }

        // Zone und Kategorie
        if ($zone === 6) {
            $this->SetStatus(self::STATUS_INST_NO_ZONE_SELECTED);
            return false;
        }

        // 2. Verbindung prüfen
        if ($this->GetIPParent() === false) {
            $this->SetStatus(self::STATUS_INST_IP_IS_INVALID);
            return false;
        }

        // 3. Finaler Status
        $status = $this->HasActiveParent() ? IS_ACTIVE : IS_INACTIVE;
        $this->SetStatus($status);

        return $status === IS_ACTIVE;
    }

    // Daten vom Splitter Instanz
    public function ReceiveData(string $JSONString):string
    {

        // Empfangene Daten vom Splitter
        $data = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $this->Logger_Dbg(__FUNCTION__, json_encode($data->Buffer->Data, JSON_THROW_ON_ERROR));
        $this->UpdateVariable($data->Buffer);
        return '';
    }

    // Wertet Response aus und setzt Variablen
    protected function UpdateVariable($data): bool
    {
        //$data = json_decode('{"ResponseType":"TELNET","Data":[],"SurroundDisplay":"","Display":{"1":"\u0001GAMPER & DADONI - BITTERSWEET SYMPHONY (feat. Emily Roberts)","2":"\u0001Radio 7"}}');
        $this->Logger_Dbg(__FUNCTION__, 'data: ' . json_encode($data, JSON_THROW_ON_ERROR));

        $ResponseType = $data->ResponseType;

        $Zone = $this->ReadPropertyInteger('Zone');
        $this->Logger_Dbg(__FUNCTION__, sprintf('ResponseType: %s, Zone: %s', $ResponseType, $Zone));

        switch ($ResponseType) {
            case 'HTTP':
                $datavalues = match ($Zone) {
                    0       => $data->Data->Mainzone,
                    1       => $data->Data->Zone2,
                    2       => $data->Data->Zone3,
                    default => null,
                };
                break;

            case 'TELNET':
                $datavalues = $data->Data;
                $this->Logger_Dbg(__FUNCTION__, 'Data Telnet: ' . json_encode($datavalues, JSON_THROW_ON_ERROR));

                if ($Zone === 0) {
                    //SurroundDisplay
                    if ($this->ReadPropertyBoolean('SurroundDisplay')) {
                        $SurroundDisplay = $data->SurroundDisplay;
                        if ($SurroundDisplay !== '') {
                            $this->Logger_Dbg(__FUNCTION__, 'Surround Display: ' . $SurroundDisplay);
                            $this->SetValue('SurroundDisplay', $SurroundDisplay);
                            //SetValueString($this->GetIDForIdent('SurroundDisplay'), $SurroundDisplay);
                        }
                    }
                    // OnScreenDisplay
                    if ($this->ReadPropertyBoolean('Display')) {
                        $OnScreenDisplay = $data->Display;
                        $this->Logger_Dbg(__FUNCTION__, 'Display: ' . json_encode($OnScreenDisplay, JSON_THROW_ON_ERROR));

                        $DisplayHTML = $this->GetValue(DENON_API_Commands::DISPLAY);
                        $doc = new DOMDocument();
                        $doc->loadHTML($DisplayHTML);
                        foreach ($OnScreenDisplay as $row => $content) {
                            $node = $doc->getElementById('NSARow' . $row);
                            if (!isset($node)){
                                continue;
                            }
                            if (($row > 0) && ($row < 8)) {
                                if ((ord(substr($content, 0, 1)) & 8) === 8) { //Cursor Select (8) ist gesetzt
                                    $this->Logger_Dbg(__FUNCTION__, 'row: ' . $row . ', content[0]: ' . decbin(ord(substr($content, 0, 1))));
                                    $node->setAttribute('style', 'color:#FF0000');
                                } elseif ($node->hasAttribute('style')) {
                                    $node->removeAttribute('style');
                                }
                                if ($content !== ''){
                                    $content = substr($content, 1);
                                }
                            }

                            $node->textContent = $content;
                        }

                        $this->SetValue(DENON_API_Commands::DISPLAY, $doc->saveHTML());
                    }
                }
                break;
            default:
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Unknown response type: ' . $ResponseType);

                return false;
        }

        if ($datavalues === null) {
            $this->Logger_Err(__FUNCTION__ . ': ' . json_encode(debug_backtrace(), JSON_THROW_ON_ERROR));
            return false;
        }

        foreach ($datavalues as $Ident => $Values) {
            $Ident = str_replace(' ', '_', $Ident);

            $VarID = @$this->GetIDForIdent($Ident);

            if ($VarID === false) {
                $this->Logger_Dbg(__FUNCTION__, $this->InstanceID . ': Info: Keine Variable mit dem Ident "' . $Ident . '" gefunden.');
                continue;
            }

            $VarType = $Values->VarType;
            $Subcommand = $Values->Subcommand;
            $value = $Values->Value;

            // Spezialbehandlung für Float (is_numeric Check)
            if ($VarType === DENONIPSVarType::vtFloat) {
                $value = is_numeric($value) ? (float)$value : 0.0;
            }

            // Setzen des Wertes
            $this->SetValue($Ident, $value);

            // Logging vorbereiten
            $logValue = ($VarType === DENONIPSVarType::vtBoolean) ? (int)$value : $value;
            $logMessage = sprintf(
                'Update ObjektID %d (%s): %s(%s)',
                $VarID,
                IPS_GetName($VarID),
                $Subcommand,
                $logValue
            );

            // Spezielle Log-Anpassung für String (falls Subcommand nicht nötig)
            if ($VarType === DENONIPSVarType::vtString) {
                $logMessage = sprintf('Update ObjektID %d (%s): %s', $VarID, IPS_GetName($VarID), $value);
            }

            $this->Logger_Dbg(__FUNCTION__, $logMessage);
        }

        return true;
    }

    protected function RegisterProperties(): void
    {
        // 1. Experten-Parameter (Logging)
        $this->RegisterPropertyBoolean(self::PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE, false);

        // 2. Geräte-Basiskonfiguration
        $this->RegisterPropertyInteger('manufacturer', 0);
        $this->RegisterPropertyInteger('AVRTypeDenon', 50);
        $this->RegisterPropertyInteger('AVRTypeMarantz', 50);
        $this->RegisterPropertyInteger('Zone', 6);

        // 3. Dynamische Profile registrieren
        $this->registerDynamicAVRProperties();

        // 4. Zusätzliche Features
        $this->registerAdditionalInputs();
    }

    private function registerDynamicAVRProperties(): void
    {
        $profileManager = new DENONIPSProfiles(null, null, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        });

        foreach ($profileManager->GetAllProfiles() as $profile) {
            $name = $profile['PropertyName'];
            $defaultValue = in_array($name, self::LEGACY_TRUE_PROPERTIES, true);

            $this->RegisterPropertyBoolean($name, $defaultValue);
        }
    }

    private function registerAdditionalInputs(): void
    {
        $inputs = ['FAVORITES', 'IRADIO', 'SERVER', 'NAPSTER', 'LASTFM', 'FLICKR'];
        foreach ($inputs as $input) {
            $this->RegisterPropertyBoolean($input, false);
        }
    }

    protected function GetVariablePresentation(array $varDef): array|string
    {
        $suffix = match ($varDef['Suffix'] ?? '') {
            '%' => self::ZERO_WIDTH_SPACE . '%',
            '' => '',
            default => ' ' . $varDef['Suffix']
        };

        $associations = $varDef['Associations'] ?? [];
        $enumOptions = $this->buildEnumerationOptions($associations);
        $valueOptions = $this->buildValuePresentationOptions($associations);
        $intervals = $this->buildValuePresentationIntervals($associations);

        if ($varDef['displayOnly']) {
            if (($varDef['ProfilName'] ?? '') === '~HTMLBox') {
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
                ];
            }

            $presentation = [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON'         => $varDef['Icon'] ?? false,
                'SUFFIX'       => $suffix,
            ];

            if (in_array($varDef['Type'], [DENONIPSVarType::vtBoolean, DENONIPSVarType::vtString], true) && $valueOptions !== null) {
                $presentation['OPTIONS'] = $valueOptions;
            }

            if (in_array($varDef['Type'], [DENONIPSVarType::vtInteger, DENONIPSVarType::vtFloat], true) && $intervals !== null) {
                $presentation['INTERVALS'] = $intervals;
                $presentation['INTERVALS_ACTIVE'] = true;
            }

            return $presentation;
        }

        return match ($varDef['Type']) {
            DENONIPSVarType::vtBoolean => [
                'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                'ICON'         => $varDef['Icon'] ?? false,
            ],

            DENONIPSVarType::vtInteger => array_filter([
                'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                'ICON'         => $varDef['Icon'] ?? false,
                'SUFFIX'       => $suffix,
                'OPTIONS'      => $enumOptions,
            ], static fn ($value) => $value !== null),

            DENONIPSVarType::vtFloat => [
                'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                'ICON'         => $varDef['Icon'] ?? false,
                'SUFFIX'       => $suffix,
                'MIN'          => $varDef['MinValue'],
                'MAX'          => $varDef['MaxValue'],
                'STEP_SIZE'    => $varDef['Stepsize'],
                'PERCENTAGE'   => $varDef['Suffix'] === '%',
                'DIGITS'       => $varDef['Digits'],
            ],

            default => throw new InvalidArgumentException(sprintf('Unsupported type: %s', $varDef['Type'])),
        };
    }

    private function buildEnumerationOptions(array $associations): ?string
    {
        if (count($associations) === 0) {
            return null;
        }

        $options = [];
        foreach ($associations as $association) {
            if (!array_key_exists(0, $association)) {
                continue;
            }

            $value     = $association[0];
            $caption   = (string)($association[1] ?? $value);
            $options[] = [
                'Value'      => $value,
                'Caption'    => $caption,
                'IconActive' => false,
                'IconValue'  => '',
                'Color'      => -1,
            ];
        }

        if (count($options) === 0) {
            return null;
        }

        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    private function buildValuePresentationOptions(array $associations): ?string
    {
        if (count($associations) === 0) {
            return null;
        }

        $options = [];
        foreach ($associations as $association) {
            if (!array_key_exists(0, $association)) {
                continue;
            }

            $value = $association[0];
            $caption = (string)($association[1] ?? $value);
            $options[] = [
                'Value'       => $value,
                'Caption'     => $caption,
                'IconActive'  => false,
                'IconValue'   => '',
                'ColorActive' => false,
                'ColorValue'  => -1,
            ];
        }

        if (count($options) === 0) {
            return null;
        }

        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    private function buildValuePresentationIntervals(array $associations): ?string
    {
        if (count($associations) === 0) {
            return null;
        }

        $intervals = [];
        foreach ($associations as $association) {
            if (!array_key_exists(0, $association)) {
                continue;
            }

            $value = $this->normalizeNumericValue($association[0]);
            if ($value === null) {
                continue;
            }

            $intervals[] = [
                'From'        => $value,
                'To'          => $value,
                'IconActive'  => false,
                'Icon'        => '',
                'ColorActive' => false,
                'Color'       => -1,
            ];
        }

        if (count($intervals) === 0) {
            return null;
        }

        return json_encode($intervals, JSON_THROW_ON_ERROR);
    }

    private function normalizeNumericValue(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (!is_string($value) || !is_numeric($value)) {
            return null;
        }

        if (str_contains($value, '.') || str_contains($value, ',')) {
            return (float) str_replace(',', '.', $value);
        }

        return (int) $value;
    }

    protected function RegisterVariables(DENONIPSProfiles $DenonAVRVar, array $idents, string $manufacturername): bool
    {
        $this->Logger_Dbg(__FUNCTION__, 'idents: ' . json_encode($idents, JSON_THROW_ON_ERROR));

        if (!in_array($manufacturername, [DENONIPSProfiles::ManufacturerDenon, DENONIPSProfiles::ManufacturerMarantz], true)) {
            trigger_error('ManufacturerName not set');
            return false;
        }

        foreach ($idents as $configId => $selected) {
            $config = $DenonAVRVar->GetVariableConfig($configId);

            if ($config === false) {
                continue;
            }

            if (!$selected) {
                $this->removeVariable($config['Ident']);
                continue;
            }

            // Variable registrieren basierend auf Typ
            if (!$this->registerSingleVariable($config, $configId)) {
                return false;
            }

            // Aktions-Handler aktivieren, wenn es keine reine Anzeige-Variable ist
            if (empty($config['displayOnly'])) {
                $this->EnableAction($config['Ident']);
            }
        }

        return true;
    }

    private function registerSingleVariable(array $config, $configId): bool
    {
        $presentation = $this->GetVariablePresentation($config);
        $this->SendDebug(__FUNCTION__, sprintf('presentation: %s', json_encode($presentation, JSON_THROW_ON_ERROR)), 0);

        switch ($config['Type']) {
            case DENONIPSVarType::vtString:
                $this->RegisterVariableString($config['Ident'], $config['Name'], $presentation, $config['Position']);
                if ($configId === DENON_API_Commands::DISPLAY) {
                    $this->SetValue($config['Ident'], $this->getDisplayTemplate());
                }
                break;

            case DENONIPSVarType::vtBoolean:
                $this->RegisterVariableBoolean($config['Ident'], $config['Name'], $presentation, $config['Position']);
                break;

            case DENONIPSVarType::vtInteger:
                $this->RegisterVariableInteger($config['Ident'], $config['Name'], $presentation, $config['Position']);
                break;

            case DENONIPSVarType::vtFloat:
                $this->RegisterVariableFloat($config['Ident'], $config['Name'], $presentation, $config['Position']);
                break;

            default:
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': invalid Type: ' . $config['Type']);
                return false;
        }

        return true;
    }

    private function getDisplayTemplate(): string
    {
        $rows = '';
        for ($i = 0; $i <= 8; $i++) {
            $rows .= "<div id=\"NSARow$i\"></div>";
        }

        return "<!--suppress HtmlRequiredLangAttribute --><html><body>$rows</body></html>";
    }


    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID); //array
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : 0; //ConnectionID
    }


    protected function GetAPICommandFromIdent($Ident): string
    {
        if (in_array($Ident, [DENON_API_Commands::Z2POWER, DENON_API_Commands::Z2INPUT, DENON_API_Commands::Z2VOL], true)) {
            $APICommand = DENON_API_Commands::Z2;
        } elseif (in_array($Ident, [DENON_API_Commands::Z3POWER, DENON_API_Commands::Z3INPUT, DENON_API_Commands::Z3VOL], true)) {
            $APICommand = DENON_API_Commands::Z3;
        } elseif ($Ident === 'PVPICT') {
            $APICommand = 'PV';
        } else {
            $APICommand = str_replace('_', ' ', $Ident); //Ident _ von Ident mit Leerzeichen ersetzten
        }

        return $APICommand;
    }

    public function RequestAction($Ident, $Value): void
    {
        //Input übergeben
        $InputMapping = $this->getSplitterInputVarMapping();
        $this->Logger_Dbg(__FUNCTION__, 'InputMapping: ' . json_encode($InputMapping, JSON_THROW_ON_ERROR));

        //Command aus Ident
        $APICommand = $this->GetAPICommandFromIdent($Ident);

        // Subcommand holen
        $AVRType       = $this->GetAVRType($this->GetManufacturerName());
        $APISubCommand = new DENONIPSProfiles($AVRType, $InputMapping, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        })->GetSubCommandOfValue($Ident, $Value);
        $this->Logger_Dbg(__FUNCTION__, 'Ident: ' . $Ident . ', Value: ' . $Value . ', SubCommand: ' . $APISubCommand);

        // Daten senden
        try {
            $this->SendCommand($APICommand . $APISubCommand);
            $this->afterRequestAction($APICommand);
        } catch (Exception $ex) {
            $this->Logger_Err($ex->getMessage() . ', Code: ' . $ex->getCode());
        }
    }

    // vom Modul zu überschreiben: InputMapping vom jeweiligen Splitter holen
    protected function getSplitterInputVarMapping(): array
    {
        return [];
    }

    // Hook nach dem Senden (z. B. Status-Nachführung im Telnet-Modul)
    protected function afterRequestAction(string $APICommand): void
    {
    }

    // Ident gehört zu einer Nebenzone (Z2/Z3/Zone2/Zone3-Präfix)?
    protected function isZoneSpecificIdent(string $ident): bool
    {
        return in_array(substr($ident, 0, 2), ['Z2', 'Z3'], true)
               || in_array(substr($ident, 0, 5), ['Zone2', 'Zone3'], true);
    }

    // Ident gehört zur übergebenen Zonennummer (2 oder 3)?
    protected function identMatchesZone(string $ident, int $zoneNumber): bool
    {
        return str_starts_with($ident, 'Z' . $zoneNumber)
               || str_starts_with($ident, 'Zone' . $zoneNumber);
    }

    /**
     * Kanonisches Befehlsmuster: Subcommand aus dem Profilkatalog nachschlagen
     * und (Sende-Präfix . Subcommand) an den Splitter schicken.
     * $sendPrefix nur setzen, wenn er vom Lookup-Ident abweicht (z. B. Z2POWER -> Z2).
     */
    protected function sendMappedValue(string $lookupIdent, bool|int|float $value, ?string $sendPrefix = null): void
    {
        $subCommand = new DENONIPSProfiles()->GetSubCommandOfValue($lookupIdent, $value);
        $this->SendCommand(($sendPrefix ?? $lookupIdent) . $subCommand);
    }

    protected function sendMappedValueName(string $lookupIdent, string $valueName, ?string $sendPrefix = null): void
    {
        $subCommand = new DENONIPSProfiles()->GetSubCommandOfValueName($lookupIdent, $valueName);
        $this->SendCommand(($sendPrefix ?? $lookupIdent) . $subCommand);
    }

    protected function GetManufacturerName(): string
    {
        $manufacturer = $this->ReadPropertyInteger('manufacturer');
        switch ($manufacturer) {
            case 0:
                $manufacturername = DENONIPSProfiles::ManufacturerNone;
                break;
            case 1:
                $manufacturername = DENONIPSProfiles::ManufacturerDenon;
                break;
            case 2:
                $manufacturername = DENONIPSProfiles::ManufacturerMarantz;
                break;

            default:
                trigger_error('Unknown manufacturer: ' . $manufacturer);
                $manufacturername = '';
        }

        return $manufacturername;
    }

    protected function GetAVRType($manufacturername)
    {
        switch ($manufacturername) {
            case DENONIPSProfiles::ManufacturerDenon:
                $TypeInt = $this->ReadPropertyInteger('AVRTypeDenon');
                break;
            case DENONIPSProfiles::ManufacturerMarantz:
                $TypeInt = $this->ReadPropertyInteger('AVRTypeMarantz');
                break;
            default:
                return false;
        }

        if ($TypeInt === 50) { //none
            return false;
        }

        foreach (AVRs::getAllAVRs() as $Caps) {
            if ($Caps['internalID'] === $TypeInt) {
                return $Caps['Name'];
            }
        }

        return false;
    }

    protected function removeVariable($Ident): void
    {
        if ($vid = @$this->GetIDForIdent($Ident)) {
            $Name = IPS_GetName($vid);
            $this->DisableAction($Ident);
            $this->UnregisterVariable($Ident);
            $this->Logger_Inf('Variable gelöscht - Name: ' . $Name . ', Ident: ' . $Ident . ', ObjektID: ' . $vid);
        }
    }

    protected function GetInputsAVR(DENONIPSProfiles $DenonAVRVar): array
    {
        $Zone = $this->ReadPropertyInteger('Zone');

        $DenonAVRVar->SetInputSources(
            $this->GetIPParent(),
            $Zone,
            $this->ReadPropertyBoolean('FAVORITES'),
            $this->ReadPropertyBoolean('IRADIO'),
            $this->ReadPropertyBoolean('SERVER'),
            $this->ReadPropertyBoolean('NAPSTER'),
            $this->ReadPropertyBoolean('LASTFM'),
            $this->ReadPropertyBoolean('FLICKR')
        );

        return $DenonAVRVar->GetInputVarMapping($Zone);
    }

    //IP des AVR aus der IO Instanz
    protected function GetIPParent()
    {
        $parentId = $this->GetParent();
        if ($parentId <= 0 || !@IPS_InstanceExists($parentId)) {
            return false;
        }

        $io_instance = IPS_GetInstance($parentId)['ConnectionID'];
        if ($io_instance <= 0 || !@IPS_InstanceExists($io_instance)) {
            return false;
        }

        $IP = IPS_GetProperty($io_instance, 'Host');
        if (!filter_var($IP, FILTER_VALIDATE_IP) === false) {
            return $IP;
        }

        return false;
    }

    protected function FormSelectionZone(): array
    {
        return [
            [
                'type'    => 'Label',
                'caption' => 'Please select an AVR zone and push the "Apply Changes" button'
            ],
            [
                'type'    => 'Select',
                'name'    => 'Zone',
                'caption' => 'AVR Zone',
                'options' => [
                    [
                        'label' => 'Main Zone',
                        'value' => 0
                    ],
                    [
                        'label' => 'Zone 2',
                        'value' => 1
                    ],
                    [
                        'label' => 'Zone 3',
                        'value' => 2
                    ],
                    [
                        'label' => 'select zone',
                        'value' => 6
                    ]
                ]
            ]
        ];
    }

    protected function FormSelectionAVR($manufacturer): array
    {
        return [
            [
                'type'    => 'Label',
                'caption' => 'Please select an AVR type and push the "Apply Changes" button'
            ],
            [
                'type'    => 'Select',
                'name'    => 'AVRType' . $manufacturer,
                'caption' => 'Type AVR ' . $manufacturer,
                'options' => $this->FormSelectionAVROptions($manufacturer)
            ]
        ];
    }

    protected function FormSelectionAVROptions(string $manufacturer): array
    {
        $form = [
            [
                'value'   => 50,
                'caption' => 'select AVR Type'
            ]
        ];
        foreach (AVRs::getAllAVRs() as $AVRName => $Caps) {
            if ($Caps['Manufacturer'] === $manufacturer) {
                $form[] = [
                    'value'   => $Caps['internalID'],
                    'caption' => $AVRName
                ];
            }
        }
        return $form;
    }

    protected function FormMoreInputs(): array
    {
        return [
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'more inputs',
                'items'   => [
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'FAVORITES',
                        'caption' => 'favorites'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'IRADIO',
                        'caption' => 'internet radio'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'SERVER',
                        'caption' => 'Server'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'NAPSTER',
                        'caption' => 'Napster'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'LASTFM',
                        'caption' => 'LastFM'
                    ],
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'FLICKR',
                        'caption' => 'Flickr'
                    ],
                ]
            ]
        ];
    }

    protected function FormExpertParameters(): array
    {
        return [
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Expert Parameters',
                'items'   => [
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'WriteDebugInformationToLogfile',
                        'caption' => 'Debug information are written additionally to standard logfile'],

                ]]];
    }

    protected function FormStatus(): array
    {
        return  [
            [
                'code'    => 204,
                'icon'    => 'error',
                'caption' => 'IP address is not valid.'
            ],
            [
                'code'    => 210,
                'icon'    => 'error',
                'caption' => 'select a manufacturer.'
            ],
            [
                'code'    => 211,
                'icon'    => 'error',
                'caption' => 'select category for import.'
            ],
            [
                'code'    => 212,
                'icon'    => 'error',
                'caption' => 'please select an AVR Zone.'
            ],
            [
                'code'    => 213,
                'icon'    => 'error',
                'caption' => 'please select a Denon AVR type.'
            ],
            [
                'code'    => 214,
                'icon'    => 'error',
                'caption' => 'please select a Marantz AVR type.'
            ]
        ];
    }

    protected function getTypeItem($type, $command, $propertyname, $caption, $CapsItems = null): ?array
    {
        if ($propertyname === '') {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': ' . $command . ': PropertyName nicht gesetzt.');

            return null;
        }

        // is the command supported?
        if ($CapsItems === null || in_array($command, $CapsItems, true)) {
            return [
                'type'    => $type,
                'name'    => $propertyname,
                'caption' => $caption . ' (' . $command . ')'
            ];
        }
        return null;
    }

    protected function Logger_Err(string $message): void
    {
        $this->SendDebug('LOG_ERR', $message, 0);
        /*
        if (function_exists('IPSLogger_Err') && $this->ReadPropertyBoolean('WriteLogInformationToIPSLogger')) {
            IPSLogger_Err(__CLASS__, $message);
        }
        */
        $this->LogMessage($message, KL_ERROR);

    }

    protected function Logger_Warn(string $message): void
    {
        $this->SendDebug('LOG_WARN', $message, 0);
        $this->LogMessage($message, KL_WARNING);
    }

    protected function Logger_Inf(string $message): void
    {
        $this->SendDebug('LOG_INFO', $message, 0);
        $this->LogMessage($message, KL_NOTIFY);
    }

    protected function Logger_Dbg(string $message, string $data): void
    {
        $this->SendDebug($message, $data, 0);
        /*
        if (function_exists('IPSLogger_Dbg') && $this->ReadPropertyBoolean('WriteDebugInformationToIPSLogger')) {
            IPSLogger_Dbg(__CLASS__ . '.' . IPS_GetObject($this->InstanceID)['ObjectName'] . '.' . $message, $data);
        }
        */
        if ($this->ReadPropertyBoolean(self::PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE)) {
            $this->LogMessage(sprintf('%s: %s', $message, $data), KL_DEBUG);
        }
    }
}
