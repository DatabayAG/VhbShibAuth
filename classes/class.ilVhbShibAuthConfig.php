<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Vhb Shibboleth Authentication plugin config class
 */
class ilVhbShibAuthConfig
{
    /**
     * @var ilVhbShibAuthPlugin
     */
    protected $plugin;

	/**
	 * @var ilVhbShibAuthParam[]  name => ilVhbShibAuthParam
	 */
	protected $params = array();

	/**
	 * Constructor.
	 * @param ilVhbShibAuthPlugin $a_plugin_object
	 */
	public function __construct($a_plugin_object)
	{
		$this->plugin = $a_plugin_object;
		$this->plugin->includeClass('class.ilVhbShibAuthParam.php');

		/** @var ilVhbShibAuthParam[] $params */
		$params = array();

        $params[] = ilVhbShibAuthParam::_create(
            'auth_settings',
            'Authentifizierungs-Einstellungen',
            'Einstellungen zum Anlegen und Auffinden von Benutzerkonten',
            ilVhbShibAuthParam::TYPE_HEAD
        );

        $params[] = ilVhbShibAuthParam::_create(
            'check_vhb_access',
            'vhb-Zugriffsrecht prüfen',
            'Prüft, ob das Shibboleth-Attribut "eduPersonEntitlement" die Zeichenkette "urn:mace:vhb.org:entitlement:vhb-access" enthält. Nur mit dieser erhalten Nutzer/-innen Zugriff.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'resolve_aggregation',
            'Aggregation auflösen',
            'Bei Fehlern der Attribut-Aggregation kann es vorkommen, dass die Attribute eduPersonPrincipalName, sn, mail und givenName mehrfach Werte enthalten. '
            . 'Falls aktiviert, wird versucht, die Werte zu nehmen, die zu einem lokalen Account oder vhb-Account gehören. '
            . 'Falls nicht, wird ein Fehler ausgegeben.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_suffix',
            'Suffix bei lokalen Benutzern',
            'Von Shibbloeth wird eine Benutzerkennung in der Form "kennung@hochschule.domain" übermittelt. "@hochschule.domain" ist das Suffix für den Identity Provider, zu dem die Kennung gehört.'
            . 'Tragen Sie hier das Suffix inklusive @-Zeichen ein, das zu Ihrer Hochschule gehört. Anhand dessen erkennt die Schnittstelle, dass es sich um einen lokalen Benutzer Ihrer Hochschule handelt.'
            . '<br />Das Shibboleth-Attribut, das die Benutzerkennung enthält, wird in den Shibboleth-Einstellungen von ILIAS als "Eindeutiges Shibboleth Attribut" konfiguriert, in der Regel ist es "eduPersonPrincipalName"'
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_take_login',
            'Kurzes Kennung als Login für lokale Benutzer',
            'Lokale Benutzer sollen mit der Kennung ohne Suffix als Login-Name angelegt werden. Im Standard wird ein Login-Name generiert.'
            . '<br />Die Kennung entspricht bei lokalen Benutzern der Benutzerkennung aus dem eigenen Identity Provider.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_short_external',
            'Kurze Kennung als Externes Benutzerkonto für lokale Benutzer',
            'Bei lokalen Benutzern soll die Kennung ohne Suffix als "Externes Benutzerkonto" eingetragen werden. Im Standard und bei externen Benutzern wird sie komplett eingetragen.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'external_user_login_prefix',
            'Login-Präfix für externe Benutzer',
            'Externe Benutzer sollen mit diesem Präfix und einer anonymen Nummmer als Login-Name angelegt werden. Das entspricht dem Verfahren der alten vhb-Schnittstelle. Wenn das Feld leer ist ein Login-Name aus Vor- und Nachname generiert.',
            ilVhbShibAuthParam::TYPE_TEXT,
            'vhb.'
        );

        $params[] = ilVhbShibAuthParam::_create(
            'external_user_matrikulation',
            'Kurze vhb-Kennung als Matrikelnummer für externe Benutzer',
            'Für externe Benutzer wird die kurze vhb-Kennung ohne Suffix, also z.B. "123457X25" als Matrikelnummer eingetragen.'
            . '<br />Das entspricht dem Verfahren der alten vhb-Schnittstelle.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_auth_mode',
            'Authentifizierungsmodus für lokale Benutzer',
            'Authentifizierungsmodus, der für lokale Benutzer gesetzt werden soll.',
            ilVhbShibAuthParam::TYPE_SELECT,
            4,
            $this->getAuthModes()
        );

        $params[] = ilVhbShibAuthParam::_create(
            'external_user_auth_mode',
            'Authentifizierungsmodus für externe Benutzer',
            'Authentifizierungsmodus, der für externe Benutzer gesetzt werden soll (normalerweise Shibboleth).',
            ilVhbShibAuthParam::TYPE_SELECT,
            4,
            $this->getAuthModes()
        );


        $params[] = ilVhbShibAuthParam::_create(
            'entitle_settings',
            'Kurszuweisungs-Einstellungen',
            'Einstellungen zum Eintragen des Nutzers in den entsprechenden ILIAS-Kurs. Das Shibboleth-Attribut "eduPersonEntitlement" kann mehrere, durch Semikolon getrennte Einträge der folgenden Form enthalten:<br/>'
            .'<code>urn:mace:vhb.org:entitlement:lms:student:uni-erlangen.de:LV_463_1227_1_67_1</code><br/><br/>'
            .'<code>student</code> ist die Nutzerrolle der Kurszuordnung bei der vhb.<br/>'
            .'<code>uni-erlangen.de</code> ist der Scope, d.h. eine Kennung der anbietenden Hochschule.<br/>'
            .'<code>LV_463_1227_1_67_1</code> ist die Lehrveranstaltungsnummer der vhb. Ein zugehöriger ILIAS-Kurs muss in seinen Metadaten unter "Allgemein" eine Kennung mit Katalog "vhb" und der LV-Nummer als Eintrag haben.<br/>'
            .'Ab ILIAS 7 kann die LV-Nummer auch als Schlagwort in der Schnellbearbeitung der Metadaten eingegeben werden. Der Eintrag muss mit "LV_" beginnen und kann Platzhalter ? und * enthalten, um für mehrere Semester zu gelten.',
            ilVhbShibAuthParam::TYPE_HEAD
        );
        $params[] = ilVhbShibAuthParam::_create(
            'local_scope',
            'Lokaler Scope',
            'Scope der eigenen Hochschule im Shibboleth-Attribut "eduPersonEntitlement", also die Entsprechung zu <code>uni-erlangen.de</code>. Es wird nur für Kurse mit diesem Scope eine Zuordnung vorgenommen.'
        );
        $params[] = ilVhbShibAuthParam::_create(
            'evaluator_role',
            'Evaluatorenrolle',
            'Suchmuster für Namen der ILIAS-Kursrolle, die Evaluatoren zugewiesen werden soll. Evaluatoren haben im Shibboleth-Attribut "eduPersonEntitlement" die Nutzerrolle "evaluator". Sie sollen im ILIAS-Kurs eine entsprechende Kursrolle bekommen. Mit dem Suchmuster wird in den Titeln aller Rollen des gefundenen Kurses nach der Evaluatoren-Rolle gesucht. Das Muster kann "?" oder "*" als Platzhalter für einzelne oder beliebig viele Zeichen enthalten.',
            ilVhbShibAuthParam::TYPE_TEXT,
            'Kursgast*'
        );
        $params[] = ilVhbShibAuthParam::_create(
            'guest_role',
            'Gastrolle',
            'Suchmuster für Namen der ILIAS-Kursrolle, die Gästen zugewiesen werden soll. Gäste haben im Shibboleth-Attribut "eduPersonEntitlement" die Nutzerrolle "appr". Sie sollen im ILIAS-Kurs eine entsprechende Kursrolle bekommen. Mit dem Suchmuster wird in den Titeln aller Rollen des gefundenen Kurses nach der Gast-Rolle gesucht. Das Muster kann "?" oder "*" als Platzhalter für einzelne oder beliebig viele Zeichen enthalten.',
            ilVhbShibAuthParam::TYPE_TEXT,
            'Kursgast*'
        );
        $params[] = ilVhbShibAuthParam::_create(
            'debugging_settings',
            'Debugging-Einstellungen',
            '',
            ilVhbShibAuthParam::TYPE_HEAD
        );
        $params[] = ilVhbShibAuthParam::_create(
            'show_server_data',
            'Zeige Serverdaten',
            'Gibt die übermittelten Daten aus und beendet die Verarbeitung. Bitte nur auf Testsystemen oder im Notfall aktivieren! Sonst könenn sich keine Studierenden mehr anmelden.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );
        $params[] = ilVhbShibAuthParam::_create(
            'log_server_data',
            'Logge Serverdaten',
            'Protokolliere die Serverdaten unter ' . ILIAS_DATA_DIR . '/VhbShibAuth.log',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_activation',
            'Testmodus-Aktivierung',
            'Beim Aufruf der URL, die die Shibboleth-Sitzung gestartet kann ein Parameter angehängt werden, der den Testmodus aktiviert, z.B. "www.host@domain/__vhb__?test=xxx". '
            . " Entspricht der Wert dem Eintrag hier, können die von Shibboleth übermittelten Attribute mit eigenen Werten überschrieben werden.",
            ilVhbShibAuthParam::TYPE_TEXT
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_given_name',
            'Test: givenName',
            'Überschreibt im Testmodus den Wert des Attributs givenName (Vorname).',
            ilVhbShibAuthParam::TYPE_TEXT
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_sn',
            'Test: sn',
            'Überschreibt im Testmodus den Wert des Attributs sn (Nachname).',
            ilVhbShibAuthParam::TYPE_TEXT
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_mail',
            'Test: mail',
            'Überschreibt im Testmodus den Wert des Attributs mail (E-Mail-Adresse).',
            ilVhbShibAuthParam::TYPE_TEXT
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_principal_name',
            'Test: eduPersonPrincipalName',
            'Überschreibt im Testmodus den Wert des Attributs eduPersonPrincipalName (Benutzerkennung).',
            ilVhbShibAuthParam::TYPE_TEXT
        );
        $params[] = ilVhbShibAuthParam::_create(
            'test_entitlement',
            'Test: eduPersonEntitlement',
            'Überschreibt im Testmodus den Wert des Attributs eduPersonEntitlement (Kurszuordnung).',
            ilVhbShibAuthParam::TYPE_TEXT
        );

        foreach ($params as $param)
        {
            $this->params[$param->name] = $param;
        }
        $this->read();
	}

    /**
     * Get the array of all parameters
     * @return ilVhbShibAuthParam[]
     */
	public function getParams()
    {
        return $this->params;
    }

    /**
     * Get the value of a named parameter
     * @param $name
     * @return  mixed
     */
	public function get($name)
    {
        if (!isset($this->params[$name]))
        {
            return null;
        }
        else
        {
            return $this->params[$name]->value;
        }
    }

    /**
     * Set the value of the named parameter
     * @param string $name
     * @param mixed $value
     *
     */
    public function set($name, $value = null)
    {
       $param = $this->params[$name];

       if (isset($param))
       {
           if (!isset($value))
           {
               $param->value = $value;
           }
           else
           {
               switch($param->type)
               {
                   case ilVhbShibAuthParam::TYPE_SELECT:
                   case ilVhbShibAuthParam::TYPE_TEXT:
                       $param->value = (string) $value;
                       break;
                   case ilVhbShibAuthParam::TYPE_BOOLEAN:
                       $param->value = (bool) $value;
                       break;
                   case ilVhbShibAuthParam::TYPE_INT:
                       $param->value = (integer) $value;
                       break;
                   case ilVhbShibAuthParam::TYPE_FLOAT:
                       $param->value = (float) $value;
                       break;
               }
           }
       }
    }


    /**
     * Read the configuration from the database
     */
	public function read()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "SELECT * FROM vhbshib_config";
        $res = $ilDB->query($query);
        while($row = $ilDB->fetchAssoc($res))
        {
            $this->set($row['param_name'], $row['param_value']);
        }
    }

