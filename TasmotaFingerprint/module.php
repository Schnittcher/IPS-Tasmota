<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';

class TasmotaFingerprint extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterPropertyInteger('PowerOnState', 3);
        $this->RegisterPropertyBoolean('SystemVariables', false);

        $this->createVariabenProfiles();
        $this->RegisterVariableInteger('ID', $this->Translate('ID'), '', 1);
        $this->RegisterVariableInteger('Confidence', $this->Translate('Confidence'), '', 2);
        $this->RegisterVariableBoolean('DeviceStatus', 'Status', 'Tasmota.DeviceStatus', 3);
        $this->RegisterVariableInteger('RSSI', 'RSSI', 'Tasmota.RSSI', 4);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));
        }

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

            // Buffer decodieren und in eine Variable schreiben
            $Payload = json_decode($Buffer->Payload);
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            if (fnmatch('*LWT', $Buffer->Topic)) {
                $this->SendDebug('State Payload', $Buffer->Payload, 0);
                if (strtolower($Buffer->Payload) == 'online') {
                    SetValue($this->GetIDForIdent('DeviceStatus'), true);
                } else {
                    SetValue($this->GetIDForIdent('DeviceStatus'), false);
                }
            }
            if (fnmatch('*RESULT', $Buffer->Topic)) {
                $this->SendDebug('Result', $Buffer->Payload, 0);
                $this->BufferResponse = $Buffer->Payload;
            }
            switch ($Buffer->Topic) {
            case 'stat/' . $this->ReadPropertyString('Topic') . '/RESULT':
                if (property_exists($Payload, 'PowerOnState')) {
                    $this->SendDebug('Receive Result: PowerOnState', $Payload->PowerOnState, 0);
                    $this->setPowerOnStateInForm($Payload->PowerOnState);
                }
                if (property_exists($Payload, 'FPrint')) {
                    $this->SendDebug('Receive Result: FPrint', json_encode($Payload->FPrint), 0);
                    SetValue($this->GetIDForIdent('ID'), $Payload->FPrint->Id);
                    SetValue($this->GetIDForIdent('Confidence'), $Payload->FPrint->Confidence);
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
                    SetValue($this->GetIDForIdent('RSSI'), $Payload->Wifi->RSSI);
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

    private function createVariabenProfiles()
    {
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('Tasmota.DeviceStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);
        $this->RegisterProfileInteger('Tasmota.RSSI', 'Intensity', '', '', 1, 100, 1);
    }
}
