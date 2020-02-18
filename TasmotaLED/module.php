<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';

class TasmotaLED extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterAttributeInteger('GatewayMode', 0); // 0 = MQTTServer 1 = MQTTClient

        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterPropertyInteger('PowerOnState', 3);
        $this->RegisterPropertyBoolean('SystemVariables', false);
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        $this->RegisterPropertyBoolean('Sensoren', true);

        $this->createVariabenProfiles();
        $this->RegisterVariableBoolean('TasmotaLED_Fade', $this->Translate('Fade'), 'Switch', 1);
        $this->RegisterVariableInteger('TasmotaLED_Color', $this->Translate('Color'), 'HexColor', 2);
        $this->RegisterVariableInteger('TasmotaLED_Dimmer', $this->Translate('Dimmer'), 'Intensity.100', 3);
        $this->RegisterVariableInteger('TasmotaLED_Scheme', $this->Translate('Scheme'), 'TasmotaLED.Scheme', 4);
        $this->RegisterVariableInteger('TasmotaLED_Speed', $this->Translate('Speed'), 'TasmotaLED.Speed', 5);
        $this->RegisterVariableInteger('TasmotaLED_Pixels', $this->Translate('Pixels'), '', 6);
        $this->RegisterVariableInteger('TasmotaLED_RSSI', 'RSSI', 'TasmotaLED.RSSI', 7);
        $this->RegisterVariableBoolean('TasmotaLED_DeviceStatus', 'Status', 'TasmotaLED.DeviceStatus', 8);
        $this->EnableAction('TasmotaLED_Speed');
        $this->EnableAction('TasmotaLED_Fade');
        $this->EnableAction('TasmotaLED_Scheme');
        $this->EnableAction('TasmotaLED_Color');
        $this->EnableAction('TasmotaLED_Dimmer');
    }

    public function ApplyChanges()
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
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

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case FM_CONNECT:
                //$this->LogMessage('parentGUID '. print_r($Data),KL_DEBUG);
                $parentGUID = IPS_GetInstance($Data[0])['ModuleInfo']['ModuleID'];
                switch ($parentGUID) {
                    case '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}':
                        $this->WriteAttributeInteger('GatewayMode', 0);
                        break;
                    case '{EE0D345A-CF31-428A-A613-33CE98E752DD}':
                        $this->WriteAttributeInteger('GatewayMode', 1);
                        break;
                }
                break;
            default:
                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $GatewayMode = $this->ReadAttributeInteger('GatewayMode');
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);

            $this->SendDebug('GatewayMode', $GatewayMode, 0);
            if ($GatewayMode == 0) {
                $Buffer = $data;
            } else {
                $Buffer = json_decode($data->Buffer);
            }

            // Buffer decodieren und in eine Variable schreiben
            $Payload = json_decode($Buffer->Payload);
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            if (fnmatch('*LWT', $Buffer->Topic)) {
                $this->SendDebug('State Payload', $Buffer->Payload, 0);
                if (strtolower($Buffer->Payload) == 'online') {
                    SetValue($this->GetIDForIdent('TasmotaLED_DeviceStatus'), true);
                } else {
                    SetValue($this->GetIDForIdent('TasmotaLED_DeviceStatus'), false);
                }
            }
            if (fnmatch('*RESULT', $Buffer->Topic)) {
                $this->SendDebug('Result', $Buffer->Payload, 0);
                $this->BufferResponse = $Buffer->Payload;
            }
            switch ($Buffer->Topic) {
            case 'stat/' . $this->ReadPropertyString('Topic') . '/RESULT':
                if ($this->ReadPropertyBoolean('Power1Deactivate') == false) {
                    if (property_exists($Payload, 'POWER')) {
                        $this->RegisterVariableBoolean('TasmotaLED_POWER', 'POWER', '~Switch');
                        $this->EnableAction('TasmotaLED_POWER');
                        $this->SendDebug('Receive Result: POWER', $Payload->POWER, 0);
                        switch ($Payload->POWER) {
                            case $this->ReadPropertyString('Off'):
                                SetValue($this->GetIDForIdent('TasmotaLED_POWER'), 0);
                                break;
                            case $this->ReadPropertyString('On'):
                                SetValue($this->GetIDForIdent('TasmotaLED_POWER'), 1);
                                break;
                        }
                    }
                } else {
                    //Es kann bei einem MultiSwitch 4 Power Variablen geben
                    for ($i = 1; $i <= 4; $i++) {
                        if (property_exists($Payload, 'POWER' . $i)) {
                            $this->SendDebug('Receive Result: POWER' . $i, $Payload->{'POWER' . $i}, 0);
                            $this->RegisterVariableBoolean('TasmotaLED_POWER' . $i, 'POWER' . $i, '~Switch');
                            $this->EnableAction('TasmotaLED_POWER' . $i);
                            $this->SendDebug('Receive Result: POWER' . $i, $Payload->{'POWER' . $i}, 0);
                            switch ($Payload->{'POWER' . $i}) {
                                case $this->ReadPropertyString('Off'):
                                    SetValue($this->GetIDForIdent('TasmotaLED_POWER' . $i), 0);
                                    break;
                                case $this->ReadPropertyString('On'):
                                    SetValue($this->GetIDForIdent('TasmotaLED_POWER' . $i), 1);
                                    break;
                            }
                        }
                    }
                }

                if (property_exists($Payload, 'PowerOnState')) {
                    $this->SendDebug('Receive Result: PowerOnState', $Payload->PowerOnState, 0);
                    $this->setPowerOnStateInForm($Payload->PowerOnState);
                }
                if (property_exists($Payload, 'Pixels')) {
                    $this->SendDebug('Receive Result: Pixels', $Payload->Pixels, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Pixels'), $Payload->Pixels);
                }
                if (property_exists($Payload, 'Speed')) {
                    $this->SendDebug('Receive Result: Speed', $Payload->Speed, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Speed'), $Payload->Speed);
                }
                if (property_exists($Payload, 'Scheme')) {
                    $this->SendDebug('Receive Result: Scheme', $Payload->Scheme, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Scheme'), $Payload->Scheme);
                }
                if (property_exists($Payload, 'Dimmer')) {
                    $this->SendDebug('Receive Result: Dimmer', $Payload->Dimmer, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Dimmer'), $Payload->Dimmer);
                }
                if (property_exists($Payload, 'Color')) {
                    $this->SendDebug('Receive Result: Color', $Payload->Color, 0);
                    $rgb = explode(',', $Payload->Color);
                    $color = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                    SetValue($this->GetIDForIdent('TasmotaLED_Color'), hexdec(($color)));
                }
                if (property_exists($Payload, 'Fade')) {
                    $this->SendDebug('Receive Result: Fade', $Payload->Fade, 0);
                    if (strtoupper($Payload->Fade) == 'ON') {
                        SetValue($this->GetIDForIdent('TasmotaLED_Fade'), true);
                    } else {
                        SetValue($this->GetIDForIdent('TasmotaLED_Fade'), false);
                    }
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
                    SetValue($this->GetIDForIdent('TasmotaLED_RSSI'), $Payload->Wifi->RSSI);
                }

            }
        }
    }

    public function setLED(int $LED, string $color)
    {
        $command = 'Led' . $LED;
        $msg = $color;
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setLED', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setScheme(int $schemeID)
    {
        $command = 'Scheme';
        $msg = strval($schemeID);
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setScheme', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setPixel(int $count)
    {
        $command = 'Pixels';
        $msg = strval($count);
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setPixel', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setDimmer(int $value)
    {
        $command = 'Dimmer';
        $msg = strval($value);
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setDimmer', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setColorHex(string $color)
    {
        $command = 'Color';
        $msg = $color;
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setColorHex', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setFade(bool $value)
    {
        $command = 'Fade';
        $msg = $value;
        if ($msg === false) {
            $msg = 'OFF';
        } elseif ($msg === true) {
            $msg = 'ON';
        }
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setFade', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setSpeed(int $value)
    {
        $command = 'Speed';
        $msg = strval($value);
        $DataJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setSpeed', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function RequestAction($Ident, $Value)
    {
        //Power Variablen
        if (fnmatch('TasmotaLED_POWER*', $Ident)) {
            if (strlen($Ident) != 16) {
                $power = substr($Ident, 16);
            } else {
                $power = 0;
            }
            $this->setPower($power, $Value);
        }
        switch ($Ident) {
      case 'TasmotaLED_Speed':
        $this->setSpeed($Value);
        break;
      case 'TasmotaLED_Fade':
        $this->setFade($Value);
        break;
      case 'TasmotaLED_Scheme':
        $this->setScheme($Value);
        break;
      case 'TasmotaLED_Color':
          $rgb = $Value;
           $r = (($rgb >> 16) & 0xFF);
          $g = (($rgb >> 8) & 0xFF);
          $b = ($rgb & 0xFF);
          $this->setColorHex("$r,$g,$b");
        break;
      case 'TasmotaLED_Dimmer':
        $this->setDimmer($Value);
        break;
      default:
        // code...
        break;
    }
    }

    private function createVariabenProfiles()
    {
        //Speed Profile
        $this->RegisterProfileInteger('TasmotaLED.Speed', 'Speedo', '', '', 1, 20, 1);
        $this->RegisterProfileInteger('TasmotaLED.RSSI', 'Intensity', '', '', 1, 100, 1);
        //Scheme Profile
        $this->RegisterProfileIntegerEx('TasmotaLED.Scheme', 'Shuffle', '', '', [
            [0, 'Default',  '', -1],
            [1, 'Wake up',  '', -1],
            [2, 'RGB Cycle', '', -1],
            [3, 'RBG Cycle', '', -1],
            [4, 'Random cycle', '', -1],
            [5, 'Clock', '', -1],
            [6, 'Incandescent pattern', '', -1],
            [7, 'RGB Pattern', '', -1],
            [8, 'Christmas', '', -1],
            [9, 'Hanukkah', '', -1],
            [10, 'Kwanzaa', '', -1],
            [11, 'Rainbow', '', -1],
            [12, 'Fire', '', -1]
        ]);
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('TasmotaLED.DeviceStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);
    }
}
