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
    protected shibServerData $shibServerData;

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
    public static function buildInstance(shibServerData $shibServerData): shibUser
    {
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
     * Return if the user account already exists
     */
    public function isNew(): bool
    {
        return $this->hasToBeCreated;
    }

    /**
     * Set specific fields for a new user from the server data
     * All other fields ar set according to the standard shibboleth configuration
     */
    public function createFields(): void
    {
        parent::createFields();

        $this->setExternalAccount($this->returnNewExtAccount());
        $this->setAuthMode($this->returnNewAuthMode());
        $this->setMatriculation($this->shibServerData->getMatriculation());
    }

    /**
     * Update the fields for an existing user from the server data
     * The fields ar updated according to the standard shibboleth configuration
     */
    public function updateFields(): void
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
    public function create(): int
    {
         if ($this->hasToBeCreated) {
            parent::create();
            $this->updateLoginName();
        }
        else {
            $this->setTimeLimitUnlimited(1);
            $this->setTimeLimitFrom(time());
            $this->setTimeLimitUntil(time());
            $this->setActive(true);
            parent::update();
            
        }
        return $this->getId();
    }

    /**
     * Update or create the user depending on configuration result
     * @return bool
     * @throws ilUserException
     * @see ilAuthProviderShibboleth::doAuthentication()
     */
    public function update(): bool 
    {
        if ($this->hasToBeCreated) {
            // do things normally done in ilAuthProviderShibboleth::doAuthentication when account is created
            parent::create();
            $this->updateLoginName();
            $this->updateOwner();
            $this->saveAsNew();
            $this->writePrefs();
            return true;
        }
        else {
            $this->setTimeLimitUnlimited(1);
            $this->setTimeLimitFrom(time());
            $this->setTimeLimitUntil(time());
            $this->setActive(true);
            return parent::update();
        }
    }

    /**
     * @inheritDoc
     */
    public function saveAsNew(): void
    {
        if ($this->hasToBeCreated) {
            parent::saveAsNew();
        }
    }

    /**
     * @inheritDoc
     */
    public function writePref($a_keyword, $a_value): void
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
    protected function returnNewLoginName(): ?string
    {
        global $DIC;
        $ilDB = $DIC->database();

        if ($this->shibServerData->isLocalUser() && $this->config->get('local_user_take_login')) {
            return $this->getUniqueLoginName($this->shibServerData->getLocalUserName());
        }
        if (!$this->shibServerData->isLocalUser() && $this->config->get('external_user_login_prefix')) {
            // This may be identical to the created usr_id but does not need
            // updateLoginName() handle this after the user is created
            $number = $ilDB->nextId('object_data') + 1;
            $login = $this->config->get('external_user_login_prefix') . $number;
            return $this->getUniqueLoginName($login);
        }

        return parent::returnNewLoginName();
    }

    /**
     * Update the login name if it should match the
     */
    protected function updateLoginName()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $prefix = $this->config->get('external_user_login_prefix');
        if (!$this->shibServerData->isLocalUser() && !empty($prefix) && $this->getLogin() != $prefix . $this->getId()) {
            $this->setLogin($this->getUniqueLoginName($prefix . $this->getId()));
            $ilDB->manipulateF('
				UPDATE usr_data
				SET login = %s
				WHERE usr_id = %s',
                array('text', 'integer'), array($this->getLogin(), $this->getId()));
        }
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
        if ($this->shibServerData->isLocalUser() && $this->config->get('local_user_auth_mode')) {
            return $this->config->get('local_user_auth_mode');
        }
        elseif (!$this->shibServerData->isLocalUser() && $this->config->get('external_user_auth_mode')) {
            return $this->config->get('external_user_auth_mode');
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
        $appendix = 1;
        $login_base = $login;
        while (self::_loginExists($login, $this->getId())) {
            $login = $login_base . '.' .$appendix;
            $appendix ++;
        }
        return $login;
    }

}