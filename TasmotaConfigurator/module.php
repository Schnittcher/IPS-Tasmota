<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TasmotaService.php';
class TasmotaConfigurator extends TasmotaService
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('StartIP', '');
        $this->RegisterPropertyString('EndIP', '');
    }

    public function ApplyChanges()
    {
        //Apply filter
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('keine-daten-für-mich');
    }

    public function GetConfigurationForm()
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));
        $TasmotaDevices = $this->getTasmotaDevices();
        if (count($TasmotaDevices) > 0) {
            foreach ($TasmotaDevices as $device) {
                $InstanzName = '-';
                $instanceID = $this->searchTasmotaDevice($device['Topic']);
                if ($instanceID != 0) {
                    $InstanzName = IPS_GetInstance($instanceID)['ModuleInfo']['ModuleName'];
                }
                $data->actions[0]->values[] = [
                    'IP'                    => $device['IP'],
                    'Topic'                 => $device['Topic'],
                    'FriendlyName'          => $device['FriendlyName'],
                    'Module'                => $device['Module'],
                    'Firmware'              => $device['FW'],
                    'Instanz'               => $InstanzName,
                    'instanceID'            => $instanceID,
                    'create'                => [
                        'Tasmota'     => [
                            'moduleID'      => '{1349F095-4820-4DB8-82EB-C1E93E680F08}',
                            'configuration' => [
                                'Topic'    => $device['Topic']
                            ]
                        ],
                        'TasmotaLED'     => [
                            'moduleID'      => '{5466CCED-1DA1-4FD9-9CBD-18E9399EFF42}',
                            'configuration' => [
                                'Topic'    => $device['Topic']
                            ]
                        ],
                        'TasmotaSwitchTopic'     => [
                            'moduleID'      => '{74BEB8D0-6BA8-4159-B7B8-E95EB7B29779}',
                            'configuration' => [
                                'Topic'    => $device['Topic']
                            ]
                        ]
                    ]
                ];
            }
        }
        return json_encode($data);
    }

    private function searchTasmotaDevice($topic)
    {
        $idsTasmota = IPS_GetInstanceListByModuleID('{1349F095-4820-4DB8-82EB-C1E93E680F08}');
        $idsTasmotaLed = IPS_GetInstanceListByModuleID('{5466CCED-1DA1-4FD9-9CBD-18E9399EFF42}');
        $ids = array_merge($idsTasmota, $idsTasmotaLed);
        foreach ($ids as $id) {
            if (IPS_GetProperty($id, 'Topic') == $topic) {
                return $id;
            }
        }
        return 0;
    }

    private function getTasmotaDevices()
    {
        $OfflineIPs = [];
        $sIP1 = $this->ReadPropertyString('StartIP');
        $sIP2 = $this->ReadPropertyString('EndIP');
        $TasmotaDevices = [];

        if ($sIP1 != '' && $sIP2 != '') {
            $i = 0;
            if ((ip2long($sIP1) !== -1) && (ip2long($sIP2) !== -1)) { // As of PHP5, -1 => False
                for ($lIP = ip2long($sIP1); $lIP <= ip2long($sIP2); $lIP++) {
                    $result = Sys_Ping(long2ip($lIP), 100); //Max. 10 ms warten
                    if ($result) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, long2ip($lIP) . '/cm?cmnd=Topic');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, false);

                        $apiResultJSON = curl_exec($ch);
                        $headerInfo = curl_getinfo($ch);
                        if ($headerInfo['http_code'] == 200) {
                            if ($apiResultJSON != false) {
                                $result = json_decode($apiResultJSON, true);
                            } else {
                                $this->LogMessage('Tasmota Curl Error: ' . curl_error($ch), 10204);
                                continue;
                            }
                        }
                        curl_close($ch);
                        if (is_array($result)) {
                            if (array_key_exists('WARNING', $result)) {
                                $TasmotaDevices[$i]['IP'] = long2ip($lIP);
                                $TasmotaDevices[$i]['Topic'] = '';
                                $TasmotaDevices[$i]['FriendlyName'] = '';
                                $TasmotaDevices[$i]['Module'] = '';
                                $TasmotaDevices[$i]['FW'] = '';
                            }
                            if (array_key_exists('Topic', $result)) {
                                $TasmotaDevices[$i]['IP'] = long2ip($lIP);
                                $TasmotaDevices[$i]['Topic'] = $result['Topic'];
                                $TasmotaDevices[$i]['FriendlyName'] = $this->getFriendlyName(long2ip($lIP));
                                $TasmotaDevices[$i]['Module'] = $this->getModule(long2ip($lIP));
                                $TasmotaDevices[$i]['FW'] = $this->getFirmwareVersion(long2ip($lIP));
                            }
                            $i++;
                        }
                    }
                }
            }
        }
        return $TasmotaDevices;
    }

    private function getFirmwareVersion($ip)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ip . '/cm?cmnd=Status%202');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $apiResultJSON = curl_exec($ch);
        $headerInfo = curl_getinfo($ch);
        $result = json_decode($apiResultJSON, true);
        curl_close($ch);
        return $result['StatusFWR']['Version'];
    }

    private function getModule($ip)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ip . '/cm?cmnd=Module');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $apiResultJSON = curl_exec($ch);
        $headerInfo = curl_getinfo($ch);
        $result = json_decode($apiResultJSON, true);
        curl_close($ch);
        return $result['Module'];
    }

    private function getFriendlyName($ip)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ip . '/cm?cmnd=friendlyname');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $apiResultJSON = curl_exec($ch);
        $this->SendDebug('apiResultJSON', $apiResultJSON, true);
        $headerInfo = curl_getinfo($ch);
        $result = json_decode($apiResultJSON, true);
        curl_close($ch);
        return $result['FriendlyName1'];
    }
}
