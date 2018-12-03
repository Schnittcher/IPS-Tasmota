<?php

require_once __DIR__ . '/../libs/TasmotaService.php';

class IPS_TasmotaLED extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterPropertyInteger('PowerOnState', 3);
        //$this->RegisterPropertyString("DeviceLanguage","en");
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        $this->RegisterPropertyBoolean('Sensoren', true);

        $this->createVariabenProfiles();
        $this->RegisterVariableBoolean('TasmotaLED_Fade', 'Fade', 'Switch', 1);
        $this->RegisterVariableInteger('TasmotaLED_Color', 'Color', 'HexColor', 2);
        $this->RegisterVariableInteger('TasmotaLED_Dimmer', 'Dimmer', 'Intensity.100', 3);
        $this->RegisterVariableInteger('TasmotaLED_Scheme', 'Scheme', 'TasmotaLED.Scheme', 4);
        $this->RegisterVariableInteger('TasmotaLED_Speed', 'Speed', 'TasmotaLED.Speed', 5);
        $this->RegisterVariableInteger('TasmotaLED_Pixels', 'Pixels', '', 6);
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
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        //Setze Filter für ReceiveData
        $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));
        $topic = $this->ReadPropertyString('Topic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);

            // Buffer decodieren und in eine Variable schreiben
            $Buffer = json_decode($data->Buffer);
            $MSG = json_decode($Buffer->MSG);
            $this->SendDebug('Topic', $Buffer->TOPIC, 0);
            if (fnmatch('*LWT', $Buffer->TOPIC)) {
                $this->Debug('State MSG', $Buffer->MSG, 'State');
                if (strtolower($Buffer->MSG) == 'online') {
                    SetValue($this->GetIDForIdent('TasmotaLED_DeviceStatus'), true);
                } else {
                    SetValue($this->GetIDForIdent('TasmotaLED_DeviceStatus'), false);
                }
            }
            switch ($Buffer->TOPIC) {
            case 'stat/' . $this->ReadPropertyString('Topic') . '/RESULT':
                if ($this->ReadPropertyBoolean('Power1Deactivate') == false) {
                    if (property_exists($MSG, 'POWER')) {
                        $this->RegisterVariableBoolean('TasmotaLED_POWER', 'POWER', '~Switch');
                        $this->EnableAction('TasmotaLED_POWER');
                        $this->SendDebug('Receive Result: POWER', $MSG->POWER, 0);
                        switch ($MSG->POWER) {
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
                        if (property_exists($MSG, 'POWER' . $i)) {
                            $this->SendDebug('Receive Result: POWER' . $i, $MSG->{'POWER' . $i}, 0);
                            $this->RegisterVariableBoolean('TasmotaLED_POWER' . $i, 'POWER' . $i, '~Switch');
                            $this->EnableAction('TasmotaLED_POWER' . $i);
                            $this->SendDebug('Receive Result: POWER' . $i, $MSG->{'POWER' . $i}, 0);
                            switch ($MSG->{'POWER' . $i}) {
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

                if (property_exists($MSG, 'PowerOnState')) {
                    $this->SendDebug('Receive Result: PowerOnState', $MSG->PowerOnState, 0);
                    $this->setPowerOnStateInForm($MSG->PowerOnState);
                }
                if (property_exists($MSG, 'Pixels')) {
                    $this->SendDebug('Receive Result: Pixels', $MSG->Pixels, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Pixels'), $MSG->Pixels);
                }
                if (property_exists($MSG, 'Speed')) {
                    $this->SendDebug('Receive Result: Speed', $MSG->Speed, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Speed'), $MSG->Speed);
                }
                if (property_exists($MSG, 'Scheme')) {
                    $this->SendDebug('Receive Result: Scheme', $MSG->Scheme, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Scheme'), $MSG->Scheme);
                }
                if (property_exists($MSG, 'Dimmer')) {
                    $this->SendDebug('Receive Result: Dimmer', $MSG->Dimmer, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_Dimmer'), $MSG->Dimmer);
                }
                if (property_exists($MSG, 'Color')) {
                    $this->SendDebug('Receive Result: Color', $MSG->Color, 0);
                    $rgb = explode(',', $MSG->Color);
                    $color = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                    SetValue($this->GetIDForIdent('TasmotaLED_Color'), hexdec(($color)));
                }
                if (property_exists($MSG, 'Fade')) {
                    $this->SendDebug('Receive Result: Fade', $MSG->Fade, 0);
                    if ($MSG->Fade == 'ON') {
                        SetValue($this->GetIDForIdent('TasmotaLED_Fade'), true);
                    } else {
                        SetValue($this->GetIDForIdent('TasmotaLED_Fade'), false);
                    }
                }
                break;
            case 'tele/' . $this->ReadPropertyString('Topic') . '/SENSOR':
                $myBuffer = json_decode($Buffer->MSG, true);
                $this->traverseArray($myBuffer, $myBuffer);
                break;
            case 'tele/' . $this->ReadPropertyString('Topic') . '/STATE':
                if (property_exists($MSG, 'Wifi')) {
                    $this->SendDebug('Receive Sate: Wifi RSSI', $MSG->Wifi->RSSI, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_RSSI'), $MSG->Wifi->RSSI);
                }

            }
        }
    }

    public function setLED(int $LED, string $color)
    {
        $command = 'Led' . $LED;
        $msg = $color;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setLED', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setScheme(int $schemeID)
    {
        $command = 'Scheme';
        $msg = $schemeID;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setScheme', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setPixel(int $count)
    {
        $command = 'Pixels';
        $msg = $count;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setPixel', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setDimmer(int $value)
    {
        $command = 'Dimmer';
        $msg = $value;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setDimmer', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setColorHex(string $color)
    {
        $command = 'Color';
        $msg = $color;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setColorHex', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
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
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setFade', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setSpeed(int $value)
    {
        $command = 'Speed';
        $msg = $value;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setSpeed', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
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
        $this->setFade(intval($Value));
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
        $this->RegisterProfileIntegerEx('TasmotaLED.Scheme', 'Shuffle', '', '', array(
                                        array(0, 'Default',  '', -1),
                                        array(1, 'Wake up',  '', -1),
                                        array(2, 'RGB Cycle', '', -1),
                                        array(3, 'RBG Cycle', '', -1),
                                        array(4, 'Random cycle', '', -1),
                                        array(5, 'Clock', '', -1),
                                        array(6, 'Incandescent pattern', '', -1),
                                        array(7, 'RGB Pattern', '', -1),
                                        array(8, 'Christmas', '', -1),
                                        array(9, 'Hanukkah', '', -1),
                                        array(10, 'Kwanzaa', '', -1),
                                        array(11, 'Rainbow', '', -1),
                                        array(12, 'Fire', '', -1)
                                    ));
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('TasmotaLED.DeviceStatus', 'Network', '', '', array(
            array(false, 'Offline',  '', 0xFF0000),
            array(true, 'Online',  '', 0x00FF00)
        ));
    }
}
