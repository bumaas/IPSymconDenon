<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

class DenonDiscovery extends IPSModule
{

    private const PROPERTY_TARGET_CATEGORY_ID = 'targetCategoryID';
    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    private const WS_DISCOVERY_TIMEOUT = 2;

    /**
     * The multicast address to use in the socket for the discovery request.
     */
    private const WS_DISCOVERY_MULTICAST_ADDRESS = '239.255.255.250';

    /**
     * The port that will be used in the socket for the discovery request.
     */
    private const WS_DISCOVERY_MULTICAST_PORT = 1900;

    private const WS_DISCOVERY_ST = 'urn:schemas-upnp-org:device:MediaRenderer:1';


    private const MODID_SPLITTER_TELNET = '{9AE3087F-DC25-4ADB-AB46-AD7455E71032}';
    private const MODID_DENON_TELNET    = '{DC733830-533B-43CD-98F5-23FC2E61287F}';
    private const MODID_CLIENT_SOCKET   = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(self::PROPERTY_TARGET_CATEGORY_ID, 0);

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->ApplyChanges();
        }
    }

    private function getPathOfCategory(int $categoryId): array
    {
        if ($categoryId === 0) {
            return [];
        }

        $path[]   = IPS_GetName($categoryId);
        $parentId = IPS_GetObject($categoryId)['ParentID'];

        while ($parentId > 0) {
            $path[]   = IPS_GetName($parentId);
            $parentId = IPS_GetObject($parentId)['ParentID'];
        }

        return array_reverse($path);
    }

    /**
     * Liefert alle Geräte.
     *
     * @return array configlist all devices
     */
    private function Get_ConfiguratorValues(): array
    {
        $config_values = [];

        $configuredDevices = IPS_GetInstanceListByModuleID(self::MODID_DENON_TELNET);
        $this->SendDebug('configured devices', json_encode($configuredDevices), 0);

        $discoveredDevices = $this->DiscoverDevices();
        $this->SendDebug('discovered devices', json_encode($discoveredDevices), 0);

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

            $config_values[] = [
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
                        ],
                        'location'      => $this->getPathOfCategory($this->ReadPropertyInteger(self::PROPERTY_TARGET_CATEGORY_ID))
                    ],
                    [
                        'moduleID'      => self::MODID_SPLITTER_TELNET,
                        'configuration' => [
                            'WriteDebugInformationToLogfile' => false,
                        ]
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

        return $config_values;
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

    private function DiscoverDevices(): array
    {
        // BUILD MESSAGE
        $message  = [
            'M-SEARCH * HTTP/1.1',
            'HOST: 239.255.255.250:1900',
            'MAN: "ssdp:discover"',
            'MX: 2',                    // maximum amount of seconds it takes for a device to respond
            // 'ST: upnp:rootdevice'       //This defines the devices we would like to discover on the network.
            'ST: ' . self::WS_DISCOVERY_ST       //This defines the devices we would like to discover on the network.
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->SendDebug('----' . __FUNCTION__, 'ST: ' . self::WS_DISCOVERY_ST, 0);
        if (!$socket) {
            return [];
        }

        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, true);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
        socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);

        $this->SendDebug('Search', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT)
            === false) {
            return [];
        }

        // RECIEVE RESPONSE
        $device_info      = [];
        $IPAddress        = '';
        $Port             = 0;
        $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;

        do {
            $buf   = null;
            $bytes = @socket_recvfrom($socket, $buf, 2048, 0, $IPAddress, $Port);
            if ((bool)$bytes === false) {
                break;
            }
            $this->SendDebug(sprintf('Receive (%s:%s)', $IPAddress, $Port), (string)$buf, 0);

            if (!is_null($buf)) {
                $device = $this->parseHeader($buf);
                if (isset($device['SERVER'])) {
                    if ((strpos($device['SERVER'], 'Denon') !== false) || (strpos($device['SERVER'], 'KnOS') !== false)) {
                        $locationInfo  = $this->GetDeviceInfoFromLocation($device['LOCATION']);
                        $device_info[] = [
                            'host'         => $IPAddress,
                            'friendlyName' => $locationInfo['friendlyName'],
                            'manufacturer' => $locationInfo['manufacturer'],
                            'modelName'    => $locationInfo['modelName']
                        ];
                    }
                }
            }
        } while (time() < $discoveryTimeout);

        // CLOSE SOCKET
        socket_close($socket);

        // zum Test wird der Eintrag verdoppelt und eine abweichende IP eingesetzt
        //$denon_info[]=$denon_info[0];
        //$denon_info[1]['host']='192.168.178.34';

        return $device_info;
    }

    private function parseHeader(string $Data): array
    {
        $Lines = explode("\r\n", $Data);
        array_shift($Lines);
        array_pop($Lines);
        $Header = [];
        foreach ($Lines as $Line) {
            $line_array                                         = explode(':', $Line);
            $Header[strtoupper(trim(array_shift($line_array)))] = trim(implode(':', $line_array));
        }
        return $Header;
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
     */
    public function GetConfigurationForm(): string
    {
        // return current form
        $Form = json_encode(
            [
                'elements' => $this->FormElements(),
                'actions'  => $this->FormActions(),
                'status'   => []
            ]
        );
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return $Form;
    }

    /**
     * return form elements
     *
     * @return array
     */
    private function FormElements(): array
    {
        return [
            [
                'type'    => 'SelectCategory',
                'name'    => 'targetCategoryID',
                'caption' => 'Target Category'
            ]
        ];
    }

    /**
     * return form actions
     *
     * @return array
     */
    private function FormActions(): array
    {
        return [
            [
                'name'     => 'DenonDiscovery',
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
                'values'   => $this->Get_ConfiguratorValues()
                //'values' => []
            ]
        ];
    }

}
