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
            $Buffer = json_decode($JSONString);

            //Für MQTT Fix in IPS Version 6.3
            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer->Payload = utf8_decode($Buffer->Payload);
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
