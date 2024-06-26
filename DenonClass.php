<?php

declare(strict_types=1);
require_once __DIR__ . '/AVRModels.php';  // diverse Klassen

class AVRModule extends IPSModule
{
    private const PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE = 'WriteDebugInformationToLogfile';

    protected bool $testAllProperties = false;

    private const STATUS_INST_IP_IS_INVALID = 204; //IP-Adresse ist ungültig
    private const STATUS_INST_NO_MANUFACTURER_SELECTED = 210;
    private const STATUS_INST_NO_NEO_CATEGORY_SELECTED = 211;
    private const STATUS_INST_NO_ZONE_SELECTED = 212;
    private const STATUS_INST_NO_DENON_AVR_TYPE_SELECTED = 213;
    private const STATUS_INST_NO_MARANTZ_AVR_TYPE_SELECTED = 214;

    protected function SetInstanceStatus(): bool
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return false;
        }
        //Zone prüfen
        $Zone = $this->ReadPropertyInteger('Zone');
        $manufacturer = $this->ReadPropertyInteger('manufacturer');

        $Status = IS_INACTIVE;

        if ($manufacturer === 0) {
            // Error Manufacturer auswählen
            $Status = self::STATUS_INST_NO_MANUFACTURER_SELECTED;
        } elseif ($manufacturer === 1 && $this->ReadPropertyInteger('AVRTypeDenon') === 50) {
            // Error Denon AVR Type auswählen
            $Status = self::STATUS_INST_NO_DENON_AVR_TYPE_SELECTED;
        } elseif ($manufacturer === 2 && $this->ReadPropertyInteger('AVRTypeMarantz') === 50) {
            // Error Marantz AVR Type auswählen
            $Status = self::STATUS_INST_NO_MARANTZ_AVR_TYPE_SELECTED;
        } elseif ($Zone === 6) {
            // Error Zone auswählen
            $Status = self::STATUS_INST_NO_ZONE_SELECTED;
        } elseif (!$this->isNeoCategoryValid()) {
            // Error keine gültige Category ausgewählt
            $Status = self::STATUS_INST_NO_NEO_CATEGORY_SELECTED;
        } elseif ($this->GetIPParent() === false) {
            // Status keine gültige IP
            $Status = self::STATUS_INST_IP_IS_INVALID;
        } elseif ($this->HasActiveParent()) {
            $Status = IS_ACTIVE;
        }

        $this->SetStatus($Status);

        return $Status === IS_ACTIVE;
    }

    private function isNeoCategoryValid(): bool
    {
        if ($this->ReadPropertyBoolean('NEOToggle')) {
            $CatId = $this->ReadPropertyInteger('NEOToggleCategoryID');
            if (!IPS_ObjectExists($CatId) || ((int) IPS_GetObject($CatId)['ObjectType'] !== OBJECTTYPE_CATEGORY)) {
                return false;
            }
        }

        return true;
    }

    // Daten vom Splitter Instanz
    public function ReceiveData($JSONString):void
    {

        // Empfangene Daten vom Splitter
        $data = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $this->Logger_Dbg(__FUNCTION__, json_encode($data->Buffer->Data, JSON_THROW_ON_ERROR));
        $this->UpdateVariable($data->Buffer);
    }

    // Wertet Response aus und setzt Variable
    protected function UpdateVariable($data): bool
    {
        //$data = json_decode('{"ResponseType":"TELNET","Data":[],"SurroundDisplay":"","Display":{"1":"\u0001GAMPER & DADONI - BITTERSWEET SYMPHONY (feat. Emily Roberts)","2":"\u0001Radio 7"}}');
        $this->Logger_Dbg(__FUNCTION__, 'data: ' . json_encode($data, JSON_THROW_ON_ERROR));

        $ResponseType = $data->ResponseType;

        $Zone = $this->ReadPropertyInteger('Zone');
        $this->Logger_Dbg(__FUNCTION__, sprintf('ResponseType: %s, Zone: %s', $ResponseType, $Zone));

        $datavalues = null;

        switch ($ResponseType) {
            case 'HTTP':
                if ($Zone === 0) {
                    $datavalues = $data->Data->Mainzone;
                } elseif ($Zone === 1) {
                    $datavalues = $data->Data->Zone2;
                } elseif ($Zone === 2) {
                    $datavalues = $data->Data->Zone3;
                }
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
                            SetValueString($this->GetIDForIdent('SurroundDisplay'), $SurroundDisplay);
                        }
                    }
                    // OnScreenDisplay
                    if ($this->ReadPropertyBoolean('Display')) {
                        $OnScreenDisplay = $data->Display;
                        $this->Logger_Dbg(__FUNCTION__, 'Display: ' . json_encode($OnScreenDisplay, JSON_THROW_ON_ERROR));

                        $idDisplay = $this->GetIDForIdent(DENON_API_Commands::DISPLAY);
                        $DisplayHTML = GetValue($idDisplay);
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

                        SetValueString($idDisplay, $doc->saveHTML());
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
            $Ident = str_replace(' ', '_', $Ident); //Ident Leerzeichen von Command mit _ ersetzten
            $VarID = @$this->GetIDForIdent($Ident);
            if ($VarID > 0) {
                $VarType = $Values->VarType;
                $Subcommand = $Values->Subcommand;
                $Subcommandvalue = $Values->Value;
                switch ($VarType) {
                    case 0: //Boolean
                        SetValueBoolean($VarID, $Subcommandvalue);
                        $this->Logger_Dbg(__FUNCTION__, 'Update ObjektID ' . $VarID . ' (' . IPS_GetName($VarID) . '): ' . $Subcommand . '(' . (int) $Subcommandvalue . ')');
                        break;
                    case 1: //Integer
                        SetValueInteger($VarID, $Subcommandvalue);
                        $this->Logger_Dbg(__FUNCTION__, 'Update ObjektID ' . $VarID . ' (' . IPS_GetName($VarID) . '): ' . $Subcommand . '(' . $Subcommandvalue . ')');
                        break;
                    case 2: //Float
                        SetValueFloat($this->GetIDForIdent($Ident), is_numeric($Subcommandvalue)?$Subcommandvalue:0);
                        $this->Logger_Dbg(__FUNCTION__, 'Update ObjektID ' . $VarID . ' (' . IPS_GetName($VarID) . '): ' . $Subcommand . '(' . $Subcommandvalue . ')');
                        break;
                    case 3: //String
                        SetValueString($this->GetIDForIdent($Ident), $Subcommandvalue);
                        $this->Logger_Dbg(__FUNCTION__, 'Update ObjektID ' . $VarID . ' (' . IPS_GetName($VarID) . '): ' . $Subcommandvalue);
                        break;
                    default:
                        trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': invalid VarType: ' . $VarType);
                }
            } else {
                $this->Logger_Dbg(__FUNCTION__, $this->InstanceID . ': Info: Keine Variable mit dem Ident "' . $Ident . '" gefunden.');
            }
        }

        return true;
    }

    protected function RegisterProperties(): void
    {
        //Expert Parameters, must set first, because Logging functions are using it
        $this->RegisterPropertyBoolean(self::PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE, false);

        $this->RegisterPropertyInteger('manufacturer', 0);
        $this->RegisterPropertyInteger('AVRTypeDenon', 50);
        $this->RegisterPropertyInteger('AVRTypeMarantz', 50);
        $this->RegisterPropertyInteger('Zone', 6);

        // all Checkboxes for the selection of the variables have to be registered
        $DenonAVRVar = new DENONIPSProfiles(null, null, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        });

        $profiles = $DenonAVRVar->GetAllProfiles();
        foreach ($profiles as $profile) {
            //some variables were registered with 'true' in the former version. So due to compatibility reasons they were registered with 'true' again
            $DefaultValue = in_array(
                $profile['PropertyName'],
                [
                    DENONIPSProfiles::ptPower,
                    DENONIPSProfiles::ptMainZonePower,
                    DENONIPSProfiles::ptMainMute,
                    'InputSource',
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
                    ],
                true
            );
            $this->Logger_Dbg(__FUNCTION__, 'Property registered: ' . $profile['PropertyName'] . '(' . (int) $DefaultValue . ')');
            $this->RegisterPropertyBoolean($profile['PropertyName'], $DefaultValue);
        }

        //Zusätzliche Inputs
        $this->RegisterPropertyBoolean('FAVORITES', false);
        $this->RegisterPropertyBoolean('IRADIO', false);
        $this->RegisterPropertyBoolean('SERVER', false);
        $this->RegisterPropertyBoolean('NAPSTER', false);
        $this->RegisterPropertyBoolean('LASTFM', false);
        $this->RegisterPropertyBoolean('FLICKR', false);

        //Neo
        $this->RegisterPropertyBoolean('NEOToggle', false);
        $this->RegisterPropertyInteger('NEOToggleCategoryID', 0);

    }

    protected function RegisterReferences(): void
    {
        $objectIDs = [
            $this->ReadPropertyInteger('NEOToggleCategoryID')
        ];

        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }

        foreach ($objectIDs as $id) {
            if ($id !== 0) {
                $this->RegisterReference($id);
            }
        }
    }

    protected function RegisterVariables(DENONIPSProfiles $DenonAVRVar, $idents, $AVRType, $manufacturername): bool
    {
        $this->Logger_Dbg(__FUNCTION__, 'idents: ' . json_encode($idents, JSON_THROW_ON_ERROR));

        if (!in_array($manufacturername, [DENONIPSProfiles::ManufacturerDenon, DENONIPSProfiles::ManufacturerMarantz], true)) {
            trigger_error('ManufacturerName not set');

            return false;
        }

        // Add/Remove according to feature activation

        //Selektierte Variablen anlegen
        foreach ($idents as $ident => $selected) {
            $statusvariable = $DenonAVRVar->SetupVariable($ident);

            //Auswahl Prüfen
            if ($selected) {
                switch ($statusvariable['Type']) {
                    case DENONIPSVarType::vtString:
                        if ($statusvariable['ProfilName'] === '~HTMLBox') {
                            $profilname = '~HTMLBox';
                        } else {
                            $profilname = $manufacturername . '.' . $AVRType . '.' . $statusvariable['ProfilName'];
                            $this->CreateProfileString($profilname, $statusvariable['Icon']);
                        }

                        $this->RegisterVariableString($statusvariable['Ident'], $statusvariable['Name'], $profilname, $statusvariable['Position']);

                        if ($ident === DENON_API_Commands::DISPLAY) {
                            $DisplayHTML = '<!--suppress HtmlRequiredLangAttribute -->
<html><body><div id="NSARow0"></div><div id="NSARow1"></div><div id="NSARow2"></div><div id="NSARow3"></div><div id="NSARow4"></div><div id="NSARow5"></div><div id="NSARow6"></div><div id="NSARow7"></div><div id="NSARow8"></div></body></html>';
                            SetValueString($this->GetIDForIdent(DENON_API_Commands::DISPLAY), $DisplayHTML);
                        }
                        break;

                    case DENONIPSVarType::vtBoolean:
                        $this->RegisterVariableBoolean($statusvariable['Ident'], $statusvariable['Name'], '~Switch', $statusvariable['Position']);
                        break;

                    case DENONIPSVarType::vtInteger:
                        $profilname = $manufacturername . '.' . $AVRType . '.' . $statusvariable['ProfilName'];
                        $this->CreateProfileIntegerAss(
                            $profilname,
                            $statusvariable['Icon'],
                            $statusvariable['Prefix'],
                            $statusvariable['Suffix'],
                            $statusvariable['Stepsize'],
                            $statusvariable['Digits'],
                            $statusvariable['Associations']
                        );

                        $this->RegisterVariableInteger($statusvariable['Ident'], $statusvariable['Name'], $profilname, $statusvariable['Position']);
                        break;

                    case DENONIPSVarType::vtFloat:
                        $profilname = $manufacturername . '.' . $AVRType . '.' . $statusvariable['ProfilName'];
                        $this->CreateProfileFloat($profilname, $statusvariable['Icon'], $statusvariable['Prefix'], $statusvariable['Suffix'], $statusvariable['MinValue'], $statusvariable['MaxValue'], $statusvariable['Stepsize'], $statusvariable['Digits']);
                        $this->Logger_Dbg(__FUNCTION__, 'Variablenprofil angelegt: ' . $profilname);
                        $this->RegisterVariableFloat($statusvariable['Ident'], $statusvariable['Name'], $profilname, $statusvariable['Position']);
                        break;

                    default:
                        trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': invalid Type: ' . $statusvariable['Type']);

                        return false;

                }

                if (!isset($statusvariable['displayOnly']) || !$statusvariable['displayOnly']){
                    $this->EnableAction($statusvariable['Ident']);
                }

            }
            // wenn nicht, selektiert löschen
            else {
                $this->removeVariableAction($statusvariable['Ident'], $ident);
            }
        }

        return true;
    }

    protected function CreateNEOScripts($NEO_Parameter): void
    {
        // alle Instanzvariablen vom Typ boolean suchen
        $ObjectIds = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($ObjectIds as $ObjectId) {
            // wenn es sich um eine Variable handelt und die vom Typ Boolean ist
            $obj = IPS_GetObject($ObjectId);
            if (($obj['ObjectType'] === 2 /*Variable*/) && IPS_GetVariable($ObjectId)['VariableType'] === DENONIPSVarType::vtBoolean) {
                $Ident = $obj['ObjectIdent'];
                if (array_key_exists($Ident, $NEO_Parameter)) {
                    $this->WriteNEOScript($ObjectId, $NEO_Parameter[$Ident][0], $NEO_Parameter[$Ident][1]);
                }
            }
        }
    }

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID); //array
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : 0; //ConnectionID
    }

    private function checkProfileType($ProfileName, $VarType): void
    {
        $profile = IPS_GetVariableProfile($ProfileName);
        if ($profile['ProfileType'] !== $VarType) {
            trigger_error('Variable profile type does not match for already existing profile "' . $ProfileName . '". The existing profile has to be deleted manually.');
        }
    }

    private function CreateProfileInteger($ProfileName, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits): void
    {
        if (!IPS_VariableProfileExists($ProfileName)) {
            IPS_CreateVariableProfile($ProfileName, 1);

            $this->Logger_Inf('Variablenprofil angelegt: ' . $ProfileName);
        } else {
            $this->checkProfileType($ProfileName, 1); //integer
        }

        IPS_SetVariableProfileIcon($ProfileName, $Icon);
        IPS_SetVariableProfileText($ProfileName, $Prefix, $Suffix);
        IPS_SetVariableProfileDigits($ProfileName, $Digits); //  Nachkommastellen
        IPS_SetVariableProfileValues($ProfileName, $MinValue, $MaxValue, $StepSize);
    }

    private function CreateProfileIntegerAss($ProfileName, $Icon, $Prefix, $Suffix, $StepSize, $Digits, $Associations): void
    {
        if (count($Associations) === 0) {
            trigger_error(__FUNCTION__ . ': Associations of profil "' . $ProfileName . '" is empty');
            $this->Logger_Err(__FUNCTION__ . ': ' .  json_encode(debug_backtrace(), JSON_THROW_ON_ERROR));

            return;
        }

        $MinValue = 0;
        $MaxValue = 0;

        $this->CreateProfileInteger($ProfileName, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits);

        //zunächst werden alte Assoziationen gelöscht
        //bool IPS_SetVariableProfileAssociation ( string $ProfilName, float $Wert, string $Name, string $Icon, integer $Farbe )
        foreach (IPS_GetVariableProfile($ProfileName)['Associations'] as $Association) {
            IPS_SetVariableProfileAssociation($ProfileName, $Association['Value'], '', '', -1);
        }

        //dann werden die aktuellen eingetragen
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($ProfileName, $Association[0], $Association[1], '', -1);
        }
    }

    private function CreateProfileString($ProfileName, $Icon): void
    {
        if (!IPS_VariableProfileExists($ProfileName)) {
            IPS_CreateVariableProfile($ProfileName, DENONIPSVarType::vtString);

            $this->Logger_Inf('Variablenprofil angelegt: ' . $ProfileName);
        } else {
            $this->checkProfileType($ProfileName, DENONIPSVarType::vtString);
        }

        IPS_SetVariableProfileIcon($ProfileName, $Icon);
    }

    private function CreateProfileFloat($ProfileName, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits): void
    {
        if (!IPS_VariableProfileExists($ProfileName)) {
            IPS_CreateVariableProfile($ProfileName, DENONIPSVarType::vtFloat);

            $this->Logger_Inf('Variablenprofil angelegt: ' . $ProfileName);
        } else {
            $this->checkProfileType($ProfileName, DENONIPSVarType::vtFloat);
        }

        IPS_SetVariableProfileIcon($ProfileName, $Icon);
        IPS_SetVariableProfileText($ProfileName, $Prefix, $Suffix);
        IPS_SetVariableProfileDigits($ProfileName, $Digits); //  Nachkommastellen
        IPS_SetVariableProfileValues($ProfileName, $MinValue, $MaxValue, $StepSize);
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

    protected function removeVariableAction($Ident, $Profile): void
    {
        $vid = @$this->GetIDForIdent($Ident);
        if ($vid !== false) {
            $Name = IPS_GetName($vid);
            $this->DisableAction($Ident);
            $this->UnregisterVariable($Ident);
            $this->Logger_Inf('Variable gelöscht - Name: ' . $Name . ', Ident: ' . $Ident . ', ObjektID: ' . $vid);
            //delete Profile
            if (IPS_VariableProfileExists($Profile)) {
                IPS_DeleteVariableProfile($Profile);
                $this->Logger_Inf('Variablenprofil gelöscht:' . $Profile);
            }
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

    protected function FormSelectionNEO(): array
    {
        return [
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'create helper scripts for toggling with NEO (Mediola)',
                'items'   => [
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'NEOToggle',
                        'caption' => 'create separate NEO toggle scripts'
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => 'category for creating NEO scripts:'
                    ],
                    [
                        'type'    => 'SelectCategory',
                        'name'    => 'NEOToggleCategoryID',
                        'caption' => 'script category'
                    ]
                ]
            ]
        ];
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

    private function WriteNEOScript($ObjectID, $FunctionName, $LogLabel): void
    {
        $InstanzID = IPS_GetParent($ObjectID);
        $InstanzName = IPS_GetName($InstanzID);
        $Name = IPS_GetName($ObjectID);
        $KatID = $this->ReadPropertyInteger('NEOToggleCategoryID');
        $ScriptName = $InstanzName . ' ' . $Name . '_toggle';
        $SkriptID = @IPS_GetScriptIDByName($ScriptName, $KatID);

        if (!$SkriptID) {
            $Content
                = '
<?
$status = GetValueBoolean(' . $ObjectID . '); // Status des Geräts auslesen
if ($status == false)// Einschalten
	{
	' . $FunctionName . '(' . $InstanzID . ', true);
	IPS_LogMessage( "Denon Telnet AVR" , "' . $LogLabel . ' einschalten" );
   }
elseif ($status == true)// Ausschalten
	{
	' . $FunctionName . '(' . $InstanzID . ', false);
	IPS_LogMessage( "Denon Telnet AVR" , "' . $LogLabel . ' ausschalten" );
	}

?>';

            // write Script
            $ScriptID = IPS_CreateScript(0);
            IPS_SetName($ScriptID, $ScriptName);
            IPS_SetParent($ScriptID, $KatID);
            IPS_SetScriptContent($ScriptID, $Content);

        }
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
    public const vtBoolean = 0;
    public const vtInteger = 1;
    public const vtFloat = 2;
    public const vtString = 3;
}

#[AllowDynamicProperties] class DENONIPSProfiles extends stdClass
{
    private $Logger_Dbg;

    private bool  $debug = false; //wird im Constructor gesetzt

    private mixed $AVRType;
    private array $profiles;

    public const ManufacturerDenon = 'Denon';
    public const ManufacturerMarantz = 'Marantz';
    public const ManufacturerNone = 'none';

    //Profiltype
    public const ptPower = 'Power';
    public const ptMasterVolume = 'MasterVolume';
    public const ptBalance = 'Balance';

    public const ptChannelVolumeFL = 'ChannelVolumeFL';
    public const ptChannelVolumeFR = 'ChannelVolumeFR';
    public const ptChannelVolumeC = 'ChannelVolumeC';
    public const ptChannelVolumeSW = 'ChannelVolumeSW';
    public const ptChannelVolumeSW2 = 'ChannelVolumeSW2';
    public const ptChannelVolumeSW3 = 'ChannelVolumeSW3';
    public const ptChannelVolumeSW4 = 'ChannelVolumeSW4';
    public const ptChannelVolumeSL = 'ChannelVolumeSL';
    public const ptChannelVolumeSR = 'ChannelVolumeSR';
    public const ptChannelVolumeSBL = 'ChannelVolumeSBL';
    public const ptChannelVolumeSBR = 'ChannelVolumeSBR';
    public const ptChannelVolumeSB = 'ChannelVolumeSB';
    public const ptChannelVolumeFHL = 'ChannelVolumeFHL';
    public const ptChannelVolumeFHR = 'ChannelVolumeFHR';
    public const ptChannelVolumeFWL = 'ChannelVolumeFWL';
    public const ptChannelVolumeFWR = 'ChannelVolumeFWR';
    public const ptMainMute = 'MainMute';
    public const ptInputSource = 'Inputsource';
    public const ptMainZonePower = 'MainZonePower';
    public const ptInputMode = 'InputMode';
    public const ptDigitalInputMode = 'DigitalInputMode';
    public const ptVideoSelect = 'VideoSelect';
    public const ptSleep = 'Sleep';
    public const ptSurroundMode = 'SurroundMode';
    public const ptQuickSelect = 'QuickSelect';
    public const ptSmartSelect = 'SmartSelect';
    public const ptHDMIMonitor = 'HDMIMonitor';
    public const ptASP = 'ASP';
    public const ptResolution = 'Resolution';
    public const ptResolutionHDMI = 'ResolutionHDMI';
    public const ptHDMIAudioOutput = 'HDMIAudioOutput';
    public const ptVideoProcessingMode = 'VideoProcessingMode';
    public const ptToneCTRL = 'ToneCTRL';
    public const ptSurroundBackMode = 'SurroundBackMode';
    public const ptSurroundPlayMode = 'SurroundPlayMode';
    public const ptFrontHeight = 'FrontHeight';
    public const ptPLIIZHeightGain = 'PLIIZHeightGain';
    public const ptSpeakerOutput = 'SpeakerOutputFront';
    public const ptMultiEQMode = 'MultiEQMode';
    public const ptDynamicEQ = 'DynamicEQ';
    public const ptAudysseyLFC = 'AudysseyLFC';
    public const ptAudysseyContainmentAmount = 'AudysseyContainmantAmount';
    public const ptReferenceLevel = 'ReferenceLevel';
    public const ptDiracLiveFilter = 'DiracLiveFilter';
    public const ptDynamicVolume = 'DynamicVolume';
    public const ptAudysseyDSX = 'AudysseyDSX';
    public const ptStageWidth = 'StageWidth';
    public const ptStageHeight = 'StageHeight';
    public const ptBassLevel = 'BassLevel';
    public const ptTrebleLevel = 'TrebleLevel';
    public const ptLoudnessManagement = 'LoudnessManagement';
    public const ptDynamicRangeCompression = 'DynamicRangeCompression';
    public const ptMDAX = 'MDAX';
    public const ptDynamicCompressor = 'DynamicCompressor';
    public const ptCenterLevelAdjust = 'CenterLevelAdjust';
    public const ptLFELevel = 'LFELevel';
    public const ptLFE71Level = 'LFE71Level';
    public const ptEffectLevel = 'EffectLevel';
    public const ptDelay = 'Delay';
    public const ptAFDM = 'AFDM';
    public const ptPanorama = 'Panorama';
    public const ptDimension = 'Dimension';
    public const ptDialogControl = 'DialogControl';
    public const ptCenterWidth = 'CenterWidth';
    public const ptCenterImage = 'CenterImage';
    public const ptCenterGain = 'CenterGain';
    public const ptSubwoofer = 'Subwoofer';
    public const ptRoomSize = 'RoomSize';
    public const ptAudioDelay = 'AudioDelay';
    public const ptAudioRestorer = 'AudioRestorer';
    public const ptFrontSpeaker = 'FrontSpeaker';
    public const ptContrast = 'Contrast';
    public const ptBrightness = 'Brightness';
    public const ptSaturation = 'Saturation';
    public const ptChromalevel = 'Chromalevel';
    public const ptHue = 'Hue';
    public const ptDigitalNoiseReduction = 'DNRDirectChange';
    public const ptPictureMode = 'PictureMode';
    public const ptEnhancer = 'Enhancer';
    public const ptBluetoothTransmitter = 'BluetoothTransmitter';
    public const ptSpeakerPreset = 'SpeakerPreset';

    public const ptZone2Power = 'Zone2Power';
    public const ptZone2InputSource = 'Zone2InputSource';
    public const ptZone2Volume = 'Zone2Volume';
    public const ptZone2Mute = 'Zone2Mute';
    public const ptZone2ChannelSetting = 'Zone2ChannelSetting';
    public const ptZone2ChannelVolumeFL = 'Zone2ChannelVolumeFL';
    public const ptZone2ChannelVolumeFR = 'Zone2ChannelVolumeFR';
    public const ptZone2HPF = 'Zone2HPF';
    public const ptZone2Bass = 'Zone2Bass';
    public const ptZone2Treble = 'Zone2Treble';
    public const ptZone2QuickSelect = 'Zone2QuickSelect';
    public const ptZone2SmartSelect = 'Zone2SmartSelect';
    public const ptZone2Sleep = 'Zone2Sleep';

    public const ptZone3InputSource = 'Zone3InputSource';
    public const ptZone3Volume = 'Zone3Volume';
    public const ptZone3Mute = 'Zone3Mute';
    public const ptZone3ChannelSetting = 'Zone3ChannelSetting';
    public const ptZone3ChannelVolumeFL = 'Zone3ChannelVolumeFL';
    public const ptZone3ChannelVolumeFR = 'Zone3ChannelVolumeFR';
    public const ptZone3HPF = 'Zone3HPF';
    public const ptZone3Bass = 'Zone3Bass';
    public const ptZone3Treble = 'Zone3Treble';
    public const ptZone3QuickSelect = 'Zone3QuickSelect';
    public const ptZone3SmartSelect = 'Zone3SmartSelect';
    public const ptZone3Sleep = 'Zone3Sleep';

    public const ptCinemaEQ = 'CinemaEQ';
    public const ptHTEQ = 'HTEQ';
    public const ptDynamicRange = 'DynamicRange';
    public const ptPreset = 'Preset';
    public const ptZone2Name = 'Zone2Name';
    public const ptZone3Power = 'Zone3Power';
    public const ptZone3Name = 'Zone3Name';
    public const ptNavigation = 'Navigation';
    public const ptNavigationNetwork = 'NavigationNetwork';
    public const ptSubwooferATT = 'SubwooferATT';
    //public const ptDCOMPDirectChange = 'DCOMPDirectChange';
    public const ptDolbyVolumeLeveler = 'DolbyVolumeLeveler';
    public const ptDolbyVolumeModeler = 'DolbyVolumeModeler';
    public const ptVerticalStretch = 'VerticalStretch';
    public const ptDolbyVolume = 'DolbyVolume';
    public const ptFriendlyName = 'FriendlyName';
    public const ptMainZoneName = 'MainZoneName';
    public const ptTopMenuLink = 'TopMenuLink';
    public const ptModel = 'Model';
    public const ptGUIMenuSourceSelect = 'GUIMenuSourceSelect';
    public const ptGUIMenuSetup = 'GUIMenuSetup';
    public const ptSurroundDisplay = 'SurroundDisplay';
    public const ptDisplay = 'Display';
    public const ptGraphicEQ = 'GraphicEQ';
    public const ptHeadphoneEQ = 'HeadphoneEQ';
    public const ptDimmer = 'Dimmer';
    public const ptDialogLevelAdjust = 'DialogLevelAdjust';
    public const ptMAINZONEAutoStandbySetting = 'MAINZONEAutoStandbySetting';
    public const ptMAINZONEECOModeSetting = 'MAINZONEECOModeSetting';
    public const ptCenterSpread = 'Centerspread';
    public const ptSpeakerVirtualizer = 'SpeakerVirtualizer';
    public const ptNeural = 'Neural';
    public const ptAllZoneStereo = 'AllZoneStereo';
    public const ptAutoLipSync = 'AutoLipSync';
    public const ptBassSync = 'BassSync';
    public const ptSubwooferLevel = 'SubwooferLevel';
    public const ptSubwoofer2Level = 'Subwoofer2Level';
    public const ptSubwoofer3Level = 'Subwoofer3Level';
    public const ptSubwoofer4Level = 'Subwoofer4Level';
    public const ptDialogEnhancer = 'DialogEnhancer';
    public const ptAuroMatic3DPreset = 'AuroMatic3DPreset';
    public const ptAuroMatic3DStrength = 'AuroMatic3DStrength';
    public const ptAuro3DMode = 'Auro3DMode';
    public const ptTopFrontLch = 'TopFrontLch';
    public const ptTopFrontRch = 'TopFrontRch';
    public const ptTopMiddleLch = 'TopMiddleLch';
    public const ptTopMiddleRch = 'TopMiddleRch';
    public const ptTopRearLch = 'TopRearLch';
    public const ptTopRearRch = 'TopRearRch';
    public const ptRearHeightLch = 'RearHeightLch';
    public const ptRearHeightRch = 'RearHeightRch';
    public const ptFrontDolbyLch = 'FrontDolbyLch';
    public const ptFrontDolbyRch = 'FrontDolbyRch';
    public const ptSurroundDolbyLch = 'SurroundDolbyLch';
    public const ptSurroundDolbyRch = 'SurroundDolbyRch';
    public const ptBackDolbyLch = 'BackDolbyLch';
    public const ptBackDolbyRch = 'BackDolbyRch';
    public const ptSurroundHeightLch = 'SurroundHeightLch';
    public const ptSurroundHeightRch = 'SurroundHeightRch';
    public const ptTopSurround = 'TopSurround';
    public const ptCenterHeight = 'CenterHeight';
    public const ptChannelVolumeReset = 'ChannelVolumeReset';
    public const ptTactileTransducer = 'TactileTransducer';
    public const ptZone2HDMIAudio = 'Zone2HDMIAudio';
    public const ptZone2AutoStandbySetting = 'Zone2AutoStandbySetting';
    public const ptZone3AutoStandbySetting = 'Zone3AutoStandbySetting';

    public const ptTunerAnalogPreset = 'TunerAnalogPresets';
    public const ptTunerAnalogBand   = 'TunerAnalogBand';
    public const ptTunerAnalogMode = 'TunerAnalogMode';

    public const ptSYSMI = 'SysMI';
    public const ptSYSDA = 'SysDA';
    public const ptSSINFAISFSV = 'SsInfAISFSV';

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

    public function __construct($AVRType = null, $InputMapping = null, callable $Logger_Dbg = null)
    {
        if (isset($Logger_Dbg)){
            $this->debug = true;
            $this->Logger_Dbg = $Logger_Dbg;
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'AVRType: ' . ($AVRType ?? 'null') . ', InputMapping: ' . ($InputMapping === null ? 'null' : json_encode(
                                                $InputMapping,
                                                JSON_THROW_ON_ERROR
                                            )));
        }

        $assRange00to98_add05step = $this->GetAssociationOfAsciiTodB('00', '98', '80', 1, true, false);
        $assRange00to98 = $this->GetAssociationOfAsciiTodB('00', '98', '80', 1, false, false);
        $assRange38to62 = $this->GetAssociationOfAsciiTodB('38', '62', '50');
        $assRange38to62_add05step = $this->GetAssociationOfAsciiTodB('38', '62', '50', 1, true);
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
            self::ptDialogControl => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIC, 'Name' => 'DialogControl',
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
                'PropertyName'                                            => 'AudysseyContainmentAmount', 'Profilesettings' => ['Intensity',  '', ' dB', 0, 7, 1, 0], 'Associations' => $assRange00to07, ],
            self::ptBassSync => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSBSC, 'Name' => 'BassSync',
                'PropertyName'                                            => 'BassSync', 'Profilesettings' => ['Intensity', '', ' dB', 0, 16, 1, 0], 'Associations' => $assRange00to16, ],
            self::ptSubwooferLevel => ['Type'                             => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL, 'Name' => 'Subwoofer Level',
                'PropertyName'                                            => 'SubwooferLevel', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptSubwoofer2Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL2, 'Name' => 'Subwoofer 2 Level',
                'PropertyName'                                            => 'Subwoofer2Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptSubwoofer3Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL3, 'Name' => 'Subwoofer 3 Level',
                'PropertyName'                                            => 'Subwoofer3Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptSubwoofer4Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL4, 'Name' => 'Subwoofer 4 Level',
                'PropertyName'                                            => 'Subwoofer4Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptDialogLevelAdjust => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIL, 'Name' => 'Dialog Level Adjust',
                'PropertyName'                                            => 'DialogLevelAdjust', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
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
                'PropertyName'                                            => 'Z2CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptZone2ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2CVFR, 'Name' => 'Zone 2 Channel Volume Front Right',
                'PropertyName'                                            => 'Z2CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptZone3ChannelVolumeFL => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFL, 'Name' => 'Zone 3 Channel Volume Front Left',
                'PropertyName'                                            => 'Z3CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptZone3ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFR, 'Name' => 'Zone 3 Channel Volume Front Right',
                'PropertyName'                                            => 'Z3CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62, ],
            self::ptZone2Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSBAS, 'Name' => 'Zone 2 Bass',
                'PropertyName'                                            => 'Z2Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSBAS, 'Name' => 'Zone 3 Bass',
                'PropertyName'                                            => 'Z3Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone2Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSTRE, 'Name' => 'Zone 2 Treble',
                'PropertyName'                                            => 'Z2Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSTRE, 'Name' => 'Zone 3 Treble',
                'PropertyName'                                            => 'Z3Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptSSINFAISFSV => ['Type' => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::SSINFAISFSV, 'Name' => 'Audio: Abtastrate',
                              'PropertyName'                                        => 'SSINFAISFSV', 'Profilesettings' => ['Information', '', ' kHz', 0, 0, 0, 1], 'Associations' => [], 'displayOny' => true],

            //Type String
            self::ptMainZoneName    => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'MainZoneName', 'Name' => 'MainZone Name', 'PropertyName' => 'ZoneName', 'Profilesettings' => ['Information'], 'displayOny' => true],
            self::ptModel           => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Model', 'Name' => 'Model', 'PropertyName' => 'Model', 'Profilesettings' => ['Information'], 'displayOny' => true],
            self::ptSurroundDisplay => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SURROUNDDISPLAY, 'Name' => 'Surround Mode Display',
                                        'PropertyName'                                        => 'SurroundDisplay', 'Profilesettings' => ['Information'], 'displayOny' => true ],
            self::ptSYSMI => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSMI, 'Name' => 'Audio: Soundmodus',
                                        'PropertyName'                                        => 'SYSMI', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOny' => true],
            self::ptSYSDA => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSDA, 'Name' => 'Audio: Eingangssignal',
                                        'PropertyName'                                        => 'SYSDA', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOny' => true],
            self::ptDisplay => ['Type'                                => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::DISPLAY, 'Name' => 'OSD Info', 'ProfilName' => '~HTMLBox', 'PropertyName' => 'Display', 'Profilesettings' => ['TV'],
                'IndividualStatusRequest'                             => 'NSA', 'displayOny' => true],
            self::ptZone2Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone2Name', 'Name' => 'Zone 2 Name', 'PropertyName' => self::ptZone2Name, 'Profilesettings' => ['Information'], 'displayOny' => true],
            self::ptZone3Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone3Name', 'Name' => 'Zone 3 Name', 'PropertyName' => self::ptZone3Name, 'Profilesettings' => ['Information'], 'displayOny' => true],
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

    public function GetInputVarMapping($Zone)
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

    public function SetupVariable($ident)
    {
        if ($this->debug){
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Setup Variable with ident ' . $ident);
        }

        if (!array_key_exists($ident, $this->profiles)) {
            trigger_error('unknown ident: ' . $ident);

            return false;
        }

        $profile = $this->profiles[$ident];
        if (!isset($profile['Type'])) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Type not set in profile "' . $ident . '"');

            return false;
        }

        switch ($profile['Type']) {
            case DENONIPSVarType::vtBoolean:
                $ret = ['Name'     => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => '~Switch',
                    'Position'     => $this->getpos($ident),
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
                    'ProfilName'   => $ident,
                    'Icon'         => $profilesettings[0],
                    'Prefix'       => $profilesettings[1],
                    'Suffix'       => $profilesettings[2],
                    'MinValue'     => $profilesettings[3],
                    'MaxValue'     => $profilesettings[4],
                    'Stepsize'     => $profilesettings[5],
                    'Digits'       => $profilesettings[6],
                    'Associations' => $profile['Associations'],
                    'Position'     => $this->getpos($ident),
                ];
                break;

            case DENONIPSVarType::vtString:
                $profilename=$profile['ProfilName'] ?? $ident;
                $ret        = [
                    'Name'         => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => $profilename,
                    'Position'     => $this->getpos($ident),
                    'Icon'         => $profile['Profilesettings'][0],
                ];
                break;

            default:
                trigger_error('unknown profile type: ' . $profile['Type']);

                return false;

        }

        return $ret;
    }

    public function GetVarMapping(): array
    {
        $ret = [];

        foreach ($this->profiles as $profile) {
            if (isset($profile['Associations'])) {
                $ValueMapping = [];
                foreach ($profile['Associations'] as $association) {
                    switch ($profile['Type']) {
                        case DENONIPSVarType::vtBoolean:
                            $ValueMapping[$association[1]] = $association[0];
                            break;
                        case DENONIPSVarType::vtInteger:
                            $ValueMapping[$association[2]] = $association[0];
                            break;
                        case DENONIPSVarType::vtFloat:
                            $ValueMapping[$association[0]] = $association[1];
                            break;
                        case DENONIPSVarType::vtString:
                            break;
                        default:
                            trigger_error(__FUNCTION__ . ': unexpected type: ' . $profile['Type']);
                    }
                }
                $ret[$profile['Ident']] = ['VarType' => $profile['Type'], 'ValueMapping' => $ValueMapping];
            }
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

    public function GetSubCommandOfValue(string $Ident, $Value): ?string
    {
        $ret = null;
        foreach ($this->profiles as $profile) {
            if (($profile['Ident'] === $Ident) && isset($profile['Associations'])) {
                if ($this->debug){
                    call_user_func($this->Logger_Dbg, __FUNCTION__, 'Profile "' . $Ident . '" found: ' . json_encode($profile, JSON_THROW_ON_ERROR));
                }
                foreach ($profile['Associations'] as $item) {
                    switch ($profile['Type']) {
                        case DENONIPSVarType::vtBoolean:
                            if ($item[0] === $Value) {
                                $ret = $item[1];
                            }
                            break;
                        case DENONIPSVarType::vtInteger:
                            if ($item[0] === $Value) {
                                $ret = $item[2];
                            }
                            break;
                        case DENONIPSVarType::vtFloat:
                            if (round($item[1], 1) === round($Value, 1)) { //Float Werte mit Nachkommastellen müssen zum Vergleich gerundet werden!
                                $ret = $item[0];
                            }
                            break;
                        default:
                            trigger_error(__FUNCTION__ . ': unknown type: ' . $profile['Type']);
                    }
                    if ($ret !== null) {
                        break;
                    }
                }
                if ($ret === null) {
                    trigger_error('no association found. Ident: ' . $Ident . ', Value: ' . $Value);
                    return null;
                }

                break;
            }
        }

        if ($ret === null) {
            trigger_error('no profile found. Ident: ' . $Ident . ', Value: ' . $Value);
            return null;
        }

        return (string) $ret;
    }

    public function GetSubCommandOfValueName(string $Ident, string $ValueName): ?string
    {
        $ret = null;
        foreach ($this->profiles as $profile) {
            if (($profile['Ident'] === $Ident) && isset($profile['Associations'])) {
                foreach ($profile['Associations'] as $item) {
                    if ($profile['Type'] == DENONIPSVarType::vtInteger) {
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

    private function getpos($profilename)
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

    private function GetAssociationOfAsciiTodB($ascii_from, $ascii_to, $ascii_of_0, $db_stepsize = 1, $add_05step = false, $leading_blank = true, $invertValue = false, $scalefactor_to_db = 1): array
    {
        if (($db_stepsize <= 0) || ($scalefactor_to_db <= 0)) {
            trigger_error('StepSize and ScaleFactor must be greater than 0');

            return [];
        }

        $db = 0 - (int) $ascii_of_0 + (int) $ascii_from;
        $db_to = ((int) $ascii_to - (int) $ascii_of_0) * $scalefactor_to_db;

        $value_mapping = [];

        if (!$invertValue) {
            $faktor = 1;
        } else {
            $faktor = -1;
        }

        if ($leading_blank) {
            $prefix = ' ';
        } else {
            $prefix = '';
        }

        while ($db <= $db_to) {
            $ascii = (int) ($ascii_of_0 + $db / $scalefactor_to_db);
            $pad_length = strlen($ascii_to);
            $ascii = str_pad((string) $ascii, $pad_length, '0', STR_PAD_LEFT);

            $value_mapping[] = [$prefix . $ascii, $db * $faktor];

            if ($add_05step && ($db < $db_to)) {
                $value_mapping[] = [$prefix . $ascii . '5', ($db + 0.5) * $faktor];
            }

            $db+=$db_stepsize;
        }

        return $value_mapping;
    }
}

class DENON_StatusHTML extends stdClass
{
    private bool $debug = false; //wird im Constructor gesetzt
    private $Logger_Dbg;

    public function __construct(callable $Logger_Dbg = null)
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

        $VarMappings = $DenonAVRVar->GetVarMapping();
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
    public const NoHTTPInterface = '';
    public const MainForm_old = '/goform/formMainZone_MainZoneXml.xml';
    public const MainForm = '/goform/formMainZone_MainZoneXmlStatus.xml';
}

class DENON_API_Commands extends stdClass
{
    //MAIN Zone
    public const PW = 'PW'; // Power
    public const MV = 'MV'; // Master Volume
    public const BL = 'BL'; // Balance
    //CV
    public const CVFL = 'CVFL'; // Channel Volume Front Left
    public const CVFR = 'CVFR'; // Channel Volume Front Right
    public const CVC = 'CVC'; // Channel Volume Center
    public const CVSW = 'CVSW'; // Channel Volume Subwoofer
    public const CVSW2 = 'CVSW2'; // Channel Volume Subwoofer2
    public const CVSW3 = 'CVSW3'; // Channel Volume Subwoofer3
    public const CVSW4 = 'CVSW4'; // Channel Volume Subwoofer4
    public const CVSL = 'CVSL'; // Channel Volume Surround Left
    public const CVSR = 'CVSR'; // Channel Volume Surround Right
    public const CVSBL = 'CVSBL'; // Channel Volume Surround Back Left
    public const CVSBR = 'CVSBR'; // Channel Volume Surround Back Right
    public const CVSB = 'CVSB'; // Channel Volume Surround Back
    public const CVFHL = 'CVFHL'; // Channel Volume Front Height Left
    public const CVFHR = 'CVFHR'; // Channel Volume Front Height Right
    public const CVFWL = 'CVFWL'; // Channel Volume Front Wide Left
    public const CVFWR = 'CVFWR'; // Channel Volume Front Wide Right
    public const MU = 'MU'; // Volume Mute
    public const SI = 'SI'; // Select Input
    public const ZM = 'ZM'; // Main Zone
    public const SD = 'SD'; // Select Auto/HDMI/Digital/Analog
    public const DC = 'DC'; // Digital Input Mode Select Auto/PCM/DTS
    public const SV = 'SV'; // Video Select
    public const SLP = 'SLP'; // Main Zone Sleep Timer
    public const MS = 'MS'; // Select Surround Mode
    public const SP = 'SP'; // Speaker Preset
    public const MN = 'MN'; // System
    public const MSQUICK = 'MSQUICK'; // Quick Select Mode Select (Denon)
    public const MSQUICKMEMORY = 'MEMORY'; // Quick Select Mode Memory
    public const MSSMART = 'MSSMART'; // Smart Select Mode Select (Marantz)

    //MU
    public const MUON = 'ON'; // Volume Mute ON
    public const MUOFF = 'OFF'; // Volume Mute Off

    //VS
    public const VS = 'VS'; // Video Setting
    public const VSASP = 'VSASP'; // ASP
    public const VSSC = 'VSSC'; // Set Resolution

    public const VSSCH = 'VSSCH'; // Set Resolution HDMI
    public const VSAUDIO = 'VSAUDIO'; // Set HDMI Audio Output
    public const VSMONI = 'VSMONI'; // Set HDMI Monitor
    public const VSVPM = 'VSVPM'; // Set Video Processing Mode
    public const VSVST = 'VSVST'; // Set Vertical Stretch
    //PS
    public const PS = 'PS'; // Parameter Setting
    public const PSATT = 'PSATT'; // SW ATT
    public const PSTONECTRL = 'PSTONE_CTRL'; // Tone Control !da Ident nur Buchstaben und Zahlen enthalten darf, wurde das Blank ersetzt
    public const PSSB = 'PSSB'; // Surround Back SP Mode
    public const PSCINEMAEQ = 'PSCINEMA_EQ'; // Cinema EQ
    public const PSHTEQ = 'PSHT_EQ'; // Cinema EQ
    public const PSMODE = 'PSMODE'; // Mode Music
    public const PSDOLVOL = 'PSDOLVOL'; // Dolby Volume direct change
    public const PSVOLLEV = 'PSVOLLEV'; // Dolby Volume Leveler direct change
    public const PSVOLMOD = 'PSVOLMOD'; // Dolby Volume Modeler direct change
    public const PSFH = 'PSFH'; // FRONT HEIGHT
    public const PSPHG = 'PSPHG'; // PL2z HEIGHT GAIN direct change
    public const PSSP = 'PSSP'; // Speaker Output set
    public const PSREFLEV = 'PSREFLEV'; // Dynamic EQ Reference Level
    public const PSMULTEQ = 'PSMULTEQ'; // MultEQ XT 32 mode direct change
    public const PSDYNEQ = 'PSDYNEQ'; // Dynamic EQ
    public const PSLFC = 'PSLFC'; // Audyssey LFC
    public const PSDYNVOL = 'PSDYNVOL'; // Dynamic Volume
    public const PSDSX = 'PSDSX'; // Audyssey DSX Change
    public const PSSTW = 'PSSTW'; // STAGE WIDTH
    public const PSCNTAMT = 'PSCNTAMT'; // Audyssey Containment Amount
    public const PSSTH = 'PSSTH'; // STAGE HEIGHT
    public const PSBAS = 'PSBAS'; // BASS
    public const PSTRE = 'PSTRE'; // TREBLE
    public const PSLOM = 'PSLOM'; // Loudness Management
    public const PSDRC = 'PSDRC'; // DRC direct change
    public const PSMDAX = 'PSMDAX'; // M-DAX
    public const PSDCO = 'PSDCO'; // D.COMP direct change
    public const PSCLV = 'PSCLV'; // Center Level Volume
    public const PSLFE = 'PSLFE'; // LFE
    public const PSLFL = 'PSLFL'; // LFF
    public const PSEFF = 'PSEFF'; // EFFECT direct change	Level
    public const PSDELAY = 'PSDELAY'; // Audio DELAY
    public const PSDEL = 'PSDEL'; // DELAY
    public const PSAFD = 'PSAFD'; // Auto Flag Detect Mode
    public const PSPAN = 'PSPAN'; // PANORAMA
    public const PSDIM = 'PSDIM'; // DIMENSION
    public const PSCEN = 'PSCEN'; // CENTER WIDTH
    public const PSCEI = 'PSCEI'; // CENTER IMAGE
    public const PSCEG = 'PSCEG'; // CENTER GAIN
    public const PSDIC = 'PSDIC'; // DIALOG CONTROL
    public const PSRSTR = 'PSRSTR'; //Audio Restorer
    public const PSFRONT = 'PSFRONT'; //Front Speaker
    public const PSRSZ = 'PSRSZ'; //Room Size
    public const PSSWR = 'PSSWR'; //Subwoofer

    public const BTTX = 'BTTX'; //Bluetooth Transmitter
    public const SPPR = 'SPPR'; //Speaker Preset

    //PV
    public const PV = 'PV'; // Picture Mode
    public const PVPICT = 'PVPICT'; //Picture Mode beim Senden
    public const PVPICTOFF = 'OFF'; // Picture Mode Off
    public const PVPICTSTD = 'STD'; // Picture Mode Standard
    public const PVPICTMOV = 'MOVIE'; // Picture Mode Movie
    public const PVPICTVVD = 'VVD'; // Picture Mode Vivid
    public const PVPICTSTM = 'STM'; // Picture Mode Stream
    public const PVPICTCTM = 'CTM'; // Picture Mode Custom
    public const PVPICTDAY = 'DAY'; // Picture Mode ISF Day
    public const PVPICTNGT = 'NGT'; // Picture Mode ISF Night

    public const PVCN = 'PVCN'; // Contrast
    public const PVBR = 'PVBR'; // Brightness
    public const PVST = 'PVST'; // Saturation
    public const PVCM = 'PVCM'; // Chroma
    public const PVHUE = 'PVHUE'; // Hue
    public const PVENH = 'PVENH'; // Enhancer

    public const PVDNR = 'PVDNR'; // Digital Noise Reduction direct change
    public const PVDNROFF = ' OFF'; // Digital Noise Reduction Off
    public const PVDNRLOW = ' LOW'; // Digital Noise Reduction Low
    public const PVDNRMID = ' MID'; // Digital Noise Reduction Middle
    public const PVDNRHI = ' HI'; // Digital Noise Reduction High

    // Speaker Setup
    public const SSSPC = 'SSSPC';
    public const SSSPCCEN = 'SSSPCCEN'; // Setup Center
    public const SSSPCFRO = 'SSSPCFRO'; // Setup Front
    public const SSSPCSWF = 'SSSPCSWF'; // Setup Subwoofer
    public const NON = ' NON'; // none Subwoofer
    public const SPONE = ' 1SP'; // Subwoofer 1
    public const SPTWO = ' 2SP'; // Subwoofer 2
    public const SMA = ' SMA'; // small
    public const LAR = ' LAR'; // large

    public const SR = ' ?'; //Status Request

    //Zone 2
    public const Z2 = 'Z2'; // Zone 2
    public const Z2ON = 'ON'; // Zone 2 On
    public const Z2OFF = 'OFF'; // Zone 2 Off
    public const Z2POWER = 'Z2POWER'; // Zone 2 Power Z2 beim Senden
    public const Z2INPUT = 'Z2INPUT'; // Zone 2 Input Z2 beim Senden
    public const Z2VOL = 'Z2VOL'; // Zone 2 Volume Z2 beim Senden
    public const Z2MU = 'Z2MU'; // Zone 2 Mute
    public const Z2CS = 'Z2CS'; // Zone 2 Channel Setting
    public const Z2CSST = 'ST'; // Zone 2 Channel Setting Stereo
    public const Z2CSMONO = 'MONO'; // Zone 2 Channel Setting Mono
    public const Z2CVFL = 'Z2CVFL'; // Zone 2 Channel Volume FL
    public const Z2CVFR = 'Z2CVFR'; // Zone 2 Channel Volume FR
    public const Z2HPF = 'Z2HPF'; // Zone 2 HPF
    public const Z2HDA = 'Z2HDA'; // (nur) Zone 2 HDA
    public const Z2HDATHR = ' THR'; // (nur) Zone 2 HDA
    public const Z2HDAPCM = ' PCM'; // (nur) Zone 2 HDA
    public const Z2PSBAS = 'Z2PSBAS'; // Zone 2 Parameter Bass
    public const Z2PSTRE = 'Z2PSTRE'; // Zone 2 Parameter Treble
    public const Z2SLP = 'Z2SLP'; // Zone 2 Sleep Timer
    public const Z2QUICK = 'Z2QUICK'; // Zone 2 Quick
    public const Z2SMART = 'Z2SMART'; // Zone 2 Smart

    //Zone 3
    public const Z3 = 'Z3'; // Zone 3
    public const Z3ON = 'ON'; // Zone 3 On
    public const Z3OFF = 'OFF'; // Zone 3 Off
    public const Z3POWER = 'Z3POWER'; // Zone 3 Power Z3 beim Senden
    public const Z3INPUT = 'Z3INPUT'; // Zone 3 Input Z3 beim Senden
    public const Z3VOL = 'Z3VOL'; // Zone 3 Volume Z3 beim Senden
    public const Z3MU = 'Z3MU'; // Zone 3 Mute
    public const Z3CS = 'Z3CS'; // Zone 3 Channel Setting
    public const Z3CSST = 'ST'; // Zone 3 Channel Setting Stereo
    public const Z3CSMONO = 'MONO'; // Zone 3 Channel Setting Mono
    public const Z3CVFL = 'Z3CVFL'; // Zone 3 Channel Volume FL
    public const Z3CVFR = 'Z3CVFR'; // Zone 3 Channel Volume FR
    public const Z3HPF = 'Z3HPF'; // Zone 3 HPF
    public const Z3PSBAS = 'Z3PSBAS'; // Zone 3 Parameter Bass
    public const Z3PSTRE = 'Z3PSTRE'; // Zone 3 Parameter Treble
    public const Z3SLP = 'Z3SLP'; // Zone 3 Sleep Timer
    public const Z3QUICK = 'Z3QUICK'; // Zone 3 Quick
    public const Z3SMART = 'Z3SMART'; // Zone 3 Smart

    public const NS = 'NS'; // Network Audio
    public const SY = 'SY'; // Remote Lock
    public const TR = 'TR'; // Trigger
    public const UG = 'UG'; // Upgrade ID Display

    //Analog Tuner
    public const TF = 'TF'; // Tuner Frequency

    public const TPAN = 'TPAN'; // Tuner Preset (analog)
    public const TPANUP = 'UP'; //TUNER PRESET CH UP
    public const TPANDOWN = 'DOWN'; //TUNER PRESET CH DOWN

    public const TMAN_BAND = 'TMAN'; // Tuner Mode (analog) Band
    public const TMANAM = 'AM'; // Tuner Band AM (Band)
    public const TMANFM = 'FM'; // Tuner Band FM (Band)
    public const TMANDAB = 'DAB'; // Tuner Band DAB (Band)

    public const TMAN_MODE = 'TM'; // Tuner Mode (analog) Mode
    public const TMANAUTO = 'ANAUTO'; // Tuner Mode Auto
    public const TMANMANUAL = 'ANMANUAL'; // Tuner Mode Manual

    //Network Audio
    public const NSB = 'NSB'; //Direct Preset CH Play 00-55,00=A1,01=A2,B1=08,G8=55

    // Display Network Audio Navigation
    public const NSUP = '90'; // Network Audio Cursor Up Control
    public const NSDOWN = '91'; // Network Audio Cursor Down Control
    public const NSLEFT = '92'; // Network Audio Cursor Left Control
    public const NSRIGHT = '93'; // Network Audio Cursor Right Control
    public const NSENTER = '94'; // Network Audio Cursor Enter Control
    public const NSPLAY = '9A'; // Network Audio Play
    public const NSPAUSE = '9B'; // Network Audio Pause
    public const NSSTOP = '9C'; // Network Audio Stop
    public const NSSKIPPLUS = '9D'; // Network Audio Skip +
    public const NSSKIPMINUS = '9E'; // Network Audio Skip -
    public const NSREPEATONE = '9H'; // Network Audio Repeat One
    public const NSREPEATALL = '9I'; // Network Audio Repeat All
    public const NSREPEATOFF = '9J'; // Network Audio Repeat Off
    public const NSRANDOMON = '9K'; // Network Audio Random On
    public const NSRANDOMOFF = '9M'; // Network Audio Random Off
    public const NSTOGGLE = '9W'; // Network Audio Toggle Switch
    public const NSPAGENEXT = '9X'; // Network Audio Page Next
    public const NSPAGEPREV = '9Y'; // Network Audio Page Previous

    //Display
    public const DISPLAY = 'Display'; // Display zur Anzeige
    public const NSA = 'NSA'; // Network Audio Extended
    public const NSA0 = 'NSA0'; // Network Audio Extended Line 0
    public const NSA1 = 'NSA1'; // Network Audio Extended Line 1
    public const NSA2 = 'NSA2'; // Network Audio Extended Line 2
    public const NSA3 = 'NSA3'; // Network Audio Extended Line 3
    public const NSA4 = 'NSA4'; // Network Audio Extended Line 4
    public const NSA5 = 'NSA5'; // Network Audio Extended Line 5
    public const NSA6 = 'NSA6'; // Network Audio Extended Line 6
    public const NSA7 = 'NSA7'; // Network Audio Extended Line 7
    public const NSA8 = 'NSA8'; // Network Audio Extended Line 8

    public const NSE = 'NSE'; // Network Audio Onscreen Display Information
    public const NSE0 = 'NSE0'; // Network Audio Onscreen Display Information Line 0
    public const NSE1 = 'NSE1'; // Network Audio Onscreen Display Information Line 1
    public const NSE2 = 'NSE2'; // Network Audio Onscreen Display Information Line 2
    public const NSE3 = 'NSE3'; // Network Audio Onscreen Display Information Line 3
    public const NSE4 = 'NSE4'; // Network Audio Onscreen Display Information Line 4
    public const NSE5 = 'NSE5'; // Network Audio Onscreen Display Information Line 5
    public const NSE6 = 'NSE6'; // Network Audio Onscreen Display Information Line 6
    public const NSE7 = 'NSE7'; // Network Audio Onscreen Display Information Line 7
    public const NSE8 = 'NSE8'; // Network Audio Onscreen Display Information Line 8
    public const NSE9 = 'NSE9'; // Network Audio Onscreen Display Information Line 9

    //SUB Commands

    //PW
    public const PWON = 'ON'; // Power On
    public const PWSTANDBY = 'STANDBY'; // Power Standby
    public const PWOFF = 'OFF'; // Power OFF - beim X1200 im XML beobachtet

    //MV
    public const MVUP = 'UP'; // Master Volume Up
    public const MVDOWN = 'DOWN'; // Master Volume Down

    //SI + SV
    public const IS_PHONO = 'PHONO'; // Select Input Source Phono
    public const IS_CD     = 'CD'; // Select Input Source CD
    public const IS_TUNER   = 'TUNER'; // Select Input Source Tuner
    public const IS_FM      = 'FM'; // Select Input Source FM
    public const IS_DAB     = 'DAB'; // Select Input Source DAB
    public const IS_DVD     = 'DVD'; // Select Input Source DVD
    public const IS_HDP     = 'HDP'; // Select Input Source HDP
    public const IS_BD      = 'BD'; // Select Input Source BD
    public const IS_BT      = 'BT'; // Select Input Source Blutooth
    public const IS_MPLAY   = 'MPLAY'; // Select Input Source Mediaplayer
    public const IS_TV      = 'TV'; // Select Input Source TV
    public const IS_TV_CBL  = 'TV/CBL'; // Select Input Source TV/CBL
    public const IS_SAT_CBL = 'SAT/CBL'; // Select Input Source Sat/CBL
    public const IS_SAT   = 'SAT'; // Select Input Source Sat
    public const IS_VCR    = 'VCR'; // Select Input Source VCR
    public const IS_DVR    = 'DVR'; // Select Input Source DVR
    public const IS_GAME   = 'GAME'; // Select Input Source Game
    public const IS_GAME1   = 'GAME1'; // Select Input Source Game1
    public const IS_GAME2  = 'GAME2'; // Select Input Source Game2
    public const IS_8K     = '8K'; // Select Input Source 8K
    public const IS_AUX    = 'AUX'; // Select Input Source AUX
    public const IS_AUX1   = 'AUX1'; // Select Input Source AUX1
    public const IS_AUX2   = 'AUX2'; // Select Input Source AUX2
    public const IS_VAUX   = 'V.AUX'; // Select Input Source V.AUX
    public const IS_DOCK   = 'DOCK'; // Select Input Source Dock
    public const IS_IPOD      = 'IPOD'; // Select Input Source iPOD
    public const IS_USB       = 'USB'; // Select Input Source USB
    public const IS_AUXA      = 'AUXA'; // Select Input Source AUXA
    public const IS_AUXB      = 'AUXB'; // Select Input Source AUXB
    public const IS_AUXC      = 'AUXC'; // Select Input Source AUXC
    public const IS_AUXD      = 'AUXD'; // Select Input Source AUXD
    public const IS_NETUSB    = 'NET/USB'; // Select Input Source NET/USB
    public const IS_NET       = 'NET'; // Select Input Source NET
    public const IS_LASTFM    = 'LASTFM'; // Select Input Source LastFM
    public const IS_FLICKR    = 'FLICKR'; // Select Input Source Flickr
    public const IS_FAVORITES = 'FAVORITES'; // Select Input Source Favorites
    public const IS_IRADIO    = 'IRADIO'; // Select Input Source Internet Radio
    public const IS_SERVER    = 'SERVER'; // Select Input Source Server
    public const IS_NAPSTER   = 'NAPSTER'; // Select Input Source Napster
    public const IS_USB_IPOD  = 'USB/IPOD'; // Select Input USB/IPOD
    public const IS_MXPORT    = 'MXPORT'; // Select Input MXPORT
    public const IS_SOURCE    = 'SOURCE'; // Select Input Source of Main Zone
    public const IS_ON        = 'ON'; // Select Input Source On
    public const IS_OFF       = 'OFF'; // Select Input Source Off

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
    public const ZMOFF = 'OFF'; // Power Off
    public const ZMON = 'ON'; // Power On

    //SD
    public const SDAUTO = 'AUTO'; // Auto Mode
    public const SDHDMI = 'HDMI'; // HDMI Mode
    public const SDDIGITAL = 'DIGITAL'; // Digital Mode
    public const SDANALOG = 'ANALOG'; // Analog Mode
    public const SDEXTIN = 'EXT.IN'; // Ext.In Mode
    public const SD71IN = '7.1IN'; // 7.1 In Mode
    public const SDNO = 'NO'; // no Input
    public const SDARC = 'ARC'; // ARC (nur im Event)
    public const SDEARC = 'EARC'; // EARC (nur im Event)

    //DC Digital Input
    public const DCAUTO = 'AUTO'; // Auto Mode
    public const DCPCM = 'PCM'; // PCM Mode
    public const DCDTS = 'DTS'; // DTS Mode

    //MS Surround Mode
    public const MSDIRECT = 'DIRECT'; // Direct Mode
    public const MSPUREDIRECT = 'PURE DIRECT'; // Pure Direct Mode
    public const MSSTEREO = 'STEREO'; // Stereo Mode
    public const MSSTANDARD = 'STANDARD'; // Standard Mode
    public const MSDOLBYDIGITAL = 'DOLBY DIGITAL'; // Dolby Digital Mode
    public const MSDTSSURROUND = 'DTS SURROUND'; // DTS Surround Mode
    public const MSMCHSTEREO = 'MCH STEREO'; // Multi Channel Stereo Mode
    public const MS7CHSTEREO = '7CH STEREO'; // 7 Channel Stereo Mode
    public const MSWIDESCREEN = 'WIDE SCREEN'; // Wide Screen Mode
    public const MSSUPERSTADIUM = 'SUPER STADIUM'; // Super Stadium Mode
    public const MSROCKARENA = 'ROCK ARENA'; // Rock Arena Mode
    public const MSJAZZCLUB = 'JAZZ CLUB'; // Jazz Club Mode
    public const MSCLASSICCONCERT = 'CLASSIC CONCERT'; // Classic Concert Mode
    public const MSMONOMOVIE = 'MONO MOVIE'; // Mono Movie Mode
    public const MSMATRIX = 'MATRIX'; // Matrix Mode
    public const MSVIDEOGAME = 'VIDEO GAME'; // Video Game Mode
    public const MSVIRTUAL = 'VIRTUAL'; // Virtual Mode
    public const MSMOVIE = 'MOVIE'; // Movie
    public const MSMUSIC = 'MUSIC'; // Music
    public const MSGAME = 'GAME'; // Game
    public const MSAUTO = 'AUTO'; // Auto
    public const MSNEURAL = 'NEURAL'; // Neural
    public const MSAURO3D = 'AURO3D'; //Auro 3D
 //   public const AURO3D = 'AURO3D'; //Auro 3D
    public const MSAURO2DSURR = 'AURO2DSURR'; //Auro 2D

    public const MSLEFT = 'LEFT'; // Change to previous Surround Mode
    public const MSRIGHT = 'RIGHT'; // Change to next Surround Mode
    //Quick Select Mode
    public const MSQUICK0 = '0'; // Quick Select 0 Mode Select
    public const MSQUICK1 = '1'; // Quick Select 1 Mode Select
    public const MSQUICK2 = '2'; // Quick Select 2 Mode Select
    public const MSQUICK3 = '3'; // Quick Select 3 Mode Select
    public const MSQUICK4 = '4'; // Quick Select 4 Mode Select
    public const MSQUICK5 = '5'; // Quick Select 5 Mode Select

    //MSQUICKMEMORY
    public const MSQUICK1MEMORY = '1 MEMORY'; // Quick Select 1 Mode Memory
    public const MSQUICK2MEMORY = '2 MEMORY'; // Quick Select 2 Mode Memory
    public const MSQUICK3MEMORY = '3 MEMORY'; // Quick Select 3 Mode Memory
    public const MSQUICK4MEMORY = '4 MEMORY'; // Quick Select 4 Mode Memory
    public const MSQUICK5MEMORY = '5 MEMORY'; // Quick Select 5 Mode Memory
    public const MSQUICKSTATE = 'QUICK ?'; // QUICK ? Return MSQUICK Status

    //Smart Select Mode
    public const MSSMART0 = '0'; // Smart Select 0 Mode Select
    public const MSSMART1 = '1'; // Smart Select 1 Mode Select
    public const MSSMART2 = '2'; // Smart Select 2 Mode Select
    public const MSSMART3 = '3'; // Smart Select 3 Mode Select
    public const MSSMART4 = '4'; // Smart Select 4 Mode Select
    public const MSSMART5 = '5'; // Smart Select 5 Mode Select

    //VS
    //VSMONI Set HDMI Monitor
    public const VSMONIAUTO = 'AUTO'; // 1
    public const VSMONI1 = '1'; // 1
    public const VSMONI2 = '2'; // 2

    //VSASP
    public const ASPNRM = 'NRM'; // Set Normal Mode
    public const ASPFUL = 'FUL'; // Set Full Mode
    public const ASP = ' ?'; // ASP? Return VSASP Status

    //VSSC Set Resolution
    public const SC48P = '48P'; // Set Resolution to 480p/576p
    public const SC10I = '10I'; // Set Resolution to 1080i
    public const SC72P = '72P'; // Set Resolution to 720p
    public const SC10P = '10P'; // Set Resolution to 1080p
    public const SC10P24 = '10P24'; // Set Resolution to 1080p:24Hz
    public const SC4K = '4K'; // Set Resolution to 4K
    public const SC4KF = '4KF'; // Set Resolution to 4K (60/50)
    public const SC8K = '8K'; // Set Resolution to 8K
    public const SCAUTO = 'AUTO'; // Set Resolution to Auto
    public const SC = ' ?'; // SC? Return VSSC Status

    //VSSCH Set Resolution HDMI
    public const SCH48P = '48P'; // Set Resolution to 480p/576p HDMI
    public const SCH10I = '10I'; // Set Resolution to 1080i HDMI
    public const SCH72P = '72P'; // Set Resolution to 720p HDMI
    public const SCH10P = '10P'; // Set Resolution to 1080p HDMI
    public const SCH10P24 = '10P24'; // Set Resolution to 1080p:24Hz HDMI
    public const SCH4K = '4K'; // Set Resolution to 4K
    public const SCH4KF = '4KF'; // Set Resolution to 4K (60/50)
    public const SCH8K = '8K'; // Set Resolution to 8K
    public const SCHAUTO = 'AUTO'; // Set HDMI Upcaler to Auto
    public const SCHOFF = 'OFF'; // Set HDMI Upscale to Off
    public const SCH = ' ?'; // SCH? Return VSSCH Status(HDMI)

    //VSAUDIO Set HDMI Audio Output
    public const AUDIOAMP = ' AMP'; // Set HDMI Audio Output to AMP
    public const AUDIOTV = ' TV'; // Set HDMI Audio Output to TV
    public const AUDIO = ' ?'; // AUDIO? Return VSAUDIO Status

    //VSVPM Set Video Processing Mode
    public const VPMAUTO = 'AUTO'; // Set Video Processing Mode to Auto
    public const VPGAME = 'GAME'; // Set Video Processing Mode to Game
    public const VPMOVI = 'MOVI'; // Set Video Processing Mode to Movie
    public const VPMBYP = 'MBYP'; // Set Video Processing Mode to Bypass
    public const VPM = ' ?'; // VPM? Return VSVPM Status

    //VSVST Set Vertical Stretch
    public const VSTON = ' ON'; // Set Vertical Stretch On
    public const VSTOFF = ' OFF'; // Set Vertical Stretch Off
    public const VST = ' ?'; // VST? Return VSVST Status

    //PS Parameter
    //PSTONE Tone Control
    public const TONECTRL = 'PSTONE CTRL'; // Tone Control On
    public const PSTONECTRLON = ' ON'; // Tone Control On
    public const PSTONECTRLOFF = ' OFF'; // Tone Control Off
    public const PSTONECTRLSTATE = ' ?'; // TONE CTRL ? Return PSTONE CONTROL Status

    //PSSB Surround Back SP Mode
    public const SBMTRXON = ':MTRX ON'; // Surround Back SP Mode Matrix
    public const SBPL2XCINEMA = ':PL2X CINEMA'; // Surround Back SP Mode	PL2X Cinema
    public const SBPL2XMUSIC = ':PL2X MUSIC'; // Surround Back SP Mode	PL2X Music
    public const SBON = ':ON'; // Surround Back SP Mode on
    public const SBOFF = ':OFF'; // Surround Back SP Mode off

    //PSCINEMAEQ Cinema EQ
    public const CINEMAEQCOMMAND = 'PSCINEMA EQ'; // Cinema EQ
    public const CINEMAEQON = '.ON'; // Cinema EQ on
    public const CINEMAEQOFF = '.OFF'; // Cinema EQ off
    public const CINEMAEQ = '. ?'; // Return PSCINEMA EQ.Status

    //PSHTEQ HT EQ
    public const HTEQCOMMAND = 'PSHTEQ'; // HT EQ
    public const HTEQON = ' ON'; // HT EQ on
    public const HTEQOFF = ' OFF'; // HT EQ off
    public const HTEQ = ' ?'; // Return HT EQ.Status

    //PSMODE Mode Music
    public const MODEMUSIC = ':MUSIC'; // Mode Music CINEMA / MUSIC / GAME / PL mode change
    public const MODECINEMA = ':CINEMA'; // This parameter can change DOLBY PL2,PL2x,NEO:6 mode.
    public const MODEGAME = ':GAME'; // SB=ON：PL2x mode / SB=OFF：PL2 mode GAME can change DOLBY PL2 & PL2x mode PSMODE:PRO LOGIC
    public const MODEPROLOGIC = ':PRO LOGIC'; // PL can change ONLY DOLBY PL2 mode
    public const MODESTATE = ': ?'; // Return PSMODE: Status

    //PSDOLVOL Dolby Volume direct change
    public const DOLVOLON = ' ON'; // Dolby Volume direct change on
    public const DOLVOLOFF = ' OFF'; // Dolby Volume direct change off
    public const DOLVOL = ': ?'; // Return PSDOLVOL Status

    //PSVOLLEV Dolby Volume Leveler direct change
    public const VOLLEVLOW = ' LOW'; // Dolby Volume Leveler direct change Low
    public const VOLLEVMID = ' MID'; // Dolby Volume Leveler direct change Middle
    public const VOLLEVHI = ' HI'; // Dolby Volume Leveler direct change High
    public const VOLLEV = ': ?'; // Return PSVOLLEV Status

    // PSVOLMOD Dolby Volume Modeler direct change
    public const VOLMODHLF = ' HLF'; // Dolby Volume Modeler direct change half
    public const VOLMODFUL = ' FUL'; // Dolby Volume Modeler direct change full
    public const VOLMODOFF = ' OFF'; // Dolby Volume Modeler direct change off
    public const VOLMOD = ': ?'; // Return PSVOLMOD Status

    //PSFH Front Height
    public const PSFHON = ':ON'; // FRONT HEIGHT ON
    public const PSFHOFF = ':OFF'; // FRONT HEIGHT OFF
    public const PSFHSTATE = ': ?'; // Return PSFH: Status

    //PSPHG PL2z Height Gain direct change
    public const PHGLOW = ' LOW'; // PL2z HEIGHT GAIN direct change low
    public const PHGMID = ' MID'; // PL2z HEIGHT GAIN direct change middle
    public const PHGHI = ' HI'; // PL2z HEIGHT GAIN direct change high
    public const PHGSTATE = ' ?'; // Return PSPHG Status

    //PSSP Speaker Output set
    public const SPFH = ':FH'; // Speaker Output set FH
    public const SPFW = ':FW'; // Speaker Output set FW
    public const SPSB = ':SB'; // Speaker Output set SB
    public const SPHW = ':HW'; // Speaker Output set HW
    public const SPBH = ':BH'; // Speaker Output set BH
    public const SPBW = ':BW'; // Speaker Output set BW
    public const SPFL = ':FL'; // Speaker Output set FL
    public const SPHF = ':HF'; // Speaker Output set HF
    public const SPFR = ':FR'; // Speaker Output set FR
    public const SPOFF = ':OFF'; // Speaker Output set off
    public const SPSTATE = ' ?'; // Return PSSP: Status

    // MulEQ XT 32 mode direct change
    public const MULTEQAUDYSSEY = ':AUDYSSEY'; // MultEQ XT 32 mode direct change MULTEQ:AUDYSSEY
    public const MULTEQBYPLR = ':BYP.LR'; // MultEQ XT 32 mode direct change MULTEQ:BYP.LR
    public const MULTEQFLAT = ':FLAT'; // MultEQ XT 32 mode direct change MULTEQ:FLAT
    public const MULTEQMANUAL = ':MANUAL'; // MultEQ XT 32 mode direct change MULTEQ:MANUAL
    public const MULTEQOFF = ':OFF'; // MultEQ XT 32 mode direct change MULTEQ:OFF
    public const MULTEQ = ': ?'; // Return PSMULTEQ: Status

    //PSDYNEQ Dynamic EQ
    public const DYNEQON = ' ON'; // Dynamic EQ = ON
    public const DYNEQOFF = ' OFF'; // Dynamic EQ = OFF
    public const DYNEQ = ' ?'; // Return PSDYNEQ Status

    //PSLFC Audyssey LFC
    public const LFCON = ' ON'; // Audyssey LFC = ON
    public const LFCOFF = ' OFF'; // Audyssey LFC = OFF
    public const LFC = ' ?'; // Return Audyssey LFC Status

    //PSGEQ Graphic EQ
    public const GEQON = ' ON'; // Graphic EQ = ON
    public const GEQOFF = ' OFF'; // Graphic EQ = OFF
    public const GEQ = ' ?'; // Return Graphic EQ Status

    //PSREFLEV Reference Level Offset
    public const REFLEV0 = ' 0'; // Reference Level Offset=0dB
    public const REFLEV5 = ' 5'; // Reference Level Offset=5dB
    public const REFLEV10 = ' 10'; // Reference Level Offset=10dB
    public const REFLEV15 = ' 15'; // Reference Level Offset=15dB
    public const REFLEV = ' ?'; // Return PSREFLEV Status

    //PSREFLEV Reference Level Offset
    public const DIRAC1 = ' 1'; // Filter Slot 1
    public const DIRAC2 = ' 2'; // Filter Slot 2
    public const DIRAC3 = ' 3'; // Filter Slot 3
    public const DIRACOFF = ' OFF'; // Filter Off


    //PSDYNVOL (old version)
    public const DYNVOLNGT = ' NGT'; // Dynamic Volume = Midnight
    public const DYNVOLEVE = ' EVE'; // Dynamic Volume = Evening
    public const DYNVOLDAY = ' DAY'; // Dynamic Volume = Day
    public const DYNVOL = ' ?'; // Return PSDYNVOL Status
    //PSDYNVOL
    public const DYNVOLHEV = ' HEV'; // Dynamic Volume = Heavy
    public const DYNVOLMED = ' MED'; // Dynamic Volume = Medium
    public const DYNVOLLIT = ' LIT'; // Dynamic Volume = Light
    public const DYNVOLOFF = ' OFF'; // Dynamic Volume = Off
    public const DYNVOLON = ' ON'; // Dynamic Volume = Off

    //PSDSX Audyssey DSX ON
    public const PSDSXONHW = ' ONHW'; // Audyssey DSX ON(Height/Wide)
    public const PSDSXONH = ' ONH'; // Audyssey DSX ON(Height)
    public const PSDSXONW = ' ONW'; // Audyssey DSX ON(Wide)
    public const PSDSXOFF = ' OFF'; // Audyssey DSX OFF
    public const PSDSXSTATUS = ' ?'; // Return PSDSX Status

    //PSSTW Stage Width
    public const STWUP = ' UP'; // STAGE WIDTH UP
    public const STWDOWN = ' DOWN'; // STAGE WIDTH DOWN
    public const STW = ' '; // STAGE WIDTH ** ---AVR-4311 can be operated from -10 to +10

    //PSSTH Stage Height
    public const STHUP = ' UP'; // STAGE HEIGHT UP
    public const STHDOWN = ' DOWN'; // STAGE HEIGHT DOWN
    public const STH = ' '; // STAGE HEIGHT ** ---AVR-4311 can be operated from -10 to +10

    //PSBAS Bass
    public const BASUP = ' UP'; // BASS UP
    public const BASDOWN = ' DOWN'; // BASS DOWN
    public const BAS = ' '; // BASS ** ---AVR-4311 can be operated from -6 to +6

    //PSTRE Treble
    public const TREUP = ' UP'; // TREBLE UP
    public const TREDOWN = ' DOWN'; // TREBLE DOWN
    public const TRE = ' '; // TREBLE ** ---AVR-4311 can be operated from -6 to +6

    //PSDRC DRC direct change
    public const DRCAUTO = ' AUTO'; // DRC direct change
    public const DRCLOW = ' LOW'; // DRC Low
    public const DRCMID = ' MID'; // DRC Middle
    public const DRCHI = ' HI'; // DRC High
    public const DRCOFF = ' OFF'; // DRC off
    public const DRC = ' ?'; // Return PSDRC Status

    //PSMDAX MDAX direct change
    public const MDAXLOW = ' LOW'; // DRC Low
    public const MDAXMID = ' MID'; // DRC Middle
    public const MDAXHI = ' HI'; // DRC High
    public const MDAXOFF = ' OFF'; // DRC off
    public const MDAX = ' ?'; // Return PSDRC Status

    //PSDCO D.Comp direct change
    public const DCOOFF = ' OFF'; // D.COMP direct change
    public const DCOLOW = ' LOW'; // D.COMP Low
    public const DCOMID = ' MID'; // D.COMP Middle
    public const DCOHIGH = ' HIGH'; // D.COMP High
    public const DCO = ' ?'; // Return PSDCO Status

    //PSLFE LFE
    public const LFEDOWN = ' DOWN'; // LFE DOWN
    public const LFEUP = ' UP'; // LFE UP
    public const LFE = ' '; // LFE ** ---AVR-4311 can be operated from 0 to -10

    //PSEFF Effect direct change
    public const PSEFFON = ' ON'; // EFFECT ON direct change
    public const PSEFFOFF = ' OFF'; // EFFECT OFF direct change

    public const PSEFFUP = ' UP'; // EFFECT UP direct change
    public const PSEFFDOWN = ' DOWN'; // EFFECT DOWN direct change
    public const PSEFFSTATUS = ' ?'; // EFFECT ** ---AVR-4311 can be operated from 1 to 15

    //PSDELAY Delay
    public const PSDELAYUP = ' UP'; // DELAY UP
    public const PSDELAYDOWN = ' DOWN'; // DELAY DOWN
    public const PSDELAYVAL = ' '; // DELAY ** ---AVR-4311 can be operated from 0 to 300

    //PSAFD Auto Flag Detection Mode
    public const AFDON = ' ON'; // AFDM ON
    public const AFDOFF = ' OFF'; // AFDM OFF
    public const AFD = ' '; // Return PSAFD Status

    //PSPAN Panorama
    public const PANON = ' ON'; // PANORAMA ON
    public const PANOFF = ' OFF'; // PANORAMA OFF
    public const PAN = ' ?'; // Return PSPAN Status

    //PSDIM Dimension
    public const PSDIMUP = ' UP'; // DIMENSION UP
    public const PSDIMDOWN = ' DOWN'; // DIMENSION DOWN
    public const PSDIMSET = ' '; // ---AVR-4311 can be operated from 0 to 6

    //PSCEN Center Width
    public const CENUP = 'CEN UP'; // CENTER WIDTH UP
    public const CENDOWN = 'CEN DOWN'; // CENTER WIDTH DOWN
    public const CEN = 'CEN '; // ---AVR-4311 can be operated from 0 to 7

    //PSCEI Center Image
    public const CEIUP = 'CEI UP'; // CENTER IMAGE UP
    public const CEIDOWN = 'CEI DOWN'; // CENTER IMAGE DOWN
    public const CEI = 'CEI '; // ---AVR-4311 can be operated from 0 to 7

    //PSRSZ Room Size
    public const RSZN = ' N';
    public const RSZS = ' S';
    public const RSZMS = ' MS';
    public const RSZM = ' M';
    public const RSZML = ' ML';
    public const RSZL = ' L';

    //PSSW ATT
    public const ATTON = 'ATT ON'; // SW ATT ON
    public const ATTOFF = 'ATT OFF'; // SW ATT OFF
    public const ATT = 'ATT ?'; // Return PSATT Status

    //PSSWR
    public const PSSWRON = ' ON'; // SW ATT ON
    public const PSSWROFF = ' OFF'; // SW ATT OFF
    public const SWR = ' ?'; // Return PSATT Status

    //PSLOM
    public const PSLOMON = ' ON'; // SW ATT ON
    public const PSLOMOFF = ' OFF'; // SW ATT OFF
    public const LOM = ' ?'; // Return PSATT Status

    //Audio Restorer - neue Kommandos bei neueren(?) Modellen
    public const PSRSTROFF = ' OFF'; //Audio Restorer Off
    //public const PSRSTRMODE1 = ' MODE1'; //Audio Restorer 64
    //public const PSRSTRMODE2 = ' MODE2'; //Audio Restorer 96
    //public const PSRSTRMODE3 = ' MODE3'; //Audio Restorer HQ
    public const PSRSTRMODE1 = ' HI'; //Audio Restorer 64
    public const PSRSTRMODE2 = ' MID'; //Audio Restorer 96
    public const PSRSTRMODE3 = ' LOW'; //Audio Restorer HQ

    //Front Speaker
    public const PSFRONTSPA = ' SPA'; //Speaker A
    public const PSFRONTSPB = ' SPB'; //Speaker B
    public const PSFRONTSPAB = ' A+B'; //Speaker A+B

    //Cursor Menu
    public const MNCUP = 'CUP'; // Cursor Up
    public const MNCDN = 'CDN'; // Cursor Down
    public const MNCRT = 'CRT'; // Cursor Right
    public const MNCLT = 'CLT'; // Cursor Left
    public const MNENT = 'ENT'; // Cursor Enter
    public const MNRTN = 'RTN'; // Cursor Return

    //GUI Menu (Setup Menu)
    public const MNMEN = 'MNMEN'; // GUI Menu
    public const MNMENON = ' ON'; // GUI Menu On
    public const MNMENOFF = ' OFF'; // GUI Menu Off

    //GUI Source Select Menu
    public const MNSRC = 'MNSRC'; // Source Select Menu
    public const MNSRCON = ' ON'; // Source Select Menu On
    public const MNSRCOFF = ' OFF'; // Source Select Menu Off

    // Surround Modes Response

    // Surround Modes Varmapping

    //Dolby Digital
    public const DOLBYPROLOGIC = 'DOLBY PRO LOGIC'; // DOLBY PRO LOGIC
    public const DOLBYPL2C = 'DOLBY PL2 C'; // DOLBY PL2 C
    public const DOLBYPL2M = 'DOLBY PL2 M'; // DOLBY PL2 M
    public const DOLBYPL2G = 'DOLBY PL2 G'; // DOLBY PL2 G
    public const DOLBYPLIIMV = 'DOLBY PLII MV';
    public const DOLBYPLIIMS = 'DOLBY PLII MS';
    public const DOLBYPLIIGM = 'DOLBY PLII GM';
    public const DOLBYPL2XC = 'DOLBY PL2X C'; // DOLBY PL2X C
    public const DOLBYPL2XM = 'DOLBY PL2X M'; // DOLBY PL2X M
    public const DOLBYPL2XG = 'DOLBY PL2X G'; // DOLBY PL2X G
    public const DOLBYPL2ZH = 'DOLBY PL2Z H'; // DOLBY PL2Z H
    public const DOLBYPL2XH = 'DOLBY PL2X H'; // DOLBY PL2X H
    public const DOLBYDEX = 'DOLBY D EX'; // DOLBY D EX
    public const DOLBYDPL2XC = 'DOLBY D+PL2X C';
    public const DOLBYDPL2XM = 'DOLBY D+PL2X M';
    public const DOLBYDPL2ZH = 'DOLBY D+PL2Z H';
    public const DOLBYAUDIODDDSUR = 'DOLBY AUDIO-DD+DSUR';
    public const PLDSX = 'PL DSX'; // PL DSX
    public const PL2CDSX = 'PL2 C DSX'; // PL2 C DSX
    public const PL2MDSX = 'PL2 M DSX'; // PL2 M DSX
    public const PL2GDSX = 'PL2 G DSX'; // PL2 G DSX
    public const PL2XCDSX = 'PL2X C DSX'; // PL2X C DSX
    public const PL2XMDSX = 'PL2X M DSX'; // PL2X M DSX
    public const PL2XGDSX = 'PL2X G DSX'; // PL2X G DSX
    public const DOLBYDPLUSPL2XC = 'DOLBY D+ +PL2X C'; // DOLBY D+ +PL2X C
    public const DOLBYDPLUSPL2XM = 'DOLBY D+ +PL2X M'; // DOLBY D+ +PL2X M
    public const DOLBYDPLUSPL2XH = 'DOLBY D+ +PL2X H'; // DOLBY D+ +PL2X H
    public const DOLBYHDPL2XC = 'DOLBY HD+PL2X C'; // DOLBY HD+PL2X C
    public const DOLBYHDPL2XM = 'DOLBY HD+PL2X M'; // DOLBY HD+PL2X M
    public const DOLBYHDPL2XH = 'DOLBY HD+PL2X H'; // DOLBY HD+PL2X H
    public const MULTICNIN = 'MULTI CH IN'; // MULTI CH IN
    public const MCHINPL2XC = 'M CH IN+PL2X C'; // M CH IN+PL2X C
    public const MCHINPL2XM = 'M CH IN+PL2X M'; // M CH IN+PL2X M
    public const MCHINPL2ZH = 'M CH IN+PL2Z H';
    public const MCHINDSUR = 'M CH IN+DSUR';
    public const MCHINNEURALX = 'M CH IN+NEURAL:X'; // M CH IN+NEURAL:X

    public const DOLBYDPLUS = 'DOLBY D+'; // DOLBY D+
    public const DOLBYDPLUSEX = 'DOLBY D+ +EX'; // DOLBY D+ +EX
    public const DOLBYTRUEHD = 'DOLBY TRUEHD'; // DOLBY TRUEHD
    public const DOLBYHD = 'DOLBY HD'; // DOLBY HD
    public const DOLBYHDEX = 'DOLBY HD+EX'; // DOLBY HD+EX
    public const DOLBYPL2H = 'DOLBY PL2 H'; // MSDOLBY PL2 H

    public const DOLBYSURROUND  = 'DOLBY SURROUND'; // MSDOLBY SURROUND
    public const DOLBYAUDIODSUR = 'DOLBY AUDIO-DSUR';
    public const DOLBYATMOS     = 'DOLBY ATMOS'; // MSDOLBY ATMOS
    public const DOLBYAUDIODD   = 'DOLBY AUDIO-DD';
    public const DOLBYDIGITAL   = 'DOLBY DIGITAL'; // MSDOLBY DIGITAL
    public const DOLBYDDS       = 'DOLBY D+DS'; // MSDOLBY D+DS
    public const MPEG2AAC       = 'MPEG2 AAC'; // MSMPEG2 AAC
    public const MPEG4AAC       = 'MPEG4 AAC'; // MSMPEG4 AAC
    public const MPEGH          = 'MPEG-H'; // MSMPEG4 AAC
    public const AACDOLBYEX     = 'AAC+DOLBY EX'; // MSAAC+DOLBY EX
    public const AACPL2XC       = 'AAC+PL2X C'; // MSAAC+PL2X C
    public const AACPL2XM       = 'AAC+PL2X M'; // MSAAC+PL2X M
    public const AACPL2ZH       = 'AAC+PL2Z H';
    public const AACDSUR        = 'AAC+DSUR';
    public const AACDS          = 'AAC+DS'; // MSAAC+DS
    public const AACNEOXC       = 'AAC+NEO:X C'; // MSAAC+NEO:X C
    public const AACNEOXM       = 'AAC+NEO:X M'; // MSAAC+NEO:X M
    public const AACNEOXG       = 'AAC+NEO:X G'; // MSAAC+NEO:X G

    //DTS Surround
    public const DTSNEO6C = 'DTS NEO:6 C'; // DTS NEO:6 C
    public const DTSNEO6M = 'DTS NEO:6 M'; // DTS NEO:6 M
    public const DTSNEOXC = 'DTS NEO:X C'; // DTS NEO:X C
    public const DTSNEOXM = 'DTS NEO:X M'; // DTS NEO:X M
    public const DTSNEOXG = 'DTS NEO:X G'; // DTS NEO:X G
    public const NEURALX = 'NEURAL:X'; // NEURAL:X
    public const VIRTUALX = 'VIRTUAL:X'; // VIRTUAL:X
    public const DTSESDSCRT61 = 'DTS ES DSCRT6.1'; // DTS ES DSCRT6.1
    public const DTSESMTRX61 = 'DTS ES MTRX6.1'; // DTS ES MTRX6.1
    public const DTSPL2XC = 'DTS+PL2X C'; // DTS+PL2X C
    public const DTSPL2XM = 'DTS+PL2X M'; // DTS+PL2X M
    public const DTSPL2ZH = 'DTS+PL2Z H'; // DTS+PL2Z H
    public const DTSDSUR = 'DTS+DSUR';
    public const DTSDS = 'DTS+DS'; // DTS+DS
    public const DTSPLUSNEO6 = 'DTS+NEO:6'; // DTS+NEO:6
    public const DTSPLUSNEOXC = 'DTS+NEO:X C'; // DTS PLUS NEO:X C
    public const DTSPLUSNEOXM = 'DTS+NEO:X M'; // DTS PLUS NEO:X M
    public const DTSPLUSNEOXG = 'DTS+NEO:X G'; // DTS PLUS NEO:X G
    public const DTSPLUSNEURALX = 'DTS+NEURAL:X'; // DTS+NEURAL:X
    public const DTS9624 = 'DTS96/24'; // DTS96/24
    public const DTS96ESMTRX = 'DTS96 ES MTRX'; // DTS96 ES MTRX
    public const DTSHDPL2XC = 'DTS HD+PL2X C'; // DTS HD+PL2X C
    public const DTSHDPL2XM = 'DTS HD+PL2X M'; // DTS HD+PL2X M
    public const DTSHDPL2ZH = 'DTS HD+PL2Z H'; // DTS HD+PL2Z H
    public const DTSHDDSUR = 'DTS HD+DSUR';
    public const DTSHDDS = 'DTS HD+DS'; // DTS HD+DS
    public const NEO6CDSX = 'NEO:6 C DSX'; // NEO:6 C DSX
    public const NEO6MDSX = 'NEO:6 M DSX'; // NEO:6 M DSX
    public const DTSHD = 'DTS HD'; // DTS HD
    public const DTSHDMSTR = 'DTS HD MSTR'; // DTS HD MSTR
    public const DTSHDNEO6 = 'DTS HD+NEO:6'; // DTS HD+NEO:6
    public const DTSES8CHDSCRT = 'DTS ES 8CH DSCRT'; // DTS ES 8CH DSCRT
    public const DTSEXPRESS = 'DTS EXPRESS'; // DTS EXPRESS
    public const DOLBYDNEOXC = 'DOLBY D+NEO:X C'; // MSDOLBY D+NEO:X C
    public const DOLBYDNEOXM = 'DOLBY D+NEO:X M'; // MSDOLBY D+NEO:X M
    public const DOLBYDNEOXG = 'DOLBY D+NEO:X G'; // MSDOLBY D+NEO:X G
    public const DOLBYAUDIODDPLUSNEURALX = 'DOLBY AUDIO-DD+NEURAL:X';
    public const DOLBYAUDIODDPLUS = 'DOLBY AUDIO-DD+';
    public const DOLBYDNEURALX = 'DOLBY D+NEURAL:X'; // MSDOLBY D+NEURAL:X
    public const MCHINDS = 'M CH IN+DS'; // MSM CH IN+DS
    public const MCHINNEOXC = 'M CH IN+NEO:X C'; // MSM CH IN+NEO:X C
    public const MCHINNEOXM = 'M CH IN+NEO:X M'; // MSM CH IN+NEO:X M
    public const MCHINNEOXG = 'M CH IN+NEO:X G'; // MSM CH IN+NEO:G C
    public const DOLBYDPLUSDS = 'DOLBY D+ +DS'; // MSDOLBY D+ +DS
    public const DOLBYAUDIODDPLUSDSUR = 'DOLBY AUDIO-DD+ +DSUR';
    public const DOLBYDPLUSNEOXC = 'DOLBY D+ +NEO:X C'; // MSDOLBY D+ +NEO:X C
    public const DOLBYDPLUSNEOXM = 'DOLBY D+ +NEO:X M'; // MSDOLBY D+ +NEO:X M
    public const DOLBYDPLUSNEOXG = 'DOLBY D+ +NEO:X G'; // MSDOLBY D+ +NEO:X G
    public const DOLBYAUDIODDPLUSPLUSNEURALX = 'DOLBY AUDIO-DD+ +NEURAL:X';
    public const DOLBYAUDIOTRUEHD = 'DOLBY AUDIO-TRUEHD';
    public const DOLBYDPLUSNEURALX = 'DOLBY D+ +NEURAL:X'; // MSDOLBY D+ +NEURAL:X
    public const DOLBYHDDS = 'DOLBY HD+DS'; // MSDOLBY HD+DS
    public const DOLBYAUDIOTRUEHDDSUR = 'DOLBY AUDIO-TRUEHD+DSUR';
    public const DOLBYAUDIOTRUEHDNEURALX = 'DOLBY AUDIO-TRUEHD+NEURAL:X';
    public const DOLBYHDNEOXC = 'DOLBY HD+NEO:X C'; // MSDOLBY HD+NEO:X C
    public const DOLBYHDNEOXM = 'DOLBY HD+NEO:X M'; // MSDOLBY HD+NEO:X M
    public const DOLBYHDNEOXG = 'DOLBY HD+NEO:X G'; // MSDOLBY HD+NEO:X G
    public const DOLBYHDNEURALX = 'DOLBY HD+NEURAL:X'; // MSDOLBY HD+NEURAL:X
    public const DTSHDNEOXC = 'DTS HD+NEO:X C'; // MSDTS HD+NEO:X C
    public const DTSHDNEOXM = 'DTS HD+NEO:X M'; // MSDTS HD+NEO:X M
    public const DTSHDNEOXG = 'DTS HD+NEO:X G'; // MSDTS HD+NEO:X G

    public const DSDDIRECT = 'DSD DIRECT'; // DSD DIRECT
    public const DSDPUREDIRECT = 'DSD PURE DIRECT'; // DSD PURE DIRECT

    public const MCHINDOLBYEX = 'M CH IN+DOLBY EX'; // M CH IN+DOLBY EX
    public const MULTICHIN71 = 'MULTI CH IN 7.1'; // MULTI CH IN 7.1

    public const AUDYSSEYDSX = 'AUDYSSEY DSX'; // AUDYSSEY DSX

    public const SURROUNDDISPLAY = 'SurroundDisplay'; // Nur DisplayIdent
    public const SYSMI = 'SYSMI'; // Nur DisplayIdent
    public const SYSDA = 'SYSDA'; // Nur DisplayIdent
    public const SSINFAISFSV = 'SSINFAISFSV'; // Nur DisplayIdent
    public const SSINFAISSIG = 'SSINFAISSIG'; // Nur DisplayIdent

    public const BTTXON = ' ON';
    public const BTTXOFF = ' OFF';
    public const BTTXSP = ' SP';
    public const BTTXBT = ' BT';

    public const SPPR_1 = ' 1';
    public const SPPR_2 = ' 2';

    // All Zone Stereo
    public const MNZST = 'MNZST';
    public const MNZSTON = ' ON';
    public const MNZSTOFF = ' OFF';

    public const PSGEQ = 'PSGEQ'; // Graphic EQ
    public const PSGEQON = ' ON'; // Graphic EQ On
    public const PSGEQOFF = ' OFF'; // Graphic EQ Off

    public const PSHEQ = 'PSHEQ'; // Headphone EQ
    public const PSHEQON = ' ON'; // Headphone EQ On
    public const PSHEQOFF = ' OFF'; // Headphone EQ Off

    public const PSSWL = 'PSSWL'; // Subwoofer Level
    public const PSSWL2 = 'PSSWL2'; // Subwoofer2 Level
    public const PSSWL3 = 'PSSWL3'; // Subwoofer3 Level
    public const PSSWL4 = 'PSSWL4'; // Subwoofer4 Level
    public const PSSWLON = ' ON'; // Subwoofer Level On
    public const PSSWLOFF = ' OFF'; // Subwoofer Level Off

    public const PSDIL = 'PSDIL'; // Dialog Level Adjust
    public const PSDILON = ' ON'; // Dialog Level Adjust On
    public const PSDILOFF = ' OFF'; // Dialog Level Adjust Off

    public const STBY = 'STBY'; // Mainzone Auto Standby
    public const STBY15M = '15M'; // Mainzone Auto Standby 15 Minuten
    public const STBY30M = '30M'; // Mainzone Auto Standby 30 Minuten
    public const STBY60M = '60M'; // Mainzone Auto Standby 60 Minuten
    public const STBYOFF = 'OFF'; // Mainzone Auto Standby Off
    public const Z2STBY = 'Z2STBY'; // Zone 2 Auto Standby
    public const Z2STBY2H = '2H'; // Zone 2 Auto Standby 2h
    public const Z2STBY4H = '4H'; // Zone 2 Auto Standby 4h
    public const Z2STBY8H = '8H'; // Zone 2 Auto Standby 8h
    public const Z2STBYOFF = 'OFF'; // Zone 2 Auto Standby Off
    public const Z3STBY = 'Z3STBY'; // Zone 3 Auto Standby
    public const Z3STBY2H = '2H'; // Zone 3 Auto Standby 2H
    public const Z3STBY4H = '4H'; // Zone 3 Auto Standby 4h
    public const Z3STBY8H = '8H'; // Zone 3 Auto Standby 8h
    public const Z3STBYOFF = 'OFF'; // Zone 3 Auto Standby Off
    public const ECO = 'ECO'; // ECO Mode
    public const ECOON = 'ON'; // ECO Mode On
    public const ECOAUTO = 'AUTO'; // ECO Mode Auto
    public const ECOOFF = 'OFF'; // ECO Mode Off
    public const DIM = 'DIM'; // Dimmer
    public const DIMBRI = ' BRI'; // Bright
    public const DIMDIM = ' DIM'; // DIM
    public const DIMDAR = ' DAR'; // Dark
    public const DIMOFF = ' OFF'; // Dimmer off

    public const SSHOSALS = 'SSHOSALS'; //Auto Lip Sync
    public const SSHOSALSON = ' ON'; //Auto Lip Sync On
    public const SSHOSALSOFF = ' OFF'; //Auto Lip Sync Off

    public const PSCES = 'PSCES'; // Center Spread
    public const PSCESON = ' ON'; // Center Spread On
    public const PSCESOFF = ' OFF'; // Center Spread Off

    public const PSSPV = 'PSSPV'; // Speaker Virtualizer
    public const PSSPVON = ' ON'; // Speaker Virtualizer On
    public const PSSPVOFF = ' OFF'; // Speaker Virtualizer Off

    public const PSNEURAL = 'PSNEURAL'; // Center Spread
    public const PSNEURALON = ' ON'; // Center Spread On
    public const PSNEURALOFF = ' OFF'; // Center Spread Off

    public const PSBSC = 'PSBSC'; // Bass Sync

    public const PSDEH = 'PSDEH'; // Dialog Enhancer
    public const PSDEHOFF = ' OFF'; // Dialog Enhancer Off
    public const PSDEHMED = ' MED'; // Dialog Enhancer Medium
    public const PSDEHLOW = ' LOW'; // Dialog Enhancer Low
    public const PSDEHHIGH = ' HIGH'; // Dialog Enhancer High

    public const PSAUROST = 'PSAUROST'; // Auro Matic 3D Strength
    public const PSAUROSTUP = ' UP'; // Auro Matic 3D Strength Up
    public const PSAUROSTDOWN = ' DOWN'; // Auro Matic 3D Strength Down

    public const PSAUROPR = 'PSAUROPR'; // Auro Matic 3D Present
    public const PSAUROPRSMA = ' SMA'; // Auro Matic 3D Present Small
    public const PSAUROPRMED = ' MED'; // Auro Matic 3D Present Medium
    public const PSAUROPRLAR = ' LAR'; // Auro Matic 3D Present Large
    public const PSAUROPRSPE = ' SPE'; // Auro Matic 3D Present SPE

    public const PSAUROMODE = 'PSAUROMODE'; // Auro 3D Mode
    public const PSAUROMODEDRCT = ' DRCTSMA'; // Auro 3D Mode Direct
    public const PSAUROMODEEXP = ' EXP'; // Auro 3D Mode Channel Expansion

    public const PSDIRAC = 'PSDIRAC'; //Dirac Live Filter
    public const CVSHL = 'CVSHL'; // Surround Height Left
    public const CVSHR = 'CVSHR'; // Surround Height Right
    public const CVTS = 'CVTS'; // Top Surround
    public const CVCH = 'CVCH'; // Center Height
    public const CVZRL = 'CVZRL'; // Reset Channel Volume Status

    public const CVTFL = 'CVTFL'; // Top Front Left
    public const CVTFR = 'CVTFR'; // Top Front Right
    public const CVTML = 'CVTML'; // Top Middle Left
    public const CVTMR = 'CVTMR'; // Top Middle Right
    public const CVTRL = 'CVTRL'; // Top Rear Left
    public const CVTRR = 'CVTRR'; // Top Rear Right
    public const CVRHL = 'CVRHL'; // Rear Height Left
    public const CVRHR = 'CVRHR'; // Rear Height Right
    public const CVFDL = 'CVFDL'; // Front Dolby Left
    public const CVFDR = 'CVFDR'; // Front Dolby Right
    public const CVSDL = 'CVSDL'; // Surround Dolby Left
    public const CVSDR = 'CVSDR'; // Surround Dolby Right
    public const CVBDL = 'CVBDL'; // Back Dolby Left
    public const CVBDR = 'CVBDR'; // Back Dolby Right
    public const CVTTR = 'CVTTR'; // Tactile Transducer
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
        $VarMapping = (new DENONIPSProfiles($this->AVRType, $InputMapping))->GetVarMapping();


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
