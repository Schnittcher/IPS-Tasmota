<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';

class TasmotaSwitchTopic extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterAttributeInteger('GatewayMode', 0); // 0 = MQTTServer 1 = MQTTClient

        //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
        $this->RegisterPropertyString('SwitchTopicPräfix', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $SwitchTopicPraefix = $this->ReadPropertyString('SwitchTopicPräfix');
        $this->SetReceiveDataFilter('.*' . $SwitchTopicPraefix . '.*');
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
        if (!empty($this->ReadPropertyString('SwitchTopicPräfix'))) {
            $this->SendDebug('ReceiveData JSON', $JSONString, 0);
            $data = json_decode($JSONString);

            $this->SendDebug('GatewayMode', $GatewayMode, 0);
            if ($GatewayMode == 0) {
                $Buffer = $data;
            } else {
                $Buffer = json_decode($data->Buffer);
            }
            $this->SendDebug('Topic', $Buffer->Topic, 0);
            $this->SendDebug('MSG', $Buffer->Payload, 0);
            $SwitchTopic = explode('/', $Buffer->Topic);
            switch ($Buffer->Topic) {
                case 'cmnd/' . $SwitchTopic[1] . '/POWER1':
                    $this->SendDebug('Receive SwitchTopic' . $SwitchTopic[1] . ' Result : ', $Buffer->Payload, 0);
                    $SwitchTopic = str_replace('-', '_', $SwitchTopic[1]);
                    $variablenID = $this->RegisterVariableBoolean('Tasmota_' . $SwitchTopic, 'SwtichTopic ' . $SwitchTopic);
                    if ($Buffer->Payload == 'ON') {
                        SetValue($this->GetIDForIdent('Tasmota_' . $SwitchTopic), true);
                    } else {
                        SetValue($this->GetIDForIdent('Tasmota_' . $SwitchTopic), false);
                    }
                    break;
            }
        }
    }
}
