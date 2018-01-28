# IPS-Tasmota
Mit diesem Modul ist es möglich geflashte ESPs kinderleicht in IPS zu integrieren.
Kommuniziert wird über das MQTT Prokotoll, somit muss der Status der Geräte nicht gepollt werden

## Inhaltverzeichnis
1. [Voraussetzungen](#1-voraussetzungen)
2. [Enthaltene Module](#2-enthaltene-module)
3. [Installation](#3-installation)
4. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)

## 1. Voraussetzungen

* [Mosquitto Broker](https://mosquitto.org)
* [MQTT Client](https://github.com/Schnittcher/IPS-KS-MQTT) - aktuell eine abgeänderte Version von [IPS_MQTT von thomasf68](https://github.com/thomasf68/IPS_MQTT)
* mindestens IPS Version 4.1

## 2. Enthaltene Module

### IPS-Tasmota
Mit dem IPS-Tasmota Modul ist es möglich, Geräte abzubilden, die Standard Funktionen der Tasmota Firmware beinhalten.
Zum Beispiel: Sonoff Switch, Sonoff POW, Sonoff 4CH, Sonoff TH

### IPS-TasmotaLED
Mit dem IPS-TasmotaLED Modul ist es möglich die LED Module abzubilden, die mit der Tasmota Firmware laufen.
Zum Beispiel: WS2812, AiLight, Sonoff Led, B1, BN-SZ01, H801 and MagicHome

## 3. Installation

IPS-KS-MQTT Client:
```
https://github.com/Schnittcher/IPS-KS-MQTT.git
```

In den Einstellungen muss der Modul Typ auf Forward gesetzt werden.

IPS-Tasmota:
```
https://github.com/Schnittcher/IPS-Tasmota.git
```

## 4. Konfiguration in IP-Symcon
Bitte den einzelnen Modulen entnehme.
