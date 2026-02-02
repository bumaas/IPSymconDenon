<?php

declare(strict_types=1);
require_once __DIR__ . '/AVRModels.php';  // diverse Klassen

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

    protected bool $testAllProperties = false;

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

            if ($VarID <= 0) {
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

        $options = null;
        if (!empty($varDef['Associations'])) {
            $formattedOptions = [];
            foreach ($varDef['Associations'] as $value) {
                $formattedOptions[] = [
                    'Value'      => $value[0],
                    'Caption'    => $value[1],
                    'IconActive' => false,
                    'IconValue'  => '',
                    'ColorActive'=> false,
                    'ColorValue' => -1,
                ];
            }
            $options = json_encode($formattedOptions, JSON_THROW_ON_ERROR);
        }

        if ($varDef['displayOnly']){
            if ($varDef['ProfilName'] === '~HTMLBox'){
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
                ];
            }
            return array_filter([
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'SUFFIX'       => $suffix,
                'OPTIONS'      => $options ? json_encode($options, JSON_THROW_ON_ERROR) : null
            ]);
        }

        // Basis-Daten für die meisten Typen
        $baseData = [
            'ICON'    => $varDef['Icon'] ?? false,
            'SUFFIX'  => $suffix,
            'OPTIONS' => $options
        ];

        return match ($varDef['Type']) {
            DENONIPSVarType::vtBoolean => array_filter([
                                                           'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                                                           'ICON'         => $varDef['Icon'] ?? false,
                                                       ]),

            DENONIPSVarType::vtInteger => array_filter(array_merge($baseData, [
                'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            ])),

            DENONIPSVarType::vtFloat => array_filter(array_merge($baseData, [
                'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                'MIN'          => $varDef['MinValue'],
                'MAX'          => $varDef['MaxValue'],
                'STEP_SIZE'    => $varDef['Stepsize'],
                'PERCENTAGE'   => $varDef['Suffix'] === '%',
                'DIGITS'       => $varDef['Digits'],
            ])),

            default => throw new InvalidArgumentException(sprintf('Unsupported type: %s', $varDef['Type'])),
        };

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
        $vid = @$this->GetIDForIdent($Ident);
        if ($vid !== 0) {
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
        $io_instance =  IPS_GetInstance($this->GetParent())['ConnectionID'];
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

class DENONIPSVarType extends stdClass
{
    //  API Datentypen
    public const int vtBoolean = 0;
    public const int vtInteger = 1;
    public const int vtFloat   = 2;
    public const int vtString = 3;
}

#[AllowDynamicProperties] class DENONIPSProfiles extends stdClass
{
    private $Logger_Dbg;

    private bool  $debug = false; //wird im Constructor gesetzt

    private mixed $AVRType;
    private array $profiles;

    public const string ManufacturerDenon   = 'Denon';
    public const string ManufacturerMarantz = 'Marantz';
    public const string ManufacturerNone    = 'none';

    //Profiltype
    public const string ptPower        = 'Power';
    public const string ptMasterVolume = 'MasterVolume';
    public const string ptBalance      = 'Balance';

    public const string ptChannelVolumeFL = 'ChannelVolumeFL';
    public const string ptChannelVolumeFR = 'ChannelVolumeFR';
    public const string ptChannelVolumeC  = 'ChannelVolumeC';
    public const string ptChannelVolumeSW = 'ChannelVolumeSW';
    public const string ptChannelVolumeSW2 = 'ChannelVolumeSW2';
    public const string ptChannelVolumeSW3 = 'ChannelVolumeSW3';
    public const string ptChannelVolumeSW4 = 'ChannelVolumeSW4';
    public const string ptChannelVolumeSL  = 'ChannelVolumeSL';
    public const string ptChannelVolumeSR = 'ChannelVolumeSR';
    public const string ptChannelVolumeSBL = 'ChannelVolumeSBL';
    public const string ptChannelVolumeSBR = 'ChannelVolumeSBR';
    public const string ptChannelVolumeSB  = 'ChannelVolumeSB';
    public const string ptChannelVolumeFHL = 'ChannelVolumeFHL';
    public const string ptChannelVolumeFHR = 'ChannelVolumeFHR';
    public const string ptChannelVolumeFWL = 'ChannelVolumeFWL';
    public const string ptChannelVolumeFWR = 'ChannelVolumeFWR';
    public const string ptMainMute         = 'MainMute';
    public const string ptInputSource = 'Inputsource';
    public const string ptMainZonePower = 'MainZonePower';
    public const string ptInputMode     = 'InputMode';
    public const string ptDigitalInputMode = 'DigitalInputMode';
    public const string ptVideoSelect      = 'VideoSelect';
    public const string ptSleep       = 'Sleep';
    public const string ptSurroundMode = 'SurroundMode';
    public const string ptQuickSelect  = 'QuickSelect';
    public const string ptSmartSelect = 'SmartSelect';
    public const string ptHDMIMonitor = 'HDMIMonitor';
    public const string ptASP         = 'ASP';
    public const string ptResolution = 'Resolution';
    public const string ptResolutionHDMI = 'ResolutionHDMI';
    public const string ptHDMIAudioOutput = 'HDMIAudioOutput';
    public const string ptVideoProcessingMode = 'VideoProcessingMode';
    public const string ptToneCTRL            = 'ToneCTRL';
    public const string ptSurroundBackMode = 'SurroundBackMode';
    public const string ptSurroundPlayMode = 'SurroundPlayMode';
    public const string ptFrontHeight      = 'FrontHeight';
    public const string ptPLIIZHeightGain = 'PLIIZHeightGain';
    public const string ptSpeakerOutput   = 'SpeakerOutputFront';
    public const string ptMultiEQMode   = 'MultiEQMode';
    public const string ptDynamicEQ   = 'DynamicEQ';
    public const string ptAudysseyLFC = 'AudysseyLFC';
    public const string ptAudysseyContainmentAmount = 'AudysseyContainmantAmount';
    public const string ptReferenceLevel            = 'ReferenceLevel';
    public const string ptDiracLiveFilter    = 'DiracLiveFilter';
    public const string ptDynamicVolume   = 'DynamicVolume';
    public const string ptAudysseyDSX   = 'AudysseyDSX';
    public const string ptStageWidth  = 'StageWidth';
    public const string ptStageHeight = 'StageHeight';
    public const string ptBassLevel   = 'BassLevel';
    public const string ptTrebleLevel = 'TrebleLevel';
    public const string ptLoudnessManagement = 'LoudnessManagement';
    public const string ptDynamicRangeCompression = 'DynamicRangeCompression';
    public const string ptMDAX                    = 'MDAX';
    public const string ptDynamicCompressor = 'DynamicCompressor';
    public const string ptCenterLevelAdjust = 'CenterLevelAdjust';
    public const string ptLFELevel          = 'LFELevel';
    public const string ptLFE71Level = 'LFE71Level';
    public const string ptEffectLevel = 'EffectLevel';
    public const string ptDelay       = 'Delay';
    public const string ptAFDM  = 'AFDM';
    public const string ptPanorama = 'Panorama';
    public const string ptDimension = 'Dimension';
    public const string ptDialogControl = 'DialogControl';
    public const string ptCenterWidth   = 'CenterWidth';
    public const string ptCenterImage = 'CenterImage';
    public const string ptCenterGain  = 'CenterGain';
    public const string ptSubwoofer  = 'Subwoofer';
    public const string ptRoomSize  = 'RoomSize';
    public const string ptAudioDelay = 'AudioDelay';
    public const string ptAudioRestorer = 'AudioRestorer';
    public const string ptFrontSpeaker  = 'FrontSpeaker';
    public const string ptContrast     = 'Contrast';
    public const string ptBrightness = 'Brightness';
    public const string ptSaturation = 'Saturation';
    public const string ptChromalevel = 'Chromalevel';
    public const string ptHue         = 'Hue';
    public const string ptDigitalNoiseReduction = 'DNRDirectChange';
    public const string ptPictureMode           = 'PictureMode';
    public const string ptEnhancer       = 'Enhancer';
    public const string ptBluetoothTransmitter = 'BluetoothTransmitter';
    public const string ptSpeakerPreset        = 'SpeakerPreset';

    public const string ptZone2Power       = 'Zone2Power';
    public const string ptZone2InputSource = 'Zone2InputSource';
    public const string ptZone2Volume      = 'Zone2Volume';
    public const string ptZone2Mute   = 'Zone2Mute';
    public const string ptZone2ChannelSetting = 'Zone2ChannelSetting';
    public const string ptZone2ChannelVolumeFL = 'Zone2ChannelVolumeFL';
    public const string ptZone2ChannelVolumeFR = 'Zone2ChannelVolumeFR';
    public const string ptZone2HPF             = 'Zone2HPF';
    public const string ptZone2Bass     = 'Zone2Bass';
    public const string ptZone2Treble = 'Zone2Treble';
    public const string ptZone2QuickSelect = 'Zone2QuickSelect';
    public const string ptZone2SmartSelect = 'Zone2SmartSelect';
    public const string ptZone2Sleep       = 'Zone2Sleep';

    public const string ptZone3InputSource = 'Zone3InputSource';
    public const string ptZone3Volume      = 'Zone3Volume';
    public const string ptZone3Mute   = 'Zone3Mute';
    public const string ptZone3ChannelSetting = 'Zone3ChannelSetting';
    public const string ptZone3ChannelVolumeFL = 'Zone3ChannelVolumeFL';
    public const string ptZone3ChannelVolumeFR = 'Zone3ChannelVolumeFR';
    public const string ptZone3HPF             = 'Zone3HPF';
    public const string ptZone3Bass     = 'Zone3Bass';
    public const string ptZone3Treble = 'Zone3Treble';
    public const string ptZone3QuickSelect = 'Zone3QuickSelect';
    public const string ptZone3SmartSelect = 'Zone3SmartSelect';
    public const string ptZone3Sleep       = 'Zone3Sleep';

    public const string ptCinemaEQ = 'CinemaEQ';
    public const string ptHTEQ     = 'HTEQ';
    public const string ptDynamicRange = 'DynamicRange';
    public const string ptPreset       = 'Preset';
    public const string ptZone2Name = 'Zone2Name';
    public const string ptZone3Power = 'Zone3Power';
    public const string ptZone3Name  = 'Zone3Name';
    public const string ptNavigation = 'Navigation';
    public const string ptNavigationNetwork = 'NavigationNetwork';
    public const string ptSubwooferATT      = 'SubwooferATT';
    //public const ptDCOMPDirectChange = 'DCOMPDirectChange';
    public const string ptDolbyVolumeLeveler = 'DolbyVolumeLeveler';
    public const string ptDolbyVolumeModeler = 'DolbyVolumeModeler';
    public const string ptVerticalStretch    = 'VerticalStretch';
    public const string ptDolbyVolume     = 'DolbyVolume';
    public const string ptFriendlyName = 'FriendlyName';
    public const string ptMainZoneName = 'MainZoneName';
    public const string ptTopMenuLink  = 'TopMenuLink';
    public const string ptModel       = 'Model';
    public const string ptGUIMenuSourceSelect = 'GUIMenuSourceSelect';
    public const string ptGUIMenuSetup        = 'GUIMenuSetup';
    public const string ptSurroundDisplay = 'SurroundDisplay';
    public const string ptDisplay         = 'Display';
    public const string ptGraphicEQ = 'GraphicEQ';
    public const string ptHeadphoneEQ = 'HeadphoneEQ';
    public const string ptDimmer      = 'Dimmer';
    public const string ptDialogLevelAdjust = 'DialogLevelAdjust';
    public const string ptMAINZONEAutoStandbySetting = 'MAINZONEAutoStandbySetting';
    public const string ptMAINZONEECOModeSetting     = 'MAINZONEECOModeSetting';
    public const string ptCenterSpread           = 'Centerspread';
    public const string ptSpeakerVirtualizer = 'SpeakerVirtualizer';
    public const string ptNeural             = 'Neural';
    public const string ptAllZoneStereo = 'AllZoneStereo';
    public const string ptAutoLipSync   = 'AutoLipSync';
    public const string ptBassSync    = 'BassSync';
    public const string ptSubwooferLevel = 'SubwooferLevel';
    public const string ptSubwoofer2Level = 'Subwoofer2Level';
    public const string ptSubwoofer3Level = 'Subwoofer3Level';
    public const string ptSubwoofer4Level = 'Subwoofer4Level';
    public const string ptDialogEnhancer  = 'DialogEnhancer';
    public const string ptAuroMatic3DPreset = 'AuroMatic3DPreset';
    public const string ptAuroMatic3DStrength = 'AuroMatic3DStrength';
    public const string ptAuro3DMode          = 'Auro3DMode';
    public const string ptTopFrontLch  = 'TopFrontLch';
    public const string ptTopFrontRch = 'TopFrontRch';
    public const string ptTopMiddleLch = 'TopMiddleLch';
    public const string ptTopMiddleRch = 'TopMiddleRch';
    public const string ptTopRearLch   = 'TopRearLch';
    public const string ptTopRearRch = 'TopRearRch';
    public const string ptRearHeightLch = 'RearHeightLch';
    public const string ptRearHeightRch = 'RearHeightRch';
    public const string ptFrontDolbyLch = 'FrontDolbyLch';
    public const string ptFrontDolbyRch = 'FrontDolbyRch';
    public const string ptSurroundDolbyLch = 'SurroundDolbyLch';
    public const string ptSurroundDolbyRch = 'SurroundDolbyRch';
    public const string ptBackDolbyLch     = 'BackDolbyLch';
    public const string ptBackDolbyRch = 'BackDolbyRch';
    public const string ptSurroundHeightLch = 'SurroundHeightLch';
    public const string ptSurroundHeightRch = 'SurroundHeightRch';
    public const string ptTopSurround       = 'TopSurround';
    public const string ptCenterHeight = 'CenterHeight';
    public const string ptChannelVolumeReset = 'ChannelVolumeReset';
    public const string ptTactileTransducer  = 'TactileTransducer';
    public const string ptZone2HDMIAudio    = 'Zone2HDMIAudio';
    public const string ptZone2AutoStandbySetting = 'Zone2AutoStandbySetting';
    public const string ptZone3AutoStandbySetting = 'Zone3AutoStandbySetting';

    public const string ptTunerAnalogPreset = 'TunerAnalogPresets';
    public const string ptTunerAnalogBand   = 'TunerAnalogBand';
    public const string ptTunerAnalogMode = 'TunerAnalogMode';

    public const string ptSYSMI = 'SysMI';
    public const string ptSYSDA = 'SysDA';
    public const string ptSSINFAISFSV = 'SsInfAISFSV';

    public static array $order = [
        //Info Display
        self::ptMainZoneName,
        self::ptModel,

        //AVR Infos
        self::ptSYSMI,
        self::ptSYSDA,
        self::ptSSINFAISFSV,

        //Power Settings
        self::ptPower,
        self::ptMainZonePower,
        self::ptMainMute,
        self::ptSleep,
        self::ptMAINZONEAutoStandbySetting,
        self::ptMAINZONEECOModeSetting,

        //Input Settings
        self::ptInputSource,
        self::ptQuickSelect,
        self::ptSmartSelect,
        self::ptDigitalInputMode,
        self::ptInputMode,
        self::ptVideoSelect,

        //Surround Mode
        self::ptSurroundMode,
        self::ptSurroundDisplay,
        self::ptDolbyVolume,
        self::ptDolbyVolumeLeveler,
        self::ptDolbyVolumeModeler,

        //OnScreenDisplay
        self::ptDisplay,
        self::ptNavigationNetwork,

        //Channel Volumes
        self::ptMasterVolume,
        self::ptBalance,
        self::ptChannelVolumeFL,
        self::ptChannelVolumeFR,
        self::ptChannelVolumeC,
        self::ptChannelVolumeSW,
        self::ptChannelVolumeSW2,
        self::ptChannelVolumeSW3,
        self::ptChannelVolumeSW4,
        self::ptChannelVolumeSL,
        self::ptChannelVolumeSR,
        self::ptChannelVolumeSBL,
        self::ptChannelVolumeSBR,
        self::ptChannelVolumeSB,
        self::ptChannelVolumeFHL,
        self::ptChannelVolumeFHR,
        self::ptChannelVolumeFWL,
        self::ptChannelVolumeFWR,
        self::ptTopFrontLch,
        self::ptTopFrontRch,
        self::ptTopMiddleLch,
        self::ptTopMiddleRch,
        self::ptTopRearLch,
        self::ptTopRearRch,
        self::ptRearHeightLch,
        self::ptRearHeightRch,
        self::ptFrontDolbyLch,
        self::ptFrontDolbyRch,
        self::ptSurroundDolbyLch,
        self::ptSurroundDolbyRch,
        self::ptBackDolbyLch,
        self::ptBackDolbyRch,
        self::ptSurroundHeightLch,
        self::ptSurroundHeightRch,
        self::ptTopSurround,
        self::ptCenterHeight,
        self::ptChannelVolumeReset,
        self::ptTactileTransducer,

        //Sound Processing (Audio Setting)
        self::ptFrontSpeaker,
        self::ptSpeakerOutput,
        self::ptSpeakerOutput,
        self::ptFrontHeight,
        self::ptSubwoofer,
        self::ptToneCTRL,
        self::ptBassLevel,
        self::ptTrebleLevel,
        self::ptLoudnessManagement,
        self::ptBassSync,
        self::ptDialogEnhancer,
        self::ptSubwooferLevel,
        self::ptSubwoofer2Level,
        self::ptSubwoofer3Level,
        self::ptSubwoofer4Level,
        self::ptDialogLevelAdjust,
        self::ptDialogLevelAdjust,
        self::ptCenterLevelAdjust,
        self::ptLFELevel,
        self::ptLFE71Level,
        self::ptPanorama,
        self::ptDimension,
        self::ptCenterWidth,
        self::ptCenterSpread,
        self::ptCenterImage,
        self::ptCenterGain,
        self::ptDialogControl,
        self::ptNeural,
        self::ptSpeakerVirtualizer,
        self::ptSurroundPlayMode,
        self::ptPLIIZHeightGain,
        self::ptAudysseyDSX,
        self::ptStageWidth,
        self::ptStageHeight,
        self::ptCinemaEQ,
        self::ptHTEQ,
        self::ptMultiEQMode,
        self::ptDynamicEQ,
        self::ptReferenceLevel,
        self::ptDiracLiveFilter,
        self::ptDynamicVolume,
        self::ptAudysseyLFC,
        self::ptAudysseyContainmentAmount,
        self::ptGraphicEQ,
        self::ptHeadphoneEQ,
        self::ptDynamicRangeCompression,
        self::ptDynamicCompressor,
        self::ptMDAX,
        self::ptAudioDelay,
        self::ptAuroMatic3DPreset,
        self::ptAuroMatic3DStrength,
        self::ptAuro3DMode,
        self::ptEffectLevel, // only Denon
        self::ptAFDM, // only Denon
        self::ptRoomSize, // only Denon
        self::ptSurroundBackMode, //only Denon
        self::ptDelay, //only Denon
        self::ptSubwooferATT, //only Denon
        self::ptAudioRestorer, // only Denon

        self::ptBluetoothTransmitter,
        self::ptSpeakerPreset,

        //Video
        self::ptPictureMode,
        self::ptContrast,
        self::ptBrightness,
        self::ptSaturation,
        self::ptChromalevel,
        self::ptHue,
        self::ptDigitalNoiseReduction,
        self::ptEnhancer,
        self::ptHDMIMonitor,
        self::ptResolution,
        self::ptResolutionHDMI,
        self::ptVideoProcessingMode,
        self::ptHDMIAudioOutput,
        self::ptASP,
        self::ptVerticalStretch,

        //GUI
        self::ptGUIMenuSetup,
        self::ptGUIMenuSourceSelect,
        self::ptNavigation,
        self::ptAllZoneStereo,
        self::ptDimmer,
        self::ptAutoLipSync,

        //Zone 2
        self::ptZone2Name,
        self::ptZone2Power,
        self::ptZone2Mute,
        self::ptZone2Volume,
        self::ptZone2InputSource,
        self::ptZone2ChannelSetting,
        self::ptZone2ChannelVolumeFL,
        self::ptZone2ChannelVolumeFR,
        self::ptZone2Bass,
        self::ptZone2Treble,
        self::ptZone2QuickSelect,
        self::ptZone2HPF,
        self::ptZone2HDMIAudio,
        self::ptZone2Sleep,
        self::ptZone2AutoStandbySetting,
        //Zone 3
        self::ptZone3Name,
        self::ptZone3Power,
        self::ptZone3Mute,
        self::ptZone3Volume,
        self::ptZone3InputSource,
        self::ptZone3ChannelSetting,
        self::ptZone3ChannelVolumeFL,
        self::ptZone3ChannelVolumeFR,
        self::ptZone3Bass,
        self::ptZone3Treble,
        self::ptZone3QuickSelect,
        self::ptZone3HPF,
        self::ptZone3Sleep,
        self::ptZone3AutoStandbySetting,

        //Tuner
        self::ptTunerAnalogPreset,
        self::ptTunerAnalogBand,
        self::ptTunerAnalogMode,
    ];

    public function __construct(?string $AVRType = null, ?array $InputMapping = null, ?callable $Logger_Dbg = null)
    {
        if (isset($Logger_Dbg)){
            $this->debug = true;
            $this->Logger_Dbg = $Logger_Dbg;
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'AVRType: ' . ($AVRType ?? 'null') . ', InputMapping: ' . ($InputMapping === null ? 'null' : json_encode(
                                                $InputMapping,
                                                JSON_THROW_ON_ERROR
                                            )));
        }

        $assRange00to98_add05step = $this->GetAssociationOfAsciiTodB('00', '98', '80', 0.5, true, false);
        $assRange00to98 = $this->GetAssociationOfAsciiTodB('00', '98', '80', 1, false, false);
        $assRange38to62 = $this->GetAssociationOfAsciiTodB('38', '62', '50');
        $assRange38to62_add05step = $this->GetAssociationOfAsciiTodB('38', '62', '50', 0.5, true);
        $assRange00to10_stepwide_01 = $this->GetAssociationOfAsciiTodB('00', '10', '00', 0.1, false, true, false, 0.1);
        $assRange000to200 = $this->GetAssociationOfAsciiTodB('000', '200', '000');
        $assRange000to300 = $this->GetAssociationOfAsciiTodB('000', '300', '000');
        $assRange00to10_invert = $this->GetAssociationOfAsciiTodB('00', '10', '00', 1, false, true, true);
        $assRange00to15_invert = $this->GetAssociationOfAsciiTodB('00', '15', '00', 1, false, true, true);
        $assRange44to56 = $this->GetAssociationOfAsciiTodB('44', '56', '50');
        $assRange40to60 = $this->GetAssociationOfAsciiTodB('40', '60', '50');
        $assRange00to06 = $this->GetAssociationOfAsciiTodB('00', '06', '00');
        $assRange00to07 = $this->GetAssociationOfAsciiTodB('00', '07', '00');
        $assRange00to12 = $this->GetAssociationOfAsciiTodB('00', '12', '00');
        $assRange00to15 = $this->GetAssociationOfAsciiTodB('00', '15', '00');
        $assRange00to16 = $this->GetAssociationOfAsciiTodB('00', '16', '00');
        $assRange000to120_ptSleep = $this->GetAssociationOfAsciiTodB('000', '120', '000', 10, false, false);
        $assRange000to120_ptSleep[0] = ['OFF', 0];
        $assRangeA1toG8 = $this->GetAssociationFromA1toG8();
        $assRange00to56 = $this->GetAssociationFrom00to56();

        //ID -> VariablenIdent, VariablenName
        // hier werden alle Variablen und ihre Profile vordefiniert
        // eine Definition hat den Aufbau
        // Key: ID =>
        // - Type: Variablentyp (boolean, integer, float oder string)
        // - Ident: Variablenident
        // - Name: Variablenname
        // - PropertyName (im Formular)
        // - Profilesettings: Icon, Praefix, Suffix, Minimum, Maximum, Schrittweite, Nachkommastellen
        // - Associations: die Assoziationen sind vom Variablentyp abhängig
        //          boolean:    <true/false, Subcommando>
        //          integer:    <Value, Label, Subcommand>
        //          float:
        //          string:     -
        //- IndividualStatusRequest: wenn abweichend von '<ident> ?', also z.B. ohne Blank
        // Boolean Variablen

        $this->profiles = [
            self::ptPower => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PW, 'Name' => 'Power',
                'PropertyName'                           => self::ptPower,
                'Associations'                           => [
                    [false, DENON_API_Commands::PWSTANDBY],
                    [true, DENON_API_Commands::PWON],
                ],
                'IndividualStatusRequest' => 'PW?',
            ],
            self::ptMainZonePower => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::ZM, 'Name' => 'MainZone Power',
                'PropertyName'                           => self::ptMainZonePower,
                'Associations'                           => [
                    [false, DENON_API_Commands::ZMOFF],
                    [true, DENON_API_Commands::ZMON], ],
                'IndividualStatusRequest' => 'ZM?',
            ],
            self::ptCinemaEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSCINEMAEQ, 'Name' => 'Cinema EQ',
                'PropertyName'                              => 'CinemaEQ',
                'Associations'                              => [
                    [false, DENON_API_Commands::CINEMAEQOFF],
                    [true, DENON_API_Commands::CINEMAEQON],
                ],
                'IndividualStatusRequest' => 'PSCINEMA EQ. ?',
            ],
            self::ptHTEQ => ['Type'                         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSHTEQ, 'Name' => 'HT-EQ',
                'PropertyName'                              => 'HTEQ',
                'Associations'                              => [
                    [false, DENON_API_Commands::HTEQOFF],
                    [true, DENON_API_Commands::HTEQON],
                ], ],
            self::ptDynamicEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSDYNEQ, 'Name' => 'Dynamic EQ',
                'PropertyName'                               => 'DynamicEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::DYNEQOFF],
                    [true, DENON_API_Commands::DYNEQON],
                ], ],
            self::ptAudysseyLFC => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSLFC, 'Name' => 'Audyssey LFC',
                'PropertyName'                                 => 'AudysseyLFC',
                'Associations'                                 => [
                    [false, DENON_API_Commands::LFCOFF],
                    [true, DENON_API_Commands::LFCON],
                ], ],
            self::ptFrontHeight => ['Type'               => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSFH, 'Name' => 'Front Height',
                'PropertyName'                           => 'FrontHeight',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSFHOFF],
                    [true, DENON_API_Commands::PSFHON],
                ],
                'IndividualStatusRequest' => 'PSFH: ?',
            ],
            self::ptMainMute => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MU, 'Name' => 'Main Mute',
                'PropertyName'                           => self::ptMainMute,
                'Associations'                           => [
                    [false, DENON_API_Commands::MUOFF],
                    [true, DENON_API_Commands::MUON],
                ],
                'IndividualStatusRequest' => 'MU?',
            ],
            self::ptPanorama => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSPAN, 'Name' => 'Panorama',
                'PropertyName'                           => 'Panorama',
                'Associations'                           => [
                    [false, DENON_API_Commands::PANOFF],
                    [true, DENON_API_Commands::PANON],
                ], ],
            self::ptToneCTRL => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSTONECTRL, 'Name' => 'Tone CTRL',
                'PropertyName'                           => 'ToneCTRL',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSTONECTRLOFF],
                    [true, DENON_API_Commands::PSTONECTRLON],
                ],
                'IndividualStatusRequest' => 'PSTONE CTRL: ?',
            ],
            self::ptVerticalStretch => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::VSVST, 'Name' => 'Vertical Stretch',
                'PropertyName'                             => 'VerticalStretch',
                'Associations'                             => [
                    [false, DENON_API_Commands::VSTOFF],
                    [true, DENON_API_Commands::VSTON],
                ], ],
            self::ptDolbyVolume => ['Type'               => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSDOLVOL, 'Name' => 'Dolby Volume',
                'PropertyName'                           => 'DolbyVolume',
                'Associations'                           => [
                    [false, DENON_API_Commands::DOLVOLOFF],
                    [true, DENON_API_Commands::DOLVOLON],
                ], ],
            self::ptAFDM => ['Type'                      => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSAFD, 'Name' => 'Auto Flag Detect Mode',
                'PropertyName'                           => 'AFDM',
                'Associations'                           => [
                    [false, DENON_API_Commands::AFDOFF],
                    [true, DENON_API_Commands::AFDON],
                ], ],
            self::ptSubwoofer => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSSWR, 'Name' => 'Subwoofer',
                'PropertyName'                           => 'Subwoofer',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSSWROFF],
                    [true, DENON_API_Commands::PSSWRON],
                ], ],
            self::ptSubwooferATT => ['Type'              => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSATT, 'Name' => 'Subwoofer ATT',
                'PropertyName'                           => 'SubwooferATT',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSSWROFF],
                    [true, DENON_API_Commands::PSSWRON],
                ], ],
            self::ptLoudnessManagement  => ['Type'            => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSLOM, 'Name' => 'Loudness Management',
                'PropertyName'                                => 'LoudnessManagement',
                'Associations'                                => [
                    [false, DENON_API_Commands::PSLOMOFF],
                    [true, DENON_API_Commands::PSLOMON],
                ], ],
            self::ptGUIMenuSetup        => ['Type'         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNMEN, 'Name' => 'GUI Setup Menu',
                'PropertyName'                             => 'GUIMenu',
                'Associations'                             => [
                    [false, DENON_API_Commands::MNMENOFF],
                    [true, DENON_API_Commands::MNMENON],
                ], ],
            self::ptGUIMenuSourceSelect => ['Type'         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNSRC, 'Name' => 'GUI Source Select Menu',
                'PropertyName'                             => 'GUIMenuSource',
                'Associations'                             => [
                    [false, DENON_API_Commands::MNSRCOFF],
                    [true, DENON_API_Commands::MNSRCON],
                ], ],
            self::ptGraphicEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSGEQ, 'Name' => 'Graphic EQ',
                'PropertyName'                               => 'GraphicEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::PSGEQOFF],
                    [true, DENON_API_Commands::PSGEQON],
                ], ],
            self::ptHeadphoneEQ => ['Type'                   => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSHEQ, 'Name' => 'Headphone EQ',
                'PropertyName'                               => 'HeadphoneEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::PSHEQOFF],
                    [true, DENON_API_Commands::PSHEQON],
                ], ],
            self::ptCenterSpread    => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSCES, 'Name' => 'Center Spread',
                'PropertyName'                                     => 'CenterSpread',
                'Associations'                                     => [
                    [false, DENON_API_Commands::PSCESOFF],
                    [true, DENON_API_Commands::PSCESON],
                ], ],
            self::ptSpeakerVirtualizer    => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSSPV, 'Name' => 'Speaker Virtualizer',
                'PropertyName'                                           => 'SpeakerVirtualizer',
                'Associations'                                           => [
                    [false, DENON_API_Commands::PSSPVOFF],
                    [true, DENON_API_Commands::PSSPVON],
                ], ],
            self::ptNeural   => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSNEURAL, 'Name' => 'Neural:X',
                'PropertyName'                              => 'Neural',
                'Associations'                              => [
                    [false, DENON_API_Commands::PSNEURALOFF],
                    [true, DENON_API_Commands::PSNEURALON],
                ], ],
            self::ptAllZoneStereo   => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNZST, 'Name' => 'All Zone Stereo',
                'PropertyName'                                     => 'AllZoneStereo',
                'Associations'                                     => [
                    [false, DENON_API_Commands::MNZSTOFF],
                    [true, DENON_API_Commands::MNZSTON],
                ], ],
            self::ptAutoLipSync   => ['Type'                       => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::SSHOSALS, 'Name' => 'Auto Lip Sync',
                'PropertyName'                                     => 'AutoLipSync',
                'Associations'                                     => [
                    [false, DENON_API_Commands::SSHOSALSOFF],
                    [true, DENON_API_Commands::SSHOSALSON],
                ], ],
            self::ptZone2Power      => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2POWER, 'Name' => 'Zone 2 Power',
                'PropertyName'                             => self::ptZone2Power,
                'Associations'                             => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ],
                'IndividualStatusRequest' => 'Z2?',
            ],
            self::ptZone2Mute => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2MU, 'Name' => 'Zone 2 Mute',
                'PropertyName'                           => self::ptZone2Mute,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ], ],
            self::ptZone2HPF => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2HPF, 'Name' => 'Zone 2 HPF',
                'PropertyName'                              => 'Z2HPF',
                'Associations'                              => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ], ],
            self::ptZone3Power => ['Type'                => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3POWER, 'Name' => 'Zone 3 Power',
                'PropertyName'                           => self::ptZone3Power,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ],
                'IndividualStatusRequest' => 'Z3?',
            ],
            self::ptZone3Mute => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3MU, 'Name' => 'Zone 3 Mute',
                'PropertyName'                           => self::ptZone3Mute,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ], ],

            self::ptZone3HPF => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3HPF, 'Name' => 'Zone 3 HPF',
                'PropertyName'                           => 'Z3HPF',
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ], ],

            //Ident, Variablenname, Profilesettings
            //Associations: Value, Label, Association
            self::ptBalance => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::BL, 'Name' => 'Balance',
                                   'PropertyName'                        => 'Balance',
                                   'Profilesettings'                     => ['', '', '', 0, 0, 0, 0],
                                   'Associations'                        => [
                                       [-12, 'L 12', 'L12'],
                                       [-11, 'L 11', 'L11'],
                                       [-10, 'L 10', 'L10'],
                                       [-9, 'L 9', 'L9'],
                                       [-8, 'L 8', 'L8'],
                                       [-7, 'L 7', 'L7'],
                                       [-6, 'L 6', 'L6'],
                                       [-5, 'L 5', 'L5'],
                                       [-4, 'L 4', 'L4'],
                                       [-3, 'L 3', 'L3'],
                                       [-2, 'L 2', 'L2'],
                                       [-1, 'L 1', 'L1'],
                                       [0, '0', '0'],
                                       [1, 'R 1', 'R1'],
                                       [2, 'R 2', 'R2'],
                                       [3, 'R 3', 'R3'],
                                       [4, 'R 4', 'R4'],
                                       [5, 'R 5', 'R5'],
                                       [6, 'R 6', 'R6'],
                                       [7, 'R 7', 'R7'],
                                       [8, 'R 8', 'R8'],
                                       [9, 'R 9', 'R9'],
                                       [10, 'R 10', 'R10'],
                                       [11, 'R 11', 'R11'],
                                       [12, 'R 12', 'R12'],
                                   ],
            ],

            self::ptInputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SI, 'Name' => 'Input Source',
                'PropertyName'                         => 'InputSource',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'              => 'SI?',
            ],
            self::ptZone2InputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2INPUT, 'Name' => 'Zone 2 Input Source',
                'PropertyName'                              => self::ptZone2InputSource,
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'                   => 'Z2?',
            ],
            self::ptZone3InputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3INPUT, 'Name' => 'Zone 3 Input Source',
                'PropertyName'                              => self::ptZone3InputSource,
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'                   => 'Z3?',
            ],
            self::ptChannelVolumeReset => ['Type'                      => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::CVZRL, 'Name' => 'Channel Volume Reset',
                'PropertyName'                                         => 'ChannelVolumeReset',
                'Profilesettings'                                      => ['Script', '', '', 0, 0, 0, 0],
                'Associations'                                         => [
                    [1, 'Reset', ''],
                ],
                'IndividualStatusRequest' => 'CV?',
            ],
            self::ptNavigation => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MN, 'Name' => 'Navigation Setup Menu',
                'PropertyName'                        => 'Navigation',
                'Profilesettings'                     => ['Move', '', '', 0, 0, 0, 0],
                'Associations'                        => [
                    [0, 'Left', DENON_API_Commands::MNCLT],
                    [1, 'Down', DENON_API_Commands::MNCDN],
                    [2, 'Up', DENON_API_Commands::MNCUP],
                    [3, 'Right', DENON_API_Commands::MNCRT],
                    [4, 'Enter', DENON_API_Commands::MNENT],
                    [5, 'Return', DENON_API_Commands::MNRTN],
                ],
            ],
            self::ptNavigationNetwork => ['Type'      => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::NS, 'Name' => 'Navigation Network',
                'PropertyName'                        => 'NavigationNetwork',
                'Profilesettings'                     => ['Move', '', '', 0, 0, 0, 0],
                'Associations'                        => [
                    [0, 'Up', DENON_API_Commands::NSUP],
                    [1, 'Down', DENON_API_Commands::NSDOWN],
                    [2, 'Left', DENON_API_Commands::NSLEFT],
                    [3, 'Enter (Play/Pause)', DENON_API_Commands::NSENTER],
                    [4, 'Stop', DENON_API_Commands::NSSTOP],
                    [5, 'Skip <', DENON_API_Commands::NSSKIPMINUS],
                    [6, 'Skip >', DENON_API_Commands::NSSKIPPLUS],
                    [12, 'Page Previous', DENON_API_Commands::NSPAGEPREV],
                    [13, 'Page Next', DENON_API_Commands::NSPAGENEXT],
                ],
            ],
            self::ptQuickSelect => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MSQUICK, 'Name' => 'Quick Select',
                'PropertyName'                                    => 'QuickSelect',
                'Profilesettings'                                 => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                ],
            ],
            self::ptSmartSelect => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MSSMART, 'Name' => 'Smart Select',
                'PropertyName'                                    => 'SmartSelect',
                'Profilesettings'                                 => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, '-', DENON_API_Commands::MSSMART0],
                    [1, 'Select 1', DENON_API_Commands::MSSMART1],
                    [2, 'Select 2', DENON_API_Commands::MSSMART2],
                    [3, 'Select 3', DENON_API_Commands::MSSMART3],
                    [4, 'Select 4', DENON_API_Commands::MSSMART4],
                    [5, 'Select 5', DENON_API_Commands::MSSMART5],
                ],
            ],
            self::ptDigitalInputMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::DC, 'Name' => 'Audio Decode Mode',
                'PropertyName'                              => 'DigitalInputMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Auto', DENON_API_Commands::DCAUTO],
                    [1, 'PCM', DENON_API_Commands::DCPCM],
                    [2, 'DTS', DENON_API_Commands::DCDTS],
                ],
            ],
            self::ptAudysseyDSX => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDSX, 'Name' => 'Audyssey DSX',
                'PropertyName'                         => 'AudysseyDSX',
                'Profilesettings'                      => ['Speaker', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'Off', DENON_API_Commands::PSDSXOFF],
                    [1, 'Audyssey DSX On(Wide)', DENON_API_Commands::PSDSXONW],
                    [2, 'Audyssey DSX On(Height)', DENON_API_Commands::PSDSXONH],
                    [3, 'Audyssey DSX On(Wide/Height)', DENON_API_Commands::PSDSXONHW],
                ],
            ],

            self::ptSurroundMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MS, 'Name' => 'Surround Mode',
                'PropertyName'                          => self::ptSurroundMode,
                'Profilesettings'                       => ['Melody', '', '', 0, 0, 0, 0],
                'Associations'                          => [
                    [0, 'Movie', DENON_API_Commands::MSMOVIE],
                    [1, 'Music', DENON_API_Commands::MSMUSIC],
                    [2, 'Game', DENON_API_Commands::MSGAME],
                    [3, 'Direct', DENON_API_Commands::MSDIRECT],
                    [4, 'Pure Direct', DENON_API_Commands::MSPUREDIRECT],
                    [5, 'Stereo', DENON_API_Commands::MSSTEREO],
                    [6, 'Standard', DENON_API_Commands::MSSTANDARD],
                    [7, 'Dolby Surround', DENON_API_Commands::MSDOLBYDIGITAL],
                    [8, 'DTS Surround', DENON_API_Commands::MSDTSSURROUND],
                    [9, 'Auro 3D', DENON_API_Commands::MSAURO3D],
                    [10, 'Auro 2D', DENON_API_Commands::MSAURO2DSURR],
                    [11, '7 Channel Stereo', DENON_API_Commands::MS7CHSTEREO],
                    [12, 'Multi Ch Stereo', DENON_API_Commands::MSMCHSTEREO],
                    [13, 'Wide Screen', DENON_API_Commands::MSWIDESCREEN],
                    [14, 'Super Stadium', DENON_API_Commands::MSSUPERSTADIUM],
                    [15, 'Rock Arena', DENON_API_Commands::MSROCKARENA],
                    [16, 'Jazz Club', DENON_API_Commands::MSJAZZCLUB],
                    [17, 'Classic Concert', DENON_API_Commands::MSCLASSICCONCERT],
                    [18, 'Mono Movie', DENON_API_Commands::MSMONOMOVIE],
                    [19, 'Matrix', DENON_API_Commands::MSMATRIX],
                    [20, 'Video Game', DENON_API_Commands::MSVIDEOGAME],
                    [21, 'Virtual', DENON_API_Commands::MSVIRTUAL],
                ],
                'IndividualStatusRequest' => 'MS?',
            ],
            self::ptSurroundPlayMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMODE, 'Name' => 'Surround Play Mode',
                'PropertyName'                              => 'SurroundPlayMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Cinema', DENON_API_Commands::MODECINEMA],
                    [1, 'Music', DENON_API_Commands::MODEMUSIC],
                    [2, 'Game', DENON_API_Commands::MODEGAME],
                    [3, 'Pro Logic', DENON_API_Commands::MODEPROLOGIC],
                ],
                'IndividualStatusRequest' => 'PSMODE: ?',
            ],
            self::ptMultiEQMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMULTEQ, 'Name' => 'Multi EQ Mode',
                'PropertyName'                         => 'MultiEQMode',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'Off', DENON_API_Commands::MULTEQOFF],
                    [1, 'Reference', DENON_API_Commands::MULTEQAUDYSSEY],
                    [2, 'L/R Bypass', DENON_API_Commands::MULTEQBYPLR],
                    [3, 'Flat', DENON_API_Commands::MULTEQFLAT],
                    [4, 'Manual', DENON_API_Commands::MULTEQMANUAL],
                ],
                'IndividualStatusRequest' => 'PSMULTEQ: ?',
            ],
            self::ptAudioRestorer => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSRSTR, 'Name' => 'Audio Restorer',
                'PropertyName'                           => 'AudioRestorer',
                'Profilesettings'                        => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                           => [
                    [0, 'Off', DENON_API_Commands::PSRSTROFF],
                    [1, 'Hoch', DENON_API_Commands::PSRSTRMODE1],
                    [2, 'Mittel', DENON_API_Commands::PSRSTRMODE2],
                    [3, 'Gering', DENON_API_Commands::PSRSTRMODE3],
                ],
            ],
            self::ptFrontSpeaker => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSFRONT, 'Name' => 'Speaker A/B',
                'PropertyName'                          => 'FrontSpeaker',
                'Profilesettings'                       => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                          => [
                    [0, 'Speaker A', DENON_API_Commands::PSFRONTSPA],
                    [1, 'Speaker B', DENON_API_Commands::PSFRONTSPB],
                    [2, 'Speaker A+B', DENON_API_Commands::PSFRONTSPAB],
                ],
                'IndividualStatusRequest' => 'PSFRONT?',
            ],
            self::ptRoomSize => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSRSZ, 'Name' => 'Room Size',
                'PropertyName'                      => 'RoomSize',
                'Profilesettings'                   => ['Sofa', '', '', 0, 0, 0, 0],
                'Associations'                      => [
                    [0, 'Normal', DENON_API_Commands::RSZN],
                    [1, 'Small', DENON_API_Commands::RSZS],
                    [2, 'Small/Medium', DENON_API_Commands::RSZMS],
                    [3, 'Medium', DENON_API_Commands::RSZM],
                    [4, 'Medium/Large', DENON_API_Commands::RSZML],
                    [5, 'Large', DENON_API_Commands::RSZL],
                ],
            ],
            self::ptDynamicCompressor       => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDCO, 'Name' => 'Dynamic Compressor',
                'PropertyName'                                     => 'DynamicCompressor',
                'Profilesettings'                                  => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                     => [
                    [0, 'Off', DENON_API_Commands::DCOOFF],
                    [1, 'Low', DENON_API_Commands::DCOLOW],
                    [2, 'Middle', DENON_API_Commands::DCOMID],
                    [3, 'High', DENON_API_Commands::DCOHIGH],
                ],
            ],
            self::ptDynamicRangeCompression => ['Type'                          => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDRC, 'Name' => 'Dynamic Range Compression',
                'PropertyName'                                                  => 'DynamicRange',
                'Profilesettings'                                               => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                  => [
                    [0, 'Off', DENON_API_Commands::DRCOFF],
                    [1, 'Auto', DENON_API_Commands::DRCAUTO],
                    [2, 'Low', DENON_API_Commands::DRCLOW],
                    [3, 'Middle', DENON_API_Commands::DRCMID],
                    [4, 'High', DENON_API_Commands::DRCHI],
                ],
            ],
            self::ptMDAX => ['Type'                          => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMDAX, 'Name' => 'M-DAX',
                'PropertyName'                               => 'MDAX',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Off', DENON_API_Commands::MDAXOFF],
                    [1, 'Low', DENON_API_Commands::MDAXLOW],
                    [2, 'Middle', DENON_API_Commands::MDAXMID],
                    [3, 'High', DENON_API_Commands::MDAXHI],
                ],
            ],
            self::ptVideoSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SV, 'Name' => 'Video Select',
                'PropertyName'                         => 'VideoSelect',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'DVD', DENON_API_Commands::IS_DVD],
                    [1, 'BD', DENON_API_Commands::IS_BD],
                    [2, 'TV', DENON_API_Commands::IS_TV],
                    [3, 'Sat/CBL', DENON_API_Commands::IS_SAT_CBL],
                    [4, 'Sat', DENON_API_Commands::IS_SAT],
                    [5, 'MediaPlayer', DENON_API_Commands::IS_MPLAY],
                    [6, 'VCR', DENON_API_Commands::IS_VCR],
                    [7, 'DVR', DENON_API_Commands::IS_DVR],
                    [8, 'Game', DENON_API_Commands::IS_GAME],
                    [9, 'Game2', DENON_API_Commands::IS_GAME2],
                    [10, 'V.AUX', DENON_API_Commands::IS_VAUX],
                    [11, 'AUX1', DENON_API_Commands::IS_AUX1],
                    [12, 'AUX2', DENON_API_Commands::IS_AUX2],
                    [13, 'CD', DENON_API_Commands::IS_CD],
                    [14, 'Source', DENON_API_Commands::IS_SOURCE],
                    [15, 'On', DENON_API_Commands::IS_ON],
                    [16, 'Off', DENON_API_Commands::IS_OFF],
                ],
            ],
            self::ptSurroundBackMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSSB, 'Name' => 'Surround Back Mode',
                'PropertyName'                              => 'SurroundBackMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Off', DENON_API_Commands::SBOFF],
                    [1, 'On', DENON_API_Commands::SBON],
                    [2, 'Matrix On', DENON_API_Commands::SBMTRXON],
                    [3, 'PL2X Cinema', DENON_API_Commands::SBPL2XCINEMA],
                    [4, 'PL2X Music', DENON_API_Commands::SBPL2XMUSIC],
                ],
                'IndividualStatusRequest' => 'PSSB: ?',
            ],
            self::ptHDMIMonitor   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSMONI, 'Name' => 'HDMI Monitor',
                'PropertyName'                           => 'HDMIMonitor',
                'Profilesettings'                        => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                           => [
                    [0, 'Auto', DENON_API_Commands::VSMONIAUTO],
                    [1, 'Monitor 1', DENON_API_Commands::VSMONI1],
                    [2, 'Monitor 2', DENON_API_Commands::VSMONI2],
                ],
            ],
            self::ptSpeakerOutput => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSSP, 'Name' => 'Effekt Speaker',
                'PropertyName'                                      => 'SpeakerOutputFront',
                'Profilesettings'                                   => ['Speaker', '', '', 0, 0, 0, 0],
                'Associations'                                      => [
                    [0, 'Off', DENON_API_Commands::SPOFF],
                    [1, 'Front Height', DENON_API_Commands::SPFH],
                    [2, 'Front Wide', DENON_API_Commands::SPFW],
                    [3, 'Surround Back', DENON_API_Commands::SPSB],
                    [4, 'Fr.Height & Fr.Wide', DENON_API_Commands::SPHW],
                    [5, 'Surr.Back & Fr.Height', DENON_API_Commands::SPBH],
                    [6, 'Surr.Back & Fr.Wide', DENON_API_Commands::SPBW],
                    [7, 'Floor', DENON_API_Commands::SPFL],
                    [8, 'Height & Floor', DENON_API_Commands::SPHF],
                    [9, 'Front', DENON_API_Commands::SPFR],
                ],
                'IndividualStatusRequest' => 'PSSP: ?',
            ],
            self::ptReferenceLevel   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSREFLEV, 'Name' => 'Reference Level',
                'PropertyName'                              => 'ReferenceLevel',
                'Profilesettings'                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Offset 0', DENON_API_Commands::REFLEV0],
                    [5, 'Offset 5', DENON_API_Commands::REFLEV5],
                    [10, 'Offset 10', DENON_API_Commands::REFLEV10],
                    [15, 'Offset 15', DENON_API_Commands::REFLEV15],
                ],
            ],
            self::ptDiracLiveFilter   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDIRAC, 'Name' => 'Dirac Live Filter',
                'PropertyName'                              => 'DiracLiveFilter',
                'Profilesettings'                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Off', DENON_API_Commands::DIRACOFF],
                    [1, 'Slot 1', DENON_API_Commands::DIRAC1],
                    [2, 'Slot 2', DENON_API_Commands::DIRAC2],
                    [3, 'Slot 3', DENON_API_Commands::DIRAC3]
                ],
            ],
            self::ptPLIIZHeightGain => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSPHG, 'Name' => 'PLIIZ Height Gain',
                'PropertyName'                             => 'PLIIZHeightGain',
                'Profilesettings'                          => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'Low', DENON_API_Commands::PHGLOW],
                    [1, 'Middle', DENON_API_Commands::PHGMID],
                    [2, 'High', DENON_API_Commands::PHGHI],
                ],
            ],
            self::ptDolbyVolumeModeler => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSVOLMOD, 'Name' => 'Dolby Volume Modeler',
                'PropertyName'                                => 'DolbyVolumeModeler',
                'Profilesettings'                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                => [
                    [0, 'Off', DENON_API_Commands::VOLMODOFF],
                    [1, 'Half', DENON_API_Commands::VOLMODHLF],
                    [2, 'Full', DENON_API_Commands::VOLMODFUL],
                ],
            ],
            self::ptDolbyVolumeLeveler => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSVOLLEV, 'Name' => 'Dolby Volume Leveler',
                'PropertyName'                                => 'DolbyVolumeLeveler',
                'Profilesettings'                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                => [
                    [0, 'Low', DENON_API_Commands::VOLLEVLOW],
                    [1, 'Middle', DENON_API_Commands::VOLLEVMID],
                    [2, 'High', DENON_API_Commands::VOLLEVHI],
                ],
            ],
            self::ptVideoProcessingMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSVPM, 'Name' => 'Video Processing Mode',
                'PropertyName'                                 => 'VideoProcessingMode',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Auto', DENON_API_Commands::VPMAUTO],
                    [1, 'Game', DENON_API_Commands::VPGAME],
                    [2, 'Movie', DENON_API_Commands::VPMOVI],
                    [3, 'Bypass', DENON_API_Commands::VPMBYP],
                ],
            ],
            self::ptHDMIAudioOutput => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSAUDIO, 'Name' => 'HDMI Audio Output',
                'PropertyName'                             => 'HDMIAudioOutput',
                'Profilesettings'                          => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'TV', DENON_API_Commands::AUDIOTV],
                    [1, 'AMP', DENON_API_Commands::AUDIOAMP],
                ],
            ],
            self::ptASP => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSASP, 'Name' => 'ASP',
                'PropertyName'                 => 'ASP',
                'Profilesettings'              => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                 => [
                    [0, 'Normal', DENON_API_Commands::ASPNRM],
                    [1, 'Full', DENON_API_Commands::ASPFUL],
                ],
            ],
            self::ptPictureMode => ['Type'                                  => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PVPICT, 'Name' => 'Picture Mode',
                'PropertyName'                                              => 'PictureMode',
                'Profilesettings'                                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                              => [
                    [0, 'Off', DENON_API_Commands::PVPICTOFF],
                    [1, 'Standard', DENON_API_Commands::PVPICTSTD],
                    [2, 'Movie', DENON_API_Commands::PVPICTMOV],
                    [3, 'Vivid', DENON_API_Commands::PVPICTVVD],
                    [4, 'Stream', DENON_API_Commands::PVPICTSTM],
                    [5, 'Custom', DENON_API_Commands::PVPICTCTM],
                    [6, 'ISF Day', DENON_API_Commands::PVPICTDAY],
                    [7, 'ISF Night', DENON_API_Commands::PVPICTNGT],
                ],
                'IndividualStatusRequest' => 'PV?',

            ],
            self::ptDigitalNoiseReduction => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PVDNR, 'Name' => 'Digital Noise Reduction',
                'PropertyName'                                              => 'DNRDirectChange',
                'Profilesettings'                                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                              => [
                    [0, 'Off', DENON_API_Commands::PVDNROFF],
                    [1, 'Low', DENON_API_Commands::PVDNRLOW],
                    [2, 'Middle', DENON_API_Commands::PVDNRMID],
                    [3, 'High', DENON_API_Commands::PVDNRHI],
                ],
            ],
            self::ptInputMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SD, 'Name' => 'Audio Input Mode',
                'PropertyName'                       => 'InputMode',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'AUTO', DENON_API_Commands::SDAUTO],
                    [1, 'HDMI', DENON_API_Commands::SDHDMI],
                    [2, 'DIGITAL', DENON_API_Commands::SDDIGITAL],
                    [3, 'ANALOG', DENON_API_Commands::SDANALOG],
                    [4, 'Ext.IN', DENON_API_Commands::SDEXTIN],
                    [5, '7.1 IN', DENON_API_Commands::SD71IN],
                    [6, 'No', DENON_API_Commands::SDNO],
                ],
            ],
            self::ptBluetoothTransmitter => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::BTTX, 'Name' => 'Bluetooth Transmitter',
                'PropertyName'                       => 'BluetoothTransmitter',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'Off', DENON_API_Commands::BTTXOFF],
                    [1, 'On', DENON_API_Commands::BTTXON],
                    [2, 'Bluetooth + Speaker', DENON_API_Commands::BTTXSP],
                    [3, 'Bluetooth only', DENON_API_Commands::BTTXBT],
                ],
            ],
            self::ptSpeakerPreset => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SPPR, 'Name' => 'Speaker Preset',
                'PropertyName'                       => 'SpeakerPreset',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'Preset 1', DENON_API_Commands::SPPR_1],
                    [1, 'Preset 2', DENON_API_Commands::SPPR_2],
                ],
            ],
            self::ptDialogEnhancer => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDEH, 'Name' => 'Dialog Enhancer',
                'PropertyName'                            => 'DialogEnhancer',
                'Profilesettings'                         => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                            => [
                    [0, 'Off', DENON_API_Commands::PSDEHOFF],
                    [1, 'Low', DENON_API_Commands::PSDEHLOW],
                    [2, 'Medium', DENON_API_Commands::PSDEHMED],
                    [3, 'High', DENON_API_Commands::PSDEHHIGH],
                ],
            ],
            self::ptAuroMatic3DPreset => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSAUROPR, 'Name' => 'Auro-Matic 3D Preset',
                'PropertyName'                               => 'AuroMatic3DPreset',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Small', DENON_API_Commands::PSAUROPRSMA],
                    [1, 'Medium', DENON_API_Commands::PSAUROPRMED],
                    [2, 'Large', DENON_API_Commands::PSAUROPRLAR],
                    [3, 'SPE', DENON_API_Commands::PSAUROPRSPE],
                ],
            ],
            self::ptAuro3DMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSAUROMODE, 'Name' => 'Auro 3D Mode',
                'PropertyName'                               => 'Auro3DMode',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Direct', DENON_API_Commands::PSAUROMODEDRCT],
                    [1, 'Ch.Expansion', DENON_API_Commands::PSAUROMODEEXP],
                    [2, 'Large', DENON_API_Commands::PSAUROPRLAR],
                ],
            ],
            self::ptMAINZONEAutoStandbySetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::STBY, 'Name' => 'Mainzone Auto Standby',
                'PropertyName'                                        => 'MAINZONEAutoStandbySetting',
                'Profilesettings'                                     => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                        => [
                    [0, 'Off', DENON_API_Commands::STBYOFF],
                    [1, '15 Min', DENON_API_Commands::STBY15M],
                    [2, '30 Min', DENON_API_Commands::STBY30M],
                    [3, '60 Min', DENON_API_Commands::STBY60M],
                ],
            ],
            self::ptMAINZONEECOModeSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::ECO, 'Name' => 'Mainzone ECO Mode',
                'PropertyName'                                    => 'MAINZONEECOModeSetting',
                'Profilesettings'                                 => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, 'Off', DENON_API_Commands::ECOOFF],
                    [1, 'Auto', DENON_API_Commands::ECOAUTO],
                    [2, 'On', DENON_API_Commands::ECOON],
                ],
            ],
            self::ptDimmer            => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::DIM, 'Name' => 'Dimmer',
                'PropertyName'                               => 'Dimmer',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Off', DENON_API_Commands::DIMOFF],
                    [1, 'Dark', DENON_API_Commands::DIMDAR],
                    [2, 'Dim', DENON_API_Commands::DIMDIM],
                    [3, 'Bright', DENON_API_Commands::DIMBRI],
                ],
            ],
            self::ptDynamicVolume => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDYNVOL, 'Name' => 'Dynamic Volume',
                'PropertyName'                                      => 'DynamicVolume',
                'Profilesettings'                                   => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                      => [
                    [0, 'Off', DENON_API_Commands::DYNVOLOFF],
                    [1, 'Light', DENON_API_Commands::DYNVOLLIT],
                    [2, 'Medium', DENON_API_Commands::DYNVOLMED],
                    [3, 'Heavy', DENON_API_Commands::DYNVOLHEV],
                    [4, 'Day', DENON_API_Commands::DYNVOLDAY],    // only older AVRs
                    [5, 'Evening', DENON_API_Commands::DYNVOLEVE], // only older AVRs
                    [6, 'Midnight', DENON_API_Commands::DYNVOLNGT], // only older AVRs
                    [7, 'Midnight', DENON_API_Commands::DYNVOLON], // only older Denon AVRs (i.e. 4310)
                ],
            ],
            self::ptResolutionHDMI => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSSCH, 'Name' => 'Resolution HDMI',
                'PropertyName'                            => 'ResolutionHDMI',
                'Profilesettings'                         => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                            => [
                    [0, '480p/576p', DENON_API_Commands::SCH48P],
                    [1, '1080i', DENON_API_Commands::SCH10I],
                    [2, '720p', DENON_API_Commands::SCH72P],
                    [3, '1080p', DENON_API_Commands::SCH10P],
                    [4, '1080p 24Hz', DENON_API_Commands::SCH10P24],
                    [5, '4K', DENON_API_Commands::SCH4K],
                    [6, '4K(60/50)', DENON_API_Commands::SCH4KF],
                    [7, '8K', DENON_API_Commands::SCH8K],
                    [8, 'Auto', DENON_API_Commands::SCHAUTO],
                    [9, 'Off', DENON_API_Commands::SCHOFF],
                ],
            ],
            self::ptResolution => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSSC, 'Name' => 'Resolution',
                'PropertyName'                                   => 'Resolution',
                'Profilesettings'                                => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                                   => [
                    [0, '480p/576p', DENON_API_Commands::SC48P],
                    [1, '1080i', DENON_API_Commands::SC10I],
                    [2, '720p', DENON_API_Commands::SC72P],
                    [3, '1080p', DENON_API_Commands::SC10P],
                    [4, '1080p 24Hz', DENON_API_Commands::SC10P24],
                    [5, '4K', DENON_API_Commands::SC4K],
                    [6, '4K(60/50)', DENON_API_Commands::SC4KF],
                    [7, '8K', DENON_API_Commands::SC8K],
                    [8, 'Auto', DENON_API_Commands::SCAUTO],
                ],
            ],
            self::ptDimension => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDIM, 'Name' => 'Dimension',
                'PropertyName'                                  => 'Dimension',
                'Profilesettings'                               => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                  => [
                    [0, '0', ' 00'],
                    [1, '1', ' 01'],
                    [2, '2', ' 02'],
                    [3, '3', ' 03'],
                    [4, '4', ' 04'],
                    [5, '5', ' 05'],
                    [6, '6', ' 06'],
                ],
            ],
            self::ptSleep => ['Type'                            => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SLP, 'Name' => 'Sleep',
                'PropertyName'                                  => 'Sleep',
                'Profilesettings'                               => ['Clock', '', '', 0, 0, 0, 0],
                'Associations'                                  => [
                    [0, 'Off', 'OFF'],
                    [1, '10 min', '010'],
                    [2, '20 min', '020'],
                    [3, '30 min', '030'],
                    [4, '40 min', '040'],
                    [5, '50 min', '050'],
                    [6, '60 min', '060'],
                    [7, '70 min', '070'],
                    [8, '80 min', '080'],
                    [9, '90 min', '090'],
                    [10, '100 min', '100'],
                    [11, '110 min', '110'],
                    [12, '120 min', '120'],
                ],
                'IndividualStatusRequest' => 'SLP?',
            ],
            self::ptZone2ChannelSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2CS, 'Name' => 'Zone 2 Channel Setting',
                'PropertyName'                                 => 'Z2Channel',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Stereo', DENON_API_Commands::Z2CSST],
                    [1, 'Mono', DENON_API_Commands::Z2CSMONO],
                ],
            ],
            self::ptZone3ChannelSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3CS, 'Name' => 'Zone 3 Channel Setting',
                'PropertyName'                                 => 'Z3Channel',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Stereo', DENON_API_Commands::Z3CSST],
                    [1, 'Mono', DENON_API_Commands::Z3CSMONO],
                ],
            ],
            self::ptZone2QuickSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2QUICK, 'Name' => 'Zone 2 Quick Select',
                'PropertyName'                              => 'Z2Quick',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                ],
            ],
            self::ptZone3QuickSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3QUICK, 'Name' => 'Zone 3 Quick Select',
                'PropertyName'                              => 'Z3Quick',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                ],
            ],
            self::ptZone2AutoStandbySetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2STBY, 'Name' => 'Zone 2 Auto Standby',
                'PropertyName'                                     => 'ZONE2AutoStandbySetting',
                'Profilesettings'                                  => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                     => [
                    [0, 'Off', DENON_API_Commands::Z2STBYOFF],
                    [1, '2 h', DENON_API_Commands::Z2STBY2H],
                    [2, '4 h', DENON_API_Commands::Z2STBY4H],
                    [3, '8 h', DENON_API_Commands::Z2STBY8H],
                ],
            ],
            self::ptZone3AutoStandbySetting => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3STBY, 'Name' => 'Zone 3 Auto Standby',
                'PropertyName'                                                => 'ZONE3AutoStandbySetting',
                'Profilesettings'                                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                => [
                    [0, 'Off', DENON_API_Commands::Z3STBYOFF],
                    [1, '2 h', DENON_API_Commands::Z3STBY2H],
                    [2, '4 h', DENON_API_Commands::Z3STBY4H],
                    [3, '8 h', DENON_API_Commands::Z3STBY8H],
                ],
            ],
            self::ptZone2HDMIAudio => ['Type'                                 => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2HDA, 'Name' => 'Zone 2 HDMI Audio',
                'PropertyName'                                                => 'Zone2HDMIAudio',
                'Profilesettings'                                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                => [
                    [0, 'Pass-Through', DENON_API_Commands::Z2HDATHR],
                    [1, 'PCM', DENON_API_Commands::Z2HDAPCM],
                ],
            ],

            self::ptTunerAnalogPreset => ['Type'                    => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TPAN, 'Name' => 'Tuner Preset',
                                          'PropertyName'            => 'TunerPreset',
                                          'Profilesettings'         => ['Database', '', '', 0, 0, 0, 0],
                                          'Associations'            => $assRange00to56,
                                          'IndividualStatusRequest' => 'TPAN?',
            ],


            //--- Attention: the order of the next two items may not be changed, becauseTM is a substring of TMAN
            self::ptTunerAnalogBand => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TMAN_BAND, 'Name' => 'Tuner Band',
                'PropertyName'                                 => 'TunerBand',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'AM', DENON_API_Commands::TMANAM],
                    [1, 'FM', DENON_API_Commands::TMANFM],
                    [2, 'DAB', DENON_API_Commands::TMANDAB],
                ],
                'IndividualStatusRequest' => 'TMAN?',
            ],

            self::ptTunerAnalogMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TMAN_MODE, 'Name' => 'Tuner Mode',
                                        'PropertyName'                                 => 'TunerMode',
                                        'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                                        'Associations'                                 => [
                                            [0, 'automatisch', DENON_API_Commands::TMANAUTO],
                                            [1, 'manuell', DENON_API_Commands::TMANMANUAL],
                                        ],
                                        'IndividualStatusRequest' => 'TMAN?',
            ],

            //Type Float
            //           DENONIPSProfiles::ptDimension => ["Type" => DENONIPSVarType::vtFloat, "Ident" => DENON_API_Commands::PSDIM, "Name" => "Dimension",
            //                                             "PropertyName" => "Dimension", "Profilesettings" => ["Intensity", "", " dB", 0, 6, 1, 0], "Associations" => $assRange00to06],
            self::ptDialogControl => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIC, 'Name' => 'Dialog Control',
                'PropertyName'                                                => 'DialogControl', 'Profilesettings' => ['Intensity', '', ' dB', 0, 6, 1, 0], 'Associations' => $assRange00to06, ],
            self::ptMasterVolume => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::MV, 'Name' => 'Master Volume',
                'PropertyName'                                                => self::ptMasterVolume, 'Profilesettings' => ['Intensity', '', ' dB', -80.0, 18.0, 0.5, 1], 'Associations' => $assRange00to98_add05step,
                'IndividualStatusRequest'                                     => 'MV?', ],
            self::ptChannelVolumeFL => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFL, 'Name' => 'Channel Volume Front Left',
                'PropertyName'                                                => 'FL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFR => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFR, 'Name' => 'Channel Volume Front Right',
                'PropertyName'                                                => 'FR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeC => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVC, 'Name' => 'Channel Volume Center',
                'PropertyName'                                                => 'C', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW, 'Name' => 'Channel Volume Subwoofer',
                'PropertyName'                                                => 'SW', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW2 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW2, 'Name' => 'Channel Volume Subwoofer 2',
                'PropertyName'                                                => 'SW2', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW3 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW3, 'Name' => 'Channel Volume Subwoofer 3',
                'PropertyName'                                                => 'SW3', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW4 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW4, 'Name' => 'Channel Volume Subwoofer 4',
                'PropertyName'                                                => 'SW4', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSL => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSL, 'Name' => 'Channel Volume Surround Left',
                'PropertyName'                                                => 'SL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSR => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSR, 'Name' => 'Channel Volume Surround Right',
                'PropertyName'                                                => 'SR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSBL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSBL, 'Name' => 'Channel Volume Surround Back Left',
                'PropertyName'                                                => 'SBL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSBR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSBR, 'Name' => 'Channel Volume Surround Back Right',
                'PropertyName'                                                => 'SBR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSB => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSB, 'Name' => 'Channel Volume Surround Back',
                'PropertyName'                                                => 'SB', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFHL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFHL, 'Name' => 'Channel Volume Front Height Left',
                'PropertyName'                                                => 'FHL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFHR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFHR, 'Name' => 'Channel Volume Front Height Right',
                'PropertyName'                                                => 'FHR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFWL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFWL, 'Name' => 'Channel Volume Front Wide Left',
                'PropertyName'                                                => 'FWL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFWR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFWR, 'Name' => 'Channel Volume Front Wide Right',
                'PropertyName'                                                => 'FWR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundHeightLch => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSHL, 'Name' => 'Surround Height Left',
                'PropertyName'                                                => 'SurroundHeightLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundHeightRch => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSHR, 'Name' => 'Surround Height Right',
                'PropertyName'                                                => 'SurroundHeightRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopSurround => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTS, 'Name' => 'Top Surround',
                'PropertyName'                                                => 'TopSurround', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptCenterHeight => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVCH, 'Name' => 'Center Height',
                'PropertyName'                                                => 'CenterHeight', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTactileTransducer => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTTR, 'Name' => 'Tactile Transducer',
                'PropertyName'                                                => 'TactileTransducer', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopFrontLch => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTFL, 'Name' => 'Channel Volume Top Front Left',
                'PropertyName'                                                => 'TopFrontLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopFrontRch => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTFR, 'Name' => 'Channel Volume Top Front Right',
                'PropertyName'                                                => 'TopFrontRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopMiddleLch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTML, 'Name' => 'Channel Volume Top Middle Left',
                'PropertyName'                                                => 'TopMiddleLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopMiddleRch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTMR, 'Name' => 'Channel Volume Top Middle Right',
                'PropertyName'                                                => 'TopMiddleRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopRearLch => ['Type'                                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTRL, 'Name' => 'Channel Volume Top Rear Left',
                'PropertyName'                                                => 'TopRearLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopRearRch => ['Type'                                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTRR, 'Name' => 'Channel Volume Top Rear Right',
                'PropertyName'                                                => 'TopRearRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptRearHeightLch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVRHL, 'Name' => 'Channel Volume Rear Height Left',
                'PropertyName'                                                => 'RearHeightLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptRearHeightRch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVRHR, 'Name' => 'Channel Volume Rear Height Right',
                'PropertyName'                                                => 'RearHeightRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptFrontDolbyLch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFDL, 'Name' => 'Channel Volume Front Dolby Left',
                'PropertyName'                                                => 'FrontDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptFrontDolbyRch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFDR, 'Name' => 'Channel Volume Front Dolby Right',
                'PropertyName'                                                => 'FrontDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundDolbyLch => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSDL, 'Name' => 'Channel Volume Surround Dolby Left',
                'PropertyName'                                                => 'SurroundDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundDolbyRch => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSDR, 'Name' => 'Channel Volume Surround Dolby Right',
                'PropertyName'                                                => 'SurroundDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptBackDolbyLch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVBDL, 'Name' => 'Channel Volume Back Dolby Left',
                'PropertyName'                                                => 'BackDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptBackDolbyRch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVBDR, 'Name' => 'Channel Volume Back Dolby Right',
                'PropertyName'                                                => 'BackDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],

            //--- Attention: the order of the next two items may not be changed, because PSDEL is a substring of PSDELAY
            self::ptAudioDelay => ['Type'                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDELAY, 'Name' => 'Audio Delay',
                'PropertyName'                                => 'AudioDelay', 'Profilesettings' => ['Intensity', '', ' ms', 0, 200, 1, 0], 'Associations' => $assRange000to200, ],
            self::ptDelay => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDEL, 'Name' => 'Delay',
                'PropertyName'                                => 'Delay', 'Profilesettings' => ['Intensity', '', ' ms', 0, 300, 1, 0], 'Associations' => $assRange000to300, ],
            //---
            self::ptCenterLevelAdjust => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCLV, 'Name' => 'Center Level Adjust',
                'PropertyName'                                            => 'CenterLevelAdjust', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62],
            self::ptLFELevel => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSLFE, 'Name' => 'LFE Level',
                'PropertyName'                                            => 'LFELevel', 'Profilesettings' => ['Intensity', '', ' dB', -10.0, 0.0, 1, 0], 'Associations' => $assRange00to10_invert, ],
            self::ptLFE71Level => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSLFL, 'Name' => 'LFE 7.1 Level',
                'PropertyName'                                            => 'LFE71Level', 'Profilesettings' => ['Intensity', '', ' dB', -15.0, 0.0, 1, 0], 'Associations' => $assRange00to15_invert, ],
            self::ptBassLevel => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSBAS, 'Name' => 'Bass Level',
                'PropertyName'                                            => 'BassLevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptTrebleLevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSTRE, 'Name' => 'Treble Level',
                'PropertyName'                                            => 'TrebleLevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptCenterWidth => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEN, 'Name' => 'Center Width',
                'PropertyName'                                            => 'CenterWidth', 'Profilesettings' => ['Intensity',  '', ' dB', 0, 7, 1, 0], 'Associations' => $assRange00to07, ],
            self::ptEffectLevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSEFF, 'Name' => 'Effect Level',
                'PropertyName'                                            => 'EffectLevel', 'Profilesettings' => ['Intensity', '', ' dB', 0, 15, 1, 0], 'Associations' => $assRange00to15, ],
            self::ptCenterImage => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEI, 'Name' => 'Center Image',
                'PropertyName'                                            => 'CenterImage', 'Profilesettings' => ['Intensity', '', ' dB', 0.0, 1.0, 0.1, 1], 'Associations' => $assRange00to10_stepwide_01, ],
            self::ptCenterGain => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEG, 'Name' => 'Center Gain',
                'PropertyName'                                            => 'CenterGain', 'Profilesettings' => ['Intensity', '', ' dB', 0.0, 1.0, 0.1, 1], 'Associations' => $assRange00to10_stepwide_01, ],
            self::ptContrast => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVCN, 'Name' => 'Contrast',
                'PropertyName'                                            => 'Contrast', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptBrightness => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVBR, 'Name' => 'Brightness',
                'PropertyName'                                            => 'Brightness', 'Profilesettings' => ['Intensity', '', ' dB', 0, 12, 1, 0], 'Associations' => $assRange00to12, ],
            self::ptSaturation => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVST, 'Name' => 'Saturation',
                'PropertyName'                                            => 'Saturation', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptChromalevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVCM, 'Name' => 'Chroma Level',
                'PropertyName'                                            => 'Chromalevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptHue => ['Type'                                        => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVHUE, 'Name' => 'Hue',
                'PropertyName'                                            => 'Hue', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptEnhancer => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVENH, 'Name' => 'Enhancer',
                'PropertyName'                                            => 'Enhancer', 'Profilesettings' => ['Intensity', '', ' dB', 0, 12, 1, 0], 'Associations' => $assRange00to12, ],
            self::ptStageHeight => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSTH, 'Name' => 'Stage Height',
                'PropertyName'                                            => 'StageHeight', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptStageWidth => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSTW, 'Name' => 'Stage Width',
                'PropertyName'                                            => 'StageWidth', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptAudysseyContainmentAmount => ['Type'                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCNTAMT, 'Name' => 'Audyssey Containment Amount',
                'PropertyName'                                            => 'AudysseyContainmentAmount', 'Profilesettings' => ['Intensity',  '', '', 1, 7, 1, 0], 'Associations' => $assRange00to07, ],
            self::ptBassSync => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSBSC, 'Name' => 'BassSync',
                'PropertyName'                                            => 'BassSync', 'Profilesettings' => ['Intensity', '', ' dB', 0, 16, 1, 0], 'Associations' => $assRange00to16, ],
            self::ptSubwooferLevel => ['Type'                             => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL, 'Name' => 'Subwoofer Level',
                'PropertyName'                                            => 'SubwooferLevel', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer2Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL2, 'Name' => 'Subwoofer 2 Level',
                'PropertyName'                                            => 'Subwoofer2Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer3Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL3, 'Name' => 'Subwoofer 3 Level',
                'PropertyName'                                            => 'Subwoofer3Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer4Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL4, 'Name' => 'Subwoofer 4 Level',
                'PropertyName'                                            => 'Subwoofer4Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptDialogLevelAdjust => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIL, 'Name' => 'Dialog Level Adjust',
                'PropertyName'                                            => 'DialogLevelAdjust', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptAuroMatic3DStrength => ['Type'                        => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSAUROST, 'Name' => 'Auromatic 3D Strength',
                'PropertyName'                                            => 'AuroMatic3DStrength', 'Profilesettings' => ['Intensity', '', ' dB', 0, 16, 1, 0], 'Associations' => $assRange00to16, ],
            self::ptZone2Volume => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2VOL, 'Name' => 'Zone 2 Volume',
                'PropertyName'                                            => self::ptZone2Volume, 'Profilesettings' => ['Intensity', '', ' dB', -80, 18, 1, 0], 'Associations' => $assRange00to98,
                'IndividualStatusRequest'                                 => 'Z2?', ],
            self::ptZone3Volume => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3VOL, 'Name' => 'Zone 3 Volume',
                'PropertyName'                                            => self::ptZone3Volume, 'Profilesettings' => ['Intensity', '', ' dB', -80, 18, 1, 0], 'Associations' => $assRange00to98,
                'IndividualStatusRequest'                                 => 'Z3?', ],
            self::ptZone2Sleep => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2SLP, 'Name' => 'Zone 2 Sleep',
                'PropertyName'                                            => 'Z2Sleep', 'Profilesettings' => ['Clock', '', ' Min', 0, 120, 10, 0], 'Associations' => $assRange000to120_ptSleep, ],
            self::ptZone3Sleep => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3SLP, 'Name' => 'Zone 3 Sleep',
                'PropertyName'                                            => 'Z3Sleep', 'Profilesettings' => ['Clock', '', ' Min', 0, 120, 10, 0], 'Associations' => $assRange000to120_ptSleep, ],
            self::ptZone2ChannelVolumeFL => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2CVFL, 'Name' => 'Zone 2 Channel Volume Front Left',
                'PropertyName'                                            => 'Z2CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone2ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2CVFR, 'Name' => 'Zone 2 Channel Volume Front Right',
                'PropertyName'                                            => 'Z2CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone3ChannelVolumeFL => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFL, 'Name' => 'Zone 3 Channel Volume Front Left',
                'PropertyName'                                            => 'Z3CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone3ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFR, 'Name' => 'Zone 3 Channel Volume Front Right',
                'PropertyName'                                            => 'Z3CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone2Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSBAS, 'Name' => 'Zone 2 Bass',
                'PropertyName'                                            => 'Z2Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSBAS, 'Name' => 'Zone 3 Bass',
                'PropertyName'                                            => 'Z3Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone2Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSTRE, 'Name' => 'Zone 2 Treble',
                'PropertyName'                                            => 'Z2Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSTRE, 'Name' => 'Zone 3 Treble',
                'PropertyName'                                            => 'Z3Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptSSINFAISFSV => ['Type' => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::SSINFAISFSV, 'Name' => 'Audio: Abtastrate',
                              'PropertyName'                                        => 'SSINFAISFSV', 'Profilesettings' => ['Information', '', ' kHz', 0, 0, 0, 1], 'Associations' => [], 'displayOnly' => true],

            //Type String
            self::ptMainZoneName    => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'MainZoneName', 'Name' => 'MainZone Name', 'PropertyName' => 'ZoneName', 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptModel           => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Model', 'Name' => 'Model', 'PropertyName' => 'Model', 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptSurroundDisplay => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SURROUNDDISPLAY, 'Name' => 'Surround Mode Display',
                                        'PropertyName'                                        => 'SurroundDisplay', 'Profilesettings' => ['Information'], 'displayOnly' => true ],
            self::ptSYSMI => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSMI, 'Name' => 'Audio: Soundmodus',
                                        'PropertyName'                                        => 'SYSMI', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOnly' => true],
            self::ptSYSDA => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSDA, 'Name' => 'Audio: Eingangssignal',
                                        'PropertyName'                                        => 'SYSDA', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOnly' => true],
            self::ptDisplay => ['Type'                                => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::DISPLAY, 'Name' => 'OSD Info', 'ProfilName' => '~HTMLBox', 'PropertyName' => 'Display', 'Profilesettings' => ['TV'],
                'IndividualStatusRequest'                             => 'NSA', 'displayOnly' => true],
            self::ptZone2Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone2Name', 'Name' => 'Zone 2 Name', 'PropertyName' => self::ptZone2Name, 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptZone3Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone3Name', 'Name' => 'Zone 3 Name', 'PropertyName' => self::ptZone3Name, 'Profilesettings' => ['Information'], 'displayOnly' => true],
        ];

        if ($AVRType !== null) {
            $this->AVRType = $AVRType;

            // some profiles have to be adapted to the capabilities of the AVR
            $caps = AVRs::getCapabilities($AVRType);
            $this->updateProfileAccordingToCaps(self::ptSurroundMode, $caps);
            $this->updateProfileAccordingToCaps(self::ptResolution, $caps);
            $this->updateProfileAccordingToCaps(self::ptResolutionHDMI, $caps);
            $this->updateProfileAccordingToCaps(self::ptSpeakerOutput, $caps);
            $this->updateProfileAccordingToCaps(self::ptDynamicVolume, $caps);
            $this->updateProfileAccordingToCaps(self::ptVideoSelect, $caps);

            if (in_array($AVRType, ['AVR-X4000', 'AVR_3808A', 'AVR-X3000', 'AVR-4310', 'AVR-4311', 'AVR-3310', 'AVR-3311', 'AVR-3312', 'AVR-3313',
                                    'Marantz-SR6005', 'Marantz-SR6006', 'Marantz-NR1602', 'Marantz-SR5006', 'Marantz-SR7005', 'Marantz-AV7005'])){
                $this->profiles[self::ptTunerAnalogPreset]['Associations'] = $assRangeA1toG8;
            }

            if (in_array($AVRType, ['DRA-N5', 'RCD-N8'])) {
                $this->profiles[self::ptMasterVolume] = [
                    'Type'                    => DENONIPSVarType::vtFloat,
                    'Ident'                   => DENON_API_Commands::MV,
                    'Name'                    => 'Master Volume',
                    'PropertyName'            => self::ptMasterVolume,
                    'Profilesettings'         => ['Intensity', '', '', 0, 60, 1, 0],
                    'Associations'            => $this->GetAssociationOfAsciiTodB('00', '60', '00', 1, false, false),
                    'IndividualStatusRequest' => 'MV?', ];

                $this->profiles[self::ptBassLevel]    = [
                    'Type'            => DENONIPSVarType::vtFloat,
                    'Ident'           => DENON_API_Commands::PSBAS,
                    'Name'            => 'Bass Level',
                    'PropertyName'    => self::ptBassLevel,
                    'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 2, 0],
                    'Associations'    => $this->GetAssociationOfAsciiTodB('40', '60', '50', 2)];

                $this->profiles[self::ptTrebleLevel] = [
                    'Type'            => DENONIPSVarType::vtFloat,
                    'Ident'           => DENON_API_Commands::PSTRE,
                    'Name'            => 'Treble Level',
                    'PropertyName'    => self::ptTrebleLevel,
                    'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 2, 0],
                    'Associations'    => $this->GetAssociationOfAsciiTodB('40', '60', '50', 2)];

            }
        }

        if ($InputMapping !== null) {
            $associations = [];
            foreach ($InputMapping as $key=> $value) {
                $associations[] = [$value, '', $key];
            }
            $associations[] = [count($associations), 'Source', 'SOURCE'];
            $this->profiles[self::ptInputSource]['Associations'] = $associations;
            $this->profiles[self::ptZone2InputSource]['Associations'] = $associations;
            $this->profiles[self::ptZone3InputSource]['Associations'] = $associations;
            if ($this->debug) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Association: ' . json_encode($associations, JSON_THROW_ON_ERROR));
            }
        }
    }

    private function updateProfileAccordingToCaps($profilename, $caps): void
    {
        $ident = $this->profiles[$profilename]['Ident'];
        $associations = $this->profiles[$profilename]['Associations'];
        if (!array_key_exists($ident . '_SubCommands', $caps)) {
            trigger_error(__FUNCTION__ . ': unknown capability "' . $ident . '_SubCommands' . '"');
        }
        $subcommands = $caps[$ident . '_SubCommands'];
        for ($i = (count($associations) - 1); $i >= 0; $i--) {
            if (!in_array($associations[$i][2], $subcommands, true)) {
                unset($associations[$i]);
            }
        }
        $this->profiles[$profilename]['Associations'] = array_values($associations);
    }

    public function SetInputSources($DenonIP, $Zone, $FAVORITES, $IRADIO, $SERVER, $NAPSTER, $LASTFM, $FLICKR): void
    {
        if ($this->debug) {
            call_user_func(
                $this->Logger_Dbg,
                __CLASS__ . '::' . __FUNCTION__,
                sprintf(
                    'Parameters - IP: %s, Zone: %s, Favorites: %s, IRadio: %s, Server: %s, Napster: %s, LastFM: %s, Flickr: %s',
                    $DenonIP,
                    $Zone,
                    (int)$FAVORITES,
                    (int)$IRADIO,
                    (int)$SERVER,
                    (int)$NAPSTER,
                    (int)$LASTFM,
                    (int)$FLICKR
                )
            );
        }

        $caps = AVRs::getCapabilities($this->AVRType);
        if ($caps['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) {
            if (!filter_var($DenonIP, FILTER_VALIDATE_IP)) {
                trigger_error(__FUNCTION__ . ': Die IP Adresse "' . $DenonIP . '" ist ungültig!');

                return;
            }
            $Associations = $this->GetAssociationsOfInputSourcesAccordingToHTTPInfo(
                $DenonIP,
                $caps['httpMainZone'],
                $Zone
            );

            if ($Associations === null) {

                return;
            }

        } else {
            //Assoziationen aufbauen
            $Associations = [];
            foreach ($caps['SI_SubCommands'] as $key=>$subcommand){
                $Associations[] = [$key, $subcommand, $subcommand];
            }
        }

        //zusätzliche Auswahl 'SOURCE' bei Zonen
        if ($Zone > 0) {
            $Associations[] = [count($Associations), 'SOURCE', DENON_API_Commands::IS_SOURCE];
        }

        //zusätzliche Inputs bei Auswahl
        if ($FAVORITES && (!in_array(DENON_API_Commands::IS_FAVORITES, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Favoriten', DENON_API_Commands::IS_FAVORITES];
        }
        if ($IRADIO && (!in_array(DENON_API_Commands::IS_IRADIO, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Internet Radio', DENON_API_Commands::IS_IRADIO];
        }
        if ($SERVER && (!in_array(DENON_API_Commands::IS_SERVER, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Server', DENON_API_Commands::IS_SERVER];
        }
        if ($NAPSTER && (!in_array(DENON_API_Commands::IS_LASTFM, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Napster', DENON_API_Commands::IS_NAPSTER];
        }
        if ($LASTFM && (!in_array(DENON_API_Commands::IS_FAVORITES, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'LastFM', DENON_API_Commands::IS_LASTFM];
        }
        if ($FLICKR && (!in_array(DENON_API_Commands::IS_FLICKR, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Flickr', DENON_API_Commands::IS_FLICKR];
        }

        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Associations: ' . json_encode($Associations, JSON_THROW_ON_ERROR));
        }

        switch ($Zone) {
            case 0:
                $this->profiles[self::ptInputSource]['Associations'] = $Associations;
                break;

            case 1:
                $this->profiles[self::ptZone2InputSource]['Associations'] = $Associations;
                break;

            case 2:
                $this->profiles[self::ptZone3InputSource]['Associations'] = $Associations;
                break;

            default:
                trigger_error('unknown zone: ' . $Zone);
       }

    }

    private function GetInputsFromXMLZone(SimpleXMLElement $xmlZone, $MainForm, $filename): ?array
    {
        //Inputs
        $InputFuncList = $xmlZone->xpath('.//InputFuncList');
        if (count($InputFuncList) === 0) {
            trigger_error('InputFuncList has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $RenameSource = $xmlZone->xpath('.//RenameSource');
        if (count($RenameSource) === 0) {
            trigger_error('RenameSource has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $SourceDelete = $xmlZone->xpath('.//SourceDelete');
        if (count($SourceDelete) === 0) {
            trigger_error('SourceDelete has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $Inputs = [];
        $UsedInput_i = -1;
        $countinput = count($InputFuncList[0]->value);

        for ($i = 0; $i <= $countinput - 1; $i++) {
            //manche AVRs(z.B. Marantz 7010 bei 'Online Music') liefern auch schon mal einen Leerstring anstelle von 'USE'
            if (((string) $SourceDelete[0]->value[$i] === 'USE') || ((string) $SourceDelete[0]->value[$i] === '')) {
                $UsedInput_i++;
                if ($MainForm === DENON_HTTP_Interface::MainForm_old) {
                    $RenameInput = (string) $RenameSource[0]->value[$i];
                } else {
                    $RenameInput = (string) $RenameSource[0]->value[$i]->value;
                }

                if ($RenameInput !== '') {
                    $Inputs[$UsedInput_i] = ['Source' => (string) $InputFuncList[0]->value[$i], 'RenameSource' => $RenameInput];
                } else {
                    $Inputs[$UsedInput_i] = ['Source' => (string) $InputFuncList[0]->value[$i], 'RenameSource' => (string) $InputFuncList[0]->value[$i]];
                }
            }
        }

        //Assoziationen aufbauen
        $Associations = [];

        foreach ($Inputs as $Value => $Input) {
            // Beispiel: Association[] = [1, 'SONOS', 'CD']
            $Associations[] = [$Value, str_replace(' ', '', $Input['RenameSource']), str_replace(' ', '', $Input['Source'])];
        }

        return $Associations;
    }

    private function GetAssociationsOfInputSourcesAccordingToHTTPInfo($IP, $MainForm, $Zone): ?array
    {
        $filename = 'http://' . $IP . $MainForm . '?_=&ZoneName=ZONE' . ($Zone + 1);
        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'filename: ' . $filename);
        }

        $content = @file_get_contents($filename);
        if ($content === false) {
            trigger_error('Datei ' . $filename . ' konnte nicht geöffnet werden.');

            return null;
        }

        $xmlZone = new SimpleXMLElement($content);
        if ($xmlZone->count() === 0) {
            trigger_error('xmlzone has no children. '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        return $this->GetInputsFromXMLZone($xmlZone, $MainForm, $filename);

    }

    public function GetInputVarMapping($Zone): false|array
    {
        if ($Zone === 0) {
            $associations = $this->profiles[self::ptInputSource]['Associations'];
        } elseif ($Zone === 1) {
            $associations = $this->profiles[self::ptZone2InputSource]['Associations'];
        } elseif ($Zone === 2) {
            $associations = $this->profiles[self::ptZone3InputSource]['Associations'];
        } else {
            trigger_error('unknown zone: ' . $Zone);

            return false;
        }

        $InputSourcesMapping = [];
        foreach ($associations as $association) {
            $InputSourcesMapping[] = ['Source' => $association[2], 'RenameSource' => $association[1]];
        }

        $ret = ['AVRType' => $this->AVRType, 'Inputs' => $InputSourcesMapping];

        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'return: ' . json_encode($ret, JSON_THROW_ON_ERROR));
        }

        return $ret;
    }

    public function GetVariableConfig($configId): false|array
    {
        if ($this->debug){
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Get variable config for id ' . $configId);
        }

        if (!array_key_exists($configId, $this->profiles)) {
            trigger_error('unknown ident: ' . $configId);

            return false;
        }

        $profile = $this->profiles[$configId];
        if (!isset($profile['Type'])) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Type not set in profile "' . $configId . '"');

            return false;
        }

        switch ($profile['Type']) {
            case DENONIPSVarType::vtBoolean:
                $ret = ['Name'     => $profile['Name'],
                        'Ident'        => $profile['Ident'],
                        'Type'         => $profile['Type'],
                        'PropertyName' => $profile['PropertyName'],
                        'ProfilName'   => '~Switch',
                        'Position'     => $this->getpos($configId),
                        'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            case DENONIPSVarType::vtInteger:
            case DENONIPSVarType::vtFloat:
                $profilesettings = $profile['Profilesettings'];

                $ret = [
                    'Name'         => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => $configId,
                    'Icon'         => $profilesettings[0],
                    'Prefix'       => $profilesettings[1],
                    'Suffix'       => $profilesettings[2],
                    'MinValue'     => $profilesettings[3],
                    'MaxValue'     => $profilesettings[4],
                    'Stepsize'     => $profilesettings[5],
                    'Digits'       => $profilesettings[6],
                    'Associations' => $profile['Associations'],
                    'Position'     => $this->getpos($configId),
                    'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            case DENONIPSVarType::vtString:
                $profilename=$profile['ProfilName'] ?? $configId;
                $ret        = [
                    'Name'         => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => $profilename,
                    'Position'     => $this->getpos($configId),
                    'Icon'         => $profile['Profilesettings'][0],
                    'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            default:
                trigger_error('unknown profile type: ' . $profile['Type']);

                return false;

        }

        return $ret;
    }

    public function GetVariableProfileMapping(): array
    {
        $ret = [];

        foreach ($this->profiles as $profile) {
            if (!isset($profile['Associations'])) {
                continue;
            }

            $ValueMapping = [];
            foreach ($profile['Associations'] as $association) {
                try {
                    match ($profile['Type']) {
                        DENONIPSVarType::vtBoolean => $ValueMapping[$association[1]] = $association[0],
                        DENONIPSVarType::vtInteger => $ValueMapping[$association[2]] = $association[0],
                        DENONIPSVarType::vtFloat   => $ValueMapping[$association[0]] = $association[1],
                        DENONIPSVarType::vtString  => null, // Strings benötigen oft kein Mapping
                        default                    => throw new UnexpectedValueException('Unexpected type: ' . $profile['Type'])
                    };
                } catch (UnhandledMatchError|UnexpectedValueException $e) {
                    trigger_error(__FUNCTION__ . ': ' . $e->getMessage());
                }
            }

            $ret[$profile['Ident']] = [
                'VarType'      => $profile['Type'],
                'ValueMapping' => $ValueMapping
            ];
        }

        return $ret;
    }

    public function GetAllProfiles(): array
    {
        return $this->profiles;
    }

    public function GetAllProfilesSortedByPos(): array
    {
        $ret = [];
        $this->checkProfiles();

        foreach (static::$order as $profileID) {
            $ret[$profileID] = $this->profiles[$profileID];
        }

        return $ret;
    }

    private function checkProfiles(): bool
    {
        //check if all profiles have a position in $order
        $profile_without_pos = [];
        if (count(static::$order) !== count($this->profiles)) {
            foreach ($this->profiles as $profileID => $profile) {
                if (!in_array($profileID, static::$order, true)) {
                    $profile_without_pos[] = $profileID;
                }
            }
            if (count($profile_without_pos) > 0) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Order: ' . json_encode(static::$order, JSON_THROW_ON_ERROR));
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Profiles without positions: ' . json_encode(
                                  $profile_without_pos,
                                  JSON_THROW_ON_ERROR
                              )
                );

                return false;
            }
        }

        //check if all elements in order have a profile definition
        $order_without_definition = [];
        if (count(static::$order) !== count($this->profiles)) {
            foreach (static::$order as $order_item) {
                if (!array_key_exists($order_item, $this->profiles)) {
                    $order_without_definition[] = $order_item;
                }
            }
            if (count($order_without_definition) > 0) {
                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'Profiles: ' . json_encode($this->profiles, JSON_THROW_ON_ERROR));
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Keys: ' . json_encode(array_keys($this->profiles), JSON_THROW_ON_ERROR));
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Order Element without definition: ' . json_encode(
                                  $order_without_definition,
                                  JSON_THROW_ON_ERROR
                              )
                );

                return false;
            }
        }

        //check if all profiles are used in MAX Capabilities
        $all_capabilities = array_merge(
            AVR::$InfoFunctions_max,
            AVR::$AvrInfos_max,
            AVR::$PowerFunctions_max,
            AVR::$CV_Commands_max,
            AVR::$InputSettings_max,
            AVR::$PS_Commands_max,
            AVR::$PV_Commands_max,
            AVR::$SurroundMode_max,
            AVR::$VS_Commands_max,
            AVR::$SystemControl_Commands_max,
            AVR::$Zone_Commands_max,
            AVR::$Tuner_Control_max
        );

        //check if all profiles are at least used in Capabilities_max
        $profile_not_used_in_caps = [];
        foreach ($this->profiles as $profileID => $profile) {
            if (!in_array($profile['Ident'], $all_capabilities, true)) {
                $profile_not_used_in_caps[$profileID] = $profile['Ident'];
            }
        }

        if (count($profile_not_used_in_caps) > 0) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Profiles not used in Capabilities(MAX):' . json_encode(
                              $profile_not_used_in_caps,
                              JSON_THROW_ON_ERROR
                          ) . PHP_EOL . 'Capabilities: ' . json_encode($all_capabilities, JSON_THROW_ON_ERROR)
            );
            call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'Profiles not used in Capabilities(MAX):' . json_encode(
                                                              $profile_not_used_in_caps,
                                                              JSON_THROW_ON_ERROR
                                                          ) . PHP_EOL . 'Capabilities: ' . json_encode($all_capabilities, JSON_THROW_ON_ERROR)
            );

            return false;
        }

        return true;
    }

    /**
     * Ermittelt den API-Subcommand für einen gegebenen Wert basierend auf dem Profil-Ident.
     *
     * Diese Methode sucht zuerst das passende Profil anhand von $Ident. Anschließend durchsucht
     * sie die Assoziationsliste dieses Profils, um den zum Wert ($Value) passenden
     * Befehl (Subcommand) zu finden.
     *
     * @param string $Ident Der Ident des Profils (z.B. "PW", "MV", "SI").
     * @param mixed  $Value Der Wert, nach dem gesucht werden soll (Typ abhängig vom Profil: bool, int, float).
     *
     * @return string|null Der gefundene Subcommand als String oder null, wenn nichts gefunden wurde.
     */
    public function GetSubCommandOfValue(string $Ident, $Value): ?string
    {
        // 1. Profil suchen
        $selectedProfile = null;
        foreach ($this->profiles as $profile) {
            if ($profile['Ident'] === $Ident) {
                $selectedProfile = $profile;
                break;
            }
        }

        // Guard Clause: Kein Profil gefunden
        if ($selectedProfile === null || !isset($selectedProfile['Associations'])) {
            trigger_error('no profile found. Ident: ' . $Ident . ', Value: ' . $Value);
            return null;
        }

        // 2. Debugging
        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __FUNCTION__, 'Profile "' . $Ident . '" found: ' . json_encode($selectedProfile, JSON_THROW_ON_ERROR));
        }

        // 3. Wert im Profil suchen
        foreach ($selectedProfile['Associations'] as $item) {
            $foundSubCommand = $this->matchValueInAssociation($selectedProfile['Type'], $item, $Value);

            if ($foundSubCommand !== null) {
                return (string)$foundSubCommand;
            }
        }

        // Nichts gefunden
        trigger_error('no association found. Ident: ' . $Ident . ', Value: ' . $Value);
        return null;
    }

    /**
     * Hilfsmethode um die komplexe Switch-Logik und Magic-Numbers zu isolieren
     */
    private function matchValueInAssociation(int $type, array $item, $Value): ?string
    {
        switch ($type) {
            case DENONIPSVarType::vtBoolean:
                // Boolean: Vergleich Index 0, Return Index 1
                if ($item[0] === $Value) {
                    return $item[1];
                }
                break;

            case DENONIPSVarType::vtInteger:
                // Integer: Vergleich Index 0, Return Index 2
                if ($item[0] === $Value) {
                    return $item[2];
                }
                break;

            case DENONIPSVarType::vtFloat:
                // Float: Vergleich Index 1 (gerundet), Return Index 0
                // Achtung: Floats mit Nachkommastellen müssen zum Vergleich gerundet werden!
                if (round($item[1], 1) === round($Value, 1)) {
                    return $item[0];
                }
                break;

            default:
                // Optional: Fehler nur einmal loggen oder hier werfen,
                // aktuell wird er im Loop oben ignoriert, was okay ist.
                break;
        }
        return null;
    }

    public function GetSubCommandOfValueName(string $Ident, string $ValueName): ?string
    {
        $ret = null;
        foreach ($this->profiles as $profile) {
            if (($profile['Ident'] === $Ident) && isset($profile['Associations'])) {
                foreach ($profile['Associations'] as $item) {
                    if ($profile['Type'] === DENONIPSVarType::vtInteger) {
                        if (strtoupper($item[1]) === strtoupper($ValueName)) {
                            $ret = $item[2];
                        }
                    } else {
                        trigger_error(__FUNCTION__ . ': unknown type: ' . $profile['Type']);
                    }
                    if ($ret !== null) {
                        break;
                    }
                }
            }
            if ($ret !== null) {
                break;
            }
        }

        if ($ret === null) {
            trigger_error('no subcommand found. Ident: ' . $Ident . ', Value: ' . $ValueName);

            return null;
        }

        return (string) $ret;
    }

    private function getpos($profilename): false|int
    {
        $pos = array_search($profilename, static::$order, true);
        if ($pos === false) {
            trigger_error('unknown profile: ' . $profilename);

            return false;
        }

        return ($pos + 1) * 10; //starting with 10, step size 10
    }

    private function GetAssociationFromA1toG8(): array
    {
        $value_mapping = [];
        $index = 1;
        for ($i  = ord('A'); $i <= ord('G'); $i++){
            for ($j = 1; $j <= 8; $j++){
                $value_mapping[] = [$index, chr($i) . $j, chr($i) . $j];
                $index++;
            }
        }

        return $value_mapping;
    }

    private function GetAssociationFrom00to56(): array
    {
        $value_mapping = [];
        $index = 1;
        for ($i  = 1; $i <= 56; $i++){
            $value_mapping[] = [$index, sprintf('%02d', $i), sprintf('%02d',$i)];
            $index++;
        }

        return $value_mapping;
    }

    /**
     * Erzeugt eine Assoziationsliste für IP-Symcon Profil-Mappings zwischen ASCII-Protokollwerten und dB-Werten.
     *
     * @param string $asciiStart       Startwert des ASCII-Bereichs (z.B. '00')
     * @param string $asciiEnd         Endwert des ASCII-Bereichs (z.B. '98')
     * @param string $asciiReference   ASCII-Wert, der 0 dB entspricht (Referenzpunkt)
     * @param float  $dbStep           Schrittweite in dB (Standard: 1.0)
     * @param bool   $includeHalfSteps Ob zusätzlich 0.5er Zwischenschritte (z.B. '495' für 49.5) erzeugt werden sollen
     * @param bool   $useLeadingBlank  Ob ein führendes Leerzeichen im Label (für das Protokoll) nötig ist
     * @param bool   $invertDbValue    Ob der dB-Wert für die Anzeige invertiert werden soll (z. B. LFE-Level)
     * @param float  $scaleFactor      Skalierungsfaktor zur Umrechnung von ASCII-Differenz in dB
     *
     * @return array Generierte Liste von [Protokoll-String, dB-Float] Paaren
     * @throws \InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    private function GetAssociationOfAsciiTodB(
        string $asciiStart,
        string $asciiEnd,
        string $asciiReference,
        float $dbStep = 1.0,
        bool $includeHalfSteps = false,
        bool $useLeadingBlank = true,
        bool $invertDbValue = false,
        float $scaleFactor = 1.0
    ): array {
        if ($dbStep <= 0 || $scaleFactor <= 0) {
            throw new InvalidArgumentException('StepSize and ScaleFactor must be greater than 0');
        }

        $startInt = (int)$asciiStart;
        $endInt   = (int)$asciiEnd;
        $refInt   = (int)$asciiReference;

        $dbRangeStart = ($startInt - $refInt) * $scaleFactor;
        $dbRangeEnd   = ($endInt - $refInt) * $scaleFactor;

        $sign = $invertDbValue ? -1 : 1;
        $prefix = $useLeadingBlank ? ' ' : '';
        $padLength = strlen($asciiEnd);

        $associations = [];
        $epsilon = 0.0001;

        for ($currentDb = $dbRangeStart; $currentDb <= $dbRangeEnd + $epsilon; $currentDb += $dbStep) {
            $currentAscii = $refInt + ($currentDb / $scaleFactor);

            $asciiFloor = floor($currentAscii + $epsilon);
            $isHalfStep = abs($currentAscii - $asciiFloor - 0.5) < $epsilon;

            if ($includeHalfSteps && $isHalfStep) {
                $baseVal = (int)$asciiFloor;
                $suffix = '5';
            } else {
                $baseVal = (int)round($currentAscii);
                $suffix = '';
            }

            $protocolString = $prefix . str_pad((string)$baseVal, $padLength, '0', STR_PAD_LEFT) . $suffix;
            $associations[] = [$protocolString, $currentDb * $sign];
        }

        return $associations;
    }

}

class DENON_StatusHTML extends stdClass
{
    private bool $debug = false; //wird im Constructor gesetzt
    private $Logger_Dbg;

    public function __construct(?callable $Logger_Dbg = null)
    {
        if (isset($Logger_Dbg)){
            $this->debug = true;
            $this->Logger_Dbg = $Logger_Dbg;
        }

    }

    //Status
    public function getStates($ip, $InputMapping, $AVRType): array
    {
        //Main
        $DataMain = [];
        if ($this->debug) {
            $DenonAVRVar = new DENONIPSProfiles($AVRType, $InputMapping, $this->Logger_Dbg);
        } else {
            $DenonAVRVar = new DENONIPSProfiles($AVRType, $InputMapping);
        }

        $VarMappings = $DenonAVRVar->GetVariableProfileMapping();
        $DenonAVRVar->SetInputSources(
            $ip,
            0,
            false,
            false,
            false,
            false,
            false,
            false
        );

        $InputVarMapping = $DenonAVRVar->GetInputVarMapping(0);
        $Inputs = $InputVarMapping['Inputs'];

        if ($this->debug) {

            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'VarMappings: ' . json_encode($VarMappings, JSON_THROW_ON_ERROR));
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'InputVarMapping: ' . json_encode(
                                                $InputVarMapping,
                                                JSON_THROW_ON_ERROR
                                            )
            );
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Inputs: ' . json_encode($Inputs, JSON_THROW_ON_ERROR));
        }

        try {
            $http = 'http://' . $ip . AVRs::getCapabilities($AVRType)['httpMainZone'];
            if ($this->debug) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'http (MainZone): ' . $http);
            }
            $xmlMainZone = @new SimpleXMLElement(file_get_contents($http));
            if ($xmlMainZone) {
                $DataMain = $this->MainZoneXml($xmlMainZone, $DataMain, $VarMappings, $Inputs);
            } else {
                exit('Datei ' . $xmlMainZone . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        try {
            $xmlNetAudioStatus = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_NetAudioStatusXml.xml'));
            if ($xmlNetAudioStatus) {
                $DataMain = $this->NetAudioStatusXml($xmlNetAudioStatus, $DataMain);
            } else {
                exit('Datei ' . $xmlNetAudioStatus . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        try {
            $xmlDeviceinfo = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_Deviceinfo.xml'));
            if ($xmlDeviceinfo) {
                $DataMain = $this->Deviceinfo($xmlDeviceinfo, $DataMain);
            } else {
                exit('Datei ' . $xmlDeviceinfo . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        // Zone 2

        $DataZ2 = [];

        try {
            $xml = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_MainZoneXml.xml?_=&ZoneName=ZONE2'));
            if ($xml) {
                $DataZ2 = $this->StateZone2($xml, $DataZ2, $InputMapping);
            } else {
                exit('Datei ' . $xml . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        // Zone 3

        $DataZ3 = [];

        try {
            $xml = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_MainZoneXml.xml?_=&ZoneName=ZONE3'));
            if ($xml) {
                $DataZ3 = $this->StateZone3($xml, $DataZ3, $InputMapping);
            } else {
                exit('Datei ' . $xml . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        //Model
        try {
            $xmlDeviceSearch = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formiPhoneAppDeviceSearch.xml'));
            if ($xmlDeviceSearch) {
                $DataMain = $this->DeviceSearch($xmlDeviceSearch, $DataMain);
                $DataZ2 = $this->DeviceSearch($xmlDeviceSearch, $DataZ2);
                $DataZ3 = $this->DeviceSearch($xmlDeviceSearch, $DataZ3);
            } else {
                exit('Datei ' . $xmlDeviceSearch . ' konnte nicht geöffnet werden.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo "bad xml";
        }

        $datasend = [
            'ResponseType' => 'HTTP',
            'Data'         => [
                'Mainzone' => $DataMain,
                'Zone2'    => $DataZ2,
                'Zone3'    => $DataZ3,
            ],
        ];

        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'DataSend: ' . json_encode($datasend, JSON_THROW_ON_ERROR));
        }

        return $datasend;
    }

    private function MainZoneXml(SimpleXMLElement $xml, $data, $VarMappings, $Inputs)
    {

        //Power
        $Element = $xml->xpath('.//Power');
        if ($Element) {
            $VarMapping = $VarMappings[DENON_API_Commands::PW];
            $SubCommand = strtoupper((string) $Element[0]->value);
            $SubCommand = str_replace(DENON_API_Commands::IS_OFF, DENON_API_Commands::PWSTANDBY, $SubCommand); //beim X1200 beobachtet
            $data[DENON_API_Commands::PW] = ['VarType' => $VarMapping['VarType'], 'Value' => $VarMapping['ValueMapping'][$SubCommand], 'Subcommand' => $SubCommand];
        }

        //Zone Power
        $Element = $xml->xpath('.//ZonePower');
        if ($Element) {
            $VarMapping = $VarMappings[DENON_API_Commands::ZM];
            $SubCommand = strtoupper((string) $Element[0]->value);
            $data[DENON_API_Commands::ZM] = ['VarType' => $VarMapping['VarType'], 'Value' => $VarMapping['ValueMapping'][$SubCommand], 'Subcommand' => $SubCommand];
        }

        //RenameZone
        $Element = $xml->xpath('.//RenameZone');
        if ($Element) {
            $data['MainZoneName'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => trim((string) $Element[0]->value), 'Subcommand' => 'MainZone Name'];
        }

        //InputFuncSelectMain
        $Element = $xml->xpath('.//InputFuncSelectMain');
        if ($Element) {
            $SubCommand = (string) $Element[0]->value;

            // first it is checked, if the source input is renamed
            foreach ($Inputs as $Input){
                if ($Input['RenameSource'] === str_replace(' ', '', $SubCommand)) {
                    $SubCommand = $Input['Source'];
                    break;
                }
            }

            // some values are unusual and have to be mapped
            if (array_key_exists($SubCommand, DENON_API_Commands::$SIMapping)) {
                $SubCommand = DENON_API_Commands::$SIMapping[$SubCommand];
            }

            $VarMapping = $VarMappings[DENON_API_Commands::SI];
            if ($this->debug) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, sprintf('VarMapping: %s, SubCommand: %s',
                                                                                           json_encode($VarMapping, JSON_THROW_ON_ERROR), $SubCommand));
            }

            $data[DENON_API_Commands::SI] = ['VarType' => $VarMapping['VarType'], 'Value' => $VarMapping['ValueMapping'][strtoupper($SubCommand)], 'Subcommand' => $SubCommand];
        }

        //NetFuncSelect
        /*
        $NetFuncSelect = $xml->xpath('.//NetFuncSelect');
        if ($NetFuncSelect)
        {
            $data['NetFuncSelect'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$NetFuncSelect[0]->value, 'Subcommand' => 'NetFuncSelect');
        }
        */

        //selectSurround
        /*
        $selectSurround = $xml->xpath('.//selectSurround');
        if ($selectSurround)
        {
            $data['MS'] =  array('VarType' => DENONIPSVarType::vtInteger, 'Value' => (string)$selectSurround[0]->value, 'Subcommand' => 'Surround Mode');
        }
        */

        //VolumeDisplay z.B. relative
        /*
        $VolumeDisplay = $xml->xpath('.//VolumeDisplay');
        if ($VolumeDisplay)
        {
            $data['VolumeDisplay'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$VolumeDisplay[0]->value, 'Subcommand' => 'VolumeDisplay');
        }
        */

        //MasterVolume
        $Element = $xml->xpath('.//MasterVolume');
        if ($Element) {
            $VarMapping = $VarMappings[DENON_API_Commands::MV];
            $Value = (float) $Element[0]->value;
            $SubCommand = array_search($Value, $VarMapping['ValueMapping'], true);
            $data[DENON_API_Commands::MV] = ['VarType' => $VarMapping['VarType'], 'Value' => $Value, 'Subcommand' => $SubCommand];
        }

        //Mute
        $Element = $xml->xpath('.//Mute');
        if ($Element) {
            $VarMapping = $VarMappings[DENON_API_Commands::MU];
            $SubCommand = strtoupper((string) $Element[0]->value);
            $data[DENON_API_Commands::MU] = ['VarType' => $VarMapping['VarType'], 'Value' => $VarMapping['ValueMapping'][$SubCommand], 'Subcommand' => $SubCommand];
        }

        //RemoteMaintenance
        /*
        $RemoteMaintenance = $xml->xpath('.//RemoteMaintenance');
        if ($RemoteMaintenance)
        {
            $RemoteMaintenanceMapping = array("ON" => true, "OFF" => false);
            foreach ($RemoteMaintenanceMapping as $Command => $RemoteMaintenanceValue)
            {
            if ($Command == (string)$RemoteMaintenance[0]->value)
                {
                $data['RemoteMaintenance'] =  array('VarType' => DENONIPSVarType::vtBoolean, 'Value' => $RemoteMaintenanceValue, 'Subcommand' => 'RemoteMaintenance');
                }
            }
        }
        */

        //GameSourceDisplay
        /*
        $GameSourceDisplay = $xml->xpath('.//GameSourceDisplay');
        if ($GameSourceDisplay)
        {
            $data['GameSourceDisplay'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$GameSourceDisplay[0]->value, 'Subcommand' => 'GameSourceDisplay');
        }
        */

        //LastfmDisplay
        /*
        $LastfmDisplay = $xml->xpath('.//LastfmDisplay');
        if ($LastfmDisplay)
        {
            $data['LastfmDisplay'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$LastfmDisplay[0]->value, 'Subcommand' => 'LastfmDisplay');
        }
        */

        //SubwooferDisplay
        /*
        $SubwooferDisplay = $xml->xpath('.//SubwooferDisplay');
        if ($SubwooferDisplay)
        {
            $data['SubwooferDisplay'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$SubwooferDisplay[0]->value, 'Subcommand' => 'SubwooferDisplay');
        }
        */

        //Zone2VolDisp
        /*
        $Zone2VolDisp = $xml->xpath('.//Zone2VolDisp');
        if ($Zone2VolDisp )
        {
            $data['Zone2VolDisp'] =  array('VarType' => DENONIPSVarType::vtString, 'Value' => (string)$Zone2VolDisp[0]->value, 'Subcommand' => 'Zone2VolDisp');
        }
        */

        return $data;
    }

    private function NetAudioStatusXml(SimpleXMLElement $xml, $data)
    {
        //Model
        $Element = $xml->xpath('.//szLine');
        if ($Element) {
            $data['ModelDisplay'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => (string) $Element[0]->value, 'Subcommand' => 'ModelDisplay'];
        }

        return $data;
    }

    private function Deviceinfo(SimpleXMLElement $xml, $data): array
    {
        //ModelName
        $ModelName = $xml->xpath('.//ModelName');
        if ($ModelName) {
            $data['ModelName'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => trim((string) $ModelName[0]->value), 'Subcommand' => 'ModelName'];
        }

        return $data;
    }

    private function DeviceSearch(SimpleXMLElement $xml, $data)
    {
        //Model
        $Model = $xml->xpath('.//Model');
        if ($Model) {
            $ModelValue = str_replace(' ', '', trim((string) $Model[0]->value));
            $data['Model'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => $ModelValue, 'Subcommand' => 'Model'];
        }

        return $data;
    }

    private function StateZone2(SimpleXMLElement $xml, $data, $InputMapping)
    {
        //Power
        $AVRPower = $xml->xpath('.//Power');
        if ($AVRPower) {
            $AVRPowerMapping = ['ON' => true, 'STANDBY' => false];
            foreach ($AVRPowerMapping as $Command => $AVRPowerValue) {
                if ($Command === (string) $AVRPower[0]->value) {
                    $data[DENON_API_Commands::PW] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $AVRPowerValue, 'Subcommand' => $Command];
                }
            }
        }

        //Zone Power
        $ZonePower = $xml->xpath('.//ZonePower');
        if ($ZonePower) {
            $ZonePowerMapping = ['ON' => true, 'OFF' => false];
            foreach ($ZonePowerMapping as $Command => $ZonePowerValue) {
                if ($Command === (string) $ZonePower[0]->value) {
                    $data[DENON_API_Commands::Z2POWER] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $ZonePowerValue, 'Subcommand' => $Command];
                }
            }
        }

        //Zone Name
        $RenameZone = $xml->xpath('.//RenameZone');
        if ($RenameZone) {
            $data['Zone2Name'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => (string) $RenameZone[0]->value, 'Subcommand' => 'Zone2 Name'];
        }

        //InputFuncSelect
        $InputFuncSelect = $xml->xpath('.//InputFuncSelect');
        if ($InputFuncSelect) {
            foreach ($InputMapping as $Command => $InputSourceValue) {
                if ($Command === (string) $InputFuncSelect[0]->value) {
                    $data[DENON_API_Commands::Z2INPUT] = ['VarType' => DENONIPSVarType::vtInteger, 'Value' => $InputSourceValue, 'Subcommand' => $Command];
                }
            }
        }

        //MasterVolume
        $MasterVolume = $xml->xpath('.//MasterVolume');
        if ($MasterVolume) {
            $data[DENON_API_Commands::Z2VOL] = ['VarType' => DENONIPSVarType::vtFloat, 'Value' => (float) $MasterVolume[0]->value, 'Subcommand' => (float) $MasterVolume[0]->value];
        }

        //Mute
        $Mute = $xml->xpath('.//Mute');
        if ($Mute) {
            $MuteMapping = ['on' => true, 'off' => false];
            foreach ($MuteMapping as $Command => $MuteValue) {
                if ($Command === (string) $Mute[0]->value) {
                    $data[DENON_API_Commands::Z2MU] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $MuteValue, 'Subcommand' => $Command];
                }
            }
        }

        return $data;
    }

    private function StateZone3(SimpleXMLElement $xml, $data, $InputMapping)
    {
        //Power
        $AVRPower = $xml->xpath('.//Power');
        if ($AVRPower) {
            $AVRPowerMapping = ['ON' => true, 'STANDBY' => false];
            foreach ($AVRPowerMapping as $Command => $AVRPowerValue) {
                if ($Command === (string) $AVRPower[0]->value) {
                    $data[DENON_API_Commands::PW] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $AVRPowerValue, 'Subcommand' => $Command];
                }
            }
        }

        //Zone Power
        $ZonePower = $xml->xpath('.//ZonePower');
        if ($ZonePower) {
            $ZonePowerMapping = ['ON' => true, 'OFF' => false];
            foreach ($ZonePowerMapping as $Command => $ZonePowerValue) {
                if ($Command === (string) $ZonePower[0]->value) {
                    $data[DENON_API_Commands::Z3POWER] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $ZonePowerValue, 'Subcommand' => $Command];
                }
            }
        }

        //Zone Name
        $RenameZone = $xml->xpath('.//RenameZone');
        if ($RenameZone) {
            $data['Zone3Name'] = ['VarType' => DENONIPSVarType::vtString, 'Value' => (string) $RenameZone[0]->value, 'Subcommand' => 'Zone3 Name'];
        }

        //InputFuncSelect
        $InputFuncSelect = $xml->xpath('.//InputFuncSelect');
        if ($InputFuncSelect) {
            foreach ($InputMapping as $Command => $InputSourceValue) {
                if ($Command === (string) $InputFuncSelect[0]->value) {
                    $data[DENON_API_Commands::Z3INPUT] = ['VarType' => DENONIPSVarType::vtInteger, 'Value' => $InputSourceValue, 'Subcommand' => $Command];
                }
            }
        }

        //MasterVolume
        $MasterVolume = $xml->xpath('.//MasterVolume');
        if ($MasterVolume) {
            $data[DENON_API_Commands::Z3VOL] = ['VarType' => DENONIPSVarType::vtFloat, 'Value' => (float) $MasterVolume[0]->value, 'Subcommand' => (float) $MasterVolume[0]->value];
        }

        //Mute
        $Mute = $xml->xpath('.//Mute');
        if ($Mute) {
            $MuteMapping = ['on' => true, 'off' => false];
            foreach ($MuteMapping as $Command => $MuteValue) {
                if ($Command === (string) $Mute[0]->value) {
                    $data[DENON_API_Commands::Z3MU] = ['VarType' => DENONIPSVarType::vtBoolean, 'Value' => $MuteValue, 'Subcommand' => $Command];
                }
            }
        }

        return $data;
    }
}

