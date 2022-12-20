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

        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('On', 'ON');
        $this->RegisterPropertyString('Off', 'OFF');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterPropertyBoolean('SystemVariables', false);
        $this->RegisterPropertyBoolean('Info2', false);
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        $this->RegisterPropertyBoolean('HSBColor', false);
        $this->RegisterPropertyBoolean('White', false);
        $this->RegisterPropertyBoolean('CT', false);
        $this->RegisterPropertyBoolean('Sensoren', true);

        $this->createVariabenProfiles();
        $this->RegisterVariableInteger('Tasmota_PowerOnState', $this->Translate('PowerOnState'), 'Tasmota.PowerOnState', 0);
        $this->EnableAction('Tasmota_PowerOnState');
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
        //Never delete this line!
        parent::ApplyChanges();
        $this->BufferResponse = '';
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $this->MaintainVariable('TasmotaLED_White', $this->Translate('White'), 1, '~Intensity.100', 0, $this->ReadPropertyBoolean('White') == true);
        if ($this->ReadPropertyBoolean('White')) {
            $this->EnableAction('TasmotaLED_White');
        }
        $this->MaintainVariable('TasmotaLED_CT', $this->Translate('Color Temperature'), 1, 'TasmotaLED.CT', 0, $this->ReadPropertyBoolean('CT') == true);
        if ($this->ReadPropertyBoolean('CT')) {
            $this->EnableAction('TasmotaLED_CT');
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
            $Buffer = json_decode($JSONString);

            //Für MQTT Fix in IPS Version 6.3
            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer->Payload = utf8_decode($Buffer->Payload);
            }

            $Payload = json_decode($Buffer->Payload);
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            if (fnmatch('*LWT', $Buffer->Topic)) {
                $this->SendDebug('State Payload', $Buffer->Payload, 0);
                if (strtolower($Buffer->Payload) == 'online') {
                    $this->SetValue('TasmotaLED_DeviceStatus', true);
                } else {
                    $this->SetValue('TasmotaLED_DeviceStatus', false);
                }
            }
            //Info2
            if (fnmatch('*INFO2', $Buffer->Topic)) {
                $myBuffer = json_decode($Buffer->Payload);
                $this->SendDebug('Info2 Payload', $Buffer->Payload, 0);

                if ($this->ReadPropertyBoolean('Info2')) {
                    $this->getInfo2Variables($myBuffer);
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
                                $this->SetValue('TasmotaLED_POWER', 0);
                                break;
                            case $this->ReadPropertyString('On'):
                                $this->SetValue('TasmotaLED_POWER', 1);
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
                                    $this->SetValue('TasmotaLED_POWER' . $i, 0);
                                    break;
                                case $this->ReadPropertyString('On'):
                                    $this->SetValue('TasmotaLED_POWER' . $i, 1);
                                    break;
                            }
                        }
                    }
                }

                if (property_exists($Payload, 'PowerOnState')) {
                    $this->SendDebug('Receive Result: PowerOnState', $Payload->PowerOnState, 0);
                    $this->SetValue('Tasmota_PowerOnState', $Payload->PowerOnState);
                }
                if (property_exists($Payload, 'Pixels')) {
                    $this->SendDebug('Receive Result: Pixels', $Payload->Pixels, 0);
                    $this->SetValue('TasmotaLED_Pixels', $Payload->Pixels);
                }
                if (property_exists($Payload, 'Speed')) {
                    $this->SendDebug('Receive Result: Speed', $Payload->Speed, 0);
                    $this->SetValue('TasmotaLED_Speed', $Payload->Speed);
                }
                if (property_exists($Payload, 'Scheme')) {
                    $this->SendDebug('Receive Result: Scheme', $Payload->Scheme, 0);
                    $this->SetValue('TasmotaLED_Scheme', $Payload->Scheme);
                }
                if (property_exists($Payload, 'Dimmer')) {
                    $this->SendDebug('Receive Result: Dimmer', $Payload->Dimmer, 0);
                    $this->SetValue('TasmotaLED_Dimmer', $Payload->Dimmer);
                }
                if (property_exists($Payload, 'CT')) {
                    $this->SendDebug('Receive Result: CT', $Payload->CT, 0);
                    if ($this->GetIDForIdent('TasmotaLED_CT')) {
                        $this->SetValue('TasmotaLED_CT', $Payload->CT);
                    }
                }
                if (property_exists($Payload, 'White')) {
                    $this->SendDebug('Receive Result: White', $Payload->White, 0);
                    if ($this->GetIDForIdent('TasmotaLED_White')) {
                        $this->SetValue('TasmotaLED_White', $Payload->White);
                    }
                }
                if (property_exists($Payload, 'HSBColor')) {
                    if ($this->ReadPropertyBoolean('HSBColor')) {
                        $this->SendDebug('HSBColor', $Payload->HSBColor, 0);
                        $HSBColor = explode(',', $Payload->HSBColor);
                        $Color = $this->hsv2rgb($HSBColor[0], $HSBColor[1], $HSBColor[2]);
                        $color = ltrim($Color['hex'], '#');
                        $this->SetValue('TasmotaLED_Color', hexdec(($color)));
                    }
                }
                if (property_exists($Payload, 'Color')) {
                    if (!$this->ReadPropertyBoolean('HSBColor')) {
                        $this->SendDebug('Color', $Payload->Color, 0);
                        if (strlen($Payload->Color) == 6) {
                            $rgb = explode(',', $Payload->Color);
                            $color = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                            $color = ltrim($color, '#');
                            $this->SetValue('TasmotaLED_Color', hexdec(($color)));
                        }
                    }
                }
                if (property_exists($Payload, 'Fade')) {
                    $this->SendDebug('Receive Result: Fade', $Payload->Fade, 0);
                    if (strtoupper($Payload->Fade) == 'ON') {
                        $this->SetValue('TasmotaLED_Fade', true);
                    } else {
                        $this->SetValue('TasmotaLED_Fade', false);
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
                    $this->SetValue('TasmotaLED_RSSI', $Payload->Wifi->RSSI);
                }

            }
        }
    }

    public function setLED(int $LED, string $color)
    {
        $command = 'Led' . $LED;
        $msg = $color;
        $this->MQTTCommand($command, $msg);
    }

    public function setScheme(int $schemeID)
    {
        $command = 'Scheme';
        $msg = strval($schemeID);
        $this->MQTTCommand($command, $msg);
    }

    public function setPixel(int $count)
    {
        $command = 'Pixels';
        $msg = strval($count);
        $this->MQTTCommand($command, $msg);
    }

    public function setDimmer(int $value)
    {
        $command = 'Dimmer';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
    }

    public function setCT(int $value)
    {
        $command = 'CT';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
    }

    public function setWhite(int $value)
    {
        $command = 'White';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
    }

    public function setColorHex(string $color)
    {
        $command = 'Color';
        $msg = $color;
        $this->MQTTCommand($command, $msg);
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
        $this->MQTTCommand($command, $msg);
    }

    public function setSpeed(int $value)
    {
        $command = 'Speed';
        $msg = strval($value);
        $this->MQTTCommand($command, $msg);
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
            $this->setPower(intval($power), $Value);
        }
        switch ($Ident) {
      case 'Tasmota_PowerOnState':
        $this->setPowerOnState($Value);
        break;
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
      case 'TasmotaLED_White':
        $this->setWhite($Value);
        break;
    case 'TasmotaLED_CT':
        $this->setCT($Value);
        break;
      default:
        // code...
        break;
    }
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

        //Color Temperature
        $this->RegisterProfileInteger('TasmotaLED.CT', 'Intensity', '', '', 158, 500, 1);
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('TasmotaLED.DeviceStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);
    }
    private function hsv2rgb($hue, $sat, $val)
    {
        $rgb = [0, 0, 0];
        //calc rgb for 100% SV, go +1 for BR-range
        for ($i = 0; $i < 4; $i++) {
            if (abs($hue - $i * 120) < 120) {
                $distance = max(60, abs($hue - $i * 120));
                $rgb[$i % 3] = 1 - (($distance - 60) / 60);
            }
        }
        //desaturate by increasing lower levels
        $max = max($rgb);
        $factor = 255 * ($val / 100);
        for ($i = 0; $i < 3; $i++) {
            //use distance between 0 and max (1) and multiply with value
            $rgb[$i] = round(($rgb[$i] + ($max - $rgb[$i]) * (1 - $sat / 100)) * $factor);
        }
        $rgb['hex'] = sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
        return $rgb;
    }
}
