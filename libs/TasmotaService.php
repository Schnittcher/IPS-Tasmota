<?
class TasmotaService extends IPSModule {

  protected function defineLanguage($language) {
    switch ($language) {
      case 'de':
        require_once(__DIR__ . "/languages/de.php");
        break;
      default:
        require_once(__DIR__ . "/languages/en.php");
        break;
    }
  }

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

    public function setPower(int $power,bool $Value) {
      $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
      if ($power <> 0) {
        $PowerIdent = "Tasmota_POWER".strval($power);
        $powerTopic = "POWER".strval($power);
      } else {
        $PowerIdent = "Tasmota_POWER";
        $powerTopic = "POWER";
      }
      $command = $powerTopic;
      $msg = $Value;
      if($msg===false){$msg = translate::PowerFalse;}
      elseif($msg===true){$msg = translate::PowerTrue;}
      $BufferJSON = $this->MQTTCommand($command,$msg);
      $this->SendDebug(__FUNCTION__, $BufferJSON,0);
      $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
    }
}
?>