class DENON_HTTP_Interface extends stdClass
{
    public const string NoHTTPInterface = '';
    public const string MainForm_old    = '/goform/formMainZone_MainZoneXml.xml';
    public const string MainForm     = '/goform/formMainZone_MainZoneXmlStatus.xml';
}

class DENON_API_Commands extends stdClass
{
    //MAIN Zone
    public const string PW = 'PW'; // Power
    public const string MV = 'MV'; // Master Volume
    public const string BL = 'BL'; // Balance
    //CV
    public const string CVFL  = 'CVFL'; // Channel Volume Front Left
    public const string CVFR  = 'CVFR'; // Channel Volume Front Right
    public const string CVC   = 'CVC'; // Channel Volume Center
    public const string CVSW  = 'CVSW'; // Channel Volume Subwoofer
    public const string CVSW2 = 'CVSW2'; // Channel Volume Subwoofer2
    public const string CVSW3 = 'CVSW3'; // Channel Volume Subwoofer3
    public const string CVSW4 = 'CVSW4'; // Channel Volume Subwoofer4
    public const string CVSL  = 'CVSL'; // Channel Volume Surround Left
    public const string CVSR  = 'CVSR'; // Channel Volume Surround Right
    public const string CVSBL = 'CVSBL'; // Channel Volume Surround Back Left
    public const string CVSBR = 'CVSBR'; // Channel Volume Surround Back Right
    public const string CVSB  = 'CVSB'; // Channel Volume Surround Back
    public const string CVFHL = 'CVFHL'; // Channel Volume Front Height Left
    public const string CVFHR = 'CVFHR'; // Channel Volume Front Height Right
    public const string CVFWL = 'CVFWL'; // Channel Volume Front Wide Left
    public const string CVFWR = 'CVFWR'; // Channel Volume Front Wide Right
    public const string MU    = 'MU'; // Volume Mute
    public const string SI    = 'SI'; // Select Input
    public const string ZM    = 'ZM'; // Main Zone
    public const string SD    = 'SD'; // Select Auto/HDMI/Digital/Analog
    public const string DC    = 'DC'; // Digital Input Mode Select Auto/PCM/DTS
    public const string SV    = 'SV'; // Video Select
    public const string SLP   = 'SLP'; // Main Zone Sleep Timer
    public const string MS    = 'MS'; // Select Surround Mode
    public const string SP    = 'SP'; // Speaker Preset
    public const string MN    = 'MN'; // System
    public const string MSQUICK = 'MSQUICK'; // Quick Select Mode Select (Denon)
    public const string MSQUICKMEMORY = 'MEMORY'; // Quick Select Mode Memory
    public const string MSSMART       = 'MSSMART'; // Smart Select Mode Select (Marantz)

