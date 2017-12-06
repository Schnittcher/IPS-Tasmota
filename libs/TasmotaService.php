<?
class TasmotaService extends IPSModule {

  protected function MQTTCommand($command, $msg) {
    $FullTopic = explode("/",$this->ReadPropertyString("FullTopic"));
    $PrefixIndex = array_search("%prefix%",$FullTopic);
    $TopicIndex = array_search("%topic%",$FullTopic);

    $SetCommandArr = $FullTopic;
    $index = count($SetCommandArr);

    $SetCommandArr[$PrefixIndex] = "cmnd";
    $SetCommandArr[$TopicIndex] = $this->ReadPropertyString("Topic");
    $SetCommandArr[$index] = $command;

    $topic = implode("/",$SetCommandArr);
    $msg = $msg;

    $Buffer["Topic"] = $topic;
    $Buffer["MSG"] = $msg;
    $BufferJSON = json_encode($Buffer);

    return $BufferJSON;
  }

  protected function Debug($Meldungsname, $Daten, $Category) {
		if ($this->ReadPropertyBoolean($Category) == true) {
			$this->SendDebug($Meldungsname, $Daten,0);
		}
	}

  protected function setPowerOnStateInForm($MSG) {
    if ($MSG->PowerOnState <> $this->ReadPropertyInteger("PowerOnState")) {
      IPS_SetProperty($this->InstanceID, "PowerOnState", $MSG->PowerOnState);
      if(IPS_HasChanges($this->InstanceID))
      {
        IPS_ApplyChanges($this->InstanceID);
      }
    }
    return true;
  }

  protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
    if(!IPS_VariableProfileExists($Name)) {
        IPS_CreateVariableProfile($Name, 1);
    } else {
        $profile = IPS_GetVariableProfile($Name);
        if($profile['ProfileType'] != 1)
        throw new Exception("Variable profile type does not match for profile ".$Name);
    }

    IPS_SetVariableProfileIcon($Name, $Icon);
    IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
    IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
  }

  protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
      if ( sizeof($Associations) === 0 ){
          $MinValue = 0;
          $MaxValue = 0;
      } else {
          $MinValue = $Associations[0][0];
          $MaxValue = $Associations[sizeof($Associations)-1][0];
      }

      $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

      foreach($Associations as $Association) {
          IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
      }
  }

  public function restart() {
    $command = "restart";
    $msg = 1;
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("restart", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }
  public function setPowerOnState(int $value) {
    $command = "PowerOnState";
    $msg = $value;
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setPowerOnState", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  protected function tasmotaTranslate(string $language, string $value) {
    $JSONString = file_get_contents(__DIR__"/languages/".$language.".json");
    $translation = json_decode($JSONString, true);
     	if (array_key_exists($value, $translation["translations"][$language])) {
 			return $translation["translations"][$language][$value];
 		} else {
 	    	IPS_LogMessage("Tasmota", $value." konnte nicht übersetzt werden!");
 			return $value;
 		}
   }
}
?>