    /**
     * Write the configuration to the database
     */
    public function write()
    {
        global $DIC;
        $ilDB = $DIC->database();

        foreach ($this->params as $param)
        {
            $ilDB->replace('vhbshib_config',
                array('param_name' => array('text', $param->name)),
                array('param_value' => array('text', (string) $param->value))
            );
        }
    }

    /**
     * Get a list of global roles as select options
     * @return array
     */
    protected function getGlobalRoles() {
        global $DIC;
        $rbacreview = $DIC['rbacreview'];
        $role_list = $rbacreview->getRolesByFilter(2);
        foreach ($role_list as $data) {
            $roles[$data["obj_id"]] = $data["title"];
        }
        return $roles;
    }

    /**
     * Get a list of authentication modes
     * @return array
     */
    protected function getAuthModes() {
        global $DIC;
        $lng = $DIC->language();

        $active_auth_modes = ilAuthUtils::_getActiveAuthModes();
        $option = array();
        foreach ($active_auth_modes as $auth_name => $auth_key) {
            if ($auth_name == 'default') {
                $name = $lng->txt('auth_' . $auth_name) . " (" . $lng->txt('auth_' . ilAuthUtils::_getAuthModeName($auth_key)) . ")";
            } else {
                 $name = ilAuthUtils::getAuthModeTranslation($auth_key);
            }
            $option[$auth_name] = $name;
        }
        return $option;
    }
}