    //MU
    public const string MUON  = 'ON'; // Volume Mute ON
    public const string MUOFF = 'OFF'; // Volume Mute Off

    //VS
    public const string VS    = 'VS'; // Video Setting
    public const string VSASP = 'VSASP'; // ASP
    public const string VSSC  = 'VSSC'; // Set Resolution

    public const string VSSCH   = 'VSSCH'; // Set Resolution HDMI
    public const string VSAUDIO = 'VSAUDIO'; // Set HDMI Audio Output
    public const string VSMONI  = 'VSMONI'; // Set HDMI Monitor
    public const string VSVPM   = 'VSVPM'; // Set Video Processing Mode
    public const string VSVST   = 'VSVST'; // Set Vertical Stretch
    //PS
    public const string PS         = 'PS'; // Parameter Setting
    public const string PSATT      = 'PSATT'; // SW ATT
    public const string PSTONECTRL = 'PSTONE_CTRL'; // Tone Control !da Ident nur Buchstaben und Zahlen enthalten darf, wurde das Blank ersetzt
    public const string PSSB       = 'PSSB'; // Surround Back SP Mode
    public const string PSCINEMAEQ = 'PSCINEMA_EQ'; // Cinema EQ
    public const string PSHTEQ     = 'PSHT_EQ'; // Cinema EQ
    public const string PSMODE     = 'PSMODE'; // Mode Music
    public const string PSDOLVOL   = 'PSDOLVOL'; // Dolby Volume direct change
    public const string PSVOLLEV   = 'PSVOLLEV'; // Dolby Volume Leveler direct change
    public const string PSVOLMOD   = 'PSVOLMOD'; // Dolby Volume Modeler direct change
    public const string PSFH       = 'PSFH'; // FRONT HEIGHT
    public const string PSPHG      = 'PSPHG'; // PL2z HEIGHT GAIN direct change
    public const string PSSP       = 'PSSP'; // Speaker Output set
    public const string PSREFLEV   = 'PSREFLEV'; // Dynamic EQ Reference Level
    public const string PSMULTEQ   = 'PSMULTEQ'; // MultEQ XT 32 mode direct change
    public const string PSDYNEQ  = 'PSDYNEQ'; // Dynamic EQ
    public const string PSLFC    = 'PSLFC'; // Audyssey LFC
    public const string PSDYNVOL = 'PSDYNVOL'; // Dynamic Volume
    public const string PSDSX    = 'PSDSX'; // Audyssey DSX Change
    public const string PSSTW    = 'PSSTW'; // STAGE WIDTH
    public const string PSCNTAMT = 'PSCNTAMT'; // Audyssey Containment Amount
    public const string PSSTH    = 'PSSTH'; // STAGE HEIGHT
    public const string PSBAS    = 'PSBAS'; // BASS
    public const string PSTRE    = 'PSTRE'; // TREBLE
    public const string PSLOM    = 'PSLOM'; // Loudness Management
    public const string PSDRC    = 'PSDRC'; // DRC direct change
    public const string PSMDAX   = 'PSMDAX'; // M-DAX
    public const string PSDCO    = 'PSDCO'; // D.COMP direct change
    public const string PSCLV    = 'PSCLV'; // Center Level Volume
    public const string PSLFE    = 'PSLFE'; // LFE
    public const string PSLFL    = 'PSLFL'; // LFF
    public const string PSEFF  = 'PSEFF'; // EFFECT direct change	Level
    public const string PSDELAY = 'PSDELAY'; // Audio DELAY
    public const string PSDEL   = 'PSDEL'; // DELAY
    public const string PSAFD   = 'PSAFD'; // Auto Flag Detect Mode
    public const string PSPAN   = 'PSPAN'; // PANORAMA
    public const string PSDIM   = 'PSDIM'; // DIMENSION
    public const string PSCEN   = 'PSCEN'; // CENTER WIDTH
    public const string PSCEI   = 'PSCEI'; // CENTER IMAGE
    public const string PSCEG   = 'PSCEG'; // CENTER GAIN
    public const string PSDIC   = 'PSDIC'; // DIALOG CONTROL
    public const string PSRSTR  = 'PSRSTR'; //Audio Restorer
    public const string PSFRONT = 'PSFRONT'; //Front Speaker
    public const string PSRSZ   = 'PSRSZ'; //Room Size
    public const string PSSWR   = 'PSSWR'; //Subwoofer

