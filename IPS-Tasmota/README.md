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
Multi Switch| aktivieren, wenn mehr als ein Swtich an dem Gerät verfügbar ist
System Variables| aktivieren, wenn die System Variablen vom Tasmota als Variablen in IP-Symocn angelegt werden sollen

Wenn die einzelnen Haken des Debug Modus aktiviert werden, sind im Debug Fenster weitere Meldungen zu finden.

Die Übergeordnete Instanz sollte immer der IPS_KS_MQTTClient sein, dieser sollte normalerweise direkt gesetzt werden, wenn das Modul angelegt wird.

## 2. Funktionen

### Tasmota_restart($InstanceID)
Mit dieser Funktion kann das Tasmota Gerät neugestartet werden.

```php
Tasmota_Restart(25537);
```

### Tasmota_setPower($InstanceID, $power, $Value)
Mit dieser Funktion können einzelne Relais geschaltet werden.

Einfach Switch:
```php
Tasmota_setPower(25537, 0, false);  //Power Variable
```
Mehrfach Switch (z.B Sonoff 4CH):
```php
Tasmota_setPower(25537, 1, false);  //Power Variable 1
Tasmota_setPower(25537, 2, false);  //Power Variable 2
Tasmota_setPower(25537, 3, false);  //Power Variable 3
Tasmota_setPower(25537, 4, false);  //Power Variable 4
```
### Tasmota_sendMQTTCommand($InstanceID, $command, $msg)
Mit dieser Funktion kann jedes MQTT Command abgeschickt werden.
Als Rückgabewert wird JSON geliefert.

Beispiel:
```php
Tasmota_sendMQTTCommand(25537, "POWER "ON");
```
