# IPS-Tasmota
Mit dem IPS-Tasmota Modul ist es möglich, Geräte abzubilden, die Standard Funktionen der Tasmota Firmware beinhalten.
Zum Beispiel: Sonoff Switch, Sonoff POW, Sonoff 4CH, Sonoff TH

## Inhaltverzeichnis
1. [Konfiguration](#1-konfiguration)
2. [Funktionen](#2-funktionen)

## 1. Konfiguration

Feld | Beschreibung
------------ | -------------
Tasmota MQTT Topic | Name des Tasmota Gerätes, ist in den MQTT Einstellungen in der Tasmota Firmware zu finden
Power On| 1 oder ON - Je nachdem wie das Tasmota Gerät geflasht wurde
Power Off| 0 oder OFF - Je nachdem wie das Tasmota Gerät geflasht wurde
Full Topic| Full Topic des Tasmota Gerätes, ist in den MQTT Einstellungen der Tasmota Firmware zu finden
Power1 deaktivieren| Hiermit kann die Variable Power1 dekativiert werden

Wenn die einzelnen Haken des Debug Modus aktiviert werden, sind im Debug Fenster weitere Meldungen zu finden.

Die Übergeordnete Instanz sollte immer der IPS_KS_MQTTClient sein, dieser sollte normalerweise direkt gesetzt werden, wenn das Modul angelegt wird.

## 2. Funktionen

### Tasmota_restart($InstanceID)
Mit dieser Funktion kann das Tasmota Gerät neugestartet werden.

```php
Tasmota_Restart(25537);
```

### Tasmota_setPower($InstanceID, $VariablenIdent, $Value)
Mit dieser Funktion können einzelne Relais geschaltet werden.

```php
Tasmota_setPower(25537, "Tasmota_POWER", "false");  
```