    public const string BTTX = 'BTTX'; //Bluetooth Transmitter
    public const string SPPR = 'SPPR'; //Speaker Preset

    //PV
    public const string PV        = 'PV'; // Picture Mode
    public const string PVPICT    = 'PVPICT'; //Picture Mode beim Senden
    public const string PVPICTOFF = 'OFF'; // Picture Mode Off
    public const string PVPICTSTD = 'STD'; // Picture Mode Standard
    public const string PVPICTMOV = 'MOVIE'; // Picture Mode Movie
    public const string PVPICTVVD = 'VVD'; // Picture Mode Vivid
    public const string PVPICTSTM = 'STM'; // Picture Mode Stream
    public const string PVPICTCTM = 'CTM'; // Picture Mode Custom
    public const string PVPICTDAY = 'DAY'; // Picture Mode ISF Day
    public const string PVPICTNGT = 'NGT'; // Picture Mode ISF Night

    public const string PVCN  = 'PVCN'; // Contrast
    public const string PVBR  = 'PVBR'; // Brightness
    public const string PVST  = 'PVST'; // Saturation
    public const string PVCM  = 'PVCM'; // Chroma
    public const string PVHUE = 'PVHUE'; // Hue
    public const string PVENH = 'PVENH'; // Enhancer

    public const string PVDNR    = 'PVDNR'; // Digital Noise Reduction direct change
    public const string PVDNROFF = ' OFF'; // Digital Noise Reduction Off
    public const string PVDNRLOW = ' LOW'; // Digital Noise Reduction Low
    public const string PVDNRMID = ' MID'; // Digital Noise Reduction Middle
    public const string PVDNRHI  = ' HI'; // Digital Noise Reduction High

