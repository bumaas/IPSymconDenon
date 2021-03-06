<?php

declare(strict_types=1);

require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

/** @noinspection AutoloadingIssuesInspection */
class DenonSplitterTelnet extends IPSModule
{
    private const PROPERTY_PORT                               = 'Port';
    private const PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE = 'WriteDebugInformationToLogfile';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.

        $this->RegisterPropertyInteger(self::PROPERTY_PORT, 23);
        $this->RegisterPropertyBoolean(self::PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE, false);

        // ClientSocket benötigt
        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}'); //Clientsocket

        $this->RegisterPropertyString('uuid', '');
        $this->RegisterPropertyString('Host', '');

        //we will set the instance status when the parent status changes
        if($this->GetParent() > 0)
        {
            $this->RegisterMessage($this->GetParent(), IM_CHANGESTATUS);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->Logger_Dbg(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data:' . json_encode($Data));

        /** @noinspection DegradedSwitchInspection */
        switch ($Message) {
            case IM_CHANGESTATUS:
                $this->ApplyChanges();
                break;
            default:
                $this->Logger_Err('Unexpected Message: ' . $Message);
                trigger_error('Unexpected Message: ' . $Message);
        }
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableString('InputMapping', 'Input Mapping', '', 1);
        IPS_SetHidden($this->GetIDForIdent('InputMapping'), true);

        $this->RegisterVariableString('AVRType', 'AVRType', '', 2);
        IPS_SetHidden($this->GetIDForIdent('AVRType'), true);

        $ParentOpen = $this->HasActiveParent();
        if (!$ParentOpen) {
            $this->SetStatus(IS_INACTIVE);
        }
        if ($this->HasActiveParent()) {
            //Instanz aktiv
            $this->SetStatus(IS_ACTIVE);
        }
    }

    /**
     * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
     * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:.
     */

    /**
     * build configuration form.
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        // return current form
        return json_encode(
            [
                'elements' => [
                    [
                        'type'    => 'NumberSpinner',
                        'name'    => self::PROPERTY_PORT,
                        'caption' => 'Port',
                        'digits'  => 0],
                    [
                        'type'    => 'ExpansionPanel',
                        'caption' => 'Expert Parameters',
                        'items'   => [
                            [
                                'type'    => 'CheckBox',
                                'name'    => self::PROPERTY_WRITE_DEBUG_INFORMATION_TO_LOGFILE,
                                'caption' => 'Debug information are written additionally to standard logfile']]]]]
        );
    }

    /**
     * @param string $MappingInputs Input MappingInputs als JSON
     *
     * @return bool
     */
    public function SaveInputVarmapping(string $MappingInputs): bool
    {
        if ($MappingInputs === 'null') {
            $this->Logger_Err('MappingInputs is NULL');
            trigger_error('MappingInputs is NULL');

            return false;
        }

        $idInputMapping = $this->GetIDForIdent('InputMapping');
        if ($idInputMapping) {
            $InputsMapping = GetValue($idInputMapping);
            if (($InputsMapping !== '') && ($InputsMapping !== 'null')) { //Auslesen wenn Variable nicht leer
                $Writeprotected = json_decode($InputsMapping, false)->Writeprotected;
                if (!$Writeprotected) { // Auf Schreibschutz prüfen
                    $this->SetValue('InputMapping', $MappingInputs);
                    $this->SetValue('AVRType', json_decode($MappingInputs, false)->AVRType);
                }
            } else { // Schreiben wenn Variable noch nicht gesetzt
                $this->SetValue('InputMapping', $MappingInputs);
                $this->SetValue('AVRType', json_decode($MappingInputs, false)->AVRType);
            }

            return true;
        }

        $this->Logger_Err('InputMapping Variable not found!');
        trigger_error('InputMapping Variable not found!');

        return false;
    }

    public function GetInputVarMapping()
    {
        $InputsMapping = $this->GetValue('InputMapping');
        $this->Logger_Dbg(__FUNCTION__, 'InputsMapping: ' . $InputsMapping);

        $InputsMapping = json_decode($InputsMapping, false);

        if ($InputsMapping === null) {
            $this->Logger_Err(__FUNCTION__ . ': InputMapping cannot be decoded');
            trigger_error(__FUNCTION__ . ': InputMapping cannot be decoded');

            return false;
        }

        //Varmapping generieren
        $Inputs     = $InputsMapping->Inputs;
        $Varmapping = [];
        foreach ($Inputs as $Key => $Input) {
            $Command = $Input->Source;
            if (array_key_exists($Command, DENON_API_Commands::$SIMapping)) {
                $Command = DENON_API_Commands::$SIMapping[$Command];
            }
            $Varmapping[$Command] = $Key;
        }

        return $Varmapping;
    }

    //################# DUMMYS / WOARKAROUNDS - protected

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);

        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : 0;
    }

    public function GetStatusHTTP()
    {
        $data          = '';
        $InputsMapping = json_decode($this->GetValue('InputMapping'), false);

        if (!isset($InputsMapping->AVRType)) {
            IPS_LogMessage(__FUNCTION__, 'AVRType not set!');

            return false;
        }
        $AVRType = $InputsMapping->AVRType;

        if (AVRs::getCapabilities($AVRType)['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) { //Nur Ausführen wenn AVR HTTP unterstützt
            // Empfangene Daten vom Denon AVR Receiver

            //Semaphore setzen
            if ($this->lock('HTTPGetState')) {
                // Daten senden
                try {
                    //Daten abholen
                    $DenonStatusHTTP = new DENON_StatusHTML();
                    $ipdenon         = $this->ReadPropertyString('Host');
                    $AVRType         = $this->GetValue('AVRType');
                    $InputMapping    = $this->GetInputVarMapping();
                    if ($InputMapping === false) {
                        //InputMapping konnte nicht geleden werden
                        return false;
                    }
                    $data = $DenonStatusHTTP->getStates($ipdenon, $InputMapping, $AVRType);
                    $this->SendDebug('HTTP States:', json_encode($data), 0);

                    // Weiterleitung zu allen Gerät-/Device-Instanzen
                    $this->SendDataToChildren(
                        json_encode(['DataID' => '{7DC37CD4-44A1-4BA6-AC77-58369F5025BD}', 'Buffer' => $data])
                    ); //Denon Telnet Splitter Interface GUI
                } catch (Exception $exc) {
                    // Senden fehlgeschlagen
                    $this->unlock('HTTPGetState');

                    $this->Logger_Err('HTTPGetState failed');
                    trigger_error('HTTPGetState failed');
                }
                $this->unlock('HTTPGetState');
            } else {
                $this->Logger_Err('Can not set lock \'HTTPGetState\'');
                trigger_error('Can not set lock \'HTTPGetState\'');
            }

            return $data;
        }

        return false;
    }

    protected function SetStatus($Status)
    {
        $this->senddebug(__FUNCTION__, 'Status: ' . $Status, 0);

        if ($Status !== IPS_GetInstance($this->InstanceID)['InstanceStatus']) {
            parent::SetStatus($Status);
        }
    }

    // Display NSE, NSA, NSH noch ergänzen

    //Tuner ergänzen

    //################# Datapoints

    // Data an Child weitergeben
    public function ReceiveData($JSONString): bool
    {

        // Empfangene Daten vom I/O
        $payload = json_decode($JSONString, false);
        $dataio  = json_decode($this->GetBuffer(__FUNCTION__), false) . $payload->Buffer;
        $this->SetBuffer(__FUNCTION__, '');
        $this->SendDebug('Data from I/O:', json_encode($dataio), 0);

        // the received data must be terminated with \r
        if (substr($dataio, strlen($dataio) - 1) !== "\r") {
            $this->Logger_Dbg(__FUNCTION__, 'received data are buffered, because they are not terminated: ' . json_encode($dataio));
            $this->SetBuffer(__FUNCTION__, json_encode($dataio));

            return false;
        }

        //Daten aufteilen und Abschlusszeichen wegschmeißen
        $data = explode("\r", $dataio);
        array_pop($data);

        $this->SendDebug('Received Data:', json_encode($data), 0);
        $this->Logger_Dbg(__FUNCTION__, 'received data: ' . json_encode($data));

        $APIData = new DenonAVRCP_API_Data($this->GetValue('AVRType'), $data);

        $InputMapping = $this->GetInputVarMapping();
        $SetCommand   = $APIData->GetCommandResponse($InputMapping);
        $this->SendDebug('Buffer IN:', json_encode($SetCommand), 0);

        // Weiterleitung zu allen Telnet Gerät-/Device-Instanzen wenn SetCommand gefüllt ist

        if (($SetCommand['SurroundDisplay'] !== '') || (count($SetCommand['Data']) > 0) || (count($SetCommand['Display']) > 0)) {
            $this->SendDataToChildren(
                json_encode(['DataID' => '{7DC37CD4-44A1-4BA6-AC77-58369F5025BD}', 'Buffer' => $SetCommand])
            ); //Denon Telnet Splitter Interface GUI
        }

        return true;
    }

    //################# DATAPOINT RECEIVE FROM CHILD

    public function ForwardData($JSONString)
    {

        // Empfangene Daten von der Device Instanz
        $data = json_decode($JSONString, false);
        $this->SendDebug('Command Out:', print_r($data->Buffer, true), 0);

        $this->Logger_Dbg(__FUNCTION__, 'send data: ' . $data->Buffer);
        // Hier würde man den Buffer im Normalfall verarbeiten
        // z.B. CRC prüfen, in Einzelteile zerlegen

        try {
            // Weiterleiten zur I/O Instanz
            $resultat =
                $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => $data->Buffer])); //TX GUID

        } catch (Exception $ex) {
            echo $ex->getMessage();
            echo ' in ' . $ex->getFile() . ' line: ' . $ex->getLine() . '.';

            return false;
        }

        // Weiterverarbeiten und durchreichen
        return $resultat;
    }

    //################# SEMAPHOREN Helper  - private

    private function lock($ident): bool
    {
        return IPS_SemaphoreEnter('DENONAVRT_' . $this->InstanceID . $ident, 2000);
    }

    private function unlock($ident): bool
    {
        return IPS_SemaphoreLeave('DENONAVRT_' . $this->InstanceID . $ident);
    }

    private function Logger_Err(string $message): void
    {
        $this->SendDebug('LOG_ERR', $message, 0);
        /*
        if (function_exists('IPSLogger_Err') && $this->ReadPropertyBoolean('WriteLogInformationToIPSLogger')) {
            IPSLogger_Err(__CLASS__, $message);
        }
        */
        $this->LogMessage($message, KL_ERROR);

    }

    private function Logger_Dbg(string $message, string $data): void
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
