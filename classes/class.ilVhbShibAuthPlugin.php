<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/



/**
 * Plugin definition
 *
 * @author  Stefan Meyer <meyer@leifos.com>
 * @version $Id$
 *
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



    protected function checkDeepLink($user)
    {
        global $DIC;
        if (isset($_GET['id']) && !isset($_GET['target']))
        {
            if ($ref_id = $this->getMatching($user)->getTargetCourseRefId($_GET['id']))
            {
 //             $_GET['target'] = 'crs_'. $ref_id;
 //               $DIC->ctrl()->redirectToURL('https://www.demo.odl.org/__vhb__/resolver.php');
 //               $DIC->ctrl()->redirectToURL('https://www.demo.odl.org/__vhb__/resolver.php?target=crs_'.$ref_id);
            }
        }
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
