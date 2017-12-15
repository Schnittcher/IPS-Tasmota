# IPS-TasmotaLED
Mit dem IPS-TasmotaLED Modul ist es möglich die LED Module abzubilden, die mit der Tasmota Firmware laufen.
Zum Beispiel: WS2812, AiLight, Sonoff Led, B1, BN-SZ01, H801 and MagicHome

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

Die Übergeordnete Instanz sollte immer der IPS_KS_MQTTClient sein, dieser sollte normalerweise direkt gesetzt werden, wenn das Modul angelegt wird.

## 2. Funktionen

### Tasmota_restart($InstanceID)
Mit dieser Funktion kann das Tasmota Gerät neugestartet werden.

```php
Tasmota_Restart(25537);
```

### TasmotaLED_setColorHex($InstanceID, $Color)
Mit dieser Funktion wird die Farbe für den kompletten Stripe in HEX gesetzt.

Beispiel:

```php
TasmotaLED_setColorHex(25537, "FF0000");
```

### TasmotaLED_setDimmer($InstanceID, $Value)
Mit dieser Funktion kann der LED Stripe gedimmt werden, Werte von 0-100 können übergeben werden.

Beispiel:

```php
TasmotaLED_setDimmer(25537, 20);
```

### TasmotaLED_setFade($InstanceID, $Value)
Mit dieser Funktion kann der Fader aktiviert werden.

Beispiel:

```php
TasmotaLED_setFade(25537, true);
```


### TasmotaLED_setLED($InstanceID, $LED, $Color)
Mit dieser Funktion kann jede einzelne LED angesteuert werden.

Beispiel:

```php
TasmotaLED_setLED(25537, 1, "FF0000");
```

### TasmotaLED_setPixel($InstanceID, $Value)
Mit dieser Funktion werden die Pixel gesetzt, also wieviele LEDs der Stripe besitzt.

```php
TasmotaLED_setPixel(25537, 58);
```

Beispiel:

### TasmotaLED_setPower($InstanceID, $Power, $Value)
Mit dieser Funktion kann der Stripe ein- bzw. ausgeschaltet werden.

```php
TasmotaLED_setPower(25537, 0, true); //Power Variable
```

Beispiel:

### TasmotaLED_setScheme($InstanceID, $Value)
Mit dieser Funktion kann eine vordefiniertes Schema der Tasmota Firmware abgerufen werden.

Beispiel:

```php
TasmotaLED_setScheme(25537, 5);
```

### TasmotaLED_setSpeed($InstanceID, $Value)
Mit dieser Funktion kann die Geschwindigkeit eingestellt werden.

Beispiel:

```php
TasmotaLED_setSpeed(25537, 5);
```

### TasmotaLED_sendMQTTCommand($command, $msg)
Mit dieser Funktion kann jedes MQTT Command abgeschickt werden.

Beispiel:
```php
TasmotaLED_sendMQTTCommand("POWER "ON");
```
