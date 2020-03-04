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
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     * @return	string
     */
    public function getPluginName()
    {
        return "VhbShibAuth";
    }


    /**
     * Get the plugin configuration
     * @return ilvhbShibAuthConfig
     */
    public function getConfig()
    {
        if (!isset($this->config))
        {
            $this->includeClass('class.ilVhbShibAuthConfig.php');
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
        if (!isset($this->matching)) {
            $this->includeClass('class.ilVhbShibAuthMatching.php');
            $this->matching = new ilVhbShibAuthMatching($this);
        }

        if ($this->config->get('show_server_data')) {
            $this->matching->dumpData();
        }
        return $this->matching;
    }

    /**
     * Redirect after login when deep link is given
     */
    protected function checkDeepLink()
    {
        if (isset($_GET['id']) && !isset($_GET['target']))
        {
            if ($ref_id = $this->getMatching()->getTargetCourseRefId($_GET['id']))
            {
                $_GET['target'] = 'crs_'. $ref_id;
            }
        }
    }


    /**
     * Hook from shibboleth authentication before the user object is created
     * Ignore the prepared user from the default matching conditions
     * Return an own user object for the vhb matching conditions
     * @param ilObjUser $user
     * @return ilVhbShibAuthUser
     */
    public function beforeCreateUser(ilObjUser $user)
    {
        return $this->getMatching()->getMatchedUser();
    }

    /**
     * Hook from Shibboleth authentication before the user object is updated
     * Ignore the prepared user from the default matching conditions
     * Return an own user object for the vhb matching conditions
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeUpdateUser(ilObjUser $user)
    {
        return $this->getMatching()->getMatchedUser();
    }


    /**
     * Hook from Shibboleth authentication after the user object is created
     * Assigns the courses and prepares the redirection for deep links
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterCreateUser(ilObjUser $user)
    {
        $this->getMatching()->assingMatchingCourses($user);
        $this->checkDeepLink();
        return $user;
    }



    /**
     * Hook from Shibboleth authentication after the user object is updated
     * Assigns the courses and prepares the redirection for deep links
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterUpdateUser(ilObjUser $user)
    {
        $this->getMatching()->assingMatchingCourses($user);
        $this->checkDeepLink();
        return $user;
    }


    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeLogin(ilObjUser $user)
    {
        return $user;
    }


    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterLogin(ilObjUser $user) {
        return $user;
    }

    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function beforeLogout(ilObjUser $user) {
        return $user;
    }

    /**
     * Not called by shibboleth authentication!
     * @param ilObjUser $user
     * @return ilObjUser
     */
    public function afterLogout(ilObjUser $user) {
        return $user;
    }
}
