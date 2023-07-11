<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Plugin definition
 */
class ilVhbShibAuthPlugin extends ilShibbolethAuthenticationPlugin implements ilShibbolethAuthenticationPluginInt {

    /**
     * @var ilVhbShibAuthConfig
     */
    protected $config;

    /**
     * @var ilVhbShibAuthMatching
     */
    protected $matching;

    /**
     * @var string
     */
    protected $redirect_url = null;

    /**
     * Get the keyword in the meta data of a course for which students should make a join request
     * (StudOn specific)
     * @return string
     */
    public function getToConfirmKeyword()
    {
        return "VHB-Antrag";
    }

    /**
     * Check if the plugin is installed in the StudOn platform of the FAU
     */
    public function isInStudOn()
    {
        return is_dir(__DIR__ . '/../../../../../../../../Services/FAU');
    }

    /**
     * Get the plugin configuration
     * @return ilvhbShibAuthConfig
     */
    public function getConfig()
    {
        if (!isset($this->config))
        {
            $this->config = new ilVhbShibAuthConfig($this);
        }
        return $this->config;
    }


    /**
     * Get the class for doing user and course matchings
     * @return ilVhbShibAuthMatching
     */
    public function getMatching()
    {
        if (isset($this->matching)) {
            return $this->matching;
        }

        // apply test data as early as possible
        if (!empty($_GET['test']) &&  $_GET['test'] == $this->getConfig()->get('test_activation')) {
            if (!empty($this->getConfig()->get('test_given_name'))) {
                $_SERVER['givenName'] = $this->getConfig()->get('test_given_name');
            }
            if (!empty($this->getConfig()->get('test_sn'))) {
                $_SERVER['sn'] = $this->getConfig()->get('test_sn');
            }
            if (!empty($this->getConfig()->get('test_mail'))) {
                $_SERVER['mail'] = $this->getConfig()->get('test_mail');
            }
            if (!empty($this->getConfig()->get('test_principal_name'))) {
                $_SERVER['eduPersonPrincipalName'] = $this->getConfig()->get('test_principal_name');
            }
            if (!empty($this->getConfig()->get('test_entitlement'))) {
                $_SERVER['eduPersonEntitlement'] = $this->getConfig()->get('test_entitlement');
            }
        }

        $this->matching = new ilVhbShibAuthMatching($this);

        // debugging output
        if ($this->getConfig()->get('show_server_data')) {
            echo '<pre>';
            echo $this->matching->getDataDump();
            echo '</pre>';
            exit;
        }

        // logging
        if ($this->getConfig()->get('log_server_data')) {

            $content = "-------------------\n"
                . date('Y-m-d H:i:s') . "\n"
                . "-------------------\n"
                . $this->matching->getDataDump();

            file_put_contents(CLIENT_DATA_DIR . '/VhbShibAuth.log', $content, FILE_APPEND);
        }


        return $this->matching;
    }

    /**
     * Manipulate the target parameter when deep link is given or course selection is needed
     * This forces a redirection when the authentication process is finished
     * NOTE: the redirection to the course selection needs a special rewrite rule in the web server
     * @param ilObjUser $user
     * @see ../README.md
     */
    protected function prepareRedirection($user)
    {
        global $DIC;

        if (!empty($this->getMatching()->getCoursesToSelect($_GET['id']))) {
            $this->getMatching()->saveCoursesToSelect($_GET['id']);

            if (isset($_GET['id'])) {
                $DIC->ctrl()->setParameterByClass('ilVhbShibAuthCourseSelectGUI', 'deepLink', $_GET['id']);
            }
            $this->redirect_url = $DIC->ctrl()->getLinkTargetByClass(['iluipluginroutergui','ilVhbShibAuthCourseSelectGUI'],null,null, true);
        }
        elseif (isset($_GET['id']) && !isset($_GET['target'])) {
            if ($ref_id = $this->getMatching()->getTargetCourseRefId($user, $_GET['id'])) {
                $this->redirect_url = ilLink::_getLink($ref_id, 'crs');
            }
        }
    }

    /**
     * Stop the authentication and show an error message
     * @var string   $message
     */
    public function raiseError($message)
    {
        global $DIC;

        /** @var ilGlobalTemplate $tpl */
        $pl = $DIC['tpl'];
        $tpl->setOnScreenMessage('failure', $message, true);
        ilInitialisation::redirectToStartingPage();
    }

    /**
     * Hook from Shibboleth authentication before the user object is created
     * Ignore the prepared user from the default matching conditions
     * Return an own user object for the vhb matching conditions
     * @param ilObjUser $user
     * @return ilVhbShibAuthUser
     */
    public function beforeCreateUser(ilObjUser $user): ilObjUser
    {
        $user =  $this->getMatching()->getMatchedUser();
        $this->getMatching()->checkAccess($user);
        return $user;
    }

    /**
     * Hook from Shibboleth authentication before the user object is updated
     * Ignore the prepared user from the default matching conditions
     * Return an own user object for the vhb matching conditions
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeUpdateUser(ilObjUser $user): ilObjUser
    {
        $user =  $this->getMatching()->getMatchedUser();
        $this->getMatching()->checkAccess($user);
        return $user;
    }


    /**
     * Hook from Shibboleth authentication after the user object is created
     * Assigns the courses and prepares the redirection for deep links
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterCreateUser(ilObjUser $user): ilObjUser
    {
        $this->getMatching()->assingMatchingCourses($user);
        $this->prepareRedirection($user);
        return $user;
    }



    /**
     * Hook from Shibboleth authentication after the user object is updated
     * Assigns the courses and prepares the redirection for deep links
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterUpdateUser(ilObjUser $user): ilObjUser
    {
        $this->getMatching()->assingMatchingCourses($user);
        $this->prepareRedirection($user);
        return $user;
    }


    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeLogin(ilObjUser $user): ilObjUser
    {
        return $user;
    }


    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterLogin(ilObjUser $user): ilObjUser 
    {
        return $user;
    }

    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeLogout(ilObjUser $user): ilObjUser 
    {
        return $user;
    }

    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterLogout(ilObjUser $user): ilObjUser 
    {
        return $user;
    }

    /**
     * Eventually redirect after a successful login
     * @param string	$a_component
     * @param string	$a_event
     * @param mixed		$a_parameter
     */
    public function handleEvent($a_component, $a_event, $a_parameter)
    {
        global $DIC;
        if ($a_event == 'afterLogin' && !empty($this->redirect_url)) {
            $DIC->ctrl()->redirectToURL($this->redirect_url);
        }
    }

}
