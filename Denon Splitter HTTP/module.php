<?php

declare(strict_types=1);

require_once __DIR__ . '/../DenonClass.php';  // diverse Klassen

class DenonSplitterHTTP extends IPSModuleStrict
{
    protected bool $debug = false;

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);

        if (file_exists(IPS_GetLogDir() . 'denondebug.txt')) {
            $this->debug = true;
        }
    }


    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

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
     * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurde.
     * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wie folgt zur Verfügung gestellt.
     */

    // Input
    public function SaveInputVarmapping(string $MappingInputs): void
    {
        DAVRIO_SaveInputVarmapping($this->GetParent(), $MappingInputs);
    }

    public function GetInputVarMapping()
    {
        return DAVRIO_GetInputVarMapping($this->GetParent());
    }

    //################# DUMMYS / WOARKAROUNDS - protected

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);

        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    // Daten an Child weitergeben
    public function ReceiveData(string $JSONString): string
    {

        // Empfangene Daten vom Denon HTTP I/O
        $data   = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $dataio = json_encode($data->Buffer, JSON_THROW_ON_ERROR);
        $this->SendDebug('Buffer IN', $dataio, 0);

        // Hier werden die Daten verarbeitet

        // Weiterleitung zu allen Gerät-/Device-Instanzen

        $this->SendDataToChildren(
            json_encode(['DataID' => '{D9209251-0036-48C2-AF96-9F5EDE761A52}', 'Buffer' => $data->Buffer], JSON_THROW_ON_ERROR)
        ); //Denon HTTP Splitter Interface GUI
        return '';

    }

    //################# DATAPOINT RECEIVE FROM CHILD

    public function ForwardData(string $JSONString): string
    {

        // Empfangene Daten von der Device-Instanz
        $data     = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $datasend = $data->Buffer;
        $this->SendDebug('Command Out', print_r($datasend, true), 0);

        // Weiterleiten zur I/O Instanz
        return $this->SendDataToParent(
            json_encode(['DataID' => '{B403182C-3506-466C-B8D5-842D9237BF02}', 'Buffer' => $data->Buffer], JSON_THROW_ON_ERROR)
        ); // Denon I/O HTTP TX GUI

    }
}
