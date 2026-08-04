<?php

declare(strict_types=1);

/**
 * Minimaler IP-Symcon-Kernel-Stub fuer die Golden-File-Regressionstests.
 *
 * Die Konstantenwerte entsprechen dem offiziellen SDK (PhpStorm-Stub symcon.php).
 * Die Basisklasse IPSModuleStrict zeichnet alle relevanten Kernel-Aufrufe in
 * $recorded auf und beantwortet ReadProperty* aus den per RegisterProperty*
 * hinterlegten Defaults - damit entspricht eine frisch erzeugte Instanz exakt
 * dem Auslieferungszustand. Tests koennen einzelne Properties per
 * setPropertyForTest() ueberschreiben.
 */

const IPS_KERNELMESSAGE = 10100;
const KR_READY          = 10103;
const KL_MESSAGE        = 10201;
const KL_NOTIFY         = 10203;
const KL_WARNING        = 10204;
const KL_ERROR          = 10205;
const KL_DEBUG          = 10206;
const IM_CHANGESTATUS   = 10505;
const IS_ACTIVE         = 102;
const IS_INACTIVE       = 104;

const VARIABLE_PRESENTATION_VALUE_PRESENTATION = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
const VARIABLE_PRESENTATION_SLIDER             = '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}';
const VARIABLE_PRESENTATION_WEB_CONTENT        = '{9DE1D610-5106-97FB-714D-1AADEDF8377A}';
const VARIABLE_PRESENTATION_SWITCH             = '{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}';
const VARIABLE_PRESENTATION_ENUMERATION        = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';

function IPS_GetKernelRunlevel(): int
{
    return KR_READY;
}

function IPS_LogMessage(string $Sender, string $Message): bool
{
    return true;
}

function IPS_Sleep(int $Milliseconds): bool
{
    return true;
}

function IPS_GetName(int $ID): string
{
    return 'Objekt#' . $ID;
}

function IPS_InstanceExists(int $InstanceID): bool
{
    return false;
}

function IPS_GetInstance(int $InstanceID): array
{
    return ['ConnectionID' => 0];
}

function IPS_GetProperty(int $InstanceID, string $Name): string
{
    return '';
}

function IPS_GetChildrenIDs(int $ID): array
{
    return [];
}

function GetValueFloat(int $VariableID): float
{
    return 0.0;
}

// Modul-Prefix-Funktionen, die nur im laufenden Kernel existieren
function DAVRST_SaveInputVarmapping($InstanceID, string $MappingInputs): void
{
}

function DAVRST_GetInputVarMapping($InstanceID): array
{
    return [];
}

class IPSModuleStrict
{
    protected int $InstanceID;

    /** @var array<string, mixed> per RegisterProperty* hinterlegte Werte */
    private array $properties = [];

    /** @var list<array> aufgezeichnete Kernel-Aufrufe */
    public array $recorded = [];

    public function __construct(int $InstanceID = 0)
    {
        $this->InstanceID = $InstanceID;
    }

    // ---- Test-Hilfen -------------------------------------------------------

    public function setPropertyForTest(string $Name, mixed $Value): void
    {
        $this->properties[$Name] = $Value;
    }

    public function resetRecorded(): void
    {
        $this->recorded = [];
    }

    private function record(string $event, mixed ...$args): void
    {
        $this->recorded[] = [$event, ...$args];
    }

    // ---- Properties --------------------------------------------------------

    protected function RegisterPropertyBoolean(string $Name, bool $DefaultValue): bool
    {
        $this->properties[$Name] = $DefaultValue;
        return true;
    }

    protected function RegisterPropertyInteger(string $Name, int $DefaultValue): bool
    {
        $this->properties[$Name] = $DefaultValue;
        return true;
    }

    protected function RegisterPropertyFloat(string $Name, float $DefaultValue): bool
    {
        $this->properties[$Name] = $DefaultValue;
        return true;
    }

    protected function RegisterPropertyString(string $Name, string $DefaultValue): bool
    {
        $this->properties[$Name] = $DefaultValue;
        return true;
    }

    protected function ReadPropertyBoolean(string $Name): bool
    {
        if (!array_key_exists($Name, $this->properties)) {
            $this->record('readUnknownProperty', $Name);
            return false;
        }
        return (bool)$this->properties[$Name];
    }

