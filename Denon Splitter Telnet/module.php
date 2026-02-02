<?php

declare(strict_types=1);

require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

class DenonSplitterTelnet extends IPSModuleStrict
{

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.

        // ClientSocket benötigt
        //$this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}'); //Client socket

        //we will set the instance status when the parent status changes
        if($this->GetParent() > 0)
        {
            $this->RegisterMessage($this->GetParent(), IM_CHANGESTATUS);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        $this->Logger_Dbg(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data:' . json_encode($Data, JSON_THROW_ON_ERROR));

        if ($Message === IM_CHANGESTATUS) {
            $this->ApplyChanges();
        } else {
            $this->Logger_Err('Unexpected Message: ' . $Message);
            trigger_error('Unexpected Message: ' . $Message);
        }
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableString('InputMapping', 'Input Mapping', '', 1);
        $this->RegisterVariableString('AVRType', 'AVRType', '', 2);

        if ($this->HasActiveParent()) {
            //Instanz aktiv
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INACTIVE);
        }
    }

    /**
     * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
     * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wie folgt zur Verfügung gestellt:
     */

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
                               'status' => [],
                               'elements' => []
                           ],
                           JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $MappingInputs Input MappingInputs als JSON
     *
     * @return void
     * @throws \JsonException
     */
    public function SaveInputVarmapping(string $MappingInputs): void
    {
        $this->SetValue('InputMapping', $MappingInputs);
        $this->SetValue('AVRType', json_decode($MappingInputs, true, 512, JSON_THROW_ON_ERROR)['AVRType']);
    }

    public function GetInputVarMapping(): array
    {
        $InputsMapping = $this->GetValue('InputMapping');
        $this->Logger_Dbg(__FUNCTION__, 'InputsMapping: ' . $InputsMapping);

        $InputsMapping = json_decode($InputsMapping, false, 512, JSON_THROW_ON_ERROR);

        if ($InputsMapping === null) {
            $this->Logger_Err(__FUNCTION__ . ': InputMapping cannot be decoded');
            trigger_error(__FUNCTION__ . ': InputMapping cannot be decoded');

            return [];
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

    public function GetStatusHTTP(): false|array
    {
        $data          = [];
        $InputsMapping = json_decode($this->GetValue('InputMapping'), false, 512, JSON_THROW_ON_ERROR);

        if (!isset($InputsMapping->AVRType)) {
            $this->Logger_Err(__FUNCTION__ . ': AVRType not set!');

            return false;
        }
        $AVRType = $InputsMapping->AVRType;

        if (AVRs::getCapabilities($AVRType)['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) { //Nur Ausführen, wenn AVR HTTP unterstützt
            // empfangene Daten vom Denon AVR Receiver

            //Semaphore setzen
            if ($this->lock()) {
                // Daten senden
                try {
                    //Daten abholen
                    $DenonStatusHTTP = new DENON_StatusHTML();

                    $ipdenon         = IPS_GetProperty($this->GetParent(), 'host');
                    $AVRType         = $this->GetValue('AVRType');
                    $InputMapping    = $this->GetInputVarMapping();
                    if ($InputMapping === []) {
                        //InputMapping konnte nicht geladen werden
                        return false;
                    }
                    $data = $DenonStatusHTTP->getStates($ipdenon, $InputMapping, $AVRType);
                    $this->SendDebug('HTTP States:', json_encode($data, JSON_THROW_ON_ERROR), 0);

                    // Weiterleitung zu allen Gerät-/Device-Instanzen
                    $this->SendDataToChildren(
                        json_encode(['DataID' => '{7DC37CD4-44A1-4BA6-AC77-58369F5025BD}', 'Buffer' => $data], JSON_THROW_ON_ERROR)
                    ); //Denon Telnet Splitter Interface GUI
                } catch (Exception) {
                    // Senden fehlgeschlagen
                    $this->unlock();

                    $this->Logger_Err('HTTPGetState failed');
                    trigger_error('HTTPGetState failed');
                }
                $this->unlock();
            } else {
                $this->Logger_Err('Can not set lock \'HTTPGetState\'');
                trigger_error('Can not set lock \'HTTPGetState\'');
            }

            return $data;
        }

        return false;
    }

    protected function SetStatus($Status): bool
    {
        $this->SendDebug(__FUNCTION__, 'Status: ' . $Status, 0);

        if ($Status !== IPS_GetInstance($this->InstanceID)['InstanceStatus']) {
            parent::SetStatus($Status);
        }
        return true;
    }

    // Display NSE, NSA, NSH noch ergänzen

    //Tuner ergänzen

    //################# Datapoints

    // Daten an Child weitergeben
    public function ReceiveData(string $JSONString): string
    {

        // Empfangene Daten vom I/O
        $payload = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $buffer = hex2bin($payload->Buffer);

        $storedBuffer = $this->GetBuffer(__FUNCTION__);
        if ($storedBuffer !== ''){
            $buffer  = $storedBuffer . $buffer;
        }

        $this->SendDebug('Data from I/O:', $buffer, 0);

        // the received data must be terminated with \r
        if (!str_ends_with($buffer, "\r")) {
            $this->Logger_Dbg(__FUNCTION__, 'received data are buffered, because they are not terminated: ' . $buffer);
            $this->SetBuffer(__FUNCTION__, $buffer);

            return '';
        }

        $this->SetBuffer(__FUNCTION__, '');

        //Daten aufteilen und Abschlusszeichen wegschmeißen
        $data = explode("\r", $buffer);
        array_pop($data);

        $this->SendDebug('Received Data:', json_encode($data, JSON_THROW_ON_ERROR), 0);
        $this->Logger_Dbg(__FUNCTION__, 'Received data: ' . json_encode($data, JSON_THROW_ON_ERROR));

        $APIData = new DenonAVRCP_API_Data($this->GetValue('AVRType'), $data, function (string $message, string $data) {
            $this->Logger_Dbg($message, $data);
        });

        $InputMapping = $this->GetInputVarMapping();
        $SetCommand   = $APIData->GetCommandResponse($InputMapping);
        $this->SendDebug('Buffer IN:', json_encode($SetCommand, JSON_THROW_ON_ERROR), 0);

        // Weiterleitung zu allen Telnet Gerät-/Device-Instanzen, wenn SetCommand gefüllt ist

        if (($SetCommand['SurroundDisplay'] !== '') || (count($SetCommand['Data']) > 0) || (count($SetCommand['Display']) > 0)) {
            $this->SendDataToChildren(
                json_encode(['DataID' => '{7DC37CD4-44A1-4BA6-AC77-58369F5025BD}', 'Buffer' => $SetCommand], JSON_THROW_ON_ERROR)
            ); //Denon Telnet Splitter Interface GUI
        }

        return '';
    }

    //################# DATAPOINT RECEIVE FROM CHILD

    public function ForwardData($JSONString): string
    {

        // Empfangene Daten von der Device-Instanz
        $data = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $this->SendDebug('Command Out:', print_r($data->Buffer, true), 0);

        $this->Logger_Dbg(__FUNCTION__, 'send data: ' . $data->Buffer);

        try {
            // Weiterleiten zur I/O Instanz
            $resultat =
                $this->SendDataToParent(
                    json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => bin2hex($data->Buffer)], JSON_THROW_ON_ERROR)
                ); //TX GUID

        } catch (Exception $ex) {
            echo $ex->getMessage();
            echo ' in ' . $ex->getFile() . ' line: ' . $ex->getLine() . '.';

            return '';
        }

        // Weiterverarbeiten und durchreichen
        return $resultat;
    }

    //################# SEMAPHOREN Helper  - private

    private function lock(): bool
    {
        return IPS_SemaphoreEnter('DENONAVRT_HTTPGetState_' . $this->InstanceID, 2000);
    }

    private function unlock(): void
    {
        IPS_SemaphoreLeave('DENONAVRT_HTTPGetState_' . $this->InstanceID);
    }

    private function Logger_Err(string $message): void
    {
        $this->SendDebug('LOG_ERR', $message, 0);

        $this->LogMessage($message, KL_ERROR);

    }

    private function Logger_Dbg(string $message, string $data): void
    {
        $this->SendDebug($message, $data, 0);
    }
}
