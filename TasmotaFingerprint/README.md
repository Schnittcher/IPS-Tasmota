# IPS-Fingerprint
Mit diesem Modul ist es möglich einen Fingersensor, der an Tasmota angeschlossen ist in IP-Symcon zu bedienen.

## Inhaltverzeichnis
1. [Konfiguration](#1-konfiguration)
2. [Funktionen](#2-funktionen)

## 1. Konfiguration

Feld | Beschreibung
------------ | -------------
Tasmota MQTT Topic | Name des Tasmota Gerätes, ist in den MQTT Einstellungen in der Tasmota Firmware zu finden
Full Topic| Full Topic des Tasmota Gerätes, ist in den MQTT Einstellungen der Tasmota Firmware zu finden
System Variables| aktivieren, wenn die System Variablen vom Tasmota als Variablen in IP-Symocn angelegt werden sollen

Innerhalb des Actions Bereichs können Fingerabdrücke angelernt werden.

Die Übergeordnete Instanz sollte immer der MQTT Server sein, dieser sollte normalerweise direkt gesetzt werden, wenn das Modul angelegt wird.

## 2. Funktionen

### TasmotaFingerprint_enrollFP($InstanceID, $Value)
Mit dieser Funktion kann ein Finger angelernt werden.

```php
TasmotaFingerprint_enrollFP(25537,"1"); //Lernt Finger Nummer 1 an
```
### TasmotaFingerprint_deleteFP($InstanceID, $Value)
Mit dieser Funktion kann ein Finger gelöscht werden.

```php
TasmotaFingerprint_deleteFP(25537,"1"); //Löscht Finger Nummer 1 an
```

### TasmotaFingerprint_countFP($InstanceID)
Mit dieser Funktion wird die Anzahl der angelernten Finger ermittelt.

```php
TasmotaFingerprint_countFP(25537);
```