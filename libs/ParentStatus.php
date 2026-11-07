<?php

declare(strict_types=1);

/**
 * Status einer Instanz an ihren Parent koppeln (Gerät und Splitter).
 *
 * Der Konfigurator legt Gerät, Splitter und IO an und verbindet sie erst
 * danach. Beim Anlegen gibt es also noch keinen Parent, und der Kernel ruft
 * beim Verbinden kein ApplyChanges auf. Deshalb hört die Instanz auf ihr
 * eigenes FM_CONNECT/FM_DISCONNECT und registriert sich auf IM_CHANGESTATUS
 * immer beim aktuellen Parent; beim Umhängen gibt sie den alten frei.
 *
 * Bis build 107 fehlte das: Nach dem Anlegen über die Discovery blieb der
 * Splitter „nicht aktiv“ und das Gerät auf „IP-Adresse ist ungültig“
 * (Forum t/40873 #766, tests/connect_check.php).
 *
 * Am nuc gemessen (07.11.2026): Beim Zustellen von FM_DISCONNECT ist
 * ConnectionID schon 0, und das Abmelden bei einem gelöschten Parent
 * hinterlässt keine Warnung. Den Parent aus ConnectionID zu lesen ist daher
 * richtig, $Data der Nachricht wird nicht gebraucht.
 */
trait ParentStatus
{
    private const string BUFFER_PARENT_ID = 'StatusParentID';

    /** Nachrichten, auf die eine Instanz ihren Status neu bestimmt. */
    private const array PARENT_MESSAGES = [IM_CHANGESTATUS, FM_CONNECT, FM_DISCONNECT];

    /** In Create() und ApplyChanges() aufrufen. */
    protected function RegisterParentMessages(): void
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        $old = (int) $this->GetBuffer(self::BUFFER_PARENT_ID);
        $new = $this->GetParent();

        if ($old > 0 && $old !== $new) {
            $this->UnregisterMessage($old, IM_CHANGESTATUS);
        }
        if ($new > 0) {
            $this->RegisterMessage($new, IM_CHANGESTATUS);
        }
        $this->SetBuffer(self::BUFFER_PARENT_ID, (string) $new);
    }

    protected function IsParentMessage(int $Message): bool
    {
        return in_array($Message, self::PARENT_MESSAGES, true);
    }

    /** ID des Parents, 0 ohne Verbindung. */
    protected function GetParent(): int
    {
        return (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }
}