    // Speaker Setup
    public const string SSSPC    = 'SSSPC';
    public const string SSSPCCEN = 'SSSPCCEN'; // Setup Center
    public const string SSSPCFRO = 'SSSPCFRO'; // Setup Front
    public const string SSSPCSWF = 'SSSPCSWF'; // Setup Subwoofer
    public const string NON      = ' NON'; // none Subwoofer
    public const string SPONE    = ' 1SP'; // Subwoofer 1
    public const string SPTWO    = ' 2SP'; // Subwoofer 2
    public const string SMA      = ' SMA'; // small
    public const string LAR      = ' LAR'; // large

    public const string SR = ' ?'; //Status Request

    //Zone 2
    public const string Z2       = 'Z2'; // Zone 2
    public const string Z2ON     = 'ON'; // Zone 2 On
    public const string Z2OFF    = 'OFF'; // Zone 2 Off
    public const string Z2POWER  = 'Z2POWER'; // Zone 2 Power Z2 beim Senden
    public const string Z2INPUT  = 'Z2INPUT'; // Zone 2 Input Z2 beim Senden
    public const string Z2VOL    = 'Z2VOL'; // Zone 2 Volume Z2 beim Senden
    public const string Z2MU     = 'Z2MU'; // Zone 2 Mute
    public const string Z2CS     = 'Z2CS'; // Zone 2 Channel Setting
    public const string Z2CSST   = 'ST'; // Zone 2 Channel Setting Stereo
    public const string Z2CSMONO = 'MONO'; // Zone 2 Channel Setting Mono
    public const string Z2CVFL   = 'Z2CVFL'; // Zone 2 Channel Volume FL
    public const string Z2CVFR   = 'Z2CVFR'; // Zone 2 Channel Volume FR
    public const string Z2HPF    = 'Z2HPF'; // Zone 2 HPF
    public const string Z2HDA    = 'Z2HDA'; // (nur) Zone 2 HDA
    public const string Z2HDATHR = ' THR'; // (nur) Zone 2 HDA
    public const string Z2HDAPCM = ' PCM'; // (nur) Zone 2 HDA
    public const string Z2PSBAS  = 'Z2PSBAS'; // Zone 2 Parameter Bass
    public const string Z2PSTRE  = 'Z2PSTRE'; // Zone 2 Parameter Treble
    public const string Z2SLP    = 'Z2SLP'; // Zone 2 Sleep Timer
    public const string Z2QUICK  = 'Z2QUICK'; // Zone 2 Quick
    public const string Z2SMART  = 'Z2SMART'; // Zone 2 Smart

    //Zone 3
    public const string Z3       = 'Z3'; // Zone 3
    public const string Z3ON     = 'ON'; // Zone 3 On
    public const string Z3OFF    = 'OFF'; // Zone 3 Off
    public const string Z3POWER  = 'Z3POWER'; // Zone 3 Power Z3 beim Senden
    public const string Z3INPUT  = 'Z3INPUT'; // Zone 3 Input Z3 beim Senden
    public const string Z3VOL    = 'Z3VOL'; // Zone 3 Volume Z3 beim Senden
    public const string Z3MU     = 'Z3MU'; // Zone 3 Mute
    public const string Z3CS     = 'Z3CS'; // Zone 3 Channel Setting
    public const string Z3CSST   = 'ST'; // Zone 3 Channel Setting Stereo
    public const string Z3CSMONO = 'MONO'; // Zone 3 Channel Setting Mono
    public const string Z3CVFL   = 'Z3CVFL'; // Zone 3 Channel Volume FL
    public const string Z3CVFR   = 'Z3CVFR'; // Zone 3 Channel Volume FR
    public const string Z3HPF    = 'Z3HPF'; // Zone 3 HPF
    public const string Z3PSBAS  = 'Z3PSBAS'; // Zone 3 Parameter Bass
    public const string Z3PSTRE  = 'Z3PSTRE'; // Zone 3 Parameter Treble
    public const string Z3SLP    = 'Z3SLP'; // Zone 3 Sleep Timer
    public const string Z3QUICK  = 'Z3QUICK'; // Zone 3 Quick
    public const string Z3SMART  = 'Z3SMART'; // Zone 3 Smart

    public const string NS = 'NS'; // Network Audio
    public const string SY = 'SY'; // Remote Lock
    public const string TR = 'TR'; // Trigger
    public const string UG = 'UG'; // Upgrade ID Display

    //Analog Tuner
    public const string TF = 'TF'; // Tuner Frequency

