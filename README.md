[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/IPS-Tasmota/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/IPS-Tasmota/actions)

# IPS-Tasmota
Mit diesem Modul ist es möglich geflashte ESPs kinderleicht in IPS zu integrieren.
Kommuniziert wird über das MQTT Prokotoll, somit muss der Status der Geräte nicht gepollt werden

## Inhaltverzeichnis
1. [Voraussetzungen](#1-voraussetzungen)
2. [Enthaltene Module](#2-enthaltene-module)
3. [Installation](#3-installation)
4. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
5. [Spenden](#5-spenden)
6. [Lizenz](#6-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 5.1
* MQTT Server oder MQTT Client

## 2. Enthaltene Module

### IPS-Tasmota
Mit dem IPS-Tasmota Modul ist es möglich, Geräte abzubilden, die Standard Funktionen der Tasmota Firmware beinhalten.
Zum Beispiel: Sonoff Switch, Sonoff POW, Sonoff 4CH, Sonoff TH

### IPS-TasmotaLED
Mit dem IPS-TasmotaLED Modul ist es möglich die LED Module abzubilden, die mit der Tasmota Firmware laufen.
Zum Beispiel: WS2812, AiLight, Sonoff Led, B1, BN-SZ01, H801 and MagicHome

## 3. Installation

IPS-Tasmota (Branch 5.1):
```
https://github.com/Schnittcher/IPS-Tasmota.git
```

## 4. Konfiguration in IP-Symcon
Das Modul kann mit dem internen MQTT Server betrieben werden, oder aber mit einem externen MQTT Broker.
Wenn ein externer MQTT Broker verwendet werden soll, dann muss aus dem Module Store der MQTTClient installiert werden.

Standardmäßig wird der MQTT Server bei den Geräteinstanzen als Parent hinterlegt, wenn aber ein externer Broker verwendet werden soll, muss der MQTT Client per Hand angelegt werden und in der Geräteinstanz unter "Gateway ändern" ausgewählt werden.

Die weitere Dokumentation bitte den einzelnen Modulen entnehmen.

## 5. Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:    

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
