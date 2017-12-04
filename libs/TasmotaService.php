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
}
?>
