<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';
require_once __DIR__ . '/../libs/helper.php';

class Tasmota extends TasmotaService
{
    use BufferHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->createVariablenProfiles();
        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyInteger('PowerOnState', 3);
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterVariableFloat('Tasmota_RSSI', 'RSSI');
        $this->RegisterVariableBoolean('Tasmota_DeviceStatus', 'Status', 'Tasmota.DeviceStatus');
        //Settings
        $this->RegisterPropertyBoolean('SystemVariables', false);
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        $this->RegisterPropertyBoolean('Fan', false);
        //Debug Optionen
        $this->RegisterPropertyBoolean('Sensoren', false);
        $this->RegisterPropertyBoolean('State', false);
        $this->RegisterPropertyBoolean('Pow', false);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter fÃ¼r ReceiveData
        $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));

        if ($this->ReadPropertyBoolean('Fan')) {
            $this->RegisterProfileInteger('Tasmota.FanSpeed', 'Speedo', '', '', 0, 3, 1);
            $this->RegisterVariableInteger('Tasmota_FanSpeed', 'Speed', 'Tasmota.FanSpeed', 0);
            $this->EnableAction('Tasmota_FanSpeed');
        }

        $this->SendDebug(__FUNCTION__ . ' FullTopic', $this->ReadPropertyString('FullTopic'), 0);
        $topic = $this->FilterFullTopicReceiveData();
        $this->SendDebug(__FUNCTION__ . ' Filter FullTopic', $topic, 0);

        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);
            // Buffer decodieren und in eine Variable schreiben
            $Buffer = $data;
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            $off = $this->ReadPropertyString('Off');
            $on = $this->ReadPropertyString('On');

            //PowerOnState Vairablen setzen
            if (fnmatch('*PowerOnState*', $Buffer->Payload)) {
                $this->SendDebug('PowerOnState Topic', $Buffer->Topic, 0);
                $this->SendDebug('PowerOnState Payload', $Buffer->Payload, 0);
                $Payload = json_decode($Buffer->Payload);
                if (property_exists($Payload, 'PowerOnState')) {
                    $this->setPowerOnStateInForm($Payload->PowerOnState);
                }
            }
            //Power Vairablen checken
            if (property_exists($Buffer, 'Topic')) {
                if (fnmatch('*POWER*', $Buffer->Topic)) {
                    $this->SendDebug('Power Topic', $Buffer->Topic, 0);
                    $this->SendDebug('Power', $Buffer->Payload, 0);
                    $power = explode('/', $Buffer->Topic);
                    end($power);
                    $lastKey = key($power);
                    $tmpPower = 'POWER1';
                    if ($this->ReadPropertyBoolean('Power1Deactivate') == true) {
                        $tmpPower = 'POWER';
                    }
                    if ($power[$lastKey] != $tmpPower) {
                        $this->RegisterVariableBoolean('Tasmota_' . $power[$lastKey], $power[$lastKey], '~Switch');
                        $this->EnableAction('Tasmota_' . $power[$lastKey]);
                        switch ($Buffer->Payload) {
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
                if (fnmatch('*STATE', $Buffer->Topic)) {
                    $myBuffer = json_decode($Buffer->Payload);
                    $this->Debug('State Payload', $Buffer->Payload, 'State');
                    $this->Debug('State Wifi', $myBuffer->Wifi->RSSI, 'State');

                    if ($this->ReadPropertyBoolean('SystemVariables')) {
                        $this->getSystemVariables($myBuffer);
                    }

                    SetValue($this->GetIDForIdent('Tasmota_RSSI'), $myBuffer->Wifi->RSSI);
                }
                if (fnmatch('*RESULT', $Buffer->Topic)) {
                    $this->SendDebug('Result', $Buffer->Payload, 0);
                    $this->BufferResponse = $Buffer->Payload;
                }
                if (fnmatch('*LWT', $Buffer->Topic)) {
                    $this->Debug('State Payload', $Buffer->Payload, 'State');
                    if (strtolower($Buffer->Payload) == 'online') {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), true);
                    } else {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), false);
                    }
                }
                //Sensor Variablen checken
                if (fnmatch('*SENSOR', $Buffer->Topic)) {
                    $this->Debug('Sensor Payload', $Buffer->Payload, 'Sensoren');
                    $this->Debug('Sensor Topic', $Buffer->Topic, 'Sensoren');
                    $myBuffer = json_decode($Buffer->Payload, true);
                    $this->traverseArray($myBuffer, $myBuffer);
                }
            }
            //FanSpeed
            if (fnmatch('*FanSpeed*', $Buffer->Payload)) {
                $myBuffer = json_decode($Buffer->Payload);
                if (property_exists($myBuffer, 'FanSpeed')) {
                    $this->SendDebug('FandSpeed', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tasmota_FanSpeed'), $myBuffer->FanSpeed);
                }
            }
            //IrReceived
            if (fnmatch('*IrReceived*', $Buffer->Payload)) {
                $myBuffer = json_decode($Buffer->Payload);
                $this->SendDebug('IrReceived', $Buffer->Payload, 0);
                if (property_exists($myBuffer->IrReceived, 'Protocol')) {
                    $this->RegisterVariableString('Tasmota_IRProtocol', 'IR Protocol', '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRProtocol'), $myBuffer->IrReceived->Protocol);
                }
                if (property_exists($myBuffer->IrReceived, 'Bits')) {
                    $this->RegisterVariableString('Tasmota_IRBits', 'IR Bits', '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRBits'), $myBuffer->IrReceived->Bits);
                }
                if (property_exists($myBuffer->IrReceived, 'Data')) {
                    $this->RegisterVariableString('Tasmota_IRData', 'IR Data', '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRData'), $myBuffer->IrReceived->Data);
                }
            }

            //POW Variablen
            if (fnmatch('*ENERGY*', $Buffer->Payload)) {
                $myBuffer = json_decode($Buffer->Payload);
                if (property_exists($myBuffer, 'ENERGY')) {
                    $this->Debug('ENERGY Payload', $Buffer->Payload, 'Pow');

                    if (property_exists($myBuffer->ENERGY, 'Power')) {
                        $this->RegisterVariableFloat('Tasmota_POWPower', 'Power', '~Watt.3680');
                        SetValue($this->GetIDForIdent('Tasmota_POWPower'), $myBuffer->ENERGY->Power);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Total')) {
                        $this->RegisterVariableFloat('Tasmota_POWTotal', 'Total', '~Electricity');
                        SetValue($this->GetIDForIdent('Tasmota_POWTotal'), $myBuffer->ENERGY->Total);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Today')) {
                        $this->RegisterVariableFloat('Tasmota_POWToday', 'Today', '~Electricity');
                        SetValue($this->GetIDForIdent('Tasmota_POWToday'), $myBuffer->ENERGY->Today);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Yesterday')) {
                        $this->RegisterVariableFloat('Tasmota_POWYesterday', 'Yesterday', '~Electricity');
                        SetValue($this->GetIDForIdent('Tasmota_POWYesterday'), $myBuffer->ENERGY->Yesterday);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Current')) {
                        $this->RegisterVariableFloat('Tasmota_POWCurrent', 'Current', '~Ampere');
                        SetValue($this->GetIDForIdent('Tasmota_POWCurrent'), $myBuffer->ENERGY->Current);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Voltage')) {
                        $this->RegisterVariableFloat('Tasmota_POWVoltage', 'Voltage', '~Volt');
                        SetValue($this->GetIDForIdent('Tasmota_POWVoltage'), $myBuffer->ENERGY->Voltage);
                    }

                    if (property_exists($myBuffer->ENERGY, 'Factor')) {
                        $this->RegisterVariableFloat('Tasmota_POWFactor', 'Factor');
                        SetValue($this->GetIDForIdent('Tasmota_POWFactor'), $myBuffer->ENERGY->Factor);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ApparentPower')) {
                        $this->RegisterVariableFloat('Tasmota_POWApparentPower', 'ApparentPower', 'Tasmota.ApparentPower');
                        SetValue($this->GetIDForIdent('Tasmota_POWApparentPower'), $myBuffer->ENERGY->ApparentPower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ReactivePower')) {
                        $this->RegisterVariableFloat('Tasmota_POWReactivePower', 'ReactivePower', 'Tasmota.ReactivePower');
                        SetValue($this->GetIDForIdent('Tasmota_POWReactivePower'), $myBuffer->ENERGY->ReactivePower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'PhaseAngle')) {
                        $this->RegisterVariableFloat('Tasmota_PhaseAngle', 'PhaseAngle');
                        SetValue($this->GetIDForIdent('Tasmota_PhaseAngle'), $myBuffer->ENERGY->PhaseAngle);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ImportActivePower')) {
                        $this->RegisterVariableFloat('Tasmota_ImportActivePower', 'ImportActivePower', '~Electricity');
                        SetValue($this->GetIDForIdent('Tasmota_ImportActivePower'), $myBuffer->ENERGY->ImportActivePower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ExportActivePower')) {
                        $this->RegisterVariableFloat('Tasmota_ExportActivePower', 'ExportActivePower', '~Electricity');
                        SetValue($this->GetIDForIdent('Tasmota_ExportActivePower'), $myBuffer->ENERGY->ExportActivePower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ImportReactivePower')) {
                        $this->RegisterVariableFloat('Tasmota_ImportReactivePower', 'ImportReactivePower', 'Tasmota.ReactivePower_kvarh');
                        SetValue($this->GetIDForIdent('Tasmota_ImportReactivePower'), $myBuffer->ENERGY->ImportReactivePower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'ExportReactivePower')) {
                        $this->RegisterVariableFloat('Tasmota_ExportReactivePower', 'ExportReactivePower', 'Tasmota.ReactivePower_kvarh');
                        SetValue($this->GetIDForIdent('Tasmota_ExportReactivePower'), $myBuffer->ENERGY->ExportReactivePower);
                    }

                    if (property_exists($myBuffer->ENERGY, 'TotalReactivePower')) {
                        $this->RegisterVariableFloat('Tasmota_TotalReactivePower', 'TotalReactivePower', 'Tasmota.ReactivePower_kvarh');
                        SetValue($this->GetIDForIdent('Tasmota_TotalReactivePower'), $myBuffer->ENERGY->TotalReactivePower);
                    }
                }
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $this->SendDebug(__FUNCTION__ . ' Ident', $Ident, 0);
        $this->SendDebug(__FUNCTION__ . ' Value', $Value, 0);
        if ($Ident == 'Tasmota_FanSpeed') {
            $result = $this->setFanSpeed($Value);
            return true;
        }

        if (strlen($Ident) != 13) {
            $power = substr($Ident, 13);
        } else {
            $power = 0;
        }
        $result = $this->setPower(intval($power), $Value);
    }

    public function setFanSpeed(int $value)
    {
        $command = 'FanSpeed';
        $msg = strval($value);
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setFanSpeed', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    private function createVariablenProfiles()
    {
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('Tasmota.DeviceStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);

        if (!IPS_VariableProfileExists('Tasmota.ReactivePower_kvarh')) {
            IPS_CreateVariableProfile('Tasmota.ReactivePower_kvarh', 2);
        }
        IPS_SetVariableProfileDigits('Tasmota.ReactivePower_kvarh', 2);
        IPS_SetVariableProfileText('Tasmota.ReactivePower_kvarh', '', ' kvarh');

        if (!IPS_VariableProfileExists('Tasmota.ReactivePower')) {
            IPS_CreateVariableProfile('Tasmota.ReactivePower', 2);
        }
        IPS_SetVariableProfileDigits('Tasmota.ReactivePower', 2);
        IPS_SetVariableProfileText('Tasmota.ReactivePower', '', ' VAr');

        if (!IPS_VariableProfileExists('Tasmota.ApparentPower')) {
            IPS_CreateVariableProfile('Tasmota.ApparentPower', 2);
        }
        IPS_SetVariableProfileDigits('Tasmota.ApparentPower', 2);
        IPS_SetVariableProfileText('Tasmota.ApparentPower', '', ' VA');
    }
}
