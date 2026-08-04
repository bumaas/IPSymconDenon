<?php

declare(strict_types=1);

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
            $DataMain    = $this->MainZoneXml($xmlMainZone, $DataMain, $VarMappings, $Inputs);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
        }

        try {
            $xmlNetAudioStatus = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_NetAudioStatusXml.xml'));
            $DataMain          = $this->NetAudioStatusXml($xmlNetAudioStatus, $DataMain);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
        }

        try {
            $xmlDeviceinfo = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_Deviceinfo.xml'));
            $DataMain      = $this->Deviceinfo($xmlDeviceinfo, $DataMain);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
        }

        // Zone 2

        $DataZ2 = [];

        try {
            $xml    = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_MainZoneXml.xml?_=&ZoneName=ZONE2'));
            $DataZ2 = $this->StateZone2($xml, $DataZ2, $InputMapping);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
        }

        // Zone 3

        $DataZ3 = [];

        try {
            $xml    = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formMainZone_MainZoneXml.xml?_=&ZoneName=ZONE3'));
            $DataZ3 = $this->StateZone3($xml, $DataZ3, $InputMapping);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
        }

        //Model
        try {
            $xmlDeviceSearch = @new SimpleXMLElement(file_get_contents('http://' . $ip . '/goform/formiPhoneAppDeviceSearch.xml'));
            $DataMain        = $this->DeviceSearch($xmlDeviceSearch, $DataMain);
            $DataZ2          = $this->DeviceSearch($xmlDeviceSearch, $DataZ2);
            $DataZ3          = $this->DeviceSearch($xmlDeviceSearch, $DataZ3);
        } catch (Exception $e) {
            IPS_LogMessage(__CLASS__, __FUNCTION__ . ': ' . $e->getMessage());
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
