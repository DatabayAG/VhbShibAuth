Shibboleth-Authentifizierung für ILIAS im vhb-Verbund
=====================================================

Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

Autor:
* Fred Neumann <fred.neumann@fau.de>


Verwendung
----------
Das Plugin kann im Zusammenhang mit der Standard-Shibboleth-Authentifizierung von ILAS eingesetzt werden und ermöglicht die automatische Zuordnung
von ILIAS-Kursen beim Login aus dem Shibboleth-Verbund der Virtuellen Hochschule Bayern (vhb).
Zusätzlich werden Kurslinks aus dem vhb-Portal unterstützt, so dass ein Student nach dem Kogin automatisch im gewünschten Kurs landet.

Installation
------------

Wenn Sie das Plugin als ZIP-Datei aus GitHub herunterladen, benennen Sie das entpackte Verzeichnis bitte als *VhbShibAuth*
(entfernen Sie das Branch-Suffix, z.B. -master und verwenden Sie die Groß-/Kleinschreibung wie angegeben).

1. Kopieren Sie das Plugin-Verzeichnis in Ihrer ILIAS-Installation unter
Customizing/global/plugins/Services/AuthShibboleth/ShibbolethAuthenticationHook
(erzeugen Sie die Unterverzeichnisse, falls nötig)

2. Wechseln Sie zu Administration > Plugins
3. Wählen Sie die Aktion  "Aktualisieren" für das VhbShibAuth-Plugin
4. Wählen Sie die Aktion  "Aktivieren" für das VhbShibAuth-Plugin


Server-Konfiguration
--------------------

Richten Sie zunächst Ihren Webserver als Service-Provider im vhb-Verbund wie auf der folgenden Seite beschrieben ein:
https://doc.vhb.org/aai/shibboleth_service_provider_config_vhb

Die Apache-Konfiguration erfolgt ähnlich der für Moodle. Damit das Sitzungs-Cookie von ILIAS auch bei der Weiterleitung nach dem Shibboleth-Login
gültig bleibt, muss die Location "__vhb__" der Shibboleth-Sitzung auf der gleichen Ebene des URL-Pfads liegen, wie das Skript "shib_login.php" von ILIAS.
Der Standard-Aufruf "__vhb__/resolver.php " für Deep-Links aus dem vhb-Kursportal muss daher noch vor der Initialisierung der Shibboleth-Sitzung auf "__vhb__"
ohne Unterpfad weitergeleitet werden.

In der folgenden Beispiel-Konfiguration wird davon ausgegangen, dass ILIAS direkt (ohne Unterpfad) im DOCUMENT ROOT von www.demo.odl.org installiert ist:

```
## Shibboleth-Login bei ILIAS
Alias /__vhb__                  /srv/www/vhosts/demo/htdocs/shib_login.php

## Fuer Deeplinks aus dem Kursbuchungsportal
#  Weiterleitung auf Shibboleth-Sitzung ohne Unterpfad
#  Sonst werden die Sitzungs-Cookies von ILIAS falsch gesetzt
<LocationMatch "/__vhb__/resolver.php">
        RedirectMatch "/__vhb__/resolver.php(.*)" "https://www.demo.odl.org/__vhb__$1"
</LocationMatch>

## Shibboleth-Sitzung
<LocationMatch "/__vhb__">
        AuthType shibboleth
        ShibRequireSession On

        # zum Testen mit anderen IdPs
        # require valid-user

        # im Produktivbetrieb
        require shib-attr eduPersonEntitlement urn:mace:vhb.org:entitlement:vhb-access

        # applicationId aus shibboleth2.xml verwenden
        ShibRequestSetting applicationId vhbblock

        # Fehlerseiten mit Infos der vhb
        ErrorDocument 401 /Customizing/global/plugins/Services/AuthShibboleth/ShibbolethAuthenticationHook/VhbShibAuth/templates/accessDenied.html
        ErrorDocument 403 /Customizing/global/plugins/Services/AuthShibboleth/ShibbolethAuthenticationHook/VhbShibAuth/templates/accessDenied.html

        # Korrekte Weiterleitung nach dem ILIAS-Login
        RedirectMatch "/__vhb__/goto.php(.*)"  "https://www.demo.odl.org/goto.php$1"
        RedirectMatch "/__vhb__/ilias.php(.*)"  "https://www.demo.odl.org/ilias.php$1"
        RedirectMatch "/__vhb__/error.php(.*)"  "https://www.demo.odl.org/error.php$1"

        # Spezialfall: Weiterleitung zur Kursauswahl, wenn menrere Kurse passen
        RewriteEngine On
        RewriteCond %{QUERY_STRING} ^target=ilias.php(.*)$
        RewriteRule  .*/goto.php  https://www.demo.odl.org.de/ilias.php%1  [L]

</LocationMatch>
```


ILIAS-Konfiguration
-------------------

Konfigurieren und aktivieren Sie zunächst unter "Administration > Authentifizierung / Neuanmeldung > Shibboleth" die Standard-Shibboleth-Authentifizierung von ILIAS. Treffen Sie dabei die notwendigen Einstellungen:

* Shibboleth-Authentifizierung aktivieren: ja
* Erlaube lokale Authentifizierung: ja
* Generelle Rolle für Shibboleth-Benutzer: User (oder andere, je nach Ihrer ILIAS-Installation)
* Name der Shibboleth-Föderation: vhb
* Auswahl der Organisation: Anmeldebereich selber gestalten
* Eindeutiges Shibboleth Attribut: eduPersonPrincipalName
* Attribut für Vornamen: givenName
* Attribut für Nachnamen: sn
* Attribut für E-Mailadresse: mail

In der Regel wird die Authentifizierung durch Klick auf den Kurs-Link im vhb-Portal angestoßen und dabei der gewünschte Kurs als "Deep-Link" übergeben.
Wenn Sie die vhb-Authentifizierung auf der Login-Seite von ILIAS verlinken möchten, können Sie dort einfach einen Link auf  "__vhb__" eintragen, im obigen Beispiel:
https://www.demo.odl.org/__vhb__


Plugin-Konfiguration
--------------------

1. Wechseln Sie zu Asministration > Plugins
2. Wählen Sie die Aktion "Konfigurieren" für das VhbShibAuth-Plugin

Das Suchmuster für lokale Benutzer kann konfiguriert werden, wird aber aktuell noch nicht weiter beachtet.
Wichtig sind die Einstellungen zur Kurszuweisung. Nehmen Sie diese entsprechend der Erklärungen im Einstellungsformular vor.
