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

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('SwitchTopicPräfix'))) {
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

            $this->SendDebug('Topic', $Buffer->Topic, 0);
            $this->SendDebug('MSG', $Buffer->Payload, 0);
            $SwitchTopic = explode('/', $Buffer->Topic);
            switch ($Buffer->Topic) {
                case 'cmnd/' . $SwitchTopic[1] . '/POWER1':
                    $this->SendDebug('Receive SwitchTopic' . $SwitchTopic[1] . ' Result : ', $Buffer->Payload, 0);
                    $SwitchTopic = str_replace('-', '_', $SwitchTopic[1]);
                    $variablenID = $this->RegisterVariableBoolean('Tasmota_' . $SwitchTopic, 'SwtichTopic ' . $SwitchTopic);
                    if ($Buffer->Payload == 'ON') {
                        $this->SetValue('Tasmota_' . $SwitchTopic, true);
                    } else {
                        $this->SetValue('Tasmota_' . $SwitchTopic, false);
                    }
                    break;
            }
        }
    }
}
