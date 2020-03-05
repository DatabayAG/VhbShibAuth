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
            'local_user_suffix',
            'Suffix für lokaler Benutzer',
            'Von der Vhb wir eine Benutzerkennung in der Form "kennung@hochschule.domain" übermittelt. "@hochschule.domain" ist dass Suffix für den Identity Provider zu dem die Kennung gehört.'
            . 'Tragen Sie hier das Suffix inklusive @-Zeichen ein, das zu Ihrer Hochschule gehört. Anhand dessen erkennt die Schnittstelle, dass es sich um einen lokalen Benutzer Ihrer Hochschule handelt.'
            . '<br />Das Shibboleth-Attribut, das die Benutzerkennung enthält, wird in den Shibboleth-Einstellungen von ILIAS als "Eindeutiges Shibboleth Attribut" konfiguriert, in der Regel ist es "eduPersonPrincipalName"'
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_take_login',
            'Kennung als Login für lokale Benutzer',
            'Lokale Benutzer sollen mit der Kennung ohne Suffix als Login-Name angelegt werden. Im Standard und bei externen Benutzern wird ein Login-Name generiert.'
            . '<br />Die Kennung entspricht bei lokalen Benutzern der Benutzerkennung aus dem eigenen Identity Provider, bei übrigen einer Nummer der Form "123457X25" mit Suffix "@vhb.org"',
            ilVhbShibAuthParam::TYPE_BOOLEAN
        );

        $params[] = ilVhbShibAuthParam::_create(
            'local_user_short_external',
            'Kurze externe Kennung für lokale Benutzer',
            'Bei lokalen Benutzern soll die Kennung ohne Suffix als "Externes Benutzerkonto" eingetragen werden. Im Standard und bei externe Benutzer wird sie komplett eingetragen.',
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
            'entitle_settings',
            'Kurszuweisungs-Einstellungen',
            'Einstellungen zum Eintragen des Nutzers in den entsprechenden ILIAS-Kurs. Das Shibboleth-Attribut "eduPersonEntitlement" kann mehrere, durch Semikolon getrennte Einträge der folgenden Form enthalten:<br/>'
            .'<code>urn:mace:vhb.org:entitlement:lms:student:uni-erlangen.de:LV_463_1227_1_67_1</code><br/><br/>'
            .'<code>student</code> ist die Nutzerrolle der Kurszuordnung bei der vhb.<br/>'
            .'<code>uni-erlangen.de</code> ist der Scope, d.h. eine Kennung der anbietenden Hochschule.<br/>'
            .'<code>LV_463_1227_1_67_1</code> ist die Lehrveranstaltungsnummer der vhb. Ein zugehöriger ILIAS-Kurs muss in seinen Metadaten unter "Allgemein" eine Kennung mit Katalog "vhb" und der LV-Nummer als Eintrag haben.',
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
            'Gibt die übermittelten Daten aus und beendet die Verarbeitung.',
            ilVhbShibAuthParam::TYPE_BOOLEAN
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