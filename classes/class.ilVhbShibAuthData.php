<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Extension of shibServerData to support local user match and attribute aggregation
 * Attributes may hold the data of multiple sources
 * This class extracts the data from the relevant source
 */
class ilVhbShibAuthData extends shibServerData
{
    /**
     * Delimiter in the attributes for aggregated data
     */
    const DELIM = ';';

    const VHB_SUFFIX = '@vhb.org';

    /** @var ilvhbShibAuthConfig */
    protected $config = null;

    /** @var ilVhbShibAuthPlugin */
    protected $plugin;

     /** @var string */
    protected $local_user_name = '';

    /**
     * Get an instance without caching
     * (would otherwise get the parent class)
     * @return self
     */
    public static function getInstance() {
        return new self($_SERVER);
    }

    /**
     * Apply the vhb configuration
     * Should be done immediately after instantiation
     * @param ilVhbShibAuthConfig $config
     * @param ilVhbShibAuthPlugin $plugin
     * @return $this
     */
    public function configure($config, $plugin)
    {
        $this->config = $config;
        $this->plugin = $plugin;

        // index position of the relevant user in the aggregation
        $aggregation_index = 0;

        // LOCAL USER
        // get the login name and aggregation index of a local user
        // priority is on local user if aggregated
        $suffix = $this->config->get('local_user_suffix');
        $logins = explode(self::DELIM, $this->login);
        foreach ($logins as $index => $login) {
            if (!empty($login) && !empty($suffix) && strpos($login, $suffix) > 0) {
                $this->local_user_name = substr($login, 0,strpos($login, $suffix));
                $aggregation_index = $index;
                break;
            }
        }

        // VHB USER
        // get the aggregation index of the vhb user if the local user is not matched
        // this prevents a logins being taken from the home IDP
        if (empty($this->local_user_name)) {
            $suffix = self::VHB_SUFFIX;
            $logins = explode(self::DELIM, $this->login);
            foreach ($logins as $index => $login) {
                if (!empty($login) && !empty($suffix) && strpos($login, $suffix) > 0) {
                    $aggregation_index = $index;
                    break;
                }
            }
        }

        // DE-AGGREGATION
        // extract the relevant values from aggregated fields
        // should not be necessary if SP is correctly configured at vhb
        foreach (array_keys(get_class_vars('shibConfig')) as $field) {
            $values = explode(self::DELIM, (string) $this->{$field});

            if ($this->config->get('resolve_aggregation')) {
                // take the value from the position of the aggregation index
                if (isset($values[$aggregation_index])) {
                    $this->{$field} = $values[$aggregation_index];
                }
                // fallback: take the first value
                elseif (isset($values[0])) {
                    $this->{$field} = $values[0];
                }
            }
            elseif (count($values) > 1) {
                $this->plugin->raiseError(sprintf($this->plugin->txt('err_multi_values_in'), $field, $this->{$field}));
            }
        }
        return $this;
    }

    /**
     * Check if the provided data is for a local user
     */
    public function isLocalUser()
    {
        return !empty($this->local_user_name);
    }

    /**
     * Get the name of the local user account (without suffix)
     * e.g. get 'vhbtest' if login is 'vhbtest@uni-erlangen.de'
     *      and if '@uni-erlangen.de' is configured as local user suffix
     * @return string
     */
    public function getLocalUserName()
    {
        return $this->local_user_name;
    }

    /**
     * Decode a numeric gender if provided by vhb
     * @return string
     */
    public function getGender()
    {
        switch ($this->gender) {
            case 'm':
            case 'f':
                return $this->gender;

            case '1':
                return 'm';

            case '2':
                return 'f';

            case '0':
            default:
                return 'n';
        }
    }

    /**
     * Get the matriculation number
     * @return string
     */
    public function getMatriculation()
    {
        if (!$this->isLocalUser() && $this->config->get('external_user_matrikulation')) {
            $login = $this->getLogin();
            $suffix = '@vhb.org';
            if (!empty($login) && !empty($suffix) && strpos($login, $suffix) > 0) {
                return substr($login, 0,strpos($login, $suffix));
            }
        }

        return parent::getMatriculation();
    }

    /**
     * Get the pure data for a dump
     */
    public function getData() {
        $data = [];
        foreach (array_keys(get_class_vars('shibConfig')) as $field) {
            if (substr($field,0, 7)!= 'update_') {
                $data[$field] = $this->{$field};
            }
        }
        return $data;
    }
}