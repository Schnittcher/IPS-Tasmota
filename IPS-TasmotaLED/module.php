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
        $this->RegisterPropertyString('On', '1');
        $this->RegisterPropertyString('Off', '0');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyInteger('PowerOnState', 3);
        //$this->RegisterPropertyString("DeviceLanguage","en");
        $this->RegisterPropertyBoolean('Power1Deactivate', false);

        $this->createVariabenProfiles();
        $this->RegisterVariableBoolean('TasmotaLED_Power', 'Power', 'Switch', 0);
        $this->RegisterVariableBoolean('TasmotaLED_Fade', 'Fade', 'Switch', 1);
        $this->RegisterVariableInteger('TasmotaLED_Color', 'Color', 'HexColor', 2);
        $this->RegisterVariableInteger('TasmotaLED_Dimmer', 'Dimmer', 'Intensity.100', 3);
        $this->RegisterVariableInteger('TasmotaLED_Scheme', 'Scheme', 'TasmotaLED.Scheme', 4);
        $this->RegisterVariableInteger('TasmotaLED_Speed', 'Speed', 'TasmotaLED.Speed', 5);
        $this->RegisterVariableInteger('TasmotaLED_Pixels', 'Pixels', '', 6);
        $this->RegisterVariableInteger('TasmotaLED_RSSI', 'RSSI', 'TasmotaLED.RSSI', 7);
        $this->EnableAction('TasmotaLED_Power');
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
        //Setze Filter fÃ¼r ReceiveData
        $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));
        $topic = $this->ReadPropertyString('Topic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        if (!empty($this->ReadPropertyString('Topic'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);

            // Buffer decodieren und in eine Variable schreiben
            $Buffer = json_decode($data->Buffer);
            $MSG = json_decode($Buffer->MSG);
            $this->SendDebug('Topic', $Buffer->TOPIC, 0);
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
                    $this->RegisterVariableBoolean('TasmotaLED_' . $power[$lastKey], $power[$lastKey], '~Switch');
                    $this->EnableAction('TasmotaLED_' . $power[$lastKey]);
                    switch ($Buffer->MSG) {
                        case $this->ReadPropertyString('Off'):
                            SetValue($this->GetIDForIdent('TasmotaLED_' . $power[$lastKey]), 0);
                            break;
                        case $this->ReadPropertyString('On'):
                            SetValue($this->GetIDForIdent('TasmotaLED_' . $power[$lastKey]), 1);
                            break;
                    }
                }
            }
            switch ($Buffer->TOPIC) {
            case 'stat/' . $this->ReadPropertyString('Topic') . '/RESULT':
                /**                if (property_exists($MSG, 'POWER')) {
                 * $this->SendDebug('Receive Result: Power ', $MSG->POWER, 0);
                 * switch ($MSG->POWER) {
                 * case $this->ReadPropertyString('On'):
                 * SetValue($this->GetIDForIdent('TasmotaLED_Power'), true);
                 * break;
                 * case $this->ReadPropertyString('Off'):
                 * SetValue($this->GetIDForIdent('TasmotaLED_Power'), false);
                 * break;
                 * }.
                 * } **/
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
                    SetValue($this->GetIDForIdent('TasmotaLED_Color'), hexdec(($MSG->Color)));
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
            case 'tele/' . $this->ReadPropertyString('Topic') . '/STATE':
                if (property_exists($MSG, 'Wifi')) {
                    $this->SendDebug('Receive Sate: Wifi RSSI', $MSG->Wifi->RSSI, 0);
                    SetValue($this->GetIDForIdent('TasmotaLED_RSSI'), $MSG->Wifi->RSSI);
                }

        }

            /* if (fnmatch("*".translate::PowerOnState."*", $Buffer->MSG)) {
                $this->SendDebug("PowerOnState Topic", $Buffer->TOPIC,0);
                $this->SendDebug("PowerOnState MSG", $Buffer->MSG,0);
              $MSG = json_decode($Buffer->MSG);
              $this->setPowerOnStateInForm($MSG);
            }
            **/
       /**if (fnmatch("*".translate::Pixels."*", $Buffer->MSG)) {
         $this->SendDebug("Pixels Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Pixels MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Pixels"), $MSG->{translate::Pixels});
       }
       if (fnmatch("*".translate::POWER."*", $Buffer->MSG)) {
         $this->SendDebug("Power Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Power MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         switch ($MSG->{translate::POWER}) {
           case $this->ReadPropertyString("On"):
           SetValue($this->GetIDForIdent("TasmotaLED_Power"), true);
           break;
           case $this->ReadPropertyString("Off"):
           SetValue($this->GetIDForIdent("TasmotaLED_Power"), false);
           break;
         }
       }
       if (fnmatch("*".translate::Speed."*", $Buffer->MSG)) {
         $this->SendDebug("Speed Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Speed MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Speed"), $MSG->{translate::Speed});
       }
       if (fnmatch("*".translate::Scheme."*", $Buffer->MSG)) {
         $this->SendDebug("Scheme Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Scheme MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Scheme"), $MSG->{translate::Scheme});
       }
       if (fnmatch("*".translate::Dimmer."*", $Buffer->MSG)) {
         $this->SendDebug("Dimmer Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Dimmer MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Dimmer"), $MSG->{translate::Dimmer});
       }
       if (fnmatch("*".translate::Color."*", $Buffer->MSG)) {
         $this->SendDebug("Color Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Color MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Color"), hexdec(($MSG->{translate::Color})));
       }
       if (fnmatch("*".translate::Fade."*", $Buffer->MSG)) {
         $this->SendDebug("Fade Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Fade MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         if (property_exists($MSG,"Fade")) {
             if ($MSG->{translate::Fade} == "ON") {
               SetValue($this->GetIDForIdent("TasmotaLED_Fade"), true);
             } else {
               SetValue($this->GetIDForIdent("TasmotaLED_Fade"), false);
             }
           }
        }

       if (fnmatch("*".translate::STATE, $Buffer->TOPIC)) {
         $myBuffer = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_RSSI"), $myBuffer->{translate::Wifi}->RSSI);
       }**/
        }
    }

    public function setLED(int $LED, string $color)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        $command = 'Led' . $LED;
        $msg = $color;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setLED', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setScheme(int $schemeID)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        $command = 'Scheme';
        $msg = $schemeID;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setScheme', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setPixel(int $count)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        $command = 'Pixels';
        $msg = $count;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setPixel', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setDimmer(int $value)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        $command = 'Dimmer';
        $msg = $value;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setDimmer', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setColorHex(string $color)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
        $command = 'Color';
        $msg = $color;
        $BufferJSON = $this->MQTTCommand($command, $msg);
        $this->SendDebug('setColorHex', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
    }

    public function setFade(bool $value)
    {
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
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
        //$this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
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
 /*     case 'TasmotaLED_Power':
        if (strlen($Ident) != 16) {
            $power = substr($Ident, 16);
        } else {
            $power = 0;
        }
        $result = $this->setPower($power, $Value);
        break; **/
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
        $this->setColorHex('#' . dechex($Value));
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
    }
}
