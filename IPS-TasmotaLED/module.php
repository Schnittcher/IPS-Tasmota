<?
require_once(__DIR__ . "/../libs/TasmotaService.php");

class IPS_TasmotaLED extends TasmotaService {

  public function Create() {
      //Never delete this line!
      parent::Create();
      $this->ConnectParent("{EE0D345A-CF31-428A-A613-33CE98E752DD}");
      //Anzahl die in der Konfirgurationsform angezeigt wird - Hier Standard auf 1
      $this->RegisterPropertyString("Topic","");
      $this->RegisterPropertyString("On","1");
      $this->RegisterPropertyString("Off","0");
      $this->RegisterPropertyString("FullTopic","%prefix%/%topic%");
      $this->RegisterPropertyInteger("PowerOnState",3);
      $this->RegisterPropertyString("DeviceLanguage","en");

      $this->createVariabenProfiles();
      $this->RegisterVariableBoolean("TasmotaLED_Power", "Power","Switch",0);
      $this->RegisterVariableBoolean("TasmotaLED_Fade", "Fade","Switch",1);
      $this->RegisterVariableInteger("TasmotaLED_Color", "Color","HexColor",2);
      $this->RegisterVariableInteger("TasmotaLED_Dimmer", "Dimmer","Intensity.100",3);
      $this->RegisterVariableInteger("TasmotaLED_Scheme", "Scheme","TasmotaLED.Scheme",4);
      $this->RegisterVariableInteger("TasmotaLED_Speed", "Speed","TasmotaLED.Speed",5);
      $this->RegisterVariableInteger("TasmotaLED_Pixels", "Pixels","",6);
      $this->RegisterVariableInteger("TasmotaLED_RSSI", "RSSI","TasmotaLED.RSSI",7);
      $this->EnableAction("TasmotaLED_Power");
      $this->EnableAction("TasmotaLED_Speed");
      $this->EnableAction("TasmotaLED_Fade");
      $this->EnableAction("TasmotaLED_Scheme");
      $this->EnableAction("TasmotaLED_Color");
      $this->EnableAction("TasmotaLED_Dimmer");
  }

  public function ApplyChanges() {
      //Never delete this line!
      parent::ApplyChanges();
      $this->ConnectParent("{EE0D345A-CF31-428A-A613-33CE98E752DD}");
      //Setze Filter fÃ¼r ReceiveData
      $this->setPowerOnState($this->ReadPropertyInteger("PowerOnState"));
      $topic = $this->ReadPropertyString("Topic");
      $this->SetReceiveDataFilter(".*".$topic.".*");
    }

