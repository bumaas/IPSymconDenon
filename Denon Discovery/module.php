<?php

declare(strict_types=1);

require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

class DenonDiscovery extends IPSModuleStrict
{

    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    private const WS_DISCOVERY_TIMEOUT = 2;

    /**
     * The multicast address to use in the socket for the discovery request.
     */
    private const WS_DISCOVERY_MULTICAST_ADDRESS = '239.255.255.250';

    private const DISCOVERY_SEARCHTARGET = 'urn:schemas-upnp-org:device:MediaRenderer:1';

    private const MODID_SPLITTER_TELNET = '{9AE3087F-DC25-4ADB-AB46-AD7455E71032}';
    private const MODID_DENON_TELNET    = '{DC733830-533B-43CD-98F5-23FC2E61287F}';
    private const MODID_CLIENT_SOCKET   = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    private const MODID_SSDP            = '{FFFFA648-B296-E785-96ED-065F7CEE6F29}';

    private const BUFFER_DEVICES      = 'Devices';
    private const BUFFER_SEARCHACTIVE = 'SearchActive';
    private const TIMER_LOADDEVICES   = 'LoadDevicesTimer';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->SetBuffer(self::BUFFER_DEVICES, json_encode([], JSON_THROW_ON_ERROR));
        $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(false, JSON_THROW_ON_ERROR));

    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->ApplyChanges();
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->SendDebug(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, $Value), 0);

        if ($Ident === 'loadDevices') {
            $this->loadDevices();
        }
    }

    /**
     * Liefert alle Geräte.
     *
     * @return array configlist all devices
     * @throws \JsonException
     */
    private function loadDevices(): void
    {
        $configurationValues = [];

        $configuredDevices = IPS_GetInstanceListByModuleID(self::MODID_DENON_TELNET);
        $this->SendDebug('configured devices', json_encode($configuredDevices, JSON_THROW_ON_ERROR), 0);

        $discoveredDevices = $this->getDiscoveredDevices();
        $this->SendDebug('discovered devices', json_encode($discoveredDevices, JSON_THROW_ON_ERROR), 0);

        foreach ($discoveredDevices as $device) {
            $instanceID     = 0;
            $name           = $device['friendlyName'];
            $host           = $device['host'];
            $model          = $device['modelName'];
            $manufacturer   = $device['manufacturer'];
            $manufacturerID = (strtoupper($manufacturer) === 'DENON') ? 1 : 2;
            $device_id      = 0;
            foreach ($configuredDevices as $deviceID) {
                $splitterID = IPS_GetInstance($deviceID)['ConnectionID'];
                if ($splitterID) {
                    $ioID = IPS_GetInstance($splitterID)['ConnectionID'];
                    if ($ioID && ($host === IPS_GetProperty($ioID, 'Host'))) {
                        //device is already configured
                        $instanceID = $deviceID;
                    }
                }
            }

            $AVRType = $this->GetAVRType($manufacturerID, $model);
            $this->SendDebug('Manufacturer:', sprintf('Manufacturer: %s, Model: %s, Type: %s', $manufacturerID, $model, $AVRType), 0);

            $configurationValues[] = [
                'instanceID'   => $instanceID,
                'id'           => $device_id,
                'name'         => $name,
                'host'         => $host,
                'model'        => $model,
                'manufacturer' => $manufacturer,
                'create'       => [
                    [
                        'moduleID'      => self::MODID_DENON_TELNET,
                        'configuration' => [
                            'manufacturer' => $manufacturerID,
                            'AVRTypeDenon' => $AVRType,
                            'Zone'         => 0
                        ]
                    ],
                    [
                        'moduleID'      => self::MODID_SPLITTER_TELNET,
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => self::MODID_CLIENT_SOCKET,
                        'configuration' => [
                            'Host' => $host,
                            'Port' => 23,
                        ]
                    ]
                ]
            ];
        }

        $configurationValuesEncoded = json_encode($configurationValues, JSON_THROW_ON_ERROR);
        $this->SendDebug(__FUNCTION__, '$configurationValues: ' . $configurationValuesEncoded, 0);

        $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(false, JSON_THROW_ON_ERROR));
        $this->SendDebug(__FUNCTION__, 'SearchActive deactivated', 0);

        $this->SetBuffer(self::BUFFER_DEVICES, $configurationValuesEncoded);
        $this->UpdateFormField('configurator', 'values', $configurationValuesEncoded);
        $this->UpdateFormField('searchingInfo', 'visible', false);

    }

    private function GetAVRType(int $manufacturerID, string $model_name): int
    {
        foreach (AVRs::getAllAVRs() as $AVRName => $Caps) {
            $manufacturerID_caps = ($Caps['Manufacturer'] === 'Denon') ? 1 : 2;

            if (($AVRName === $model_name) && ($manufacturerID === $manufacturerID_caps)) {
                return $Caps['internalID'];
            }
        }

        return -1;
    }

    private function receiveDevicesInfo(array $devices): array
    {
        $devicesInfo = [];

        foreach ($devices as $device) {
            // Check if Server key exists and Fedora is found in its value
            if (isset($device['Server']) && (str_contains($device['Server'], 'Denon') || str_contains($device['Server'], 'KnOS'))) {
                $locationInfo = $this->getDeviceInfoFromLocation($device['Location']);
                // Add to existing device info array
                $devicesInfo[] = [
                    'host'         => $device['IPv4'],
                    'friendlyName' => $locationInfo['friendlyName'],
                    'manufacturer' => $locationInfo['manufacturer'],
                    'modelName'    => $locationInfo['modelName']
                ];
            }
        }

        return $devicesInfo;
    }

    private function getDiscoveredDevices(): array
    {
        $ssdp_id     = IPS_GetInstanceListByModuleID(self::MODID_SSDP)[0];
        $devices     = YC_SearchDevices($ssdp_id, self::DISCOVERY_SEARCHTARGET);
        $device_info = $this->receiveDevicesInfo($devices);

        // zum Test wird der Eintrag verdoppelt und eine abweichende IP eingesetzt
        //$denon_info[]=$denon_info[0];
        //$denon_info[1]['host']='192.168.178.34';

        return $device_info;
    }


    private function GetDeviceInfoFromLocation(string $location): array
    {
        $manufacturer = '';
        $friendlyName = 'Name';
        $modelName    = 'Model';

        $description = $this->GetXML($location);
        $xml         = @simplexml_load_string($description);
        if ($xml) {
            $manufacturer = (string)$xml->device->manufacturer;
            $friendlyName = (string)$xml->device->friendlyName;
            $modelName    = (string)$xml->device->modelName;
        }
        return ['manufacturer' => $manufacturer, 'friendlyName' => $friendlyName, 'modelName' => $modelName];
    }


    private function GetXML(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); //timeout after 2 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        $result      = curl_exec($ch);
        $this->SendDebug('Get XML:', sprintf('URL: %s, Status: %s, result: %s', $url, $status_code, $result), 0);
        curl_close($ch);
        return $result;
    }

    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form.
     *
     * @return string
     * @throws \JsonException
     */
    public function GetConfigurationForm(): string
    {

        $this->SendDebug(__FUNCTION__, 'Start', 0);
        $this->SendDebug(__FUNCTION__, 'SearchActive: ' . $this->GetBuffer(self::BUFFER_SEARCHACTIVE), 0);

        // Do not start a new search, if a search is currently active
        if (!json_decode($this->GetBuffer(self::BUFFER_SEARCHACTIVE), false, 512, JSON_THROW_ON_ERROR)) {
            $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(true, JSON_THROW_ON_ERROR));

            // Start device search in a timer, not prolonging the execution of GetConfigurationForm
            $this->SendDebug(__FUNCTION__, 'RegisterOnceTimer', 0);
            $this->RegisterOnceTimer(self::TIMER_LOADDEVICES, 'IPS_RequestAction($_IPS["TARGET"], "loadDevices", "");');
        }
        // return current form
        $Form = json_encode([
                                'elements' => [],
                                'actions' => $this->formActions(),
                                'status'  => []
                            ], JSON_THROW_ON_ERROR);
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return $Form;
    }

    /**
     * return form actions
     *
     * @return array
     * @throws \JsonException
     */
    private function formActions(): array
    {
        $devices = json_decode($this->GetBuffer(self::BUFFER_DEVICES), false, 512, JSON_THROW_ON_ERROR);

        return [
            [
                // Inform user, that the search for devices could take a while if no devices were found yet
                [
                    'name'          => 'searchingInfo',
                    'type'          => 'ProgressBar',
                    'caption'       => 'The configurator is currently searching for devices. This could take a while...',
                    'indeterminate' => true,
                    'visible'       => count($devices) === 0
                ],

                'name'     => 'configurator',
                'type'     => 'Configurator',
                'rowCount' => 20,
                'add'      => false,
                'delete'   => true,
                'sort'     => [
                    'column'    => 'name',
                    'direction' => 'ascending'
                ],
                'columns'  => [
                    [
                        'caption' => 'ID',
                        'name'    => 'id',
                        'width'   => '200px',
                        'visible' => false
                    ],
                    [
                        'caption' => 'name',
                        'name'    => 'name',
                        'width'   => 'auto'
                    ],
                    [
                        'caption' => 'manufacturer',
                        'name'    => 'manufacturer',
                        'width'   => '250px'
                    ],
                    [
                        'caption' => 'model',
                        'name'    => 'model',
                        'width'   => '250px'
                    ],
                    [
                        'caption' => 'host',
                        'name'    => 'host',
                        'width'   => '250px'
                    ]
                ],
                'values'   => $devices
            ]
        ];
    }

}