    protected function ReadPropertyInteger(string $Name): int
    {
        if (!array_key_exists($Name, $this->properties)) {
            $this->record('readUnknownProperty', $Name);
            return 0;
        }
        return (int)$this->properties[$Name];
    }

    protected function ReadPropertyFloat(string $Name): float
    {
        if (!array_key_exists($Name, $this->properties)) {
            $this->record('readUnknownProperty', $Name);
            return 0.0;
        }
        return (float)$this->properties[$Name];
    }

    protected function ReadPropertyString(string $Name): string
    {
        if (!array_key_exists($Name, $this->properties)) {
            $this->record('readUnknownProperty', $Name);
            return '';
        }
        return (string)$this->properties[$Name];
    }

    // ---- Variablen ---------------------------------------------------------

    protected function RegisterVariableBoolean(string $Ident, string $Name, array|string $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->record('RegisterVariableBoolean', $Ident, $Name, $ProfileOrPresentation, $Position);
        return true;
    }

    protected function RegisterVariableInteger(string $Ident, string $Name, array|string $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->record('RegisterVariableInteger', $Ident, $Name, $ProfileOrPresentation, $Position);
        return true;
    }

    protected function RegisterVariableFloat(string $Ident, string $Name, array|string $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->record('RegisterVariableFloat', $Ident, $Name, $ProfileOrPresentation, $Position);
        return true;
    }

    protected function RegisterVariableString(string $Ident, string $Name, array|string $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->record('RegisterVariableString', $Ident, $Name, $ProfileOrPresentation, $Position);
        return true;
    }

    protected function UnregisterVariable(string $Ident): bool
    {
        $this->record('UnregisterVariable', $Ident);
        return true;
    }

    protected function GetIDForIdent(string $Ident): int|false
    {
        return false;
    }

    protected function EnableAction(string $Ident): bool
    {
        $this->record('EnableAction', $Ident);
        return true;
    }

    protected function DisableAction(string $Ident): bool
    {
        $this->record('DisableAction', $Ident);
        return true;
    }

    protected function GetValue(string $Ident): mixed
    {
        return '';
    }

    protected function SetValue(string $Ident, mixed $Value): bool
    {
        $this->record('SetValue', $Ident, $Value);
        return true;
    }

    // ---- Kommunikation / Sonstiges ----------------------------------------

    protected function SendDataToParent(string $Data): string|false
    {
        $this->record('SendDataToParent', $Data);
        return '';
    }

    protected function SendDataToChildren(string $Data): array|false
    {
        $this->record('SendDataToChildren', $Data);
        return [];
    }

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        return true;
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->record('LogMessage', $Message, $Type);
        return true;
    }

    protected function SetStatus(int $Status): bool
    {
        $this->record('SetStatus', $Status);
        return true;
    }

    protected function GetStatus(): int|false
    {
        return IS_ACTIVE;
    }

    protected function SetSummary(string $Summary): bool
    {
        return true;
    }

    protected function HasActiveParent(): bool
    {
        return false;
    }

    protected function RegisterMessage(int $SenderID, int $Message): bool
    {
        return true;
    }

    protected function UnregisterMessage(int $SenderID, int $Message): bool
    {
        return true;
    }

    protected function RegisterTimer(string $Name, int $Milliseconds, string $ScriptText): bool
    {
        return true;
    }

    protected function SetTimerInterval(string $Ident, int $Milliseconds): bool
    {
        return true;
    }

    protected function ConnectParent(string $ModuleID): bool
    {
        return true;
    }

    protected function SetBuffer(string $Name, string $Data): bool
    {
        return true;
    }

    protected function GetBuffer(string $Name): string|false
    {
        return '';
    }

    public function Translate(string $Text): string
    {
        return $Text;
    }

    // ---- Framework-Hooks ---------------------------------------------------

    public function Create(): void
    {
    }

    public function Destroy(): void
    {
    }

    public function ApplyChanges(): void
    {
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
    }

    public function ReceiveData(string $JSONString): string
    {
        return '';
    }

    public function ForwardData(string $JSONString): string
    {
        return '';
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
    }

    public function GetConfigurationForm(): string
    {
        return '';
    }
}