    public function ReceiveData($JSONString) {
      $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
      if (!empty($this->ReadPropertyString("Topic"))) {
        $this->SendDebug("ReceiveData JSON", $JSONString,0);
        $data = json_decode($JSONString);

        // Buffer decodieren und in eine Variable schreiben
        $Buffer = utf8_decode($data->Buffer);
        $Buffer = json_decode($data->Buffer);

        if (fnmatch("*".translate::PowerOnState."*", $Buffer->MSG)) {
  		   $this->SendDebug("PowerOnState Topic", $Buffer->TOPIC,0);
  		   $this->SendDebug("PowerOnState MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         $this->setPowerOnStateInForm($MSG);
       }
       if (fnmatch("*".translate::Pixels."*", $Buffer->MSG)) {
         $this->SendDebug("Pixels Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Pixels MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Pixels"), $MSG->{translate::Pixels});
       }
       if (fnmatch("*".translate::POWER."*", $Buffer->MSG)) {
         $this->SendDebug("Power Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Power MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         switch ($MSG->{translate::POWER}) {
           case $this->ReadPropertyString("On"):
           SetValue($this->GetIDForIdent("TasmotaLED_Power"), true);
           break;
           case $this->ReadPropertyString("Off"):
           SetValue($this->GetIDForIdent("TasmotaLED_Power"), false);
           break;
         }
       }
       if (fnmatch("*".translate::Speed."*", $Buffer->MSG)) {
         $this->SendDebug("Speed Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Speed MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Speed"), $MSG->{translate::Speed});
       }
       if (fnmatch("*".translate::Scheme."*", $Buffer->MSG)) {
         $this->SendDebug("Scheme Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Scheme MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Scheme"), $MSG->{translate::Scheme});
       }
       if (fnmatch("*".translate::Dimmer."*", $Buffer->MSG)) {
         $this->SendDebug("Dimmer Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Dimmer MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Dimmer"), $MSG->{translate::Dimmer});
       }
       if (fnmatch("*".translate::Color."*", $Buffer->MSG)) {
         $this->SendDebug("Color Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Color MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_Color"), hexdec(($MSG->{translate::Color})));
       }
       if (fnmatch("*".translate::Fade."*", $Buffer->MSG)) {
         $this->SendDebug("Fade Topic", $Buffer->TOPIC,0);
         $this->SendDebug("Fade MSG", $Buffer->MSG,0);
         $MSG = json_decode($Buffer->MSG);
         if ($MSG->{translate::Fade} == "ON") {
           SetValue($this->GetIDForIdent("TasmotaLED_Fade"), true);
         } else {
           SetValue($this->GetIDForIdent("TasmotaLED_Fade"), false);
         }
       }
       if (fnmatch("*".translate::STATE, $Buffer->TOPIC)) {
         $myBuffer = json_decode($Buffer->MSG);
         SetValue($this->GetIDForIdent("TasmotaLED_RSSI"), $myBuffer->{translate::Wifi}->RSSI);
       }
     }
   }

   public function setLED(int $LED,string $color) {
     $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
     $command = translate::LED.$LED;
     $msg = $color;
     $BufferJSON = $this->MQTTCommand($command,$color);
     $this->SendDebug("setLED", $BufferJSON,0);
     $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
   }

   public function setScheme(int $schemeID) {
     $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
     $command = translate::Scheme;
     $msg = $schemeID;
     $BufferJSON = $this->MQTTCommand($command,$msg);
     $this->SendDebug("setScheme", $BufferJSON,0);
     $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
   }

   public function setPixel(int $count) {
     $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
     $command = translate::Pixels;
     $msg = $count;
     $BufferJSON = $this->MQTTCommand($command,$msg);
     $this->SendDebug("setPixel", $BufferJSON,0);
     $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function setDimmer(int $value) {
    $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
    $command = translate::Dimmer;
    $msg = $value;
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setDimmer", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function setColorHex(string $color) {
    $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
    $command = translate::Color;
    $msg = $color;
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setColorHex", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function setFade(bool $value) {
    $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
    $command = translate::Fade;
    $msg = $value;
    if($msg===false){$msg = translate::PowerFalse;}
    elseif($msg===true){$msg = translate::PowerTrue;}
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setFade", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function setSpeed(int $value) {
    $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
    $command = translate::Speed;
    $msg = $value;
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setSpeed", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function setPower(bool $value) {
    $this->defineLanguage($this->ReadPropertyString("DeviceLanguage"));
    $command = "Power";
    $msg = $value;
    if($msg===false){$msg = translate::PowerFalse;}
    elseif($msg===true){$msg = translate::PowerTrue;}
    $BufferJSON = $this->MQTTCommand($command,$msg);
    $this->SendDebug("setPower", $BufferJSON,0);
    $this->SendDataToParent(json_encode(Array("DataID" => "{018EF6B5-AB94-40C6-AA53-46943E824ACF}", "Action" => "Publish", "Buffer" => $BufferJSON)));
  }

  public function RequestAction($Ident, $Value) {
    switch ($Ident) {
      case 'TasmotaLED_Power':
        $this->setPower($Value);
        break;
      case 'TasmotaLED_Speed':
        $this->setSpeed($Value);
        break;
      case 'TasmotaLED_Fade':
        $this->setFade(intval($Value));
        break;
      case 'TasmotaLED_Scheme':
        $this->setScheme($Value);
        break;
      case 'TasmotaLED_Color':
        $this->setColorHex("#".dechex($Value));
        break;
      case 'TasmotaLED_Dimmer':
        $this->setDimmer($Value);
        break;
      default:
        # code...
        break;
    }
  }

  private function createVariabenProfiles() {
    //Speed Profile
    $this->RegisterProfileInteger("TasmotaLED.Speed","Speedo","","",1,20,1);
    $this->RegisterProfileInteger("TasmotaLED.RSSI","Intensity","","",1,100,1);
    //Scheme Profile
    $this->RegisterProfileIntegerEx("TasmotaLED.Scheme", "Shuffle", "", "", Array(
                                        Array(0, "Default",  "", -1),
                                        Array(1, "Wake up",  "", -1),
                                        Array(2, "RGB Cycle", "", -1),
                                        Array(3, "RBG Cycle", "", -1),
                                        Array(4, "Random cycle", "", -1),
                                        Array(5, "Clock", "", -1),
                                        Array(6, "Incandescent pattern", "", -1),
                                        Array(7, "RGB Pattern", "", -1),
                                        Array(8, "Christmas", "", -1),
                                        Array(9, "Hanukkah", "", -1),
                                        Array(10, "Kwanzaa", "", -1),
                                        Array(11, "Rainbow", "", -1),
                                        Array(12, "Fire", "", -1)
                                    ));
    }
}
?>
