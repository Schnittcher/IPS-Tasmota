<?php

require_once __DIR__ . '/../libs/TasmotaService.php';

class IPS_TasmotaSwitchTopic extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('SwitchTopicPräfix', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        //Setze Filter für ReceiveData
        $SwitchTopicPraefix = $this->ReadPropertyString('SwitchTopicPräfix');
        $this->SetReceiveDataFilter('.*' . $SwitchTopicPraefix . '.*');
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('SwitchTopicPräfix'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode(utf8_decode($JSONString));

            // Buffer decodieren und in eine Variable schreiben
            $Buffer = json_decode($data->Buffer);
            $MSG = json_decode($Buffer->MSG);
            $this->SendDebug('Topic', $Buffer->TOPIC, 0);
            $this->SendDebug('MSG', $Buffer->MSG, 0);
            $SwitchTopic = explode('/', $Buffer->TOPIC);
            switch ($Buffer->TOPIC) {
                case 'cmnd/' . $SwitchTopic[1] . '/POWER1':
                    $this->SendDebug('Receive SwitchTopic' . $SwitchTopic[1] . ' Result : ', $Buffer->MSG, 0);
                    $SwitchTopic = str_replace('-', '_', $SwitchTopic[1]);
                    $variablenID = $this->RegisterVariableBoolean('Tasmota_' . $SwitchTopic, 'SwtichTopic ' . $SwitchTopic);
                    if ($Buffer->MSG == 'ON') {
                        SetValue($this->GetIDForIdent('Tasmota_' . $SwitchTopic), true);
                    } else {
                        SetValue($this->GetIDForIdent('Tasmota_' . $SwitchTopic), false);
                    }
                    break;
            }
        }
    }
}