    public const string TPAN     = 'TPAN'; // Tuner Preset (analog)
    public const string TPANUP   = 'UP'; //TUNER PRESET CH UP
    public const string TPANDOWN = 'DOWN'; //TUNER PRESET CH DOWN

    public const string TMAN_BAND = 'TMAN'; // Tuner Mode (analog) Band
    public const string TMANAM    = 'AM'; // Tuner Band AM (Band)
    public const string TMANFM    = 'FM'; // Tuner Band FM (Band)
    public const string TMANDAB   = 'DAB'; // Tuner Band DAB (Band)

    public const string TMAN_MODE  = 'TM'; // Tuner Mode (analog) Mode
    public const string TMANAUTO   = 'ANAUTO'; // Tuner Mode Auto
    public const string TMANMANUAL = 'ANMANUAL'; // Tuner Mode Manual

    //Network Audio
    public const string NSB = 'NSB'; //Direct Preset CH Play 00-55,00=A1,01=A2,B1=08,G8=55

    // Display Network Audio Navigation
    public const string NSUP        = '90'; // Network Audio Cursor Up Control
    public const string NSDOWN      = '91'; // Network Audio Cursor Down Control
    public const string NSLEFT      = '92'; // Network Audio Cursor Left Control
    public const string NSRIGHT     = '93'; // Network Audio Cursor Right Control
    public const string NSENTER     = '94'; // Network Audio Cursor Enter Control
    public const string NSPLAY      = '9A'; // Network Audio Play
    public const string NSPAUSE     = '9B'; // Network Audio Pause
    public const string NSSTOP      = '9C'; // Network Audio Stop
    public const string NSSKIPPLUS  = '9D'; // Network Audio Skip +
    public const string NSSKIPMINUS = '9E'; // Network Audio Skip -
    public const string NSREPEATONE = '9H'; // Network Audio Repeat One
    public const string NSREPEATALL = '9I'; // Network Audio Repeat All
    public const string NSREPEATOFF = '9J'; // Network Audio Repeat Off
    public const string NSRANDOMON  = '9K'; // Network Audio Random On
    public const string NSRANDOMOFF = '9M'; // Network Audio Random Off
    public const string NSTOGGLE    = '9W'; // Network Audio Toggle Switch
    public const string NSPAGENEXT  = '9X'; // Network Audio Page Next
    public const string NSPAGEPREV  = '9Y'; // Network Audio Page Previous

    //Display
    public const string DISPLAY = 'Display'; // Display zur Anzeige
    public const string NSA     = 'NSA'; // Network Audio Extended
    public const string NSA0    = 'NSA0'; // Network Audio Extended Line 0
    public const string NSA1    = 'NSA1'; // Network Audio Extended Line 1
    public const string NSA2    = 'NSA2'; // Network Audio Extended Line 2
    public const string NSA3    = 'NSA3'; // Network Audio Extended Line 3
    public const string NSA4    = 'NSA4'; // Network Audio Extended Line 4
    public const string NSA5    = 'NSA5'; // Network Audio Extended Line 5
    public const string NSA6    = 'NSA6'; // Network Audio Extended Line 6
    public const string NSA7    = 'NSA7'; // Network Audio Extended Line 7
    public const string NSA8    = 'NSA8'; // Network Audio Extended Line 8

    public const string NSE  = 'NSE'; // Network Audio Onscreen Display Information
    public const string NSE0 = 'NSE0'; // Network Audio Onscreen Display Information Line 0
    public const string NSE1 = 'NSE1'; // Network Audio Onscreen Display Information Line 1
    public const string NSE2 = 'NSE2'; // Network Audio Onscreen Display Information Line 2
    public const string NSE3 = 'NSE3'; // Network Audio Onscreen Display Information Line 3
    public const string NSE4 = 'NSE4'; // Network Audio Onscreen Display Information Line 4
    public const string NSE5 = 'NSE5'; // Network Audio Onscreen Display Information Line 5
    public const string NSE6 = 'NSE6'; // Network Audio Onscreen Display Information Line 6
    public const string NSE7 = 'NSE7'; // Network Audio Onscreen Display Information Line 7
    public const string NSE8 = 'NSE8'; // Network Audio Onscreen Display Information Line 8
    public const string NSE9 = 'NSE9'; // Network Audio Onscreen Display Information Line 9

    //SUB Commands

    //PW
    public const string PWON      = 'ON'; // Power On
    public const string PWSTANDBY = 'STANDBY'; // Power Standby
    public const string PWOFF     = 'OFF'; // Power OFF - beim X1200 im XML beobachtet

    //MV
    public const string MVUP   = 'UP'; // Master Volume Up
    public const string MVDOWN = 'DOWN'; // Master Volume Down

    //SI + SV
    public const string IS_PHONO = 'PHONO'; // Select Input Source Phono
    public const string IS_CD    = 'CD'; // Select Input Source CD
    public const string IS_TUNER = 'TUNER'; // Select Input Source Tuner
    public const string IS_FM    = 'FM'; // Select Input Source FM
    public const string IS_DAB   = 'DAB'; // Select Input Source DAB
    public const string IS_DVD   = 'DVD'; // Select Input Source DVD
    public const string IS_HDP   = 'HDP'; // Select Input Source HDP
    public const string IS_BD    = 'BD'; // Select Input Source BD
    public const string IS_BT    = 'BT'; // Select Input Source Blutooth
    public const string IS_MPLAY = 'MPLAY'; // Select Input Source Mediaplayer
    public const string IS_TV    = 'TV'; // Select Input Source TV
    public const string IS_TV_CBL = 'TV/CBL'; // Select Input Source TV/CBL
    public const string IS_SAT_CBL = 'SAT/CBL'; // Select Input Source Sat/CBL
    public const string IS_SAT     = 'SAT'; // Select Input Source Sat
    public const string IS_VCR     = 'VCR'; // Select Input Source VCR
    public const string IS_DVR     = 'DVR'; // Select Input Source DVR
    public const string IS_GAME    = 'GAME'; // Select Input Source Game
    public const string IS_GAME1   = 'GAME1'; // Select Input Source Game1
    public const string IS_GAME2   = 'GAME2'; // Select Input Source Game2
    public const string IS_8K      = '8K'; // Select Input Source 8K
    public const string IS_AUX     = 'AUX'; // Select Input Source AUX
    public const string IS_AUX1    = 'AUX1'; // Select Input Source AUX1
    public const string IS_AUX2    = 'AUX2'; // Select Input Source AUX2
    public const string IS_VAUX  = 'V.AUX'; // Select Input Source V.AUX
    public const string IS_DOCK  = 'DOCK'; // Select Input Source Dock
    public const string IS_IPOD  = 'IPOD'; // Select Input Source iPOD
    public const string IS_USB   = 'USB'; // Select Input Source USB
    public const string IS_AUXA  = 'AUXA'; // Select Input Source AUXA
    public const string IS_AUXB  = 'AUXB'; // Select Input Source AUXB
    public const string IS_AUXC = 'AUXC'; // Select Input Source AUXC
    public const string IS_AUXD = 'AUXD'; // Select Input Source AUXD
    public const string IS_NETUSB = 'NET/USB'; // Select Input Source NET/USB
    public const string IS_NET    = 'NET'; // Select Input Source NET
    public const string IS_LASTFM = 'LASTFM'; // Select Input Source LastFM
    public const string IS_FLICKR = 'FLICKR'; // Select Input Source Flickr
    public const string IS_FAVORITES = 'FAVORITES'; // Select Input Source Favorites
    public const string IS_IRADIO    = 'IRADIO'; // Select Input Source Internet Radio
    public const string IS_SERVER    = 'SERVER'; // Select Input Source Server
    public const string IS_NAPSTER   = 'NAPSTER'; // Select Input Source Napster
    public const string IS_USB_IPOD  = 'USB/IPOD'; // Select Input USB/IPOD
    public const string IS_MXPORT    = 'MXPORT'; // Select Input MXPORT
    public const string IS_SOURCE    = 'SOURCE'; // Select Input Source of Main Zone
    public const string IS_ON        = 'ON'; // Select Input Source On
    public const string IS_OFF       = 'OFF'; // Select Input Source Off

    public static array $SIMapping        = ['CBL/SAT'      => self::IS_SAT_CBL,
                                             'MediaPlayer'  => self::IS_MPLAY,
                                             'Media Player' => self::IS_MPLAY,
                                             'Media Server' => self::IS_SERVER,
                                             'iPod/USB'     => self::IS_USB_IPOD,
                                             'M-XPORT'      => self::IS_MXPORT,
                                             'TVAUDIO'      => self::IS_TV,
                                             'TV AUDIO'     => self::IS_TV,
                                             'Bluetooth'    => self::IS_BT,
                                             'Blu-ray'      => self::IS_BD,
                                             'Online Music' => self::IS_NET,
                                             'NETWORK'                                 => self::IS_NET,
                                             'Internet Radio'                          => self::IS_IRADIO,
                                             'Last. fm'                                => self::IS_LASTFM,
                                             'FM'                                      => self::IS_TUNER,
    ];

    public static array $SI_InputSettings = [
        self::IS_PHONO,
        self::IS_CD,
        self::IS_TUNER,
        self::IS_DVD,
        self::IS_HDP,
        self::IS_BD,
        self::IS_BT,
        self::IS_MPLAY,
        self::IS_TV,
        self::IS_TV_CBL,
        self::IS_SAT_CBL,
        self::IS_SAT,
        self::IS_VCR,
        self::IS_DVR,
        self::IS_GAME,
        self::IS_GAME2,
        self::IS_AUX,
        self::IS_AUX1,
        self::IS_AUX2,
        self::IS_AUXA,
        self::IS_AUXB,
        self::IS_AUXC,
        self::IS_AUXD,
        self::IS_NETUSB,
        self::IS_VAUX,
        self::IS_DOCK,
        self::IS_IPOD,
        self::IS_NETUSB,
        self::IS_NET,
        self::IS_LASTFM,
        self::IS_FLICKR,
        self::IS_FAVORITES,
        self::IS_IRADIO,
        self::IS_SERVER,
        self::IS_NAPSTER,
        self::IS_USB,
        self::IS_USB_IPOD,
        self::IS_MXPORT,
        self::IS_SOURCE,
    ];

    //ZM Mainzone
    public const string ZMOFF = 'OFF'; // Power Off
    public const string ZMON  = 'ON'; // Power On

    //SD
    public const string SDAUTO    = 'AUTO'; // Auto Mode
    public const string SDHDMI    = 'HDMI'; // HDMI Mode
    public const string SDDIGITAL = 'DIGITAL'; // Digital Mode
    public const string SDANALOG  = 'ANALOG'; // Analog Mode
    public const string SDEXTIN   = 'EXT.IN'; // Ext.In Mode
    public const string SD71IN    = '7.1IN'; // 7.1 In Mode
    public const string SDNO      = 'NO'; // no Input
    public const string SDARC     = 'ARC'; // ARC (nur im Event)
    public const string SDEARC    = 'EARC'; // EARC (nur im Event)

    //DC Digital Input
    public const string DCAUTO = 'AUTO'; // Auto Mode
    public const string DCPCM  = 'PCM'; // PCM Mode
    public const string DCDTS  = 'DTS'; // DTS Mode

    //MS Surround Mode
    public const string MSDIRECT       = 'DIRECT'; // Direct Mode
    public const string MSPUREDIRECT   = 'PURE DIRECT'; // Pure Direct Mode
    public const string MSSTEREO       = 'STEREO'; // Stereo Mode
    public const string MSSTANDARD     = 'STANDARD'; // Standard Mode
    public const string MSDOLBYDIGITAL = 'DOLBY DIGITAL'; // Dolby Digital Mode
    public const string MSDTSSURROUND  = 'DTS SURROUND'; // DTS Surround Mode
    public const string MSMCHSTEREO    = 'MCH STEREO'; // Multi Channel Stereo Mode
    public const string MS7CHSTEREO    = '7CH STEREO'; // 7 Channel Stereo Mode
    public const string MSWIDESCREEN   = 'WIDE SCREEN'; // Wide Screen Mode
    public const string MSSUPERSTADIUM = 'SUPER STADIUM'; // Super Stadium Mode
    public const string MSROCKARENA    = 'ROCK ARENA'; // Rock Arena Mode
    public const string MSJAZZCLUB     = 'JAZZ CLUB'; // Jazz Club Mode
    public const string MSCLASSICCONCERT = 'CLASSIC CONCERT'; // Classic Concert Mode
    public const string MSMONOMOVIE      = 'MONO MOVIE'; // Mono Movie Mode
    public const string MSMATRIX         = 'MATRIX'; // Matrix Mode
    public const string MSVIDEOGAME      = 'VIDEO GAME'; // Video Game Mode
    public const string MSVIRTUAL        = 'VIRTUAL'; // Virtual Mode
    public const string MSMOVIE          = 'MOVIE'; // Movie
    public const string MSMUSIC          = 'MUSIC'; // Music
    public const string MSGAME           = 'GAME'; // Game
    public const string MSAUTO           = 'AUTO'; // Auto
    public const string MSNEURAL         = 'NEURAL'; // Neural
    public const string MSAURO3D         = 'AURO3D'; //Auro 3D
 //   public const AURO3D = 'AURO3D'; //Auro 3D
    public const string MSAURO2DSURR = 'AURO2DSURR'; //Auro 2D

    public const string MSLEFT  = 'LEFT'; // Change to previous Surround Mode
    public const string MSRIGHT = 'RIGHT'; // Change to next Surround Mode
    //Quick Select Mode
    public const string MSQUICK0 = '0'; // Quick Select 0 Mode Select
    public const string MSQUICK1 = '1'; // Quick Select 1 Mode Select
    public const string MSQUICK2 = '2'; // Quick Select 2 Mode Select
    public const string MSQUICK3 = '3'; // Quick Select 3 Mode Select
    public const string MSQUICK4 = '4'; // Quick Select 4 Mode Select
    public const string MSQUICK5 = '5'; // Quick Select 5 Mode Select

    //MSQUICKMEMORY
    public const string MSQUICK1MEMORY = '1 MEMORY'; // Quick Select 1 Mode Memory
    public const string MSQUICK2MEMORY = '2 MEMORY'; // Quick Select 2 Mode Memory
    public const string MSQUICK3MEMORY = '3 MEMORY'; // Quick Select 3 Mode Memory
    public const string MSQUICK4MEMORY = '4 MEMORY'; // Quick Select 4 Mode Memory
    public const string MSQUICK5MEMORY = '5 MEMORY'; // Quick Select 5 Mode Memory
    public const string MSQUICKSTATE   = 'QUICK ?'; // QUICK ? Return MSQUICK Status

    //Smart Select Mode
    public const string MSSMART0 = '0'; // Smart Select 0 Mode Select
    public const string MSSMART1 = '1'; // Smart Select 1 Mode Select
    public const string MSSMART2 = '2'; // Smart Select 2 Mode Select
    public const string MSSMART3 = '3'; // Smart Select 3 Mode Select
    public const string MSSMART4 = '4'; // Smart Select 4 Mode Select
    public const string MSSMART5 = '5'; // Smart Select 5 Mode Select

    //VS
    //VSMONI Set HDMI Monitor
    public const string VSMONIAUTO = 'AUTO'; // 1
    public const string VSMONI1    = '1'; // 1
    public const string VSMONI2    = '2'; // 2

    //VSASP
    public const string ASPNRM = 'NRM'; // Set Normal Mode
    public const string ASPFUL = 'FUL'; // Set Full Mode
    public const string ASP    = ' ?'; // ASP? Return VSASP Status

    //VSSC Set Resolution
    public const string SC48P   = '48P'; // Set Resolution to 480p/576p
    public const string SC10I   = '10I'; // Set Resolution to 1080i
    public const string SC72P   = '72P'; // Set Resolution to 720p
    public const string SC10P   = '10P'; // Set Resolution to 1080p
    public const string SC10P24 = '10P24'; // Set Resolution to 1080p:24Hz
    public const string SC4K    = '4K'; // Set Resolution to 4K
    public const string SC4KF   = '4KF'; // Set Resolution to 4K (60/50)
    public const string SC8K    = '8K'; // Set Resolution to 8K
    public const string SCAUTO  = 'AUTO'; // Set Resolution to Auto
    public const string SC      = ' ?'; // SC? Return VSSC Status

    //VSSCH Set Resolution HDMI
    public const string SCH48P   = '48P'; // Set Resolution to 480p/576p HDMI
    public const string SCH10I   = '10I'; // Set Resolution to 1080i HDMI
    public const string SCH72P   = '72P'; // Set Resolution to 720p HDMI
    public const string SCH10P   = '10P'; // Set Resolution to 1080p HDMI
    public const string SCH10P24 = '10P24'; // Set Resolution to 1080p:24Hz HDMI
    public const string SCH4K    = '4K'; // Set Resolution to 4K
    public const string SCH4KF   = '4KF'; // Set Resolution to 4K (60/50)
    public const string SCH8K    = '8K'; // Set Resolution to 8K
    public const string SCHAUTO  = 'AUTO'; // Set HDMI Upcaler to Auto
    public const string SCHOFF   = 'OFF'; // Set HDMI Upscale to Off
    public const string SCH      = ' ?'; // SCH? Return VSSCH Status(HDMI)

    //VSAUDIO Set HDMI Audio Output
    public const string AUDIOAMP = ' AMP'; // Set HDMI Audio Output to AMP
    public const string AUDIOTV  = ' TV'; // Set HDMI Audio Output to TV
    public const string AUDIO    = ' ?'; // AUDIO? Return VSAUDIO Status

    //VSVPM Set Video Processing Mode
    public const string VPMAUTO = 'AUTO'; // Set Video Processing Mode to Auto
    public const string VPGAME  = 'GAME'; // Set Video Processing Mode to Game
    public const string VPMOVI  = 'MOVI'; // Set Video Processing Mode to Movie
    public const string VPMBYP  = 'MBYP'; // Set Video Processing Mode to Bypass
    public const string VPM     = ' ?'; // VPM? Return VSVPM Status

    //VSVST Set Vertical Stretch
    public const string VSTON  = ' ON'; // Set Vertical Stretch On
    public const string VSTOFF = ' OFF'; // Set Vertical Stretch Off
    public const string VST    = ' ?'; // VST? Return VSVST Status

    //PS Parameter
    //PSTONE Tone Control
    public const string TONECTRL        = 'PSTONE CTRL'; // Tone Control On
    public const string PSTONECTRLON    = ' ON'; // Tone Control On
    public const string PSTONECTRLOFF   = ' OFF'; // Tone Control Off
    public const string PSTONECTRLSTATE = ' ?'; // TONE CTRL ? Return PSTONE CONTROL Status

    //PSSB Surround Back SP Mode
    public const string SBMTRXON     = ':MTRX ON'; // Surround Back SP Mode Matrix
    public const string SBPL2XCINEMA = ':PL2X CINEMA'; // Surround Back SP Mode	PL2X Cinema
    public const string SBPL2XMUSIC  = ':PL2X MUSIC'; // Surround Back SP Mode	PL2X Music
    public const string SBON         = ':ON'; // Surround Back SP Mode on
    public const string SBOFF        = ':OFF'; // Surround Back SP Mode off

    //PSCINEMAEQ Cinema EQ
    public const string CINEMAEQCOMMAND = 'PSCINEMA EQ'; // Cinema EQ
    public const string CINEMAEQON      = '.ON'; // Cinema EQ on
    public const string CINEMAEQOFF     = '.OFF'; // Cinema EQ off
    public const string CINEMAEQ        = '. ?'; // Return PSCINEMA EQ.Status

    //PSHTEQ HT EQ
    public const string HTEQCOMMAND = 'PSHTEQ'; // HT EQ
    public const string HTEQON      = ' ON'; // HT EQ on
    public const string HTEQOFF     = ' OFF'; // HT EQ off
    public const string HTEQ        = ' ?'; // Return HT EQ.Status

    //PSMODE Mode Music
    public const string MODEMUSIC    = ':MUSIC'; // Mode Music CINEMA / MUSIC / GAME / PL mode change
    public const string MODECINEMA   = ':CINEMA'; // This parameter can change DOLBY PL2,PL2x,NEO:6 mode.
    public const string MODEGAME     = ':GAME'; // SB=ON：PL2x mode / SB=OFF：PL2 mode GAME can change DOLBY PL2 & PL2x mode PSMODE:PRO LOGIC
    public const string MODEPROLOGIC = ':PRO LOGIC'; // PL can change ONLY DOLBY PL2 mode
    public const string MODESTATE    = ': ?'; // Return PSMODE: Status

    //PSDOLVOL Dolby Volume direct change
    public const string DOLVOLON  = ' ON'; // Dolby Volume direct change on
    public const string DOLVOLOFF = ' OFF'; // Dolby Volume direct change off
    public const string DOLVOL    = ': ?'; // Return PSDOLVOL Status

    //PSVOLLEV Dolby Volume Leveler direct change
    public const string VOLLEVLOW = ' LOW'; // Dolby Volume Leveler direct change Low
    public const string VOLLEVMID = ' MID'; // Dolby Volume Leveler direct change Middle
    public const string VOLLEVHI  = ' HI'; // Dolby Volume Leveler direct change High
    public const string VOLLEV    = ': ?'; // Return PSVOLLEV Status

    // PSVOLMOD Dolby Volume Modeler direct change
    public const string VOLMODHLF = ' HLF'; // Dolby Volume Modeler direct change half
    public const string VOLMODFUL = ' FUL'; // Dolby Volume Modeler direct change full
    public const string VOLMODOFF = ' OFF'; // Dolby Volume Modeler direct change off
    public const string VOLMOD    = ': ?'; // Return PSVOLMOD Status

    //PSFH Front Height
    public const string PSFHON    = ':ON'; // FRONT HEIGHT ON
    public const string PSFHOFF   = ':OFF'; // FRONT HEIGHT OFF
    public const string PSFHSTATE = ': ?'; // Return PSFH: Status

    //PSPHG PL2z Height Gain direct change
    public const string PHGLOW   = ' LOW'; // PL2z HEIGHT GAIN direct change low
    public const string PHGMID   = ' MID'; // PL2z HEIGHT GAIN direct change middle
    public const string PHGHI    = ' HI'; // PL2z HEIGHT GAIN direct change high
    public const string PHGSTATE = ' ?'; // Return PSPHG Status

    //PSSP Speaker Output set
    public const string SPFH    = ':FH'; // Speaker Output set FH
    public const string SPFW    = ':FW'; // Speaker Output set FW
    public const string SPSB    = ':SB'; // Speaker Output set SB
    public const string SPHW    = ':HW'; // Speaker Output set HW
    public const string SPBH    = ':BH'; // Speaker Output set BH
    public const string SPBW    = ':BW'; // Speaker Output set BW
    public const string SPFL    = ':FL'; // Speaker Output set FL
    public const string SPHF    = ':HF'; // Speaker Output set HF
    public const string SPFR    = ':FR'; // Speaker Output set FR
    public const string SPOFF   = ':OFF'; // Speaker Output set off
    public const string SPSTATE = ' ?'; // Return PSSP: Status

    // MulEQ XT 32 mode direct change
    public const string MULTEQAUDYSSEY = ':AUDYSSEY'; // MultEQ XT 32 mode direct change MULTEQ:AUDYSSEY
    public const string MULTEQBYPLR    = ':BYP.LR'; // MultEQ XT 32 mode direct change MULTEQ:BYP.LR
    public const string MULTEQFLAT     = ':FLAT'; // MultEQ XT 32 mode direct change MULTEQ:FLAT
    public const string MULTEQMANUAL   = ':MANUAL'; // MultEQ XT 32 mode direct change MULTEQ:MANUAL
    public const string MULTEQOFF      = ':OFF'; // MultEQ XT 32 mode direct change MULTEQ:OFF
    public const string MULTEQ         = ': ?'; // Return PSMULTEQ: Status

    //PSDYNEQ Dynamic EQ
    public const string DYNEQON  = ' ON'; // Dynamic EQ = ON
    public const string DYNEQOFF = ' OFF'; // Dynamic EQ = OFF
    public const string DYNEQ    = ' ?'; // Return PSDYNEQ Status

    //PSLFC Audyssey LFC
    public const string LFCON  = ' ON'; // Audyssey LFC = ON
    public const string LFCOFF = ' OFF'; // Audyssey LFC = OFF
    public const string LFC    = ' ?'; // Return Audyssey LFC Status

    //PSGEQ Graphic EQ
    public const string GEQON  = ' ON'; // Graphic EQ = ON
    public const string GEQOFF = ' OFF'; // Graphic EQ = OFF
    public const string GEQ    = ' ?'; // Return Graphic EQ Status

    //PSREFLEV Reference Level Offset
    public const string REFLEV0  = ' 0'; // Reference Level Offset=0dB
    public const string REFLEV5  = ' 5'; // Reference Level Offset=5dB
    public const string REFLEV10 = ' 10'; // Reference Level Offset=10dB
    public const string REFLEV15 = ' 15'; // Reference Level Offset=15dB
    public const string REFLEV   = ' ?'; // Return PSREFLEV Status

    //PSREFLEV Reference Level Offset
    public const string DIRAC1   = ' 1'; // Filter Slot 1
    public const string DIRAC2   = ' 2'; // Filter Slot 2
    public const string DIRAC3   = ' 3'; // Filter Slot 3
    public const string DIRACOFF = ' OFF'; // Filter Off


    //PSDYNVOL (old version)
    public const string DYNVOLNGT = ' NGT'; // Dynamic Volume = Midnight
    public const string DYNVOLEVE = ' EVE'; // Dynamic Volume = Evening
    public const string DYNVOLDAY = ' DAY'; // Dynamic Volume = Day
    public const string DYNVOL    = ' ?'; // Return PSDYNVOL Status
    //PSDYNVOL
    public const string DYNVOLHEV = ' HEV'; // Dynamic Volume = Heavy
    public const string DYNVOLMED = ' MED'; // Dynamic Volume = Medium
    public const string DYNVOLLIT = ' LIT'; // Dynamic Volume = Light
    public const string DYNVOLOFF = ' OFF'; // Dynamic Volume = Off
    public const string DYNVOLON  = ' ON'; // Dynamic Volume = Off

    //PSDSX Audyssey DSX ON
    public const string PSDSXONHW   = ' ONHW'; // Audyssey DSX ON(Height/Wide)
    public const string PSDSXONH    = ' ONH'; // Audyssey DSX ON(Height)
    public const string PSDSXONW    = ' ONW'; // Audyssey DSX ON(Wide)
    public const string PSDSXOFF    = ' OFF'; // Audyssey DSX OFF
    public const string PSDSXSTATUS = ' ?'; // Return PSDSX Status

    //PSSTW Stage Width
    public const string STWUP   = ' UP'; // STAGE WIDTH UP
    public const string STWDOWN = ' DOWN'; // STAGE WIDTH DOWN
    public const string STW     = ' '; // STAGE WIDTH ** ---AVR-4311 can be operated from -10 to +10

