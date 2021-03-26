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
        $this->RegisterPropertyInteger('GatewayMode', 0);
        $this->RegisterPropertyBoolean('MessageRetain', false);
        $this->RegisterVariableFloat('Tasmota_RSSI', 'RSSI');
        $this->RegisterVariableFloat('Tasmota_Signal', 'Signal');
        $this->RegisterVariableBoolean('Tasmota_DeviceStatus', 'Status', 'Tasmota.DeviceStatus');
        //Settings
        $this->RegisterPropertyBoolean('SystemVariables', false);
        $this->RegisterPropertyBoolean('Info2', false);
        $this->RegisterPropertyBoolean('Power1Deactivate', false);
        $this->RegisterPropertyBoolean('Fan', false);
        $this->RegisterPropertyBoolean('AutomatedSensorValues', true);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->BufferResponse = '';

        //Setze Filter fÃ¼r ReceiveData
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->setPowerOnState($this->ReadPropertyInteger('PowerOnState'));
        }

        if ($this->ReadPropertyBoolean('Fan')) {
            $this->RegisterProfileInteger('Tasmota.FanSpeed', 'Speedo', '', '', 0, 3, 1);
            $this->RegisterVariableInteger('Tasmota_FanSpeed', $this->Translate('Speed'), 'Tasmota.FanSpeed', 0);
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

            //PowerOnState Vairablen setzen
            if (fnmatch('*PowerOnState*', $Buffer->Payload)) {
                $this->SendDebug('PowerOnState Topic', $Buffer->Topic, 0);
                $this->SendDebug('PowerOnState Payload', $Buffer->Payload, 0);
                $Payload = json_decode($Buffer->Payload);
                if (is_object($Payload)) {
                    if (property_exists($Payload, 'PowerOnState')) {
                        $this->setPowerOnStateInForm($Payload->PowerOnState);
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
                    $this->SendDebug('State Payload', $Buffer->Payload, 0);
                    $this->SendDebug('State Wifi', $myBuffer->Wifi->RSSI, 0);

                    if ($this->ReadPropertyBoolean('SystemVariables')) {
                        $this->getSystemVariables($myBuffer);
                    }
                    SetValue($this->GetIDForIdent('Tasmota_RSSI'), $myBuffer->Wifi->RSSI);
                    if (property_exists($myBuffer->Wifi, 'Signal')) {
                        SetValue($this->GetIDForIdent('Tasmota_Signal'), $myBuffer->Wifi->Signal);
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
                    $this->SendDebug('Result Payload', $Buffer->Payload, 0);
                    $this->BufferResponse = $Buffer->Payload;
                    $Payload = json_decode($Buffer->Payload);

                    if (fnmatch('*MCP230XX_INT*', $Buffer->Payload)) {
                        $this->SendDebug('Sensor Payload', $Buffer->Payload, 0);
                        $this->SendDebug('Sensor Topic', $Buffer->Topic, 0);
                        for ($i = 0; $i <= 15; $i++) {
                            if (property_exists($Payload->MCP230XX_INT, 'D' . $i)) {
                                $this->RegisterVariableBoolean('Tasmota_MCP230XX_INT_D' . $i, 'MCP230XX_INT D' . $i, '', 0);
                                SetValue($this->GetIDForIdent('Tasmota_MCP230XX_INT_D' . $i), $Payload->MCP230XX_INT->{'D' . $i});

                                //MS
                                $this->RegisterVariableInteger('Tasmota_MCP230XX_INT_D' . $i . '_MS', 'MCP230XX_INT D' . $i . ' MS', '', 0);
                                SetValue($this->GetIDForIdent('Tasmota_MCP230XX_INT_D' . $i . '_MS'), $Payload->MCP230XX_INT->MS);
                            }
                        }
                    }
                    if (fnmatch('*S29cmnd_D*', $Buffer->Payload)) {
                        $this->SendDebug('Sensor Payload', $Buffer->Payload, 0);
                        $this->SendDebug('Sensor Topic', $Buffer->Topic, 0);
                        for ($i = 0; $i <= 15; $i++) {
                            if (property_exists($Payload, 'S29cmnd_D' . $i)) {
                                if (property_exists($Payload->{'S29cmnd_D' . $i}, 'STATE')) {
                                    $this->RegisterVariableBoolean('Tasmota_S29cmnd_D' . $i, 'S29cmnd D' . $i, '', 0);
                                    $this->EnableAction('Tasmota_S29cmnd_D' . $i);
                                    switch ($Payload->{'S29cmnd_D' . $i}->STATE) {
                                    case 'ON':
                                        $value = true;
                                        break;
                                    case 'OFF':
                                        $value = false;
                                        break;
                                }
                                    SetValue($this->GetIDForIdent('Tasmota_S29cmnd_D' . $i), $value);
                                }
                            }
                        }
                    }
                    if (fnmatch('*PCA9685*', $Buffer->Payload)) {
                        if (property_exists($Payload, 'PCA9685')) {
                            $this->RegisterProfileInteger('Tasmota.PCA9685', 'Intensity', '', '%', 0, 4095, 1);
                            $this->RegisterVariableInteger('Tasmota_PCA9685_PWM' . $Payload->PCA9685->PIN, 'PWM' . $Payload->PCA9685->PIN, 'Tasmota.PCA9685', 0);
                            $this->EnableAction('Tasmota_PCA9685_PWM' . $Payload->PCA9685->PIN);
                            $this->SetValue('Tasmota_PCA9685_PWM' . $Payload->PCA9685->PIN, $Payload->PCA9685->PWM);
                        }
                    }
                }
                if (fnmatch('*Switch*', $Buffer->Payload)) {
                    $this->SendDebug('Switch Payload', $Buffer->Payload, 0);
                    $this->SendDebug('Switch Topic', $Buffer->Topic, 0);
                    $Payload = json_decode($Buffer->Payload);
                    for ($i = 0; $i <= 15; $i++) {
                        if (is_object($Payload)) {
                            if (property_exists($Payload, 'Switch' . $i)) {
                                if (property_exists($Payload->{'Switch' . $i}, 'Action')) {
                                    $this->RegisterVariableString('Tasmota_Switch' . $i, 'Switch' . $i, '', 0);
                                    SetValue($this->GetIDForIdent('Tasmota_Switch' . $i), $Payload->{'Switch' . $i}->{'Action'});
                                }
                            }
                        }
                    }
                }
                if (fnmatch('*Shutter*', $Buffer->Payload)) {
                    $this->SendDebug('Shutter Payload', $Buffer->Payload, 0);
                    $this->SendDebug('Shutter Topic', $Buffer->Topic, 0);
                    $Payload = json_decode($Buffer->Payload);
                    for ($i = 0; $i <= 5; $i++) {
                        if (property_exists($Payload, 'Shutter' . $i)) {
                            if (property_exists($Payload->{'Shutter' . $i}, 'Position')) {
                                $this->RegisterVariableInteger('Tasmota_Shutter' . $i . '_Position', 'Shutter' . $i . '_Position', '', 0);
                                SetValue($this->GetIDForIdent('Tasmota_Shutter' . $i . '_Position'), $Payload->{'Shutter' . $i}->{'Position'});
                            }
                            if (property_exists($Payload->{'Shutter' . $i}, 'Direction')) {
                                $this->RegisterVariableInteger('Tasmota_Shutter' . $i . '_Direction', 'Shutter' . $i . '_Direction', '', 0);
                                SetValue($this->GetIDForIdent('Tasmota_Shutter' . $i . '_Direction'), $Payload->{'Shutter' . $i}->{'Direction'});
                            }
                            if (property_exists($Payload->{'Shutter' . $i}, 'Target')) {
                                $this->RegisterVariableInteger('Tasmota_Shutter' . $i . '_Target', 'Shutter' . $i . '_Target', '', 0);
                                SetValue($this->GetIDForIdent('Tasmota_Shutter' . $i . '_Target'), $Payload->{'Shutter' . $i}->{'Target'});
                            }
                        }
                    }
                }
                if (fnmatch('*Button*', $Buffer->Payload)) {
                    $this->SendDebug('Sensor Payload', $Buffer->Payload, 0);
                    $this->SendDebug('Sensor Topic', $Buffer->Topic, 0);
                    $Payload = json_decode($Buffer->Payload);
                    if (is_object($Payload)) {
                        for ($i = 0; $i <= 15; $i++) {
                            if (property_exists($Payload, 'Button' . $i)) {
                                if (property_exists($Payload->{'Button' . $i}, 'Action')) {
                                    $this->RegisterVariableString('Tasmota_Button' . $i, 'Button' . $i, '', 0);
                                    SetValue($this->GetIDForIdent('Tasmota_Button' . $i), $Payload->{'Button' . $i}->{'Action'});
                                }
                            }
                        }
                    }
                }
                if (fnmatch('*LWT', $Buffer->Topic)) {
                    $this->SendDebug('LWT Payload', $Buffer->Payload, 0);
                    if (strtolower($Buffer->Payload) == 'online') {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), true);
                    } else {
                        SetValue($this->GetIDForIdent('Tasmota_DeviceStatus'), false);
                    }
                }
                //POW Variablen
                if (fnmatch('*ENERGY*', $Buffer->Payload)) {
                    $myBuffer = json_decode($Buffer->Payload);
                    if (is_object($myBuffer)) {
                        if (property_exists($myBuffer, 'ENERGY')) {
                            $this->SendDebug('Energy Payload', $Buffer->Payload, 0);
                            $this->SendDebug('Energy Topic', $Buffer->Topic, 0);

                            if (property_exists($myBuffer->ENERGY, 'Power')) {
                                if (!is_array($myBuffer->ENERGY->Power)) {
                                    $this->RegisterVariableFloat('Tasmota_POWPower', $this->Translate('Power'), '~Watt.3680');
                                    SetValue($this->GetIDForIdent('Tasmota_POWPower'), $myBuffer->ENERGY->Power);
                                } else {
                                    foreach ($myBuffer->ENERGY->Power as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWPower' . $key, $this->Translate('Power') . ' ' . strval(intval($key) + 1), '~Watt.3680');
                                        SetValue($this->GetIDForIdent('Tasmota_POWPower' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'Total')) {
                                if (!is_array($myBuffer->ENERGY->Total)) {
                                    $this->RegisterVariableFloat('Tasmota_POWTotal', $this->Translate('Total'), '~Electricity');
                                    SetValue($this->GetIDForIdent('Tasmota_POWTotal'), $myBuffer->ENERGY->Total);
                                } else {
                                    foreach ($myBuffer->ENERGY->Total as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWTotal' . $key, $this->Translate('Total') . ' ' . strval(intval($key) + 1), '~Electricity');
                                        SetValue($this->GetIDForIdent('Tasmota_POWTotal' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'Today')) {
                                $this->RegisterVariableFloat('Tasmota_POWToday', $this->Translate('Today'), '~Electricity');
                                SetValue($this->GetIDForIdent('Tasmota_POWToday'), $myBuffer->ENERGY->Today);
                            }

                            if (property_exists($myBuffer->ENERGY, 'Yesterday')) {
                                $this->RegisterVariableFloat('Tasmota_POWYesterday', $this->Translate('Yesterday'), '~Electricity');
                                SetValue($this->GetIDForIdent('Tasmota_POWYesterday'), $myBuffer->ENERGY->Yesterday);
                            }

                            if (property_exists($myBuffer->ENERGY, 'Current')) {
                                if (!is_array($myBuffer->ENERGY->Current)) {
                                    $this->RegisterVariableFloat('Tasmota_POWCurrent', $this->Translate('Current'), '');
                                    SetValue($this->GetIDForIdent('Tasmota_POWCurrent'), $myBuffer->ENERGY->Current);
                                } else {
                                    foreach ($myBuffer->ENERGY->Current as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWCurrent' . $key, $this->Translate('Current') . ' ' . strval(intval($key) + 1), '~Ampere');
                                        SetValue($this->GetIDForIdent('Tasmota_POWCurrent' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'Voltage')) {
                                $this->RegisterVariableFloat('Tasmota_POWVoltage', $this->Translate('Voltage'), '~Volt');
                                SetValue($this->GetIDForIdent('Tasmota_POWVoltage'), $myBuffer->ENERGY->Voltage);
                            }

                            if (property_exists($myBuffer->ENERGY, 'Factor')) {
                                if (!is_array($myBuffer->ENERGY->Factor)) {
                                    $this->RegisterVariableFloat('Tasmota_POWFactor', $this->Translate('Factor'));
                                    SetValue($this->GetIDForIdent('Tasmota_POWFactor'), $myBuffer->ENERGY->Factor);
                                } else {
                                    foreach ($myBuffer->ENERGY->Factor as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWFactor' . $key, $this->Translate('Factor') . ' ' . strval(intval($key) + 1), '');
                                        SetValue($this->GetIDForIdent('Tasmota_POWFactor' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'ApparentPower')) {
                                if (!is_array($myBuffer->ENERGY->ApparentPower)) {
                                    $this->RegisterVariableFloat('Tasmota_POWApparentPower', $this->Translate('ApparentPower'), 'Tasmota.ApparentPower');
                                    SetValue($this->GetIDForIdent('Tasmota_POWApparentPower'), $myBuffer->ENERGY->ApparentPower);
                                } else {
                                    foreach ($myBuffer->ENERGY->ApparentPower as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWApparentPower' . $key, $this->Translate('ApparentPower') . ' ' . strval(intval($key) + 1), 'Tasmota.ApparentPower');
                                        SetValue($this->GetIDForIdent('Tasmota_POWApparentPower' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'ReactivePower')) {
                                if (!is_array($myBuffer->ENERGY->ReactivePower)) {
                                    $this->RegisterVariableFloat('Tasmota_POWReactivePower', $this->Translate('ReactivePower'), 'Tasmota.ReactivePower');
                                    SetValue($this->GetIDForIdent('Tasmota_POWReactivePower'), $myBuffer->ENERGY->ReactivePower);
                                } else {
                                    foreach ($myBuffer->ENERGY->ReactivePower as $key=> $value) {
                                        $this->RegisterVariableFloat('Tasmota_POWReactivePower' . $key, $this->Translate('ReactivePower') . ' ' . strval(intval($key) + 1), 'Tasmota.ReactivePower');
                                        SetValue($this->GetIDForIdent('Tasmota_POWReactivePower' . $key), $value);
                                    }
                                }
                            }

                            if (property_exists($myBuffer->ENERGY, 'PhaseAngle')) {
                                $this->RegisterVariableFloat('Tasmota_PhaseAngle', $this->Translate('PhaseAngle'));
                                SetValue($this->GetIDForIdent('Tasmota_PhaseAngle'), $myBuffer->ENERGY->PhaseAngle);
                            }

                            if (property_exists($myBuffer->ENERGY, 'ImportActivePower')) {
                                $this->RegisterVariableFloat('Tasmota_ImportActivePower', $this->Translate('ImportActivePower'), '~Electricity');
                                SetValue($this->GetIDForIdent('Tasmota_ImportActivePower'), $myBuffer->ENERGY->ImportActivePower);
                            }

                            if (property_exists($myBuffer->ENERGY, 'ExportActivePower')) {
                                $this->RegisterVariableFloat('Tasmota_ExportActivePower', $this->Translate('ExportActivePower'), '~Electricity');
                                SetValue($this->GetIDForIdent('Tasmota_ExportActivePower'), $myBuffer->ENERGY->ExportActivePower);
                            }

                            if (property_exists($myBuffer->ENERGY, 'ImportReactivePower')) {
                                $this->RegisterVariableFloat('Tasmota_ImportReactivePower', $this->Translate('ImportReactivePower'), 'Tasmota.ReactivePower_kvarh');
                                SetValue($this->GetIDForIdent('Tasmota_ImportReactivePower'), $myBuffer->ENERGY->ImportReactivePower);
                            }

                            if (property_exists($myBuffer->ENERGY, 'ExportReactivePower')) {
                                $this->RegisterVariableFloat('Tasmota_ExportReactivePower', $this->Translate('ExportReactivePower'), 'Tasmota.ReactivePower_kvarh');
                                SetValue($this->GetIDForIdent('Tasmota_ExportReactivePower'), $myBuffer->ENERGY->ExportReactivePower);
                            }

                            if (property_exists($myBuffer->ENERGY, 'TotalReactivePower')) {
                                $this->RegisterVariableFloat('Tasmota_TotalReactivePower', $this->Translate('TotalReactivePower'), 'Tasmota.ReactivePower_kvarh');
                                SetValue($this->GetIDForIdent('Tasmota_TotalReactivePower'), $myBuffer->ENERGY->TotalReactivePower);
                            }
                            if (property_exists($myBuffer->ENERGY, 'Frequency')) {
                                $this->RegisterVariableFloat('Tasmota_Frequency', $this->Translate('Frequency'), '~Hertz');
                                SetValue($this->GetIDForIdent('Tasmota_Frequency'), $myBuffer->ENERGY->Frequency);
                            }
                        }
                        return;
                    }
                }
                //Sensor Variablen checken
                if ($this->ReadPropertyBoolean('AutomatedSensorValues')) {
                    if (fnmatch('*SENSOR', $Buffer->Topic)) {
                        $this->SendDebug('Sensor Payload', $Buffer->Payload, 0);
                        $this->SendDebug('Sensor Topic', $Buffer->Topic, 0);
                        $myBuffer = json_decode($Buffer->Payload, true);

                        $Payload = json_decode($Buffer->Payload);
                        if (property_exists($Payload, 'IBEACON')) {
                            $iBeacon = $Payload->IBEACON;
                            if (!property_exists($iBeacon, 'NAME')) {
                                if (property_exists($iBeacon, 'MAC')) {
                                    $iBeaconName = $iBeacon->MAC;
                                }
                            } else {
                                $iBeaconName = $iBeacon->NAME;
                            }
                            if (property_exists($iBeacon, 'MAC')) {
                                $this->RegisterVariableString('Tasmota_iBeaconMac_' . $iBeacon->MAC, 'iBeacon ' . $iBeaconName . ' MAC', '', 0);
                                $this->SetValue('Tasmota_iBeaconMac_' . $iBeacon->MAC, $iBeacon->MAC);
                            }
                            if (property_exists($iBeacon, 'RSSI')) {
                                $this->RegisterVariableInteger('Tasmota_iBeaconRSSI_' . $iBeacon->MAC, 'iBeacon ' . $iBeaconName . ' RSSI', '', 0);
                                $this->SetValue('Tasmota_iBeaconRSSI_' . $iBeacon->MAC, $iBeacon->RSSI);
                            }
                            if (property_exists($iBeacon, 'STATE')) {
                                $this->RegisterVariableString('Tasmota_iBeaconState_' . $iBeacon->MAC, 'iBeacon ' . $iBeaconName . ' ' . $this->Translate('State'), '', 0);
                                $this->SetValue('Tasmota_iBeaconState_' . $iBeacon->MAC, $iBeacon->STATE);
                            }
                            if (property_exists($iBeacon, 'PERSEC')) {
                                $this->RegisterVariableInteger('Tasmota_iBeaconPersec_' . $iBeacon->MAC, 'iBeacon ' . $iBeaconName . ' PERSEC', '', 0);
                                $this->SetValue('Tasmota_iBeaconPersec_' . $iBeacon->MAC, $iBeacon->PERSEC);
                            }
                            return;
                        }
                        $this->traverseArray($myBuffer, $myBuffer);
                    }
                }
            }
            //FanSpeed
            if (fnmatch('*FanSpeed*', $Buffer->Payload)) {
                $myBuffer = json_decode($Buffer->Payload);
                if (property_exists($myBuffer, 'FanSpeed')) {
                    $this->SendDebug('FanSpeed Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tasmota_FanSpeed'), $myBuffer->FanSpeed);
                }
            }
            //IrReceived
            if (fnmatch('*IrReceived*', $Buffer->Payload)) {
                $myBuffer = json_decode($Buffer->Payload);
                $this->SendDebug('IrReceived Payload', $Buffer->Payload, 0);
                if (property_exists($myBuffer->IrReceived, 'Protocol')) {
                    $this->RegisterVariableString('Tasmota_IRProtocol', $this->Translate('IR Protocol'), '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRProtocol'), $myBuffer->IrReceived->Protocol);
                }
                if (property_exists($myBuffer->IrReceived, 'Bits')) {
                    $this->RegisterVariableString('Tasmota_IRBits', $this->Translate('IR Bits'), '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRBits'), $myBuffer->IrReceived->Bits);
                }
                if (property_exists($myBuffer->IrReceived, 'Data')) {
                    $this->RegisterVariableString('Tasmota_IRData', $this->Translate('IR Data'), '', 0);
                    SetValue($this->GetIDForIdent('Tasmota_IRData'), $myBuffer->IrReceived->Data);
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

        if (fnmatch('Tasmota_PCA9685_PWM*', $Ident)) {
            $pin = substr($Ident, 19);
            $command = 'driver15';
            $msg = 'pwm,' . $pin . ',' . $Value;
            $this->SendDebug(__FUNCTION__ . ' MQTT MSG', $msg, 0);
            $this->MQTTCommand($command, $msg);
            return true;
        }

        if (fnmatch('S29cmnd_D*', $Ident)) {
            $pin = substr($Ident, 9);
            $command = 'sensor29';
            switch ($Value) {
                case true:
                    $msg = $pin . ',on';
                    break;
                case false:
                    $msg = $pin . ',off';
                    break;
            }
            $this->SendDebug(__FUNCTION__ . ' MQTT MSG', $msg, 0);
            $this->MQTTCommand($command, $msg);
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
        $this->MQTTCommand($command, $msg);
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
