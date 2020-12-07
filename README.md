# Fritz-Box-Auto-Reboot

![Showcase](https://i.imgur.com/LdDFSAQ.png)

### UPDATES ###
``` no updates found ```

## Erste Schritte Fritz!Box ##
1. Erstelle einen Fritz!Box Benutzer auf deiner Fritz!Box unter System => Fritz!Box Benutzer.
2. Editiere die Konfigurationen in der config.php.

## OPTIONAL email ##
1. Aktiviere den email versand indem $send_mail auf true gesetzt wird.

## OPTIONAL smskaufen.com ##
1. Erstelle auf smskaufen.com einen Account und lade dort etwas Guthaben auf. Ändere $sms_send von false auf true. 

## MySQL Datenbank ##
1. Erstelle eine Datenbank mit dem Namen "fritzbox", indem du das SQL (beispw. via PHPMyAdmin oder adminer) ausführst.

```
CREATE TABLE `fritzbox` (
     `ID` int(11) NOT NULL,
    `downstream_current` int(11) NOT NULL,
    `upstream_current` int(11) NOT NULL,
    `dsl_state` varchar(20) NOT NULL,
    `connection_type` varchar(15) NOT NULL,
    `pppoe_connected` varchar(20) NOT NULL,
    `pppoe_time` int(11) NOT NULL,
    `ip` varchar(20) NOT NULL,
    `crc_downstream` int(15) NOT NULL,
    `crc_upstream` int(15) NOT NULL,
    `max_downstream` int(11) NOT NULL,
    `max_upstream` int(11) NOT NULL,
    `snr_downstream` int(11) NOT NULL,
    `snr_upstream` int(11) NOT NULL,
    `daempfung_downstream` int(11) NOT NULL,
    `daempfung_upstream` int(11) NOT NULL,
    `timestamp` int(11) NOT NULL,
    `gaestewlan_status` varchar(15) NOT NULL,
    `gaestewlan_ssid` varchar(200) NOT NULL,
    `router_status` varchar(15) NOT NULL,
    `send_status` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

## Projekt hochladen ##
1. Lade das gesamte Projekt auf deinen LOKALEN Webserver (beispw. über einen Raspberry PI)
2. Rufe nun die Seite unter http(s)://IP/fritzbox/fritzbox.php auf.

## Cronjob erstellen ##
1. Tippe in der Raspberry Console folgendes ein: sudo crontab -e 
2. Trage folgendes ein: 
>10 * * * * /usr/bin/php /var/www/html/fritzbox/fritzbox.php >/dev/null 2>&1
3. Speicher deinen cronjob

Info: Es wird nun alle 10 min. die Datei fritzbox.php aufgerufen. Ist der aktuelle Downstream unter dem zuvor eingestellten Wert, startet die Fritz!Box, sofern eine Interentverbindung besteht neu.