    //PSSTH Stage Height
    public const string STHUP   = ' UP'; // STAGE HEIGHT UP
    public const string STHDOWN = ' DOWN'; // STAGE HEIGHT DOWN
    public const string STH     = ' '; // STAGE HEIGHT ** ---AVR-4311 can be operated from -10 to +10

    //PSBAS Bass
    public const string BASUP   = ' UP'; // BASS UP
    public const string BASDOWN = ' DOWN'; // BASS DOWN
    public const string BAS     = ' '; // BASS ** ---AVR-4311 can be operated from -6 to +6

    //PSTRE Treble
    public const string TREUP   = ' UP'; // TREBLE UP
    public const string TREDOWN = ' DOWN'; // TREBLE DOWN
    public const string TRE     = ' '; // TREBLE ** ---AVR-4311 can be operated from -6 to +6

    //PSDRC DRC direct change
    public const string DRCAUTO = ' AUTO'; // DRC direct change
    public const string DRCLOW  = ' LOW'; // DRC Low
    public const string DRCMID  = ' MID'; // DRC Middle
    public const string DRCHI   = ' HI'; // DRC High
    public const string DRCOFF  = ' OFF'; // DRC off
    public const string DRC     = ' ?'; // Return PSDRC Status

    //PSMDAX MDAX direct change
    public const string MDAXLOW = ' LOW'; // DRC Low
    public const string MDAXMID = ' MID'; // DRC Middle
    public const string MDAXHI  = ' HI'; // DRC High
    public const string MDAXOFF = ' OFF'; // DRC off
    public const string MDAX    = ' ?'; // Return PSDRC Status

    //PSDCO D.Comp direct change
    public const string DCOOFF  = ' OFF'; // D.COMP direct change
    public const string DCOLOW  = ' LOW'; // D.COMP Low
    public const string DCOMID  = ' MID'; // D.COMP Middle
    public const string DCOHIGH = ' HIGH'; // D.COMP High
    public const string DCO     = ' ?'; // Return PSDCO Status

    //PSLFE LFE
    public const string LFEDOWN = ' DOWN'; // LFE DOWN
    public const string LFEUP   = ' UP'; // LFE UP
    public const string LFE     = ' '; // LFE ** ---AVR-4311 can be operated from 0 to -10

    //PSEFF Effect direct change
    public const string PSEFFON  = ' ON'; // EFFECT ON direct change
    public const string PSEFFOFF = ' OFF'; // EFFECT OFF direct change

    public const string PSEFFUP     = ' UP'; // EFFECT UP direct change
    public const string PSEFFDOWN   = ' DOWN'; // EFFECT DOWN direct change
    public const string PSEFFSTATUS = ' ?'; // EFFECT ** ---AVR-4311 can be operated from 1 to 15

    //PSDELAY Delay
    public const string PSDELAYUP   = ' UP'; // DELAY UP
    public const string PSDELAYDOWN = ' DOWN'; // DELAY DOWN
    public const string PSDELAYVAL  = ' '; // DELAY ** ---AVR-4311 can be operated from 0 to 300

    //PSAFD Auto Flag Detection Mode
    public const string AFDON  = ' ON'; // AFDM ON
    public const string AFDOFF = ' OFF'; // AFDM OFF
    public const string AFD    = ' '; // Return PSAFD Status

    //PSPAN Panorama
    public const string PANON  = ' ON'; // PANORAMA ON
    public const string PANOFF = ' OFF'; // PANORAMA OFF
    public const string PAN    = ' ?'; // Return PSPAN Status

    //PSDIM Dimension
    public const string PSDIMUP   = ' UP'; // DIMENSION UP
    public const string PSDIMDOWN = ' DOWN'; // DIMENSION DOWN
    public const string PSDIMSET  = ' '; // ---AVR-4311 can be operated from 0 to 6

    //PSCEN Center Width
    public const string CENUP   = 'CEN UP'; // CENTER WIDTH UP
    public const string CENDOWN = 'CEN DOWN'; // CENTER WIDTH DOWN
    public const string CEN     = 'CEN '; // ---AVR-4311 can be operated from 0 to 7

    //PSCEI Center Image
    public const string CEIUP   = 'CEI UP'; // CENTER IMAGE UP
    public const string CEIDOWN = 'CEI DOWN'; // CENTER IMAGE DOWN
    public const string CEI     = 'CEI '; // ---AVR-4311 can be operated from 0 to 7

    //PSRSZ Room Size
    public const string RSZN = ' N';
    public const string RSZS = ' S';
    public const string RSZMS = ' MS';
    public const string RSZM  = ' M';
    public const string RSZML = ' ML';
    public const string RSZL  = ' L';

    //PSSW ATT
    public const string ATTON  = 'ATT ON'; // SW ATT ON
    public const string ATTOFF = 'ATT OFF'; // SW ATT OFF
    public const string ATT    = 'ATT ?'; // Return PSATT Status

    //PSSWR
    public const string PSSWRON  = ' ON'; // SW ATT ON
    public const string PSSWROFF = ' OFF'; // SW ATT OFF
    public const string SWR      = ' ?'; // Return PSATT Status

    //PSLOM
    public const string PSLOMON  = ' ON'; // SW ATT ON
    public const string PSLOMOFF = ' OFF'; // SW ATT OFF
    public const string LOM      = ' ?'; // Return PSATT Status

    //Audio Restorer - neue Kommandos bei neueren(?) Modellen
    public const string PSRSTROFF = ' OFF'; //Audio Restorer Off
    //public const PSRSTRMODE1 = ' MODE1'; //Audio Restorer 64
    //public const PSRSTRMODE2 = ' MODE2'; //Audio Restorer 96
    //public const PSRSTRMODE3 = ' MODE3'; //Audio Restorer HQ
    public const string PSRSTRMODE1 = ' HI'; //Audio Restorer 64
    public const string PSRSTRMODE2 = ' MID'; //Audio Restorer 96
    public const string PSRSTRMODE3 = ' LOW'; //Audio Restorer HQ

    //Front Speaker
    public const string PSFRONTSPA  = ' SPA'; //Speaker A
    public const string PSFRONTSPB  = ' SPB'; //Speaker B
    public const string PSFRONTSPAB = ' A+B'; //Speaker A+B

    //Cursor Menu
    public const string MNCUP = 'CUP'; // Cursor Up
    public const string MNCDN = 'CDN'; // Cursor Down
    public const string MNCRT = 'CRT'; // Cursor Right
    public const string MNCLT = 'CLT'; // Cursor Left
    public const string MNENT = 'ENT'; // Cursor Enter
    public const string MNRTN = 'RTN'; // Cursor Return

    //GUI Menu (Setup Menu)
    public const string MNMEN    = 'MNMEN'; // GUI Menu
    public const string MNMENON  = ' ON'; // GUI Menu On
    public const string MNMENOFF = ' OFF'; // GUI Menu Off

    //GUI Source Select Menu
    public const string MNSRC    = 'MNSRC'; // Source Select Menu
    public const string MNSRCON  = ' ON'; // Source Select Menu On
    public const string MNSRCOFF = ' OFF'; // Source Select Menu Off

    // Surround Modes Response

    // Surround Modes Varmapping

    //Dolby Digital
    public const string DOLBYPROLOGIC = 'DOLBY PRO LOGIC'; // DOLBY PRO LOGIC
    public const string DOLBYPL2C     = 'DOLBY PL2 C'; // DOLBY PL2 C
    public const string DOLBYPL2M     = 'DOLBY PL2 M'; // DOLBY PL2 M
    public const string DOLBYPL2G     = 'DOLBY PL2 G'; // DOLBY PL2 G
    public const string DOLBYPLIIMV   = 'DOLBY PLII MV';
    public const string DOLBYPLIIMS   = 'DOLBY PLII MS';
    public const string DOLBYPLIIGM   = 'DOLBY PLII GM';
    public const string DOLBYPL2XC    = 'DOLBY PL2X C'; // DOLBY PL2X C
    public const string DOLBYPL2XM    = 'DOLBY PL2X M'; // DOLBY PL2X M
    public const string DOLBYPL2XG    = 'DOLBY PL2X G'; // DOLBY PL2X G
    public const string DOLBYPL2ZH    = 'DOLBY PL2Z H'; // DOLBY PL2Z H
    public const string DOLBYPL2XH  = 'DOLBY PL2X H'; // DOLBY PL2X H
    public const string DOLBYDEX    = 'DOLBY D EX'; // DOLBY D EX
    public const string DOLBYDPL2XC = 'DOLBY D+PL2X C';
    public const string DOLBYDPL2XM      = 'DOLBY D+PL2X M';
    public const string DOLBYDPL2ZH      = 'DOLBY D+PL2Z H';
    public const string DOLBYAUDIODDDSUR = 'DOLBY AUDIO-DD+DSUR';
    public const string PLDSX            = 'PL DSX'; // PL DSX
    public const string PL2CDSX          = 'PL2 C DSX'; // PL2 C DSX
    public const string PL2MDSX          = 'PL2 M DSX'; // PL2 M DSX
    public const string PL2GDSX          = 'PL2 G DSX'; // PL2 G DSX
    public const string PL2XCDSX         = 'PL2X C DSX'; // PL2X C DSX
    public const string PL2XMDSX         = 'PL2X M DSX'; // PL2X M DSX
    public const string PL2XGDSX        = 'PL2X G DSX'; // PL2X G DSX
    public const string DOLBYDPLUSPL2XC = 'DOLBY D+ +PL2X C'; // DOLBY D+ +PL2X C
    public const string DOLBYDPLUSPL2XM = 'DOLBY D+ +PL2X M'; // DOLBY D+ +PL2X M
    public const string DOLBYDPLUSPL2XH = 'DOLBY D+ +PL2X H'; // DOLBY D+ +PL2X H
    public const string DOLBYHDPL2XC    = 'DOLBY HD+PL2X C'; // DOLBY HD+PL2X C
    public const string DOLBYHDPL2XM    = 'DOLBY HD+PL2X M'; // DOLBY HD+PL2X M
    public const string DOLBYHDPL2XH    = 'DOLBY HD+PL2X H'; // DOLBY HD+PL2X H
    public const string MULTICNIN       = 'MULTI CH IN'; // MULTI CH IN
    public const string MCHINPL2XC      = 'M CH IN+PL2X C'; // M CH IN+PL2X C
    public const string MCHINPL2XM      = 'M CH IN+PL2X M'; // M CH IN+PL2X M
    public const string MCHINPL2ZH      = 'M CH IN+PL2Z H';
    public const string MCHINDSUR       = 'M CH IN+DSUR';
    public const string MCHINNEURALX    = 'M CH IN+NEURAL:X'; // M CH IN+NEURAL:X

    public const string DOLBYDPLUS   = 'DOLBY D+'; // DOLBY D+
    public const string DOLBYDPLUSEX = 'DOLBY D+ +EX'; // DOLBY D+ +EX
    public const string DOLBYTRUEHD  = 'DOLBY TRUEHD'; // DOLBY TRUEHD
    public const string DOLBYHD      = 'DOLBY HD'; // DOLBY HD
    public const string DOLBYHDEX    = 'DOLBY HD+EX'; // DOLBY HD+EX
    public const string DOLBYPL2H    = 'DOLBY PL2 H'; // MSDOLBY PL2 H

    public const string DOLBYSURROUND  = 'DOLBY SURROUND'; // MSDOLBY SURROUND
    public const string DOLBYAUDIODSUR = 'DOLBY AUDIO-DSUR';
    public const string DOLBYATMOS     = 'DOLBY ATMOS'; // MSDOLBY ATMOS
    public const string DOLBYAUDIODD   = 'DOLBY AUDIO-DD';
    public const string DOLBYDIGITAL   = 'DOLBY DIGITAL'; // MSDOLBY DIGITAL
    public const string DOLBYDDS       = 'DOLBY D+DS'; // MSDOLBY D+DS
    public const string MPEG2AAC       = 'MPEG2 AAC'; // MSMPEG2 AAC
    public const string MPEG4AAC       = 'MPEG4 AAC'; // MSMPEG4 AAC
    public const string MPEGH          = 'MPEG-H'; // MSMPEG4 AAC
    public const string AACDOLBYEX     = 'AAC+DOLBY EX'; // MSAAC+DOLBY EX
    public const string AACPL2XC       = 'AAC+PL2X C'; // MSAAC+PL2X C
    public const string AACPL2XM     = 'AAC+PL2X M'; // MSAAC+PL2X M
    public const string AACPL2ZH     = 'AAC+PL2Z H';
    public const string AACDSUR      = 'AAC+DSUR';
    public const string AACDS        = 'AAC+DS'; // MSAAC+DS
    public const string AACNEOXC   = 'AAC+NEO:X C'; // MSAAC+NEO:X C
    public const string AACNEOXM   = 'AAC+NEO:X M'; // MSAAC+NEO:X M
    public const string AACNEOXG   = 'AAC+NEO:X G'; // MSAAC+NEO:X G

    //DTS Surround
    public const string DTSNEO6C     = 'DTS NEO:6 C'; // DTS NEO:6 C
    public const string DTSNEO6M     = 'DTS NEO:6 M'; // DTS NEO:6 M
    public const string DTSNEOXC     = 'DTS NEO:X C'; // DTS NEO:X C
    public const string DTSNEOXM     = 'DTS NEO:X M'; // DTS NEO:X M
    public const string DTSNEOXG     = 'DTS NEO:X G'; // DTS NEO:X G
    public const string NEURALX      = 'NEURAL:X'; // NEURAL:X
    public const string VIRTUALX     = 'VIRTUAL:X'; // VIRTUAL:X
    public const string DTSESDSCRT61 = 'DTS ES DSCRT6.1'; // DTS ES DSCRT6.1
    public const string DTSESMTRX61  = 'DTS ES MTRX6.1'; // DTS ES MTRX6.1
    public const string DTSPL2XC     = 'DTS+PL2X C'; // DTS+PL2X C
    public const string DTSPL2XM     = 'DTS+PL2X M'; // DTS+PL2X M
    public const string DTSPL2ZH     = 'DTS+PL2Z H'; // DTS+PL2Z H
    public const string DTSDSUR      = 'DTS+DSUR';
    public const string DTSDS        = 'DTS+DS'; // DTS+DS
    public const string DTSPLUSNEO6  = 'DTS+NEO:6'; // DTS+NEO:6
    public const string DTSPLUSNEOXC = 'DTS+NEO:X C'; // DTS PLUS NEO:X C
    public const string DTSPLUSNEOXM = 'DTS+NEO:X M'; // DTS PLUS NEO:X M
    public const string DTSPLUSNEOXG = 'DTS+NEO:X G'; // DTS PLUS NEO:X G
    public const string DTSPLUSNEURALX = 'DTS+NEURAL:X'; // DTS+NEURAL:X
    public const string DTS9624        = 'DTS96/24'; // DTS96/24
    public const string DTS96ESMTRX    = 'DTS96 ES MTRX'; // DTS96 ES MTRX
    public const string DTSHDPL2XC     = 'DTS HD+PL2X C'; // DTS HD+PL2X C
    public const string DTSHDPL2XM     = 'DTS HD+PL2X M'; // DTS HD+PL2X M
    public const string DTSHDPL2ZH     = 'DTS HD+PL2Z H'; // DTS HD+PL2Z H
    public const string DTSHDDSUR      = 'DTS HD+DSUR';
    public const string DTSHDDS        = 'DTS HD+DS'; // DTS HD+DS
    public const string NEO6CDSX       = 'NEO:6 C DSX'; // NEO:6 C DSX
    public const string NEO6MDSX       = 'NEO:6 M DSX'; // NEO:6 M DSX
    public const string DTSHD          = 'DTS HD'; // DTS HD
    public const string DTSHDMSTR   = 'DTS HD MSTR'; // DTS HD MSTR
    public const string DTSHDNEO6   = 'DTS HD+NEO:6'; // DTS HD+NEO:6
    public const string DTSES8CHDSCRT = 'DTS ES 8CH DSCRT'; // DTS ES 8CH DSCRT
    public const string DTSEXPRESS    = 'DTS EXPRESS'; // DTS EXPRESS
    public const string DOLBYDNEOXC   = 'DOLBY D+NEO:X C'; // MSDOLBY D+NEO:X C
    public const string DOLBYDNEOXM   = 'DOLBY D+NEO:X M'; // MSDOLBY D+NEO:X M
    public const string DOLBYDNEOXG   = 'DOLBY D+NEO:X G'; // MSDOLBY D+NEO:X G
    public const string DOLBYAUDIODDPLUSNEURALX = 'DOLBY AUDIO-DD+NEURAL:X';
    public const string DOLBYAUDIODDPLUS        = 'DOLBY AUDIO-DD+';
    public const string DOLBYDNEURALX           = 'DOLBY D+NEURAL:X'; // MSDOLBY D+NEURAL:X
    public const string MCHINDS                 = 'M CH IN+DS'; // MSM CH IN+DS
    public const string MCHINNEOXC              = 'M CH IN+NEO:X C'; // MSM CH IN+NEO:X C
    public const string MCHINNEOXM              = 'M CH IN+NEO:X M'; // MSM CH IN+NEO:X M
    public const string MCHINNEOXG              = 'M CH IN+NEO:X G'; // MSM CH IN+NEO:G C
    public const string DOLBYDPLUSDS            = 'DOLBY D+ +DS'; // MSDOLBY D+ +DS
    public const string DOLBYAUDIODDPLUSDSUR    = 'DOLBY AUDIO-DD+ +DSUR';
    public const string DOLBYDPLUSNEOXC         = 'DOLBY D+ +NEO:X C'; // MSDOLBY D+ +NEO:X C
    public const string DOLBYDPLUSNEOXM             = 'DOLBY D+ +NEO:X M'; // MSDOLBY D+ +NEO:X M
    public const string DOLBYDPLUSNEOXG             = 'DOLBY D+ +NEO:X G'; // MSDOLBY D+ +NEO:X G
    public const string DOLBYAUDIODDPLUSPLUSNEURALX = 'DOLBY AUDIO-DD+ +NEURAL:X';
    public const string DOLBYAUDIOTRUEHD            = 'DOLBY AUDIO-TRUEHD';
    public const string DOLBYDPLUSNEURALX           = 'DOLBY D+ +NEURAL:X'; // MSDOLBY D+ +NEURAL:X
    public const string DOLBYHDDS                   = 'DOLBY HD+DS'; // MSDOLBY HD+DS
    public const string DOLBYAUDIOTRUEHDDSUR        = 'DOLBY AUDIO-TRUEHD+DSUR';
    public const string DOLBYAUDIOTRUEHDNEURALX     = 'DOLBY AUDIO-TRUEHD+NEURAL:X';
    public const string DOLBYHDNEOXC                = 'DOLBY HD+NEO:X C'; // MSDOLBY HD+NEO:X C
    public const string DOLBYHDNEOXM                = 'DOLBY HD+NEO:X M'; // MSDOLBY HD+NEO:X M
    public const string DOLBYHDNEOXG                = 'DOLBY HD+NEO:X G'; // MSDOLBY HD+NEO:X G
    public const string DOLBYHDNEURALX              = 'DOLBY HD+NEURAL:X'; // MSDOLBY HD+NEURAL:X
    public const string DTSHDNEOXC              = 'DTS HD+NEO:X C'; // MSDTS HD+NEO:X C
    public const string DTSHDNEOXM              = 'DTS HD+NEO:X M'; // MSDTS HD+NEO:X M
    public const string DTSHDNEOXG              = 'DTS HD+NEO:X G'; // MSDTS HD+NEO:X G

    public const string DSDDIRECT     = 'DSD DIRECT'; // DSD DIRECT
    public const string DSDPUREDIRECT = 'DSD PURE DIRECT'; // DSD PURE DIRECT

    public const string MCHINDOLBYEX = 'M CH IN+DOLBY EX'; // M CH IN+DOLBY EX
    public const string MULTICHIN71  = 'MULTI CH IN 7.1'; // MULTI CH IN 7.1

    public const string AUDYSSEYDSX = 'AUDYSSEY DSX'; // AUDYSSEY DSX

    public const string SURROUNDDISPLAY = 'SurroundDisplay'; // Nur DisplayIdent
    public const string SYSMI           = 'SYSMI'; // Nur DisplayIdent
    public const string SYSDA           = 'SYSDA'; // Nur DisplayIdent
    public const string SSINFAISFSV     = 'SSINFAISFSV'; // Nur DisplayIdent
    public const string SSINFAISSIG     = 'SSINFAISSIG'; // Nur DisplayIdent

    public const string BTTXON  = ' ON';
    public const string BTTXOFF = ' OFF';
    public const string BTTXSP  = ' SP';
    public const string BTTXBT = ' BT';

    public const string SPPR_1 = ' 1';
    public const string SPPR_2 = ' 2';

    // All Zone Stereo
    public const string MNZST   = 'MNZST';
    public const string MNZSTON = ' ON';
    public const string MNZSTOFF = ' OFF';

    public const string PSGEQ    = 'PSGEQ'; // Graphic EQ
    public const string PSGEQON  = ' ON'; // Graphic EQ On
    public const string PSGEQOFF = ' OFF'; // Graphic EQ Off

    public const string PSHEQ    = 'PSHEQ'; // Headphone EQ
    public const string PSHEQON  = ' ON'; // Headphone EQ On
    public const string PSHEQOFF = ' OFF'; // Headphone EQ Off

    public const string PSSWL    = 'PSSWL'; // Subwoofer Level
    public const string PSSWL2   = 'PSSWL2'; // Subwoofer2 Level
    public const string PSSWL3   = 'PSSWL3'; // Subwoofer3 Level
    public const string PSSWL4   = 'PSSWL4'; // Subwoofer4 Level
    public const string PSSWLON  = ' ON'; // Subwoofer Level On
    public const string PSSWLOFF = ' OFF'; // Subwoofer Level Off

    public const string PSDIL    = 'PSDIL'; // Dialog Level Adjust
    public const string PSDILON  = ' ON'; // Dialog Level Adjust On
    public const string PSDILOFF = ' OFF'; // Dialog Level Adjust Off

    public const string STBY      = 'STBY'; // Mainzone Auto Standby
    public const string STBY15M   = '15M'; // Mainzone Auto Standby 15 Minuten
    public const string STBY30M   = '30M'; // Mainzone Auto Standby 30 Minuten
    public const string STBY60M   = '60M'; // Mainzone Auto Standby 60 Minuten
    public const string STBYOFF   = 'OFF'; // Mainzone Auto Standby Off
    public const string Z2STBY    = 'Z2STBY'; // Zone 2 Auto Standby
    public const string Z2STBY2H  = '2H'; // Zone 2 Auto Standby 2h
    public const string Z2STBY4H  = '4H'; // Zone 2 Auto Standby 4h
    public const string Z2STBY8H  = '8H'; // Zone 2 Auto Standby 8h
    public const string Z2STBYOFF = 'OFF'; // Zone 2 Auto Standby Off
    public const string Z3STBY    = 'Z3STBY'; // Zone 3 Auto Standby
    public const string Z3STBY2H  = '2H'; // Zone 3 Auto Standby 2H
    public const string Z3STBY4H  = '4H'; // Zone 3 Auto Standby 4h
    public const string Z3STBY8H  = '8H'; // Zone 3 Auto Standby 8h
    public const string Z3STBYOFF = 'OFF'; // Zone 3 Auto Standby Off
    public const string ECO       = 'ECO'; // ECO Mode
    public const string ECOON     = 'ON'; // ECO Mode On
    public const string ECOAUTO   = 'AUTO'; // ECO Mode Auto
    public const string ECOOFF    = 'OFF'; // ECO Mode Off
    public const string DIM       = 'DIM'; // Dimmer
    public const string DIMBRI    = ' BRI'; // Bright
    public const string DIMDIM    = ' DIM'; // DIM
    public const string DIMDAR    = ' DAR'; // Dark
    public const string DIMOFF    = ' OFF'; // Dimmer off

    public const string SSHOSALS    = 'SSHOSALS'; //Auto Lip Sync
    public const string SSHOSALSON  = ' ON'; //Auto Lip Sync On
    public const string SSHOSALSOFF = ' OFF'; //Auto Lip Sync Off

    public const string PSCES    = 'PSCES'; // Center Spread
    public const string PSCESON  = ' ON'; // Center Spread On
    public const string PSCESOFF = ' OFF'; // Center Spread Off

    public const string PSSPV    = 'PSSPV'; // Speaker Virtualizer
    public const string PSSPVON  = ' ON'; // Speaker Virtualizer On
    public const string PSSPVOFF = ' OFF'; // Speaker Virtualizer Off

    public const string PSNEURAL    = 'PSNEURAL'; // Center Spread
    public const string PSNEURALON  = ' ON'; // Center Spread On
    public const string PSNEURALOFF = ' OFF'; // Center Spread Off

    public const string PSBSC = 'PSBSC'; // Bass Sync

    public const string PSDEH     = 'PSDEH'; // Dialog Enhancer
    public const string PSDEHOFF  = ' OFF'; // Dialog Enhancer Off
    public const string PSDEHMED  = ' MED'; // Dialog Enhancer Medium
    public const string PSDEHLOW  = ' LOW'; // Dialog Enhancer Low
    public const string PSDEHHIGH = ' HIGH'; // Dialog Enhancer High

    public const string PSAUROST     = 'PSAUROST'; // Auro Matic 3D Strength
    public const string PSAUROSTUP   = ' UP'; // Auro Matic 3D Strength Up
    public const string PSAUROSTDOWN = ' DOWN'; // Auro Matic 3D Strength Down

    public const string PSAUROPR    = 'PSAUROPR'; // Auro Matic 3D Present
    public const string PSAUROPRSMA = ' SMA'; // Auro Matic 3D Present Small
    public const string PSAUROPRMED = ' MED'; // Auro Matic 3D Present Medium
    public const string PSAUROPRLAR = ' LAR'; // Auro Matic 3D Present Large
    public const string PSAUROPRSPE = ' SPE'; // Auro Matic 3D Present SPE

    public const string PSAUROMODE     = 'PSAUROMODE'; // Auro 3D Mode
    public const string PSAUROMODEDRCT = ' DRCTSMA'; // Auro 3D Mode Direct
    public const string PSAUROMODEEXP  = ' EXP'; // Auro 3D Mode Channel Expansion

    public const string PSDIRAC = 'PSDIRAC'; //Dirac Live Filter
    public const string CVSHL   = 'CVSHL'; // Surround Height Left
    public const string CVSHR   = 'CVSHR'; // Surround Height Right
    public const string CVTS    = 'CVTS'; // Top Surround
    public const string CVCH    = 'CVCH'; // Center Height
    public const string CVZRL   = 'CVZRL'; // Reset Channel Volume Status

