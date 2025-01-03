<?php

declare(strict_types=1);

if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }
}

class TasmotaService extends IPSModule
{
    public function restart()
    {
        $command = 'restart';
        $msg = strval(1);
        $retain = false;

        $this->MQTTCommand($command, $msg, $retain);
    }

    public function sendMQTTCommand(string $command, string $msg)
    {
        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = true;
        } else {
            $retain = false;
        }

        $this->MQTTCommand($command, $msg, $retain);
        //$this->SendDebug('sendMQTTCommand', $DataJSON, 0);
        $this->BufferResponse = '';
        //$this->SendDataToParent($DataJSON);
        $result = false;
        for ($x = 0; $x < 500; $x++) {
            if ($this->BufferResponse != '') {
                $this->SendDebug('sendMQTTCommand Response', $this->BufferResponse, 0);
                $result = $this->BufferResponse;
                break;
            }
            IPS_Sleep(10);
        }
        return $result;
    }

    public function setPowerOnState(int $value)
    {
        $command = 'PowerOnState';
        $msg = strval($value);

        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = true;
        } else {
            $retain = false;
        }
        $this->MQTTCommand($command, $msg, $retain);
    }

    public function setPower(int $power, bool $Value)
    {
        if ($power != 0) {
            $PowerIdent = 'Tasmota_POWER' . strval($power);
            $powerTopic = 'POWER' . strval($power);
        } else {
            $PowerIdent = 'Tasmota_POWER';
            $powerTopic = 'POWER';
        }
        $command = $powerTopic;
        $msg = $Value;
        if ($msg === false) {
            $msg = 'OFF';
        } elseif ($msg === true) {
            $msg = 'ON';
        }
        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = true;
        } else {
            $retain = false;
        }
        $this->MQTTCommand($command, $msg, $retain);
    }

    protected function MQTTCommand($command, $Payload, $retain = 0)
    {
        //$retain = false; // Solange der IPS MQTT Server noch kein Retain kann

        $FullTopic = explode('/', $this->ReadPropertyString('FullTopic'));
        $PrefixIndex = array_search('%prefix%', $FullTopic);
        $TopicIndex = array_search('%topic%', $FullTopic);

        $SetCommandArr = $FullTopic;
        $index = count($SetCommandArr);

        $SetCommandArr[$PrefixIndex] = 'cmnd';
        $SetCommandArr[$TopicIndex] = $this->ReadPropertyString('Topic');
        $SetCommandArr[$index] = $command;

        $Topic = implode('/', $SetCommandArr);

        $resultServer = true;

        $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Server['PacketType'] = 3;
        $Server['QualityOfService'] = 0;
        $Server['Retain'] = boolval($retain);
        $Server['Topic'] = $Topic;
        $Server['Payload'] = $Payload;
        $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'MQTT Server', $ServerJSON, 0);
        $resultServer = @$this->SendDataToParent($ServerJSON);

        if ($resultServer === false) {
            $last_error = error_get_last();
            echo $last_error['message'];
        }
    }

    protected function Debug($Meldungsname, $Daten, $Category)
    {
        if ($this->ReadPropertyBoolean($Category) == true) {
            $this->SendDebug($Meldungsname, $Daten, 0);
        }
    }

    protected function FilterFullTopicReceiveData()
    {
        $FullTopic = explode('/', $this->ReadPropertyString('FullTopic'));
        $PrefixIndex = array_search('%prefix%', $FullTopic);
        $TopicIndex = array_search('%topic%', $FullTopic);

        $SetCommandArr = $FullTopic;
        $SetCommandArr[$PrefixIndex] = '.*.';
        //unset($SetCommandArr[$PrefixIndex]);
        $SetCommandArr[$TopicIndex] = $this->ReadPropertyString('Topic');
        $topic = implode('\/', $SetCommandArr);

        return $topic . '/';
    }

    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 0) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    //Für Sensoren
    protected function find_parent($array, $needle, $parent = null)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $pass = $parent;
                if (is_string($key)) {
                    $pass = $key;
                }
                $found = $this->find_parent($value, $needle, $pass);
                if ($found !== false) {
                    return $found;
                }
            } elseif ($value === $needle) {
                return $parent;
            }
        }
        return false;
    }

    protected function getSensorData($Array)
    {
        foreach ($Array as $key=> $Sensor) {
            if (is_array($Sensor)) {
                $SensorKey = $key;
                if ($SensorKey == 'ENERGY') {
                    continue;
                }
                if ($SensorKey == 'Shutter1') {
                    continue;
                }
                foreach ($Sensor as $DataKey=> $SensorData) {
                    if ((is_int($SensorData) || is_float($SensorData)) && ($SensorKey != 'MCP230XX') && ($SensorKey != 'PCA9685')) {
                        //Ident darf nur Buchstaben und Zahlen enthalten
                        $SensorKey = str_replace('-', '_', $SensorKey);
                        $DataKey = str_replace('-', '_', $DataKey);
                        $DataKey = str_replace('.', '_', $DataKey);

                        switch ($DataKey) {
                            case 'Temperature':
                                if (array_key_exists('Id', $Sensor)) { //Für alte Tasmota Versionen
                                    if (substr($SensorKey, 0, -2) == 'DS18B20' || $SensorKey == 'DS18B20') {
                                        $variablenID = $this->RegisterVariableFloat($Sensor['Id'], $Sensor['Id'] . ' Temperatur', '~Temperature');
                                        $this->SetValue($Sensor['Id'], $SensorData);
                                    }
                                }
                                $variablenID = $this->RegisterVariableFloat('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorKey . ' Temperatur', '~Temperature');
                                $this->SetValue('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorData);
                                break;
                            case 'Humidity':
                                $variablenID = $this->RegisterVariableFloat('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorKey . ' Feuchte', '~Humidity.F');
                                $this->SetValue('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorData);
                                break;
                            default:
                                if (($SensorKey != 'ENERGY') || ($SensorKey != 'IBEACON')) {
                                    $variablenID = $this->RegisterVariableFloat('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorKey . ' ' . $DataKey);
                                    $this->SetValue('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorData);
                                }
                        }

                        if ($SensorKey == 'PN532') {
                            $variablenID = $this->RegisterVariableString('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorKey . '_' . $DataKey, '');
                            $this->SetValue('Tasmota_' . $SensorKey . '_' . $DataKey, $SensorData);
                        }
                        if ($SensorKey == 'MCP230XX') {
                            if (@$this->GetIDForIdent('Tasmota_MCP230XX_INT_' . $DataKey) != false) {
                                $this->SendDebug('MCP230XX', $DataKey, 0);
                                $this->SetValue('Tasmota_MCP230XX_INT_' . $DataKey, $SensorData);
                            }
                        }
                        if ($SensorKey == 'PCA9685') {
                            if (@$this->GetIDForIdent('Tasmota_PCA9685_' . $DataKey) != false) {
                                $this->SendDebug('Tasmota_PCA9685 Key', $DataKey, 0);
                                $this->SetValue('Tasmota_PCA9685_' . $DataKey, $SensorData);
                            }
                        }
                        if ($SensorKey == 'RC522') {
                            $variablenID = $this->RegisterVariableString('Tasmota' . $SensorKey . '_' . $DataKey, $SensorKey . '' . $DataKey, '');
                            $this->SetValue('Tasmota' . $SensorKey . '_' . $DataKey, $SensorData);
                        }
                        if ($SensorKey == 'RDM6300') {
                            $variablenID = $this->RegisterVariableString('Tasmota' . $SensorKey . '_' . $DataKey, $SensorKey . '' . $DataKey, '');
                            $this->SetValue('Tasmota' . $SensorKey . '_' . $DataKey, $SensorData);
                        }
                    }
                }
            }
        }
    }

    protected function traverseArray($array, $GesamtArray)
    {
        foreach ($array as $key=> $value) {
            if (is_array($value)) {
                $this->traverseArray($value, $GesamtArray);
            } else {
                $ParentKey = strval($this->find_parent($GesamtArray, $value));
                $this->SendDebug('Rekursion Tasmota ' . $ParentKey . '_' . $key, "$key = $value", 0);
                $ParentKey = str_replace('-', '_', $ParentKey);
                if ((is_int($value) || is_float($value)) && ($ParentKey != 'MCP230XX') && ($ParentKey != 'PCA9685')) {
                    $key = str_replace('-', '_', $key);
                    $key = str_replace('.', '_', $key);
                    switch ($key) {
                        case 'Temperature':
                            $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' Temperatur', '~Temperature');
                            $this->SetValue('Tasmota_' . $ParentKey . '_' . $key, $value);
                            break;
                        case 'Humidity':
                            $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' Feuchte', '~Humidity.F');
                            $this->SetValue('Tasmota_' . $ParentKey . '_' . $key, $value);
                            break;
                        default:
                            if (($ParentKey != 'ENERGY') || ($ParentKey != 'IBEACON')) {
                                $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' ' . $key);
                                $this->SetValue('Tasmota_' . $ParentKey . '_' . $key, $value);
                            }
                    }
                }
                if ($ParentKey == 'PN532') {
                    $variablenID = $this->RegisterVariableString('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . '_' . $key, '');
                    $this->SetValue('Tasmota_' . $ParentKey . '_' . $key, $value);
                }
                if ($ParentKey == 'MCP230XX') {
                    if (@$this->GetIDForIdent('Tasmota_MCP230XX_INT_' . $key) != false) {
                        $this->SendDebug('MCP230XX', $key, 0);
                        $this->SetValue('Tasmota_MCP230XX_INT_' . $key, $value);
                    }
                }
                if ($ParentKey == 'PCA9685') {
                    if (@$this->GetIDForIdent('Tasmota_PCA9685_' . $key) != false) {
                        $this->SendDebug('Tasmota_PCA9685 Key', $key, 0);
                        $this->SetValue('Tasmota_PCA9685_' . $key, $value);
                    }
                }
                if ($ParentKey == 'RC522') {
                    $variablenID = $this->RegisterVariableString('Tasmota' . $ParentKey . '_' . $key, $ParentKey . '' . $key, '');
                    $this->SetValue('Tasmota' . $ParentKey . '_' . $key, $value);
                }
                if ($ParentKey == 'RDM6300') {
                    $variablenID = $this->RegisterVariableString('Tasmota' . $ParentKey . '_' . $key, $ParentKey . '' . $key, '');
                    $this->SetValue('Tasmota' . $ParentKey . '_' . $key, $value);
                }
            }
        }
    }

    protected function getSystemVariables($myBuffer)
    {
        if (is_object($myBuffer)) {
            $this->RegisterVariableString('Tasmota_Uptime', 'Uptime');
            $this->SetValue('Tasmota_Uptime', $myBuffer->Uptime);

            if (property_exists($myBuffer, 'SleepMode')) {
                $this->RegisterVariableString('Tasmota_SleepMode', 'SleepMode');
                $this->SetValue('Tasmota_SleepMode', $myBuffer->SleepMode);
            }
            if (property_exists($myBuffer, 'Vcc')) {
                $this->RegisterVariableFloat('Tasmota_Vcc', 'Vcc');
                $this->SetValue('Tasmota_Vcc', $myBuffer->Vcc);
            }
            $this->RegisterVariableInteger('Tasmota_Sleep', 'Sleep');
            $this->SetValue('Tasmota_Sleep', $myBuffer->Sleep);

            $this->RegisterVariableInteger('Tasmota_LoadAvg', 'LoadAvg');
            $this->SetValue('Tasmota_LoadAvg', $myBuffer->LoadAvg);

            if (property_exists($myBuffer, 'Wifi')) {
                $this->RegisterVariableString('Tasmota_Wifi_SSId', 'SSId');
                $this->SetValue('Tasmota_Wifi_SSId', $myBuffer->Wifi->SSId);

                $this->RegisterVariableString('Tasmota_Wifi_BSSId', 'BSSId');
                $this->SetValue('Tasmota_Wifi_BSSId', $myBuffer->Wifi->BSSId);

                $this->RegisterVariableInteger('Tasmota_Wifi_Channel', 'Channel');
                $this->SetValue('Tasmota_Wifi_Channel', $myBuffer->Wifi->Channel);
            }
        }
    }

    protected function getInfo1Variables($myBuffer)
    {
        if (is_object($myBuffer)) {
            $this->RegisterVariableString('Tasmota_Module', $this->Translate('Module'));
            $this->RegisterVariableString('Tasmota_Version', $this->Translate('Version'));
            $this->RegisterVariableString('Tasmota_FallbackTopic', $this->Translate('Fallback Topic'));
            $this->RegisterVariableString('Tasmota_GroupTopic', $this->Translate('Group Topic'));
            if (isset($myBuffer->Info1)) {
                $this->SetValue('Tasmota_Module', $myBuffer->Info1->Module);
                $this->SetValue('Tasmota_Version', $myBuffer->Info1->Version);
                $this->SetValue('Tasmota_FallbackTopic', $myBuffer->Info1->FallbackTopic);
                $this->SetValue('Tasmota_GroupTopic', $myBuffer->Info1->GroupTopic);
            }
        }
    }

    protected function getInfo2Variables($myBuffer)
    {
        if (is_object($myBuffer)) {
            $this->RegisterVariableString('Tasmota_Hostname', 'Hostname');
            if (isset($myBuffer->Info2)) {
                $this->SetValue('Tasmota_Hostname', $myBuffer->Info2->Hostname);
            }

            $this->RegisterVariableString('Tasmota_IPAddress', 'IPAddress');
            if (isset($myBuffer->Info2)) {
                $this->SetValue('Tasmota_IPAddress', $myBuffer->Info2->IPAddress);
            }
        }
    }
}