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

        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = 1;
        } else {
            $retain = 0;
        }

        $this->MQTTCommand($command, $msg, $retain);
    }

    public function sendMQTTCommand(string $command, string $msg)
    {
        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = 1;
        } else {
            $retain = 0;
        }

        $DataJSON = $this->MQTTCommand($command, $msg, $retain);
        $this->SendDebug('sendMQTTCommand', $DataJSON, 0);
        $this->BufferResponse = '';
        $this->SendDataToParent($DataJSON);
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
            $retain = 1;
        } else {
            $retain = 0;
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
            $retain = 1;
        } else {
            $retain = 0;
        }
        $this->MQTTCommand($command, $msg, $retain);
    }

    protected function MQTTCommand($command, $Payload, $retain = 0)
    {
        $retain = $this->ReadPropertyBoolean('MessageRetain');
        if ($retain) {
            $retain = 1;
        } else {
            $retain = 0;
        }

        $retain = 0; // Solange der IPS MQTT Server noch kein Retain kann

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
        $resultClient = true;
        //MQTT Server
        $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Server['PacketType'] = 3;
        $Server['QualityOfService'] = 0;
        $Server['Retain'] = false;
        $Server['Topic'] = $Topic;
        $Server['Payload'] = $Payload;
        $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'MQTT Server', $ServerJSON, 0);
        $resultServer = @$this->SendDataToParent($ServerJSON);

        //MQTT Client
        $Buffer['PacketType'] = 3;
        $Buffer['QualityOfService'] = 0;
        $Buffer['Retain'] = false;
        $Buffer['Topic'] = $Topic;
        $Buffer['Payload'] = $Payload;
        $BufferJSON = json_encode($Buffer, JSON_UNESCAPED_SLASHES);

        $Client['DataID'] = '{97475B04-67C3-A74D-C970-E9409B0EFA1D}';
        $Client['Buffer'] = $BufferJSON;

        $ClientJSON = json_encode($Client);
        $this->SendDebug(__FUNCTION__ . 'MQTT Client', $ClientJSON, 0);
        $resultClient = @$this->SendDataToParent($ClientJSON);

        if ($resultServer === false && $resultClient === false) {
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

        return $topic;
    }

    protected function setPowerOnStateInForm($value)
    {
        if ($value != $this->ReadPropertyInteger('PowerOnState')) {
            IPS_SetProperty($this->InstanceID, 'PowerOnState', $value);
            if (IPS_HasChanges($this->InstanceID)) {
                IPS_ApplyChanges($this->InstanceID);
            }
        }
        return true;
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

    protected function traverseArray($array, $GesamtArray)
    {
        $this->LogMessage(print_r($array), KL_NOTIFY);
        foreach ($array as $key=> $value) {
            if (is_array($value)) {
                $this->traverseArray($value, $GesamtArray);
            } else {
                $ParentKey = $this->find_parent($GesamtArray, $value);
                $this->SendDebug('Rekursion Tasmota ' . $ParentKey . '_' . $key, "$key = $value", 0);
                $ParentKey = str_replace('-', '_', $ParentKey);
                if ((is_int($value) || is_float($value)) && ($ParentKey != 'MCP230XX') && ($ParentKey != 'PCA9685')) {
                    $key = str_replace('-', '_', $key);
                    switch ($key) {
                        case 'Temperature':
                            $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' Temperatur', '~Temperature');
                            SetValue($this->GetIDForIdent('Tasmota_' . $ParentKey . '_' . $key), $value);
                            break;
                        case 'Humidity':
                            $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' Feuchte', '~Humidity.F');
                            SetValue($this->GetIDForIdent('Tasmota_' . $ParentKey . '_' . $key), $value);
                            break;
                        default:
                            if ($ParentKey != 'ENERGY') {
                                $variablenID = $this->RegisterVariableFloat('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . ' ' . $key);
                                SetValue($this->GetIDForIdent('Tasmota_' . $ParentKey . '_' . $key), $value);
                            }
                    }
                }
                if ($ParentKey == 'PN532') {
                    $variablenID = $this->RegisterVariableString('Tasmota_' . $ParentKey . '_' . $key, $ParentKey . '_' . $key, '');
                    SetValue($this->GetIDForIdent('Tasmota_' . $ParentKey . '_' . $key), $value);
                }
                if ($ParentKey == 'MCP230XX') {
                    if (@$this->GetIDForIdent('Tasmota_MCP230XX_INT_' . $key) != false) {
                        $this->SendDebug('MCP230XX', $key, 0);
                        SetValue($this->GetIDForIdent('Tasmota_MCP230XX_INT_' . $key), $value);
                    }
                }
                if ($ParentKey == 'PCA9685') {
                    if (@$this->GetIDForIdent('Tasmota_PCA9685_' . $key) != false) {
                        $this->SendDebug('Tasmota_PCA9685 Key', $key, 0);
                        SetValue($this->GetIDForIdent('Tasmota_PCA9685_' . $key), $value);
                    }
                }
            }
        }
    }

    protected function getSystemVariables($myBuffer)
    {
        $this->RegisterVariableString('Tasmota_Uptime', 'Uptime');
        SetValue($this->GetIDForIdent('Tasmota_Uptime'), $myBuffer->Uptime);

        $this->RegisterVariableString('Tasmota_SleepMode', 'SleepMode');
        SetValue($this->GetIDForIdent('Tasmota_SleepMode'), $myBuffer->SleepMode);

        if (property_exists($myBuffer, 'Vcc')) {
            $this->RegisterVariableFloat('Tasmota_Vcc', 'Vcc');
            SetValue($this->GetIDForIdent('Tasmota_Vcc'), $myBuffer->Vcc);
        }
        $this->RegisterVariableInteger('Tasmota_Sleep', 'Sleep');
        SetValue($this->GetIDForIdent('Tasmota_Sleep'), $myBuffer->Sleep);

        $this->RegisterVariableInteger('Tasmota_LoadAvg', 'LoadAvg');
        SetValue($this->GetIDForIdent('Tasmota_LoadAvg'), $myBuffer->LoadAvg);

        $this->RegisterVariableString('Tasmota_Wifi_SSId', 'SSId');
        SetValue($this->GetIDForIdent('Tasmota_Wifi_SSId'), $myBuffer->Wifi->SSId);

        $this->RegisterVariableString('Tasmota_Wifi_BSSId', 'BSSId');
        SetValue($this->GetIDForIdent('Tasmota_Wifi_BSSId'), $myBuffer->Wifi->BSSId);

        $this->RegisterVariableInteger('Tasmota_Wifi_Channel', 'Channel');
        SetValue($this->GetIDForIdent('Tasmota_Wifi_Channel'), $myBuffer->Wifi->Channel);
    }
}