    public const string CVTFL = 'CVTFL'; // Top Front Left
    public const string CVTFR = 'CVTFR'; // Top Front Right
    public const string CVTML = 'CVTML'; // Top Middle Left
    public const string CVTMR = 'CVTMR'; // Top Middle Right
    public const string CVTRL = 'CVTRL'; // Top Rear Left
    public const string CVTRR = 'CVTRR'; // Top Rear Right
    public const string CVRHL = 'CVRHL'; // Rear Height Left
    public const string CVRHR = 'CVRHR'; // Rear Height Right
    public const string CVFDL = 'CVFDL'; // Front Dolby Left
    public const string CVFDR = 'CVFDR'; // Front Dolby Right
    public const string CVSDL = 'CVSDL'; // Surround Dolby Left
    public const string CVSDR = 'CVSDR'; // Surround Dolby Right
    public const string CVBDL = 'CVBDL'; // Back Dolby Left
    public const string CVBDR = 'CVBDR'; // Back Dolby Right
    public const string CVTTR = 'CVTTR'; // Tactile Transducer
}

class DenonAVRCP_API_Data extends stdClass
{
    private string $AVRType;
    private array $Data;

    //Surround Display
    public static array $SurroundModes      = [
        //show display => response display
        DENON_API_Commands::MSDIRECT         => 'Direct',
        DENON_API_Commands::MSPUREDIRECT     => 'Pure Direct',
        DENON_API_Commands::MSSTEREO         => 'Stereo',
        DENON_API_Commands::MSDOLBYDIGITAL   => 'Dolby Digital',
        DENON_API_Commands::MSDTSSURROUND    => 'DTS Surround',
        DENON_API_Commands::MSAURO3D         => 'Auro 3D',
        DENON_API_Commands::MSAURO2DSURR     => 'Auro 2D Surround',
        DENON_API_Commands::MSMCHSTEREO      => 'Multi Ch Stereo',
        DENON_API_Commands::MS7CHSTEREO      => '7 Channel Stereo',
        DENON_API_Commands::MSWIDESCREEN     => 'Wide Screen',
        DENON_API_Commands::MSROCKARENA      => 'Rock Arena',
        DENON_API_Commands::MSSUPERSTADIUM   => 'Super Stadion',
        DENON_API_Commands::MSJAZZCLUB       => 'Jazz Club',
        DENON_API_Commands::MSCLASSICCONCERT => 'Klassikkonzert',
        DENON_API_Commands::MSMONOMOVIE      => 'Mono Movie',
        DENON_API_Commands::MSMATRIX         => 'Matrix',
        DENON_API_Commands::MSVIDEOGAME      => 'Video Game',
        DENON_API_Commands::MSVIRTUAL        => 'Virtual',
    ];

    public static array $DolbySurroundModes = [
        //show display => response display
        DENON_API_Commands::DOLBYPROLOGIC               => 'Dolby Pro Logic',
        DENON_API_Commands::DOLBYPL2C                   => 'Dolby Pro Logic II Cinema',
        DENON_API_Commands::DOLBYPL2M                   => 'Dolby Pro Logic II Music',
        DENON_API_Commands::DOLBYPL2H                   => 'Dolby Pro Logic II Height',
        DENON_API_Commands::DOLBYPL2G                   => 'Dolby Pro Logic II Game',
        DENON_API_Commands::DOLBYPLIIMV                 => 'Dolby Pro Logic II MV',
        DENON_API_Commands::DOLBYPLIIMS                 => 'Dolby Pro Logic II MS',
        DENON_API_Commands::DOLBYPLIIGM                 => 'Dolby Pro Logic II GM',
        DENON_API_Commands::DOLBYPL2XC                  => 'Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::DOLBYPL2XM                  => 'Dolby Pro Logic IIx Music',
        DENON_API_Commands::DOLBYPL2XH                  => 'Dolby Pro Logic IIx Height',
        DENON_API_Commands::DOLBYPL2XG                  => 'Dolby Pro Logic IIx Game',
        DENON_API_Commands::DOLBYPL2ZH                  => 'Dolby Pro Logic IIz Height',
        DENON_API_Commands::DOLBYSURROUND               => 'Dolby Surround',
        DENON_API_Commands::DOLBYATMOS                  => 'Dolby Atmos',
        DENON_API_Commands::DOLBYAUDIODSUR              => 'Dolby Audio DSUR',
        DENON_API_Commands::DOLBYAUDIODD                => 'Dolby Audio Digital',
        DENON_API_Commands::DOLBYAUDIODDPLUSNEURALX     => 'Dolby Audio Digital + Neural:X',
        DENON_API_Commands::DOLBYAUDIODDPLUSPLUSNEURALX => 'Dolby Audio Digital Plus + Neural:X',
        DENON_API_Commands::DOLBYAUDIODDPLUS            => 'Dolby Audio Digital Plus',
        DENON_API_Commands::DOLBYDEX                    => 'Dolby Digital Ex',
        DENON_API_Commands::DOLBYDPL2XC                 => 'Dolby Digital Plus + PL2X C',
        DENON_API_Commands::DOLBYDPL2XM                 => 'Dolby Digital Plus + PL2X M',
        DENON_API_Commands::DOLBYDPL2ZH                 => 'Dolby Digital Plus + PL2Z H',
        DENON_API_Commands::DOLBYAUDIODDDSUR            => 'Dolby Audio DD + DSUR',
        DENON_API_Commands::DOLBYDDS                    => 'Dolby Digital + DS',
        DENON_API_Commands::DOLBYAUDIODDPLUSDSUR        => 'Dolby Audio Digital Plus + DSUR',
        DENON_API_Commands::DOLBYDNEOXC                 => 'Dolby Digital + NEO:X Cinema',
        DENON_API_Commands::DOLBYDNEOXM                 => 'Dolby Digital + NEO:X Music',
        DENON_API_Commands::DOLBYDNEOXG                 => 'Dolby Digital + NEO:X Game',
        DENON_API_Commands::DOLBYDNEURALX               => 'Dolby Digital + Neural:X',
        DENON_API_Commands::DOLBYDPLUSDS                => 'Dolby Digital Plus + Dolby Surround',
        DENON_API_Commands::DOLBYDPLUSNEOXC             => 'Dolby Digital Plus + NEO:X Cinema',
        DENON_API_Commands::DOLBYDPLUSNEOXM             => 'Dolby Digital Plus + NEO:X Music',
        DENON_API_Commands::DOLBYDPLUSNEOXG             => 'Dolby Digital Plus + NEO:X Game',
        DENON_API_Commands::DOLBYDPLUSNEURALX           => 'Dolby Digital Plus + Neural:X',
        DENON_API_Commands::DOLBYDPLUS                  => 'Dolby Digital Plus',
        DENON_API_Commands::DOLBYDPLUSPL2XC             => 'Dolby Digital Plus + Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::DOLBYDPLUSPL2XM             => 'Dolby Digital Plus + Dolby Pro Logic IIx Music',
        DENON_API_Commands::DOLBYDPLUSPL2XH             => 'Dolby Digital Plus + Dolby Pro Logic IIx Height',
        DENON_API_Commands::DOLBYTRUEHD                 => 'Dolby True HD',
        DENON_API_Commands::DOLBYHD                     => 'Dolby HD',
        DENON_API_Commands::DOLBYHDEX                   => 'Dolby HD + Ex',
        DENON_API_Commands::DOLBYHDPL2XC                => 'Dolby HD + Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::DOLBYHDPL2XM                => 'Dolby HD + Dolby Pro Logic IIx Music',
        DENON_API_Commands::DOLBYHDPL2XH                => 'Dolby HD + Dolby Pro Logic IIx Height',
        DENON_API_Commands::DOLBYHDDS                   => 'Dolby True HD + Dolby Surround',
        DENON_API_Commands::DOLBYHDNEOXC                => 'Dolby True HD + NEO:X Cinema',
        DENON_API_Commands::DOLBYHDNEOXM                => 'Dolby True HD + NEO:X Music',
        DENON_API_Commands::DOLBYHDNEOXG                => 'Dolby True HD + NEO:X Game',
        DENON_API_Commands::DOLBYHDNEURALX              => 'Dolby HD + Neural:X',
        DENON_API_Commands::DOLBYAUDIOTRUEHDDSUR        => 'Dolby Audio True HD + DSUR',
        DENON_API_Commands::DOLBYAUDIOTRUEHDNEURALX     => 'Dolby Audio True HD + Neural:X',
    ];

    public static array $DTSSurroundModes   = [
        //show display => response display
        DENON_API_Commands::DTSESDSCRT61   => 'DTS ES Discrete 6.1',
        DENON_API_Commands::DTSESMTRX61    => 'DTS ES Matrix 6.1',
        DENON_API_Commands::DTSPL2XC       => 'DTS + Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::DTSPL2XM       => 'DTS + Dolby Pro Logic IIx Music',
        DENON_API_Commands::DTSPL2ZH       => 'DTS + Dolby Pro Logic IIx Height',
        DENON_API_Commands::DTSDSUR        => 'DTS + DSUR',
        DENON_API_Commands::DTSDS          => 'DTS + Dolby Surround',
        DENON_API_Commands::DTS9624        => 'DTS 96/24',
        DENON_API_Commands::DTS96ESMTRX    => 'DTS 96/24 ES Matrix',
        DENON_API_Commands::DTSPLUSNEO6    => 'DTS + NEO:6',
        DENON_API_Commands::DTSPLUSNEOXC   => 'DTS + NEO:X Cinema',
        DENON_API_Commands::DTSPLUSNEOXM   => 'DTS + NEO:X Music',
        DENON_API_Commands::DTSPLUSNEOXG   => 'DTS + NEO:X Game',
        DENON_API_Commands::DTSNEOXC       => 'DTS + NEO:X Cinema',
        DENON_API_Commands::DTSNEOXM       => 'DTS + NEO:X Music',
        DENON_API_Commands::DTSNEOXG       => 'DTS + NEO:X Game',
        DENON_API_Commands::DTSPLUSNEURALX => 'DTS + Neural:X',
        DENON_API_Commands::NEURALX        => 'Neural:X',
        DENON_API_Commands::VIRTUALX        => 'Virtual:X',
        DENON_API_Commands::MULTICNIN      => 'Multi Channel In',
        DENON_API_Commands::MULTICHIN71    => 'Multi Channel In 7.1',
        DENON_API_Commands::MCHINDOLBYEX   => 'Multi Channel In + Dolby Ex',
        DENON_API_Commands::MCHINPL2XC     => 'Multi Channel In + Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::MCHINPL2XM     => 'Multi Channel In + Dolby Pro Logic IIx Music',
        DENON_API_Commands::MCHINPL2ZH     => 'Multi Channel In + Dolby Pro Logic IIx Height',
        DENON_API_Commands::MCHINDSUR      => 'Multi Channel In + DSUR',
        DENON_API_Commands::MCHINNEURALX   => 'Multi Channel In + Neural:X',
        DENON_API_Commands::MCHINDS        => 'Multi Channel In + Dolby Surround',
        DENON_API_Commands::MCHINNEOXC     => 'Multi Channel In + NEO:X Cinema',
        DENON_API_Commands::MCHINNEOXM     => 'Multi Channel In + NEO:X Music',
        DENON_API_Commands::MCHINNEOXG     => 'Multi Channel In + NEO:X Game',
        DENON_API_Commands::DTSHD          => 'DTS HD',
        DENON_API_Commands::DTSHDMSTR      => 'DTS HD Master',
        DENON_API_Commands::DTSHDNEO6      => 'DTS HD + NEO:6',
        DENON_API_Commands::DTSHDPL2XC     => 'DTS HD + Dolby Pro Logic IIx Cinema',
        DENON_API_Commands::DTSHDPL2XM     => 'DTS HD + Dolby Pro Logic IIx Music',
        DENON_API_Commands::DTSHDPL2ZH     => 'DTS HD + Dolby Pro Logic IIx Height',
        DENON_API_Commands::DTSHDDSUR      => 'DTS HD + DSUR',
        DENON_API_Commands::DTSES8CHDSCRT  => 'DTS Express 8 Channel Discrect',
        DENON_API_Commands::DTSHDDS        => 'DTS HD + Dolby Surround',
        DENON_API_Commands::DTSEXPRESS     => 'DTS Express',
        DENON_API_Commands::MPEG2AAC       => 'MPEG2 AAC',
        DENON_API_Commands::MPEG4AAC       => 'MPEG4 AAC',
        DENON_API_Commands::MPEGH          => 'MPEG-H',
        DENON_API_Commands::AACDOLBYEX     => 'AAC + Dolby EX',
        DENON_API_Commands::AACPL2XC       => 'AAC + PL2X Cinema',
        DENON_API_Commands::AACPL2XM       => 'AAC + PL2X Music',
        DENON_API_Commands::AACPL2ZH       => 'AAC + PL2Z Height',
        DENON_API_Commands::AACDSUR        => 'AAC + DSUR',
        DENON_API_Commands::AACDS          => 'AAC + Dolby Surround',
        DENON_API_Commands::AACNEOXC       => 'AAC + NEO:X Cinema',
        DENON_API_Commands::AACNEOXM       => 'AAC + NEO:X Music',
        DENON_API_Commands::AACNEOXG       => 'AAC + NEO:X Game',
        DENON_API_Commands::PLDSX          => 'Dolby Pro Logic DSX',
        DENON_API_Commands::PL2CDSX        => 'Dolby Pro Logic II Cinema DSX',
        DENON_API_Commands::PL2MDSX        => 'Dolby Pro Logic II Music DSX',
        DENON_API_Commands::PL2GDSX        => 'Dolby Pro Logic II Game DSX',
        DENON_API_Commands::PL2XCDSX       => 'Dolby Pro Logic IIx Cinema DSX',
        DENON_API_Commands::PL2XMDSX       => 'Dolby Pro Logic IIx Music DSX',
        DENON_API_Commands::PL2XGDSX       => 'Dolby Pro Logic IIx Game DSX',
        DENON_API_Commands::AUDYSSEYDSX    => 'Audyssey DSX',
        DENON_API_Commands::NEO6CDSX       => 'NEO:6 Cinema DSX',
        DENON_API_Commands::NEO6MDSX       => 'NEO:6 Music DSX',
        DENON_API_Commands::DTSNEO6C       => 'DTS NEO:6 Cinema',
        DENON_API_Commands::DTSNEO6M       => 'DTS NEO:6 Music',
        DENON_API_Commands::DSDDIRECT      => 'DSD Direct',
        DENON_API_Commands::DSDPUREDIRECT  => 'DSD Pure Direct',
    ];

    private $Logger_Dbg;


    public function __construct($AVRType, array $Data, callable $Logger_Dbg)
    {
        if ($AVRType === null) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': AVRType ist nicht gesetzt!');
        }

        $this->AVRType = $AVRType;
        $this->Data = $Data;

        $this->Logger_Dbg = $Logger_Dbg;

    }

    private function getshowsurrounddisplay($response)
    {
        $showsurrounddisplay = '';
        if (array_key_exists($response, static::$SurroundModes)) {
            $showsurrounddisplay = static::$SurroundModes[$response];
        } elseif (array_key_exists($response, static::$DolbySurroundModes)) {
            $showsurrounddisplay = static::$DolbySurroundModes[$response];
        } elseif (array_key_exists($response, static::$DTSSurroundModes)) {
            $showsurrounddisplay = static::$DTSSurroundModes[$response];
        } else {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': unknown surround mode response: ' . $response);
        }

        return $showsurrounddisplay;
    }

    private function getDisplay($data): array
    {
        $debug = false;
        if ($debug){
            call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'data: ' . json_encode($data, JSON_THROW_ON_ERROR));
        }

        $Display = [];

        foreach ($data as $key => $response) {
            $Row = substr($response, 3, 1);
            if ((stripos($response, 'NSA') === 0) || (stripos($response, 'NSE') === 0)) { //Display auslesen
                if ($debug) {
                    call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'response (' . $key . ') found: ' . json_encode($response, JSON_THROW_ON_ERROR));
                    call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'response (' . $key . ') found (hex): ' . bin2hex($response));
                }
                //the first characters ('NSEx', 'NSAx') are cut
                $response = rtrim(substr($response, 4));

                $Display[$Row] = $response;
            }

        }

        return $Display;
    }

    public function GetCommandResponse($InputMapping): ?array
    {
        $debug = false;
        foreach ($this->Data as $response) {
            if (str_starts_with($response, "SSINF") || str_starts_with($response, "SSSINF")){
                $debug = false; //Entwickleroption
            }
        }

        //Debug Log
        if ($debug) {
            call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'data: ' . json_encode($this->Data, JSON_THROW_ON_ERROR));
        }

        // Response an besondere Idents anpassen
        $specialcommands = [DENON_API_Commands::CINEMAEQCOMMAND . '.OFF'           => 'PSCINEMA_EQ.OFF',
            DENON_API_Commands::CINEMAEQCOMMAND . '.ON'                            => 'PSCINEMA_EQ.ON',
            DENON_API_Commands::TONECTRL . ' OFF'                                  => 'PSTONE_CTRL OFF',
            DENON_API_Commands::TONECTRL . ' ON'                                   => 'PSTONE_CTRL ON',
            DENON_API_Commands::PSEFF . ' ON'                                      => 'PSEFF_ON',
            DENON_API_Commands::PSEFF . ' OFF'                                     => 'PSEFF_OFF',
            DENON_API_Commands::SLP . ' OFF'                                       => DENON_API_Commands::SLP . 'OFF',
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTOFF                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTOFF,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTSTD                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTSTD,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTMOV                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTMOV,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTVVD                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTVVD,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTSTM                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTSTM,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTCTM                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTCTM,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTDAY                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTDAY,
            DENON_API_Commands::PV . DENON_API_Commands::PVPICTNGT                 => DENON_API_Commands::PVPICT . DENON_API_Commands::PVPICTNGT,
        ];

        if (in_array($this->AVRType, ['DRA-N5', 'RCD-N8'])) {
            $specialcommands[DENON_API_Commands::SI . DENON_API_Commands::IS_USB_IPOD] = DENON_API_Commands::SI . DENON_API_Commands::IS_USB; //not documented, but tested
        }

        if ($this->AVRType === 'AVR-X1200W') {
            $specialcommands[DENON_API_Commands::SI . DENON_API_Commands::IS_AUX1] = DENON_API_Commands::SI . 'AUX'; //not documented, but tested with AVR-X1200W
        }

        // add special commands for zone responses
        for ($Zone = 2; $Zone <= 3; $Zone++) {
            $specialcommands['Z' . $Zone . 'ON'] = 'Z' . $Zone . 'POWERON';
            $specialcommands['Z' . $Zone . 'OFF'] = 'Z' . $Zone . 'POWEROFF';

            // add specialcommands for input settings
            foreach (DENON_API_Commands::$SI_InputSettings as $InputSetting) {
                $specialcommands['Z' . $Zone . $InputSetting] = 'Z' . $Zone . 'INPUT' . $InputSetting;
            }

            // add special commands for volume response Z2** and Z3**
            for ($Vol = 0; $Vol <= 99; $Vol++) {
                $formattedVolume = str_pad((string) $Vol, 2, '0', STR_PAD_LEFT);
                $specialcommands['Z' . $Zone . $formattedVolume] = 'Z' . $Zone . 'VOL' . $formattedVolume;
            }
        }

        foreach ($this->Data as $key => $response) {
            if (array_key_exists($response, $specialcommands)) {
                if ($debug) {
                    call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, $this->Data[$key] . ' replaced by ' . $specialcommands[$response]);
                }
                $this->Data[$key] = $specialcommands[$response];
            }
        }

        $datavalues = [];
        $SurroundDisplay = '';

        //Response einzeln auswerten
        $VarMapping = new DENONIPSProfiles($this->AVRType, $InputMapping)->GetVariableProfileMapping();


        if ($VarMapping === false) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': VarMapping failed');
        }

        foreach ($this->Data as $response) {
            if (str_starts_with($response, 'NS')) {
                //die Antworten 'NSA' und 'NSE' werden separat in getDisplay ausgewertet
                continue;
            }

            //Antworten wie 'SSINF', 'AISFSV', 'AISSIG', 'SSSMV', 'SSSMG', 'SSALS' sind laut Denon Support zu ignorieren
            //auch mit SDARC, OPT, MS MAXxxx, OPSTS, OPINF und CVEND können wir nichts anfangen
            //auch TF ignorieren wir, um es nicht speziell für die Anzeige aufbereiten zu müssen.
            //auch DAB Status Informationen ignorieren: DASTN, DAPTY, DAENL, DAFRQ, DAQUA, DAINF
            //auch SDEARC und SDARC ignorieren
            $commandToBeIgnored = false;
            foreach (
                [
                    'SSQS',
                    'SSINFSIG',
                    DENON_API_Commands::SSINFAISSIG, //ungeklärt: Beispiel: 'SSINFAISSIG 02'
                    'SSINFMO',
                    'SSFUN',
                    'SSSMG', //ungeklärt: Beispiel 'SSSMG MUS' oder SSSMG PUR
                    'SSAST',
                    'SSAUDSTS',
                    'AIS',
                    'SY_XX',
                    'OPT',
                    'OPSTS',
                    'OPINF',
                    'MVMAX',
                    DENON_API_Commands::SD . DENON_API_Commands::SDARC, //nur als Event
                    DENON_API_Commands::SD . DENON_API_Commands::SDEARC, //nur als Event
                    DENON_API_Commands::CVTTR . ' ON',
                    DENON_API_Commands::CVTTR . ' OFF',
                    'CVEND',
                    'OPALS',
                    'TFANNAME',
                    'TF',
                    'DASTN',
                    'DAPTY',
                    'DAENL',
                    'DAFRQ',
                    'DAQUA',
                    'DAINF',
                ] as $Command
            ) {
                if (str_starts_with($response, $Command)) {
                    $commandToBeIgnored = true;
                    break;
                }
            }

            if ($commandToBeIgnored) {
                continue;
            }

            if (in_array($response, ['TMANAUTO', 'TMANMANUAL'])) {
                $item = $VarMapping[DENON_API_Commands::TMAN_MODE];
                $ResponseSubCommand = substr($response, strlen(DENON_API_Commands::TMAN_MODE));
                $datavalues[DENON_API_Commands::TMAN_MODE] = [
                    'VarType'    => $item['VarType'],
                    'Value'      => $item['ValueMapping'][$ResponseSubCommand],
                    'Subcommand' => $ResponseSubCommand,
                ];
                continue;
            }

            $response_found = false;
            if ($debug) { ///sollte wieder geböscht werden
                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, sprintf('VarMapping: %s', json_encode($VarMapping, JSON_THROW_ON_ERROR)));
            }

            foreach ($VarMapping as $Command => $item) { //Zuordnung suchen
                if (stripos($response, $Command) === 0) {// Subcommand ermitteln
                    $ResponseSubCommand = substr($response, strlen($Command));
                    if ($debug) {
                        call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, sprintf('Command found: %s, SubCommand: %s', $Command, $ResponseSubCommand));
                    }

                    /** @noinspection DegradedSwitchInspection */
                    switch ($Command) {

                        case DENON_API_Commands::MS:
                            $SurroundDisplay = $this->getshowsurrounddisplay($ResponseSubCommand);

                            if (array_key_exists($ResponseSubCommand, static::$DolbySurroundModes)) {
                                $datavalues[DENON_API_Commands::MS] = ['VarType'    => $item['VarType'],
                                    'Value'                                         => $item['ValueMapping'][DENON_API_Commands::MSDOLBYDIGITAL],
                                    'Subcommand'                                    => DENON_API_Commands::MSDOLBYDIGITAL,
                                ];
                            } elseif (array_key_exists($ResponseSubCommand, static::$DTSSurroundModes)) {
                                $datavalues[DENON_API_Commands::MS] = ['VarType'    => $item['VarType'],
                                    'Value'                                         => $item['ValueMapping'][DENON_API_Commands::MSDTSSURROUND],
                                    'Subcommand'                                    => DENON_API_Commands::MSDTSSURROUND,
                                ];
                            } elseif (array_key_exists($ResponseSubCommand, static::$SurroundModes)) {
                                $datavalues[DENON_API_Commands::MS] = ['VarType'    => $item['VarType'],
                                    'Value'                                         => $item['ValueMapping'][$ResponseSubCommand],
                                    'Subcommand'                                    => $ResponseSubCommand,
                                ];
                            }
                            break;

                        default:
                            if (!isset($item['ValueMapping'])) {
                                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'ValueMapping not set - Item: ' . json_encode(
                                                                                  $item,
                                                                                  JSON_THROW_ON_ERROR
                                                                              )
                                );

                                return null;
                            }

                            if ($item['ValueMapping'] === []) {
                                $datavalues[$Command] = [
                                    'VarType'    => $item['VarType'],
                                    'Value'      => trim($ResponseSubCommand),
                                    'Subcommand' => trim($ResponseSubCommand),
                                ];
                            }
                            elseif (array_key_exists($ResponseSubCommand, $item['ValueMapping'])) {
                                $datavalues[$Command] = [
                                    'VarType'    => $item['VarType'],
                                    'Value'      => $item['ValueMapping'][$ResponseSubCommand],
                                    'Subcommand' => $ResponseSubCommand,
                                ];
                            } elseif (in_array($Command, [DENON_API_Commands::SI, DENON_API_Commands::Z2INPUT, DENON_API_Commands::Z3INPUT], true) && in_array($ResponseSubCommand, [DENON_API_Commands::IS_FAVORITES, DENON_API_Commands::IS_IRADIO, DENON_API_Commands::IS_SERVER, DENON_API_Commands::IS_NAPSTER, DENON_API_Commands::IS_LASTFM, DENON_API_Commands::IS_FLICKR], true)) {
                                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, sprintf('*Hint*: Input Source %s not configured, check your configuration. Current inputs: %s'
                                    ,                                                   $ResponseSubCommand,
                                                                                        json_encode($item['ValueMapping'], JSON_THROW_ON_ERROR)
                                ));
                            } else {
                                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, sprintf('*Warning*: No value found for SubCommand \'%s\' in response \'%s\', ValueMapping: %s, Model: %s'
                                    ,                                                   $ResponseSubCommand, $response,
                                                                                        json_encode($item['ValueMapping'], JSON_THROW_ON_ERROR), $this->AVRType));
                            }
                            break;
                    }

                    $response_found = true;
                    break;
                }
            }
            if (!$response_found) {
                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, sprintf('*Warning*: No mapping found for response \'%s\', Model: %s', $response, $this->AVRType));
            }
        }

        $datasend = [
            'ResponseType'    => 'TELNET',
            'Data'            => $datavalues,
            'SurroundDisplay' => $SurroundDisplay,
            'Display'         => $this->getDisplay($this->Data),
        ];

        //Debug Log
        if ($debug) {
            call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'datasend:' . json_encode($datasend, JSON_THROW_ON_ERROR));
        }

        return $datasend;
    }
}
