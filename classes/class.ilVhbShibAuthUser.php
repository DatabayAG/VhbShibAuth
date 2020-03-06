<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Extension of the user object created in the shibboleth authentication
 * Override create/update behavior based on local user match
 *
 * ILIAS searches existing users by the full login in the external account field.
 * This may not find the relevant user if the external account is shortened for local users.
 * So this user is newly initialized and returned in the beforeCreate() and beforeUpdate() functions.
 * The create() and update() functions are called afterwards by the the shibboleth authentication.
 * They must change their behavior vice versa if the account already exists or not.
 */
class ilVhbShibAuthUser extends shibUser
{
    /** @var ilVhbShibAuthData (extension of shibServerData) */
    protected $shibServerData;

    /** @var ilvhbShibAuthConfig */
    protected $config;

    /**
     * Create the user, even if update() is called,
     * Otherwise update the user, even if create() is called.
     * @var bool
     */
    public $hasToBeCreated = false;

    /**
     * Get an empty user instance with injected vhb server data
     * Rest is done in configure() when the config is injected
     *
     * @param ilVhbShibAuthData $shibServerData (extension of shibServerData)
     * @return self
     */
    public static function buildInstance(shibServerData $shibServerData) {
        $shibUser = new self();
        $shibUser->shibServerData = $shibServerData;
        return $shibUser;
    }

    /**
     * Apply the vhb configuration
     * Should be done immediately after instantiation
     * Will search for an existing user and set the fields
     * @param ilVhbShibAuthConfig $config
     * @return $this
     */
    public function configure($config) {

        $this->config = $config;

        // find an existing user that matches the data
        if ($id = $this->getMatchingUserId()) {
            $this->setId($id);
            $this->read();
            $this->updateFields();
            $this->hasToBeCreated = false;
        }
        else {
            $this->createFields();
            $this->hasToBeCreated = true;
        }

        return $this;
    }

    /**
     * Set specific fields for a new user from the server data
     * All other fields ar set according to the standard shibboleth configuration
     */
    public function createFields()
    {
        parent::createFields();

        $this->setLogin($this->returnNewLoginName());
        $this->setExternalAccount($this->returnNewExtAccount());
        $this->setAuthMode($this->returnNewAuthMode());

        $this->setMatriculation($this->shibServerData->getMatriculation());
    }

    /**
     * Update the fields for an existing user from the server data
     * The fields ar updated according to the standard shibboleth configuration
     */
    public function updateFields()
    {
        parent::updateFields();
    }

    /**
     * Update or create the user depending on the search result in configure()
     *
     * @return int|void
     * @throws ilUserException
     * @see ilAuthProviderShibboleth::doAuthentication()
     */
    public function create()
    {
        if ($this->hasToBeCreated) {
            return parent::create();
        }
        else {
            return parent::update();
        }
    }

    /**
     * Update or create the user depending on configuration result
     * @return bool
     * @throws ilUserException
     * @see ilAuthProviderShibboleth::doAuthentication()
     */
    public function update() {
        if ($this->hasToBeCreated) {
            // do things normally done in ilAuthProviderShibboleth::doAuthentication when account is created
            parent::create();
            $this->updateOwner();
            $this->saveAsNew();
            $this->writePrefs();
            return true;
        }
        else {
            return parent::update();
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOwner()
    {
        if ($this->hasToBeCreated) {
            return parent::updateOwner();
        }
        return true;
    }


    /**
     * @inheritDoc
     */
    public function saveAsNew($a_from_formular = true)
    {
        if ($this->hasToBeCreated) {
            return parent::saveAsNew($a_from_formular);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function writePref($a_keyword, $a_value)
    {
        if ($this->hasToBeCreated) {
            parent::writePref($a_keyword, $a_value);
        }
    }


    /**
     * Get the id of an existing user that matches the shibboleth data
     * @return int|false
     */
    protected function getMatchingUserId()
    {
        return self::getUsrIdByExtId($this->returnNewExtAccount());
    }

    /**
     * Return the login name for new users
     * @return string
     */
    protected function returnNewLoginName()
    {
        if ($this->shibServerData->isLocalUser() && $this->config->get('local_user_take_login')) {
            return $this->getUniqueLoginName($this->shibServerData->getLocalUserName());
        }
        if (!$this->shibServerData->isLocalUser() && $this->config->get('external_user_take_login')) {
            return $this->getUniqueLoginName($this->shibServerData->getLogin());
        }

        return parent::returnNewLoginName();
    }

    /**
     * Return the external account value for new users
     * This should be compatible to the search condition in getMatchingUserId()
     * @return string
     * @see getMatchingUserId
     */
    protected function returnNewExtAccount()
    {
        if ($this->shibServerData->isLocalUser() && $this->config->get('local_user_short_external')) {
            return $this->shibServerData->getLocalUserName();
        }

        return $this->shibServerData->getLogin();
    }

    /**
     * Return the authentication mode for new users
     * @return string
     */
    protected function returnNewAuthMode()
    {
        if ($this->config->get('local_user_auth_mode')) {
            return $this->config->get('local_user_auth_mode');
        }
        else {
            return 'shibboleth';
        }
    }

    /**
     * Modify a login name until it is unique
     * @param $login
     * @return string
     */
    protected function getUniqueLoginName($login)
    {
        $appendix = null;
        $login_tmp = $login;
        while (self::_loginExists($login, $this->getId())) {
            $login = $login_tmp . $appendix;
            $appendix ++;
        }
        return $login;
    }

}