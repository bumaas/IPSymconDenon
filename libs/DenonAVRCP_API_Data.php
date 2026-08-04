<?php

declare(strict_types=1);

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
