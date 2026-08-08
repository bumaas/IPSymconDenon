<?php

declare(strict_types=1);
require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

class DenonAVRTelnet extends AVRModule
{

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterProperties();

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        //we will set the instance status when the parent status changes
        if ($this->GetParent() > 0) {
            $this->RegisterMessage($this->GetParent(), IM_CHANGESTATUS);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        $this->Logger_Dbg(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data:' . json_encode($Data, JSON_THROW_ON_ERROR));

        switch ($Message) {
            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;

            case IM_CHANGESTATUS:
                $this->ApplyChanges();
                break;
        }
    }

    private function arrayToObject($array): stdClass
    {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $object->$key = $this->arrayToObject($value);
            } else {
                $object->$key = $value;
            }
        }

        return $object;
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }


        if ($this->SetInstanceStatus() === true) {
            $manufacturername = $this->GetManufacturerName();
            $AVRType          = $this->GetAVRType($manufacturername);

            $this->ValidateConfiguration($manufacturername, $AVRType);

            // über http werden zusätzliche Daten geholt (MainZoneName, Model)
            if (AVRs::getCapabilities($AVRType)['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) {
                $data = $this->GetStateHTTP();
                //das Array muss für die weitere Verarbeitung in ein Object umgewandelt werden
                $data = $this->arrayToObject($data);
                $this->UpdateVariable($data);
            }

            $this->SetSummary(sprintf('%s:%s', $AVRType, $this->ReadPropertyInteger('Zone')));
        }
    }

    private function ValidateConfiguration($manufacturername, $AVRType): void
    {
        $Zone        = $this->ReadPropertyInteger('Zone');
        $DenonAVRVar = new DENONIPSProfiles($AVRType, null, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        });
        //Input ablegen, damit sie später dem Splitter zur Verfügung stehen
        try {
            /** @noinspection PhpUndefinedFunctionInspection */
            DAVRST_SaveInputVarmapping($this->GetParent(), json_encode($this->GetInputsAVR($DenonAVRVar), JSON_THROW_ON_ERROR));
        } catch (Exception $e) {
            trigger_error($e->getMessage());
        }

        $AVRCaps = AVRs::getCapabilities($AVRType);

        $profiles = $DenonAVRVar->GetAllProfilesSortedByPos();
        $idents   = [];

        if ($Zone === 0) {//Main zone

            $idents[DENONIPSProfiles::ptMainZoneName] = $this->ReadPropertyBoolean('ZoneName');
            $idents[DENONIPSProfiles::ptModel]        = $this->ReadPropertyBoolean('Model');

            // ReadProperty for all Variables of the following areas

            $CommandAreas = [
                'AVRInfos',
                'PowerFunctions',
                'InputSettings',
                'SurroundMode',
                'CV_Commands',
                'PS_Commands',
                'VS_Commands',
                'PV_Commands',
                'SystemControl_Commands',
                'Tuner_Control'
            ];

            foreach ($CommandAreas as $commandArea) {
                $Caps = $AVRCaps[$commandArea];
                foreach ($profiles as $key => $profile) {
                    if (in_array($profile['Ident'], $Caps, true)) {
                        $idents[$key] = $this->ReadPropertyBoolean($profile['PropertyName']);
                    }
                }
            }
        } else { //Zone 2 oder 3

            // ReadProperty of CommandArea 'Zone_Commands'
            foreach ($profiles as $key => $profile) {
                if (in_array($profile['Ident'], $AVRCaps['Zone_Commands'], true)) {
                    // Zonen-Commands: nur die der aktuellen Zone, zonenneutrale immer
                    if (!$this->isZoneSpecificIdent($profile['Ident'])
                        || $this->identMatchesZone($profile['Ident'], $Zone + 1)) {
                        $idents[$key] = $this->ReadPropertyBoolean($profile['PropertyName']);
                    }
                }
            }
        }

        $this->RegisterVariables($DenonAVRVar, $idents, $manufacturername);

    }

    public function GetStates(): void
    {
        $AVRVarIDs = IPS_GetChildrenIDs($this->InstanceID);

        if (count($AVRVarIDs) === 0) {
            //nothing to do
            return;
        }

        //Array Ident erzeugen
        $AVRCommands = [];

        foreach ($AVRVarIDs as $ObjektID) {
            $ObjektIDInfo = IPS_GetObject($ObjektID);
            //Hidden nicht abfragen
            if (!$ObjektIDInfo['ObjectIsHidden']) {
                $Ident = $ObjektIDInfo['ObjectIdent'];
                //spezielle Elemente ebenfalls nicht abfragen
                if (!in_array($Ident, ['MN', 'MNMEN', 'MNSRC', 'MainZoneName', 'Model', 'SurroundDisplay'])) {
                    $AVRCommands[] = $Ident;
                }
            }
        }

        //collect all commands
        $Commands = [];
        foreach (new DENONIPSProfiles()->GetAllProfiles() as $profile) {
            if (in_array($profile['Ident'], $AVRCommands, true)) {
                if (isset($profile['IndividualStatusRequest'])) {
                    $Commands[] = $profile['IndividualStatusRequest'];
                } else {
                    $Commands[] = $profile['Ident'] . ' ?';
                }
            }
        }

        //eliminate duplicates and call each command
        foreach (array_unique($Commands) as $Command) {
            $this->SendCommand($Command);
            IPS_Sleep(200); //Doku: responses should be sent within 200ms of receiving the command
        }

        // über http werden zusätzliche Daten geholt (MainZoneName, Model)
        $AVRType = $this->GetAVRType($this->GetManufacturerName());
        if (AVRs::getCapabilities($AVRType)['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) {
            $data = $this->GetStateHTTP();
            //das Array muss für die weitere Verarbeitung in ein Object umgewandelt werden
            $data = $this->arrayToObject($data);
            $this->UpdateVariable($data);
        }
    }

    /**
     * @param $Ident
     * @param $Value
     *
     * @return void
     * @throws \Exception
     *
     */
    //Commands ohne automatischen Response: nach dem Senden wird der Status
    //nachgefragt (<command>+?), damit die Variablen nachgeführt werden.
    //Wert = mit Leerzeichen vor dem '?' (true) oder ohne (false).
    //todo: gibt es Variablen, die nachgeführt werden müssen, da sie sonst nicht aktualisiert werden?
    //die Liste ist noch zu überprüfen
    private const array STATUS_REQUEST_AFTER_SEND = [
        'PSVOLLEV' => true,  // Dolby Volume Leveler
        'PSREFLEV' => true,  // ReferenceLevel (wird manchmal(!) nicht automatisch beantwortet)
        'PSVOLMOD' => true,  // Dolby Volume Modeler
        'PSDCO'    => true,  // Dynamic Compressor
        'PSDRC'    => true,  // Dynamic Range Compression
        'PSPAN'    => true,  // Panorama
        'PSDYNEQ'  => true,  // Dynamic EQ
        'PSAFD'    => true,
        'VSAUDIO'  => true,
        'PSRSZ'    => true,  // Room Size
        'VSSC'     => true,  // Resolution
        'VSSCH'    => true,  // Resolution HDMI
        'PSSWR'    => true,  // Subwoofer
        'PSDIM'    => true,
        'Z2'       => false,
        'Z3'       => false,
        'MU'       => false,
        'PSFRONT'  => false, // Front Speaker
    ];

    //Effect Speaker Selection: Nachfrage mit ':'-Suffix
    private const array STATUS_REQUEST_WITH_COLON = ['PSSP', 'PSFH'];

    protected function getSplitterInputVarMapping(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return DAVRST_GetInputVarMapping($this->GetParent());
    }

    protected function afterRequestAction(string $APICommand): void
    {
        if (in_array($APICommand, self::STATUS_REQUEST_WITH_COLON, true)) {
            $this->SendRequest($APICommand . ':', true);
        } elseif (array_key_exists($APICommand, self::STATUS_REQUEST_AFTER_SEND)) {
            $this->SendRequest($APICommand, self::STATUS_REQUEST_AFTER_SEND[$APICommand]);
        }
    }

    private function SendRequest($APICommand, $Space): void
    {
        IPS_Sleep(30);
        if ($Space) {
            $APISubCommand = chr(32) . '?';
        } else {
            $APISubCommand = '?';
        }
        $this->SendCommand($APICommand . $APISubCommand);
    }

    //Data Transfer
    public function SendCommand(string $payload): void
    {
        $sendcommand = $payload . chr(13);
        $this->SendDebug('Send Command Telnet:', $sendcommand, 0);
        $this->SendDataToParent(json_encode(['DataID' => '{01A68655-DDAF-4F79-9F35-65878A86F344}', 'Buffer' => $sendcommand], JSON_THROW_ON_ERROR)
        ); //Denon AVR Telnet Interface GUI
    }

    //Get Status HTTP
    public function GetStateHTTP(): ?array
    {
        $AVRType = $this->GetAVRType($this->GetManufacturerName());

        $DenonGet = new DENON_StatusHTML();

        try {
            /** @noinspection PhpUndefinedFunctionInspection */
            $InputMapping = DAVRST_GetInputVarMapping($this->GetParent());
            return $DenonGet->getStates($this->GetIPParent(), $InputMapping, $AVRType);
        } catch (Exception $e) {
            trigger_error($e->getMessage());
        }

        return null;
    }

    //######################## Denon Commands #######################################
    //Power
    public function Power(bool $Value): void
    { // false (Standby) oder true (On)
        $this->sendMappedValue(DENON_API_Commands::PW, $Value);
    }

    //Main zone Power
    public function MainZonePower(bool $Value): void
    { // MainZone true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::ZM, $Value);
    }

    //Main zone Standby Setting
    public function MainzoneAutoStandbySetting(int $Value): bool
    { // 0 (Off) / 15 / 30 / 60 (Minuten)
        switch ($Value) {
            case 0:
                $subcommand = DENON_API_Commands::STBYOFF;
                break;
            case 15:
                $subcommand = DENON_API_Commands::STBY15M;
                break;
            case 30:
                $subcommand = DENON_API_Commands::STBY30M;
                break;
            case 60:
                $subcommand = DENON_API_Commands::STBY60M;
                break;
            default:
                trigger_error(__FUNCTION__ . ': unsupported Value: ' . $Value);

                return false;
        }

        $this->SendCommand(DENON_API_Commands::STBY . $subcommand);

        return true;
    }

    //Mainzone Standby Setting
    public function MainzoneEcoModeSetting(string $Value): void
    { // On / Auto / Off
        $this->sendMappedValueName(DENON_API_Commands::ECO, $Value);
    }

    //Master Volume
    public function MasterVolume(string $command): void
    { // "UP" or "DOWN"
        $payload = DENON_API_Commands::MV . $command;
        $this->SendCommand($payload);
    }

    public function MasterVolumeStep(string $command, float $step) // "UP" or "DOWN", Step Schrittweite der Lautstärke Änderung Minimum 0.5
    : void
    {
        if ($step < 1 || $step > 40) {
            $this->Logger_Err(__FUNCTION__ . ': Schrittweite muss zwischen 1 und 40 liegen');

            return;
        }
        $valmax     = 18;
        $valmin     = -80;
        $currentvol = GetValueFloat($this->GetIDForIdent('MV'));
        $Value      = $currentvol;
        if ($command === 'UP' && ($currentvol < ($valmax - $step))) {
            $Value = $currentvol + $step;
        }
        if ($command === 'DOWN' && ($currentvol > ($valmin + $step))) {
            $Value = $currentvol - $step;
        }

        $this->sendMappedValue(DENON_API_Commands::MV, $Value);
    }

    public function MasterVolumeFix(float $Value): void
    { // float -80 bis 18 Schrittweite 0.5
        $this->sendMappedValue(DENON_API_Commands::MV, $Value);
    }

    //MasterVolumePercent
    public function MasterVolumePercent(int $percent): void
    {
        $Value = ((98 / 100) * $percent) - 80;
        $Value = round($Value * 2) / 2;

        $this->sendMappedValue(DENON_API_Commands::MV, $Value);
    }

    //Main Mute
    public function MainMute(bool $Value): void
    { // false (Off) oder true (On)
        $this->sendMappedValue(DENON_API_Commands::MU, $Value);
    }

    //Input
    public function Input(string $command): void
    {
        $this->SendCommand(DENON_API_Commands::SI . $command);
    }

    //Surround Mode
    public function SurroundMode(string $command): void
    {
        $this->SendCommand(DENON_API_Commands::MS . $command);
    }

    //All Zone Stereo
    public function AllZoneStereo(bool $Value) // false (Off) oder true (On)
    : void
    {
        $this->sendMappedValue(DENON_API_Commands::MNZST, $Value);
    }

    //Get Display NSADisplay
    public function NSADisplay(): void
    {
        $this->SendCommand(DENON_API_Commands::NSA);
    }

    public function NSEDisplay(): void
    {
        $this->SendCommand(DENON_API_Commands::NSE);
    }

    //Dynamic Volume
    public function DynamicVolume(string $Value): void
    { // Dynamic Volume Midnight / Evening / Day
        $this->sendMappedValueName(DENON_API_Commands::PSDYNVOL, $Value);
    }

    //Dolby Volume
    public function DolbyVolume(bool $Value): void
    { // Dolby Volume true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSDOLVOL, $Value);
    }

    //Dolby Volume Modeler
    public function DolbyVolumeModeler(string $Value): void
    { // Dolby Volume Modeler Off / Half / Full
        $this->sendMappedValueName(DENON_API_Commands::PSVOLMOD, $Value);
    }

    //Dolby Volume Leveler
    public function DolbyVolumeLeveler(string $Value): void
    { // Dolby Volume Leveler Low / Middle / High
        $this->sendMappedValueName(DENON_API_Commands::PSVOLLEV, $Value);
    }

    //Dynamic Compressor
    public function DynamicCompressor(string $Value): void
    { // Dynamic Compressor Off / Low / Middle / High
        $this->sendMappedValueName(DENON_API_Commands::PSDCO, $Value);
    }

    //Dynamic Range Compression
    public function DynamicRangeCompression(string $Value): void
    { // Dynamic Range Compression Off / Auto / Low / Middle / High
        $this->sendMappedValueName(DENON_API_Commands::PSDRC, $Value);
    }

    //Audyssey DSX
    public function AudysseyDSX(string $Value): void
    { // Audyssey DSX Off / Wide (Audyssey DSX ON(Wide)) / Height (Audyssey DSX ON(Height)) / Height/Wide (Audyssey DSX ON(Height/Wide))
        $this->sendMappedValueName(DENON_API_Commands::PSDSX, $Value);
    }

    //CinemaEQ
    public function CinemaEQ(bool $Value): void
    { // CinemaEQ true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::CINEMAEQCOMMAND, $Value);
    }

    //Panorama
    public function Panorama(bool $Value): void
    { // Panorama true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSPAN, $Value);
    }

    //Dynamic EQ
    public function DynamicEQ(bool $Value): void
    { // Dynamic EQ true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSDYNEQ, $Value);
    }

    //Channel Volume
    public function ChannelVolumeFL(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFL, $Value);
    }

    public function ChannelVolumeFR(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFR, $Value);
    }

    public function ChannelVolumeC(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVC, $Value);
    }

    public function ChannelVolumeSW(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSW, $Value);
    }

    public function ChannelVolumeSW2(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSW2, $Value);
    }

    public function ChannelVolumeSW3(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSW3, $Value);
    }

    public function ChannelVolumeSW4(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSW4, $Value);
    }

    public function ChannelVolumeSL(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSL, $Value);
    }

    public function ChannelVolumeSR(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSR, $Value);
    }

    public function ChannelVolumeSBL(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSBL, $Value);
    }

    public function ChannelVolumeSBR(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSBR, $Value);
    }

    public function ChannelVolumeSB(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSB, $Value);
    }

    public function ChannelVolumeFHL(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFHL, $Value);
    }

    public function ChannelVolumeFHR(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFHR, $Value);
    }

    public function ChannelVolumeFWL(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFWL, $Value);
    }

    public function ChannelVolumeFWR(float $Value): void
    { // Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFWR, $Value);
    }

    public function ChannelVolumeSHL(float $Value): void
    { //Surround Height Left Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSHL, $Value);
    }

    public function ChannelVolumeSHR(float $Value): void
    { //Surround Height Right Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSHR, $Value);
    }

    public function ChannelVolumeTS(float $Value): void
    { //Top Surround Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTS, $Value);
    }

    public function ChannelVolumeCH(float $Value): void
    { //Center Height Range -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVCH, $Value);
    }

    public function ChannelVolumeZRL(): void
    { //Reset all channel volume status
        $this->SendCommand(DENON_API_Commands::CVZRL);
    }

    public function ChannelVolumeTFL(float $Value): void
    { //Top Front Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTFL, $Value);
    }

    public function ChannelVolumeTFR(float $Value): void
    { //Top Front Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTFR, $Value);
    }

    public function ChannelVolumeTML(float $Value): void
    { //Top Middle Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTML, $Value);
    }

    public function ChannelVolumeTMR(float $Value): void
    { //Top Middle Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTMR, $Value);
    }

    public function ChannelVolumeTRL(float $Value): void
    { //Top Rear Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTRL, $Value);
    }

    public function ChannelVolumeTRR(float $Value): void
    { //Top Rear Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVTRR, $Value);
    }

    public function ChannelVolumeRHL(float $Value): void
    { // Rear Height Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVRHL, $Value);
    }

    public function ChannelVolumeRHR(float $Value): void
    { // Rear Height Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVRHR, $Value);
    }

    public function ChannelVolumeFDL(float $Value): void
    { // Front Dolby Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFDL, $Value);
    }

    public function ChannelVolumeFDR(float $Value): void
    { // Front Dolby Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVFDR, $Value);
    }

    public function ChannelVolumeSDL(float $Value): void
    { // Surround Dolby Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSDL, $Value);
    }

    public function ChannelVolumeSDR(float $Value): void
    { // Surround Dolby Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVSDR, $Value);
    }

    public function ChannelVolumeBDL(float $Value): void
    { // Back Dolby Left -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVBDL, $Value);
    }

    public function ChannelVolumeBDR(float $Value): void
    { // Back Dolby Right -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::CVBDR, $Value);
    }

    //RecSelect
    public function RecSelect(string $command): void
    { // NET/USB; USB; NAPSTER; LASTFM; FLICKR; FAVORITES; IRADIO; SERVER; SERVER;  USB/IPOD
        $this->SendCommand(DENON_API_Commands::SR . $command);
    }

    //Video Select
    public function VideoSelect(string $command) // Video Select DVD , BD , TV , SAT/CBL , DVR ,GAME , AUX , DOCK , SOURCE, MPLAY
    : void
    {
        $manufacturername = $this->GetManufacturerName();
        $AVRType          = $this->GetAVRType($manufacturername);
        if ($command === 'AUX') {
            if (in_array(
                $AVRType, [
                            'AVR-X7200W',
                            'AVR-X5200W',
                            'AVR-X4100W',
                            'AVR-X3100W',
                            'AVR-X2000',
                            'AVR-X2100W',
                            'AVR-X2200W',
                            'AVR-X2300W',
                            'S900W',
                            'AVR-X7200WA',
                            'AVR-X6200W',
                            'AVR-X4200W',
                            'AVR-X3200W',
                            'AVR-X1200W'
                        ]
            )) {
                $command = 'AUX1';
            } else {
                $command = 'V.AUX';
            }
        }

        $payload = DENON_API_Commands::SV . $command;
        $this->SendCommand($payload);
    }

    //Subwoofer
    public function Subwoofer(bool $Value): void
    { // Subwoofer true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSSWR, $Value);
    }

    //Subwoofer ATT
    public function SubwooferATT(bool $Value): void
    { // Subwoofer ATT true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSATT, $Value);
    }

    /** Subwoofer Output Off.
     *
     */
    public function SubwooferOutputOff(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCSWF . DENON_API_Commands::NON);
    }

    /** Subwoofer Output One.
     *
     */
    public function SubwooferOutputOne(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCSWF . DENON_API_Commands::SPONE);
    }

    /** Subwoofer Output Two.
     *
     */
    public function SubwooferOutputTwo(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCSWF . DENON_API_Commands::SPTWO);
    }

    /** Speaker Front Small.
     *
     */
    public function SpeakerFrontSmall(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCFRO . DENON_API_Commands::SMA);
    }

    /** Speaker Front Large.
     *
     */
    public function SpeakerFrontLarge(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCFRO . DENON_API_Commands::LAR);
    }

    /** Subwoofer Output Two.
     *
     */
    public function SpeakerCenterSmall(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCCEN . DENON_API_Commands::SMA);
    }

    /** Subwoofer Output Two.
     *
     */
    public function SpeakerCenterLarge(): void
    {
        $this->SendCommand(DENON_API_Commands::SSSPCCEN . DENON_API_Commands::LAR);
    }

    //Front Height
    public function FrontHeight(bool $Value): void
    { // Front Height true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSFH, $Value);
    }

    //Tone CTRL
    public function ToneCTRL(bool $Value): void
    { // Tone CTRL true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSTONECTRL, $Value, DENON_API_Commands::TONECTRL);
    }

    //Audio Delay
    public function AudioDelay(int $Value): void
    { // can be operated from 0 to 300
        $this->sendMappedValue(DENON_API_Commands::PSDELAY, $Value);
    }

    //Speaker Output Front
    public function SpeakerOutputFront(string $Value): void
    { // Speaker Output Front Off / Wide / Height / Height/Wide
        $this->sendMappedValueName(DENON_API_Commands::PSSP, $Value);
    }

    //Auto Flag Detect Mode
    public function AutoFlagDetectMode(bool $Value): void
    { // Auto Flag Detect Mode true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::PSAFD, $Value);
    }

    //ASP
    public function ASP(string $Value): void
    { // ASP Normal / Full
        $this->sendMappedValueName(DENON_API_Commands::VSASP, $Value);
    }

    //Audio Restorer
    public function AudioRestorer(string $Value): void
    { // Audio Restorer Off / 64 / 96 / HQ
        $this->sendMappedValueName(DENON_API_Commands::PSRSTR, $Value);
    }

    //Center Image
    public function CenterImage(float $Value): void
    { //Center Image can be operated from 0.0 to 1.0 Step 0.1
        $this->sendMappedValue(DENON_API_Commands::PSCEI, $Value);
    }

    //Center Width
    public function CenterWidth(float $Value): void
    { //Center Width can be operated from 0 to 7 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PSCEN, $Value);
    }

    //Input Mode
    public function SelectDecodeMode(string $Value): void
    { // AUTO; HDMI; DIGITAL; ANALOG
        $this->sendMappedValueName(DENON_API_Commands::SD, $Value);
    }

    //Digital Input Mode
    public function DigitalInputMode(string $Value): void
    { // Digital Input Mode Auto / PCM / DTS
        $this->sendMappedValueName(DENON_API_Commands::DC, $Value);
    }

    //Dimension
    public function Dimension(int $Value): void
    { //Dimension can be operated from 0 to 6
        $this->sendMappedValue(DENON_API_Commands::PSDIM, $Value);
    }

    //Dimmer (display brightness, not to be confused with Dimension/PSDIM or Brightness/PVBR)
    public function Dimmer(int $Value): void
    { //Dimmer: 0 = Off, 1 = Dark, 2 = Dim, 3 = Bright
        $this->sendMappedValue(DENON_API_Commands::DIM, $Value);
    }

    //Effect Level
    public function EffectLevel(float $Value): void
    { //Effect Level can be operated from 1 to 15 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PSEFF, $Value);
    }

    //HDMI Audio Output
    public function HDMIAudioOutput(string $Value): void
    { // HDMI Audio Output TV / AMP
        $this->sendMappedValueName(DENON_API_Commands::VSAUDIO, $Value);
    }

    //Multi EQ Mode
    public function MultiEQMode(string $Value): void
    { // Multi EQ Mode Audyssey / BYP.LR / Flat / Manual / Off
        $this->sendMappedValueName(DENON_API_Commands::PSMULTEQ, $Value);
    }

    //PLIIZHeightGain
    public function PLIIZHeightGain(string $Value): void
    { // PLIIZHeightGain Low / Middle / High
        $this->sendMappedValueName(DENON_API_Commands::PSPHG, $Value);
    }

    //Reference Level
    public function ReferenceLevel(int $Value): void
    { // Reference Level 0 / 5 / 10 / 15
        $this->sendMappedValue(DENON_API_Commands::PSREFLEV, $Value);
    }

    //Room Size
    public function RoomSize(string $Value): void
    { // Room Size Small / Small/Medium / Medium / Medium/Large / Large
        $this->sendMappedValueName(DENON_API_Commands::PSRSZ, $Value);
    }

    //Stage Width
    public function StageWidth(float $Value): void
    { //Stage Width can be operated from -10 to +10 Step 0.5
        $this->sendMappedValueName(DENON_API_Commands::PSSTW, (string)$Value);
    }

    //Stage Height
    public function StageHeight(float $Value): void
    { //Stage Width can be operated from -10 to +10 Step 0.5
        $this->sendMappedValueName(DENON_API_Commands::PSSTH, (string)$Value);
    }

    //Surround Back Mode
    public function SurroundBackMode(string $Value): void
    { // Surround Back Mode Off / On / Matrix / Cinema / Music
        $this->sendMappedValueName(DENON_API_Commands::PSSB, $Value);
    }

    //Surround Play Mode
    public function SurroundPlayMode(string $Value): void
    { // Surround Play Mode Music / Cinema / Game / Pro Logic
        $this->sendMappedValueName(DENON_API_Commands::PSMODE, $Value);
    }

    //Vertical Stretch
    public function VerticalStretch(bool $Value): void
    { // VerticalStretch true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::VSVST, $Value);
    }

    //Contrast
    public function Contrast(float $Value): void
    { // Contrast can be operated from -6 to 6 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PVCN, $Value);
    }

    //Brightness
    public function Brightness(float $Value): void
    { //Brightness can be operated from 0 to 12 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PVBR, $Value);
    }

    //Chroma Level
    public function ChromaLevel(float $Value): void
    { //Chroma Level can be operated from -6 to 6 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PVCM, $Value);
    }

    //Digital Noise Reduction
    public function DigitalNoiseReduction(string $Value): void
    { // Digital Noise Reduction Off / Low / Middle / High
        $this->sendMappedValueName(DENON_API_Commands::PVDNR, $Value);
    }

    //Enhancer
    public function Enhancer(float $Value): void
    { //Enhancer can be operated from 0 to 12 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PVENH, $Value);
    }

    /** HDMI Monitor.
     *
     * @param string $Value AUTO / 1 / 2
     *
     * @throws \JsonException
     * @throws \JsonException
     */
    public function HDMIMonitor(string $Value): void
    { // HDMI Monitor AUTO / Monitor 1 / Monitor 2
        $this->sendMappedValueName(DENON_API_Commands::VSMONI, $Value);
    }

    //Hue
    public function Hue(float $Value): void
    { //Enhancer can be operated from -6 to 6 Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PVHUE, $Value);
    }

    //Resolution
    public function Resolution(string $Value): void
    { // Resolution 480p/576p / 1080i / 720p / 1080p / 1080p:24Hz / Auto / 4K / 4K(60/50)
        $this->sendMappedValueName(DENON_API_Commands::VSSC, $Value);
    }

    //Resolution HDMI
    public function ResolutionHDMI(string $Value): void
    { //Resolution HDMI 480p/576p / 1080i / 720p / 1080p / 1080p:24Hz / Auto / 4K / 4K(60/50)
        $this->sendMappedValueName(DENON_API_Commands::VSSCH, $Value);
    }

    //Video Processing Mode
    public function VideoProcessingMode(string $Value): void
    { // Video Processing Mode Auto / Game / Movie
        $this->sendMappedValueName(DENON_API_Commands::VSVPM, $Value);
    }

    //GUI Menu
    public function GUIMenu(bool $Value) // GUI Setup Menu true (On) or false (Off)
    : void
    {
        if ($Value === false) {
            $subcommand = DENON_API_Commands::MNMENOFF;
        } else {
            $subcommand = DENON_API_Commands::MNMENON;
        }
        $payload = DENON_API_Commands::MNMEN . $subcommand;
        $this->SendCommand($payload);
    }

    //GUI Source Select Menu
    public function GUISourceSelectMenu(bool $Value): void
    { // GUI Source Select Menu true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::MNSRC, $Value);
    }

    //PS
    public function ParameterSettings(string $subcommand): void
    { // PS
        $this->SendCommand(DENON_API_Commands::PS . $subcommand);
    }

    //Noch ergänzen

    //Preset Network Audio
    public function SelectPresetNetworkAudio(bool $Value): void
    {
        $this->sendMappedValue(DENON_API_Commands::MNSRC, $Value);
    }

    //####################### Cursor Steuerung ######################################

    public function CursorUp(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNCUP);
    }

    public function CursorDown(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNCDN);
    }

    public function CursorLeft(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNCLT);
    }

    public function CursorRight(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNCRT);
    }

    public function Enter(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNENT);
    }

    public function CursorReturn(): void
    {
        $this->SendCommand(DENON_API_Commands::MN . DENON_API_Commands::MNRTN);
    }

    //Levels

    //Bass Level
    public function BassLevel(float $Value): void
    { // can be operated from -6 to +6, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PSBAS, $Value);
    }

    //Treble Level
    public function TrebleLevel(float $Value): void
    { // can be operated from -6 to +6, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PSTRE, $Value);
    }

    //LFE Level
    public function LFELevel(float $Value): void
    { // can be operated from 0 to -10, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::PSLFE, $Value);
    }

    //Sleep
    public function SLEEP(int $Value): void
    { // 0 ist aus bis 120 Step 10
        $this->sendMappedValue(DENON_API_Commands::SLP, $Value);
    }

    //Network Audio Navigation
    public function NACursorUp(): void
    {
        $this->SendCommand(DENON_API_Commands::NSUP);
    }

    public function NACursorDown(): void
    {
        $this->SendCommand(DENON_API_Commands::NSDOWN);
    }

    public function NACursorLeft(): void
    {
        $this->SendCommand(DENON_API_Commands::NSLEFT);
    }

    public function NACursorRight(): void
    {
        $this->SendCommand(DENON_API_Commands::NSRIGHT);
    }

    public function NAEnter(): void
    {
        $this->SendCommand(DENON_API_Commands::NSENTER);
    }

    public function NAPlay(): void
    {
        $this->SendCommand(DENON_API_Commands::NSPLAY);
    }

    public function NAPause(): void
    {
        $this->SendCommand(DENON_API_Commands::NSPAUSE);
    }

    public function NAStop(): void
    {
        $this->SendCommand(DENON_API_Commands::NSSTOP);
    }

    public function NASkipPlus(): void
    {
        $this->SendCommand(DENON_API_Commands::NSSKIPPLUS);
    }

    public function NASkipMinus(): void
    {
        $this->SendCommand(DENON_API_Commands::NSSKIPMINUS);
    }

    public function NARepeatOne(): void
    {
        $this->SendCommand(DENON_API_Commands::NSREPEATONE);
    }

    public function NARepeatAll(): void
    {
        $this->SendCommand(DENON_API_Commands::NSREPEATALL);
    }

    public function NARepeatOff(): void
    {
        $this->SendCommand(DENON_API_Commands::NSREPEATOFF);
    }

    public function NARandomOn(): void
    {
        $this->SendCommand(DENON_API_Commands::NSRANDOMON);
    }

    public function NARandomOff(): void
    {
        $this->SendCommand(DENON_API_Commands::NSRANDOMOFF);
    }

    public function NAPageNext(): void
    {
        $this->SendCommand(DENON_API_Commands::NSPAGENEXT);
    }

    public function NAPagePrevious(): void
    {
        $this->SendCommand(DENON_API_Commands::NSPAGEPREV);
    }

    //####################### Zone 2 functions ######################################

    public function Z2_Volume(string $command): void
    { // "UP" or "DOWN"
        $this->SendCommand(DENON_API_Commands::Z2 . $command);
    }

    public function Zone2VolumeFix(float $Value): void
    { // 18(db) bis -80(db), Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z2VOL, $Value, DENON_API_Commands::Z2);
    }

    //Zone2 Power
    public function Zone2Power(bool $Value): void
    { // Zone2 Power true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::Z2POWER, $Value, DENON_API_Commands::Z2);
    }

    //Zone2 Mute
    public function Zone2Mute(bool $Value): void
    { // Zone2 Mute true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::Z2MU, $Value);
    }

    public function Zone2InputSource(string $subcommand): void
    { // PHONO ; DVD ; HDP ; "TV/CBL" ; SAT ; "NET/USB" ; DVR ; TUNER
        $this->SendCommand(DENON_API_Commands::Z2 . $subcommand);
    }

    //Channel Volume Front Left
    public function Zone2ChannelVolumeFL(float $Value): void
    { // -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z2CVFL, $Value);
    }

    //Channel Volume Front Right
    public function Zone2ChannelVolumeFR(float $Value): void
    { // -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z2CVFR, $Value);
    }

    public function Zone2ChannelSetting(string $Value): void
    { // Zone 2 Channel Setting: Stereo/Mono
        $this->sendMappedValueName(DENON_API_Commands::Z2CS, $Value);
    }

    public function Zone2QuickSelect(string $command): void
    { // Zone 2 Quick select 1-5
        $this->SendCommand(DENON_API_Commands::Z2QUICK . $command);
    }

    //######################### Zone 3 Functions ####################################

    public function Z3_Volume(string $command): void
    { // "UP" or "DOWN"
        $this->SendCommand(DENON_API_Commands::Z3 . $command);
    }

    public function Zone3VolumeFix(float $Value): void
    { // 18(db) bis -80(db), Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z3VOL, $Value, DENON_API_Commands::Z3);
    }

    //Zone3 Power
    public function Zone3Power(bool $Value): void
    { // Zone3 Power true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::Z3POWER, $Value, DENON_API_Commands::Z3);
    }

    //Zone3 Mute
    public function Zone3Mute(bool $Value): void
    { // Zone3 Mute true (On) or false (Off)
        $this->sendMappedValue(DENON_API_Commands::Z3MU, $Value);
    }

    public function Zone3InputSource(string $subcommand): void
    { // PHONO ; DVD ; HDP ; "TV/CBL" ; SAT ; "NET/USB" ; DVR ; TUNER
        $this->SendCommand(DENON_API_Commands::Z3 . $subcommand);
    }

    //Channel Volume Front Left
    public function Zone3ChannelVolumeFL(float $Value): void
    { // -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z3CVFL, $Value);
    }

    //Channel Volume Front Right
    public function Zone3ChannelVolumeFR(float $Value): void
    { // -12 to 12, Step 0.5
        $this->sendMappedValue(DENON_API_Commands::Z3CVFR, $Value);
    }

    public function Zone3ChannelSetting(string $Value): void
    { // Zone 3 Channel Setting: Stereo/Mono
        $this->sendMappedValueName(DENON_API_Commands::Z3CS, $Value);
    }

    public function Zone3QuickSelect(string $command): void
    { // Zone 3 Quick select 1-5
        $this->SendCommand(DENON_API_Commands::Z3QUICK . $command);
    }


    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form.
     *
     * @return string
     * @throws \JsonException
     * @throws \JsonException
     */
    public function GetConfigurationForm(): string
    {
        // return current form
        return json_encode([
                               'elements' => $this->FormElements(),
                               'actions'  => $this->FormActions(),
                               'status'   => $this->FormStatus()
                           ], JSON_THROW_ON_ERROR);
    }

    /**
     * return form configurations on configuration step.
     *
     * @return array
     * @throws \JsonException
     * @throws \JsonException
     */
    private function FormElements(): array
    {
        $manufacturername = $this->GetManufacturerName();
        $AVRType          = $this->GetAVRType($manufacturername);
        $zone             = $this->ReadPropertyInteger('Zone');

        $this->Logger_Dbg(__FUNCTION__, 'Manufacturername: ' . $manufacturername . ', AVRType: ' . $AVRType . ', Zone: ' . $zone);

        $form = [
            [
                'type'    => 'Label',
                'caption' => 'AV Receiver Control Telnet'
            ],
            [
                'type'    => 'Label',
                'caption' => 'Telnet control is working with a only a single client connection (IP-Symcon), more remote commands available compared to HTTP.'
            ],
            [
                'type'    => 'Label',
                'caption' => 'Please select a manufacturer and push the "Apply Changes" button'
            ],
            [
                'type'    => 'Select',
                'name'    => 'manufacturer',
                'caption' => 'manufacturer',
                'options' => [
                    [
                        'label' => 'Please Select',
                        'value' => 0
                    ],
                    [
                        'label' => 'Denon',
                        'value' => 1
                    ],
                    [
                        'label' => 'Marantz',
                        'value' => 2
                    ]
                ]

            ]
        ];

        if ($manufacturername === 'none') {
            $this->SendDebug('Form', 'no manufacturer selected', 0);
        } // selection model
        elseif ($AVRType === false) {
            $form = array_merge($form, $this->FormSelectionAVR($manufacturername));
        } elseif ($zone === 6) {
            $form = array_merge($form, $this->FormSelectionAVR($manufacturername), $this->FormSelectionZone());
        } elseif ($zone === 0) {
            $form = array_merge(
                $form,
                $this->FormSelectionAVR($manufacturername),
                $this->FormSelectionZone(),
                $this->FormMainzone($zone, $AVRType)
            );
        } else {
            $form = array_merge(
                $form,
                $this->FormSelectionAVR($manufacturername),
                $this->FormSelectionZone(),
                $this->FormZone($zone, $AVRType)
            );
        }

        $form = array_merge($form, $this->FormExpertParameters());

        $this->Logger_Dbg(__FUNCTION__, 'form_telnet_gen.json: ' . json_encode($form, JSON_THROW_ON_ERROR));
        return $form;
    }

    private function FormMainzone($Zone, $AVRType): array
    {
        $AVRCaps = AVRs::getCapabilities($AVRType);
        $this->Logger_Dbg(__FUNCTION__, 'AVR Caps (' . $AVRType . '): ' . json_encode($AVRCaps, JSON_THROW_ON_ERROR));

        $profiles = new DENONIPSProfiles($AVRType, null, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        })->GetAllProfilesSortedByPos();

        $form = [];

        $CommandAreas = [
            //Label => Caps CommandArea
            'Info Display'     => 'InfoFunctions',      //Info
            'AVR Infos'        => 'AVRInfos',      //AVR Infos
            'Power Settings'   => 'PowerFunctions',   //Power Funktionen (PW, ZM, SLP, ...)
            'Input Settings'   => 'InputSettings',    //Input Settings
            'Surround Mode'    => 'SurroundMode',      //Surround Mode
            'Channel Volumes'  => 'CV_Commands',     //Control Volume (CV)
            'Sound Processing' => 'PS_Commands',    //Process Sound (PS)
            'Video Settings'   => 'VS_Commands',      //Video Settings (VS)
            'Video Processing' => 'PV_Commands',    //Processing Video (PV)
            'Tuner Control'    => 'Tuner_Control',    //Processing Video (PV)
            'System Control'   => 'SystemControl_Commands', //System Control (MN, ...)
        ];

        foreach ($CommandAreas as $label => $commandArea) {
            if (count($AVRCaps[$commandArea]) === 0) {
                continue;
            }
            $form[] = [
                'type'    => 'ExpansionPanel',
                'caption' => $label,
                'items'   => $this->FormAVRProfile($Zone, $AVRType, $commandArea, $profiles)
            ];
        }

        return array_merge($form, $this->FormMoreInputs());
    }

    private function FormZone($Zone, $AVRType): array
    {
        $profiles = new DENONIPSProfiles($AVRType)->GetAllProfilesSortedByPos();

        $Zone++;

        $form = [];

        $CommandAreas = [
            //Label => Caps CommandArea
            'Zone Settings' => 'Zone_Commands',      //Zone commands
        ];

        foreach ($CommandAreas as $label => $commandArea) {
            $form[] = [
                'type'    => 'ExpansionPanel',
                'caption' => $label,
                'items'   => $this->FormAVRProfile($Zone, $AVRType, $commandArea, $profiles)
            ];
        }

        return array_merge($form, $this->FormMoreInputs());
    }

    private function FormAVRProfile($Zone, $AVRType, $commandArea, $profiles): array
    {
        $AVRCaps = AVRs::getCapabilities($AVRType);
        $this->Logger_Dbg(__FUNCTION__, 'AVR Caps (' . $AVRType . '): ' . json_encode($AVRCaps, JSON_THROW_ON_ERROR));

        $form = [];
        if ($Zone === 0) {
            foreach ($profiles as $profile) {
                $Caps = $AVRCaps[$commandArea];
                $item = $this->getTypeItem('CheckBox', $profile['Ident'], $profile['PropertyName'], $profile['Name'], $Caps);
                if ($item) {
                    $form[] = $item;
                }
            }
        } else {
            foreach ($profiles as $profile) {
                // Zonen-Commands: nur die der aktuellen Zone, zonenneutrale immer
                if ($this->isZoneSpecificIdent($profile['Ident'])
                    && !$this->identMatchesZone($profile['Ident'], $Zone)) {
                    continue;
                }
                $item = $this->getTypeItem('CheckBox', $profile['Ident'], $profile['PropertyName'], $profile['Name'], $AVRCaps['Zone_Commands']);
                if ($item) {
                    $form[] = $item;
                }
            }
        }
        return $form;
    }

    /**
     * return form actions by token.
     *
     * @return array
     */
    private function FormActions(): array
    {
        $manufacturername = $this->GetManufacturerName();
        if ($manufacturername === 'none') {
            $form = [];
        } else {
            $form = [
                [
                    'type'    => 'Button',
                    'caption' => 'Initialize status',
                    'onClick' => 'DAVRT_GetStates($id);'
                ],
                [
                    'type' => 'TestCenter'
                ]
            ];
        }

        return $form;
    }
}
