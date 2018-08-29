<?php
// Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Plugin definition
 *
 * @author  Fred Neumann <fred.neumann@fau.de>
 *
 * @ingroup ServicesAuthShibboleth
 */
class ilVhbShibAuthPlugin extends ilShibbolethAuthenticationPlugin implements ilShibbolethAuthenticationPluginInt {

    /**
     * @var array
     */
    protected $active_plugins = array();


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
     *
     * Must be overwritten in plugin class of plugin
     *
     * @return	string	Plugin Name
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
     * Get the class for doing matchings
     * @param ilObjUser
     * @return ilVhbShibAuthMatching
     */
    public function getMatching($user)
    {
        if (!isset($this->matching))
        {
            $this->includeClass('class.ilVhbShibAuthMatching.php');
            $this->matching = new ilVhbShibAuthMatching($this, $user);
        }
        return $this->matching;
    }

    /**
     * Redirect after login when deep link is given
     * @param $user
     */
    protected function checkDeepLink($user)
    {
        if (isset($_GET['id']) && !isset($_GET['target']))
        {
            if ($ref_id = $this->getMatching($user)->getTargetCourseRefId($_GET['id']))
            {
                $_GET['target'] = 'crs_'. $ref_id;
            }
        }
    }

    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function beforeLogin(ilObjUser $user)
    {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function afterLogin(ilObjUser $user) {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function beforeCreateUser(ilObjUser $user) {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function afterCreateUser(ilObjUser $user)
    {
        $this->getMatching($user)->assingMatchingCourses();
        $this->checkDeepLink($user);
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function beforeLogout(ilObjUser $user) {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function afterLogout(ilObjUser $user) {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function beforeUpdateUser(ilObjUser $user) {
        return $user;
    }


    /**
     * @param ilObjUser $user
     *
     * @return ilObjUser
     */
    public function afterUpdateUser(ilObjUser $user)
    {
        $this->getMatching($user)->assingMatchingCourses();
        $this->checkDeepLink($user);
        return $user;
    }


    /**
     * Dump the server variables
     */
    public function dump()
    {
        echo '<pre>';
        var_dump($_SERVER);
        echo '</pre>';
        exit;
    }
}

?>
