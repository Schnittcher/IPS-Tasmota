<?php

require_once __DIR__ . '/../libs/TasmotaService.php';

class IPS_Tasmota extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        $this->createVariablenProfiles();
        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyInteger('PowerOnState', 3);
        $this->RegisterVariableFloat('Tasmota_RSSI', 'RSSI');
        $this->RegisterVariableBoolean('Tasmota_DeviceStatus', 'Status', 'Tasmota.DeviceStatus');
        //Settings
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        //Debug Optionen
        $this->RegisterPropertyBoolean('Sensoren', false);
        $this->RegisterPropertyBoolean('State', false);
        $this->RegisterPropertyBoolean('Pow', false);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        //Setze Filter fÃ¼r ReceiveData
        $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));
        $topic = $this->ReadPropertyString('Topic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);
            // Buffer decodieren und in eine Variable schreiben
            $Buffer = json_decode($data->Buffer);
            $this->SendDebug('Topic', $Buffer->TOPIC, 0);
            $off = $this->ReadPropertyString('Off');
            $on = $this->ReadPropertyString('On');

            //PowerOnState Vairablen setzen
            if (fnmatch('*PowerOnState*', $Buffer->MSG)) {
                $this->SendDebug('PowerOnState Topic', $Buffer->TOPIC, 0);
                $this->SendDebug('PowerOnState MSG', $Buffer->MSG, 0);
                $MSG = json_decode($Buffer->MSG);
                if (property_exists($MSG, 'PowerOnState')) {
                    $this->setPowerOnStateInForm($MSG->PowerOnState);
                }
            }
            //Power Vairablen checken
            if (property_exists($Buffer, 'TOPIC')) {
                if (fnmatch('*POWER*', $Buffer->TOPIC)) {
                    $this->SendDebug('Power Topic', $Buffer->TOPIC, 0);
                    $this->SendDebug('Power', $Buffer->MSG, 0);
                    $power = explode('/', $Buffer->TOPIC);
                    end($power);
                    $lastKey = key($power);
                    $tmpPower = 'POWER1';
                    if ($this->ReadPropertyBoolean('Power1Deactivate') == true) {
                        $tmpPower = 'POWER';
                    }
                    if ($power[$lastKey] != $tmpPower) {
                        $this->RegisterVariableBoolean('Tasmota_' . $power[$lastKey], $power[$lastKey], '~Switch');
                        $this->EnableAction('Tasmota_' . $power[$lastKey]);
                        switch ($Buffer->MSG) {
                case $off:
                  SetValue($this->GetIDForIdent('Tasmota_' . $power[$lastKey]), 0);
                  break;
                case $on:
                SetValue($this->GetIDForIdent('Tasmota_' . $power[$lastKey]), 1);
                break;
              }
                    }
                }
                //State checken
                if (fnmatch('*STATE', $Buffer->TOPIC)) {
                    $myBuffer = json_decode($Buffer->MSG);
                    $this->Debug('State MSG', $Buffer->MSG, 'State');
                    $this->Debug('State Wifi', $myBuffer->Wifi->RSSI, 'State');
                    SetValue($this->GetIDForIdent('Tasmota_RSSI'), $myBuffer->Wifi->RSSI);
                }
                if (fnmatch('*LWT', $Buffer->TOPIC)) {
                    $this->Debug('State MSG', $Buffer->MSG, 'State');
                    if (strtolower($Buffer->MSG) == 'online') {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), true);
                    } else {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), false);
                    }
                }
                //Sensor Variablen checken
                if (fnmatch('*SENSOR', $Buffer->TOPIC)) {
                    $this->Debug('Sensor MSG', $Buffer->MSG, 'Sensoren');
                    $this->Debug('Sensor Topic', $Buffer->TOPIC, 'Sensoren');
                    $myBuffer = json_decode($Buffer->MSG, true);
                    $this->traverseArray($myBuffer, $myBuffer);
                }
            }
            //POW Variablen
            if (fnmatch('*ENERGY*', $Buffer->MSG)) {
                $myBuffer = json_decode($Buffer->MSG);
                if (property_exists($myBuffer, 'ENERGY')) {
                    $this->Debug('ENERGY MSG', $Buffer->MSG, 'Pow');
                    $this->RegisterVariableFloat('Tasmota_POWTotal', 'Total', '~Electricity');
                    $this->RegisterVariableFloat('Tasmota_POWYesterday', 'Yesterday', '~Electricity');
                    $this->RegisterVariableFloat('Tasmota_POWToday', 'Today', '~Electricity');
                    $this->RegisterVariableFloat('Tasmota_POWPower', 'Power', '~Watt.3680');
                    $this->RegisterVariableFloat('Tasmota_POWFactor', 'Factor');
                    $this->RegisterVariableFloat('Tasmota_POWVoltage', 'Voltage', '~Volt');
                    $this->RegisterVariableFloat('Tasmota_POWCurrent', 'Current', '~Ampere');

                    SetValue($this->GetIDForIdent('Tasmota_POWPower'), $myBuffer->ENERGY->Power);
                    SetValue($this->GetIDForIdent('Tasmota_POWTotal'), $myBuffer->ENERGY->Total);
                    SetValue($this->GetIDForIdent('Tasmota_POWToday'), $myBuffer->ENERGY->Today);
                    SetValue($this->GetIDForIdent('Tasmota_POWYesterday'), $myBuffer->ENERGY->Yesterday);
                    SetValue($this->GetIDForIdent('Tasmota_POWCurrent'), $myBuffer->ENERGY->Current);
                    SetValue($this->GetIDForIdent('Tasmota_POWVoltage'), $myBuffer->ENERGY->Voltage);
                    SetValue($this->GetIDForIdent('Tasmota_POWFactor'), $myBuffer->ENERGY->Factor);
                }
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $this->SendDebug(__FUNCTION__ . ' Ident', $Ident, 0);
        $this->SendDebug(__FUNCTION__ . ' Value', $Value, 0);
        if (strlen($Ident) != 13) {
            $power = substr($Ident, 13);
        } else {
            $power = 0;
        }
        $result = $this->setPower($power, $Value);
    }

    private function createVariablenProfiles()
    {
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('Tasmota.DeviceStatus', 'Network', '', '', array(
            array(false, 'Offline',  '', 0xFF0000),
            array(true, 'Online',  '', 0x00FF00)
        ));
    }
}
