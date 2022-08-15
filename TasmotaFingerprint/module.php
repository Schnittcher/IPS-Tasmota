<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';
require_once __DIR__ . '/../libs/helper.php';

class TasmotaFingerprint extends TasmotaService
{
    use BufferHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterPropertyBoolean('SystemVariables', false);
        $this->RegisterPropertyBoolean('Power1Deactivate', false);

        $this->createVariabenProfiles();
        $this->RegisterVariableInteger('Tasmota_PowerOnState', $this->Translate('PowerOnState'), 'Tasmota.PowerOnState', 0);
        $this->EnableAction('Tasmota_PowerOnState');
        $this->RegisterVariableInteger('ID', $this->Translate('ID'), '', 1);
        $this->RegisterVariableInteger('Confidence', $this->Translate('Confidence'), '', 2);
        $this->RegisterVariableBoolean('DeviceStatus', 'Status', 'Tasmota.DeviceStatus', 3);
        $this->RegisterVariableInteger('RSSI', 'RSSI', 'Tasmota.RSSI', 4);
        $this->RegisterVariableInteger('count', $this->Translate('count'), '', 5);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $this->SendDebug(__FUNCTION__ . ' FullTopic', $this->ReadPropertyString('FullTopic'), 0);
        $topic = $this->FilterFullTopicReceiveData();
        $this->SendDebug(__FUNCTION__ . ' Filter FullTopic', $topic, 0);

        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);

            switch ($data->DataID) {
                case '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}': // MQTT Server
                    $Buffer = $data;
                    break;
                case '{DBDA9DF7-5D04-F49D-370A-2B9153D00D9B}': //MQTT Client
                    $Buffer = json_decode($data->Buffer);
                    break;
                default:
                    $this->LogMessage('Invalid Parent', KL_ERROR);
                    return;
            }

            $off = $this->ReadPropertyString('Off');
            $on = $this->ReadPropertyString('On');

            // Buffer decodieren und in eine Variable schreiben
            $Payload = json_decode($Buffer->Payload);
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            if (fnmatch('*LWT', $Buffer->Topic)) {
                $this->SendDebug('State Payload', $Buffer->Payload, 0);
                if (strtolower($Buffer->Payload) == 'online') {
                    $this->SetValue('DeviceStatus', true);
                } else {
                    $this->SetValue('DeviceStatus', false);
                }
            }
            if (fnmatch('*RESULT', $Buffer->Topic)) {
                $this->SendDebug('Result', $Buffer->Payload, 0);
                $this->BufferResponse = $Buffer->Payload;
            }

            if (fnmatch('*PowerOnState*', $Buffer->Payload)) {
                $this->SendDebug('PowerOnState Topic', $Buffer->Topic, 0);
                $this->SendDebug('PowerOnState Payload', $Buffer->Payload, 0);
                $Payload = json_decode($Buffer->Payload);
                if (is_object($Payload)) {
                    if (property_exists($Payload, 'PowerOnState')) {
                        $this->SetValue('Tasmota_PowerOnState', $Payload->PowerOnState);
                    }
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
                    $this->SetValue('Tasmota_' . $power[$lastKey], 0);
                  break;
                case $on:
                    $this->SetValue('Tasmota_' . $power[$lastKey], 1);
                break;
                        }
                    }
                }
            }

            switch ($Buffer->Topic) {
            case 'stat/' . $this->ReadPropertyString('Topic') . '/RESULT':
                if (property_exists($Payload, 'PowerOnState')) {
                    $this->SendDebug('Receive Result: PowerOnState', $Payload->PowerOnState, 0);
                    $this->SetValue('Tasmota_PowerOnState', $Payload->PowerOnState);
                }
                if (property_exists($Payload, 'FPrint')) {
                    $this->SendDebug('Receive Result: FPrint', json_encode($Payload->FPrint), 0);
                    $this->SetValue('ID', $Payload->FPrint->Id);
                    $this->SetValue('Confidence', $Payload->FPrint->Confidence);
                }
                if (property_exists($Payload, 'FpEnroll')) {
                    $this->SendDebug('Receive Result: FpEnroll', json_encode($Payload->FpEnroll), 0);
                    $this->UpdateFormField('Status', 'caption', $Payload->FpEnroll);
                }
                if (property_exists($Payload, 'FpDelete')) {
                    $this->SendDebug('Receive Result: FpDelete', json_encode($Payload->FpEnroll), 0);
                    $this->UpdateFormField('Status', 'caption', $Payload->FpEnroll);
                }
                if (property_exists($Payload, 'FpCount')) {
                    $this->SendDebug('Receive Result: FpCount', json_encode($Payload->FpCount), 0);
                    $this->SetValue('count', $Payload->FpCount);
                    $this->UpdateFormField('CountValue', 'caption', $Payload->FpCount);
                }

                break;
            case 'tele/' . $this->ReadPropertyString('Topic') . '/SENSOR':
                $myBuffer = json_decode($Buffer->Payload, true);
                $this->traverseArray($myBuffer, $myBuffer);
                break;
            case 'tele/' . $this->ReadPropertyString('Topic') . '/STATE':
                if ($this->ReadPropertyBoolean('SystemVariables')) {
                    $myBuffer = json_decode($Buffer->Payload);
                    $this->getSystemVariables($myBuffer);
                }
                if (property_exists($Payload, 'Wifi')) {
                    $this->SendDebug('Receive Sate: Wifi RSSI', $Payload->Wifi->RSSI, 0);
                    $this->SetValue('RSSI', $Payload->Wifi->RSSI);
                }
            }
        }
    }

    public function enrollFP(int $value)
    {
        $command = 'Fpenroll';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
    }

    public function deleteFP(int $value)
    {
        $command = 'Fpdelete';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
    }

    public function countFP()
    {
        $command = 'FpCount';
        $this->MQTTCommand($command, '');
    }

    public function RequestAction($Ident, $Value)
    {
        $this->SendDebug(__FUNCTION__ . ' Ident', $Ident, 0);
        $this->SendDebug(__FUNCTION__ . ' Value', $Value, 0);

        if ($Ident == 'Tasmota_PowerOnState') {
            $this->setPowerOnState($Value);
            return true;
        }

        if (strlen($Ident) != 13) {
            $power = substr($Ident, 13);
        } else {
            $power = 0;
        }
        $result = $this->setPower(intval($power), $Value);
    }

    private function createVariabenProfiles()
    {
        $this->RegisterProfileIntegerEx('Tasmota.PowerOnState', 'Power', '', '', [
            [0, $this->Translate('Off'),  '', 0x08f26e],
            [1, $this->Translate('On'),  '', 0x07da63],
            [2, $this->Translate('Toggle'),  '', 0x06c258],
            [3, $this->Translate('Default'),  '', 0x06a94d],
            [4, $this->Translate('Turn relay(s) on, disable further relay control'),  '', 0x06a94d]
        ]);
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('Tasmota.DeviceStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);
        $this->RegisterProfileInteger('Tasmota.RSSI', 'Intensity', '', '', 1, 100, 1);
    }
}
