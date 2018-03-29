<?php

require_once __DIR__ . '/../libs/TasmotaService.php';
class IPS_TasmotaConfigurator extends TasmotaService
{
    private $DevicesTopics = array();

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        $this->RegisterPropertyString('FullTopic', '%prefix%/%topic%');
        $this->DevicesTopics = array();
    }

    public function ApplyChanges()
    {
        //Apply filter
        $this->SetReceiveDataFilter('.*LWT.*');
        parent::ApplyChanges();
        $this->SetBuffer('DevicesTopics', serialize($this->DevicesTopics));
    }

    public function GetConfigurationForm()
    {
        $DevicesTopics = unserialize($this->GetBuffer('DevicesTopics'));
        if (count($DevicesTopics) == 0) {
            @$this->ScanTasmotaNetwork();
            IPS_Sleep(2000);
            $DevicesTopics = unserialize($this->GetBuffer('DevicesTopics'));
        }
        $Liste = array();
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $InstanceIDListe = IPS_GetInstanceListByModuleID('{1349F095-4820-4DB8-82EB-C1E93E680F08}');
        foreach ($InstanceIDListe as $InstanceID) {
            // Fremde Geräte überspringen
            $TasmotaTopic = IPS_GetProperty($InstanceID, 'Topic');
            $Tasmotas = array(
                'InstanceID'   => $InstanceID,
                'TasmotaType'  => 'Tasmota',
                'TasmotaTopic' => $TasmotaTopic,
                'Name'         => IPS_GetName($InstanceID)
            );
            $FoundIndex = array_key_exists($TasmotaTopic, $DevicesTopics);
            if ($FoundIndex === false) {
                $TasmotaDevices['rowColor'] = '#ff0000';
            } else {
                $TasmotaDevices['rowColor'] = '#00ff00';
                unset($DevicesTopics[$TasmotaTopic]);
            }
            $Liste[] = $Tasmotas;
        }
        //Prüfe auf LED Module
        $InstanceIDListe = IPS_GetInstanceListByModuleID('{5466CCED-1DA1-4FD9-9CBD-18E9399EFF42}');
        foreach ($InstanceIDListe as $InstanceID) {
            // Fremde Geräte überspringen
            $TasmotaTopic = IPS_GetProperty($InstanceID, 'Topic');
            $Tasmotas = array(
                'InstanceID'   => $InstanceID,
                'TasmotaType'  => 'TasmotaLED',
                'TasmotaTopic' => $TasmotaTopic,
                'Name'         => IPS_GetName($InstanceID)
            );
            $FoundIndex = array_key_exists($TasmotaTopic, $DevicesTopics);
            if ($FoundIndex === false) {
                $TasmotaDevices['rowColor'] = '#ff0000';
            } else {
                $TasmotaDevices['rowColor'] = '#00ff00';
                unset($DevicesTopics[$TasmotaTopic]);
            }
            $Liste[] = $Tasmotas;
        }
        foreach ($DevicesTopics as $key => $Device) {
            $this->SendDebug('Device', $Device, 0);
            $Tasmotas = array(
                'InstanceID'   => 0,
                'TasmotaType'  => $this->Translate('unknown'),
                'TasmotaTopic' => $Device,
                'Name'         => ''
            );
            $Liste[] = $Tasmotas;
        }
        $data['actions'][2]['values'] = array_merge($data['actions'][2]['values'], $Liste);
        $data['actions'][4]['onClick'] = '
        if (($TasmotaDevices["TasmotaTopic"] == "") or ($TasmotaDevices["InstanceID"] > 0))
            {
            echo "' . $this->Translate('No tasmota device selected!') . '";
            return;
            }
            
        switch ($DeviceType) {
            case 0:
                $InstanceID = IPS_CreateInstance("{1349F095-4820-4DB8-82EB-C1E93E680F08}");
                break;
            case 1:
                $InstanceID = IPS_CreateInstance("{5466CCED-1DA1-4FD9-9CBD-18E9399EFF42}");
                break;
            default:
                echo "' . $this->Translate('Error on create instance.') . '";
                return;
                break;
        }
            
        //$InstanceID = IPS_CreateInstance("{1349F095-4820-4DB8-82EB-C1E93E680F08}");
        if ($InstanceID == false) return;
        $ParentID = IPS_GetInstance($InstanceID)["ConnectionID"];
        if ($ParentID == 0)
            {
                echo "' . $this->Translate('Error on create instance.') . '";
                return;
            }
        IPS_SetProperty($InstanceID, "Topic", $TasmotaDevices["TasmotaTopic"]);
        IPS_SetProperty($InstanceID, "FullTopic", IPS_GetProperty($id,"FullTopic"));
    
        @IPS_ApplyChanges($InstanceID);
        IPS_SetName($InstanceID,"Tasmota - ".$TasmotaDevices["TasmotaTopic"]);
        echo "OK";
        ';
        IPS_LogMessage('Action Button', print_r($data['actions'][3], 1));
        return json_encode($data);
    }

    public function ReceiveData($JSONString)
    {
        $DevicesTopics = unserialize($this->GetBuffer('DevicesTopics'));
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $data = json_decode($JSONString);
        // Buffer decodieren und in eine Variable schreiben
        //$Buffer = utf8_decode($data->Buffer);
        $Buffer = json_decode($data->Buffer);
        $this->SendDebug('ReceiveData JSON', $Buffer->TOPIC, 0);
        $TopicArr = explode('/', $Buffer->TOPIC);
        $FullTopic = explode('/', $this->ReadPropertyString('FullTopic'));
        $PrefixIndex = array_search('%prefix%', $FullTopic);
        $TopicIndex = array_search('%topic%', $FullTopic);
        $DevicesTopics[$TopicArr[$TopicIndex]] = $TopicArr[$TopicIndex];
        $this->SetBuffer('DevicesTopics', serialize($DevicesTopics));
        $this->SendDebug('Count Devices', count($DevicesTopics), 0);
        $this->SendDebug('Topic', $TopicArr[$TopicIndex], 0);
    }

    public function ScanTasmotaNetwork()
    {
        $this->DevicesTopics = array();
        $Buffer['Topic'] = '#';
        $Buffer['MSG'] = '';
        $BufferJSON = json_encode($Buffer);
        $this->SendDebug('setPowerOnState', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(array('DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON)));
        //echo $this->Translate("Please close this form, and reopen it!");
    }
}
