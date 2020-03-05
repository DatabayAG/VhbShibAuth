<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Vhb Shibboleth authentication matching functions
 */
class ilVhbShibAuthMatching
{
    /** @var ilDBInterface $db */
    protected $db;

    /** @var ilVhbShibAuthPlugin $plugin */
    protected $plugin;

    /** @var ilvhbShibAuthConfig */
    protected $config;

    /** @var ilVhbShibAuthData */
    protected $data;

    /**
     * list of relevant ILIAS courses
     * @var array ref_id => ['obj_id' => int, 'title' => int, 'lv_patterns' => [string, string, ...], ...]
     */
    protected $courses;


    /**
     * Constructor
     * @param ilVhbShibAuthPlugin $plugin
     */
    public function __construct($plugin)
    {
        global $DIC;
        $this->db = $DIC->database();
        $this->plugin = $plugin;
        $this->config = $this->plugin->getConfig();

        $this->plugin->includeClass('class.ilVhbShibAuthData.php');
        $this->data = ilVhbShibAuthData::getInstance()->configure($this->config);
    }

    /**
     * Get a matching user object
     * @return
     */
    public function getMatchedUser()
    {
        $this->plugin->includeClass('class.ilVhbShibAuthUser.php');
        return ilVhbShibAuthUser::buildInstance($this->data)->configure($this->config);
    }


    /**
     * Get the ref_id of a target course
     * @param string $lvnr
     * @return int
     */
    public function getTargetCourseRefId($lvnr)
    {
        foreach ($this->findMatchingIliasCourses($lvnr) as $ref_id => $data)
        {
            return $ref_id;
        }
    }


    /**
     * Assign the ILIAS courses to the user that match the entitled vhb course
     * @param ilObjUser $user
     */
    public function assingMatchingCourses($user)
    {
        foreach ($this->getEntitledVhbCourses() as $lvnr => $role)
        {
            foreach ($this->findMatchingIliasCourses($lvnr) as $ref_id => $data)
            {
                /** @var ilCourseParticipants $cp */
                $cp = ilCourseParticipants::_getInstanceByObjId($data['obj_id']);
                if (!$cp->isAssigned($user->getId()))
                {
                    switch($role)
                    {
                        case 'student':
                            $cp->add($user->getId(), IL_CRS_MEMBER);
                            break;

                        case 'evaluation':
                            $pattern = $this->config->get('evaluator_role');
                            $this->assignMatchingCourseRole($user->getId(), $ref_id, $pattern);
                            break;

                        case 'appr':
                            $pattern = $this->config->get('guest_role');
                            $this->assignMatchingCourseRole($user->getId(), $ref_id, $pattern);
                            break;
                    }
                }
            }
        }
    }


    /**
     * Assign the course role that matches the configured pattern for the vhb role
     * @param int $usr_id
     * @param int $ref_id
     * @param string $pattern
     */
    protected function assignMatchingCourseRole($usr_id, $ref_id, $pattern)
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();
        $rbacadmin  = $DIC->rbac()->admin();

        foreach ($rbacreview->getLocalRoles($ref_id) as $rol_id)
        {
            $title = ilObjRole::_lookupTitle($rol_id);
            if (fnmatch($pattern, $title))
            {
                $rbacadmin->assignUser($rol_id, $usr_id);
                break;
            }
        }
    }


    /**
     * Get the vhb courses for which the currently authentified user is entitled
     *
     * @return array    lv_nr => role
     */
    protected function getEntitledVhbCourses()
    {
        $courses = array();

        $entitlements = explode(';', $_SERVER['eduPersonEntitlement']);
        foreach ($entitlements as $entitlement)
        {
            $parts = explode(':',$entitlement);
            $role = $parts[5];
            $scope = $parts[6];
            $lvnr = $parts[7];

            if ($scope == $this->config->get('local_scope'))
            {
                $courses[$lvnr] = $role;
            }
        }

        return $courses;
    }

    /**
     * Find ILIAS courses that match a certain LV number
     * @param string $lvnr
     * @return array    ref_id => ['obj_id' => int, 'title' => int, 'lv_patterns' => [string, string, ...], ...]
     */
    protected function findMatchingIliasCourses($lvnr)
    {
        $courses = array();
        foreach ($this->findRelevantIliasCourses() as $ref_id => $data)
        {
            foreach ($data['lv_patterns'] as $pattern)
            {
                // use file name matching with wildcards to get courses with the LV number
                // semester independent courses can have the following entries: LV_328_822_1_*_1
                if (fnmatch(trim($pattern), $lvnr))
                {
                    $courses[$ref_id] = $data;
                }
            }
        }
        return $courses;
    }


    /**
     * Find active ILIAS courses with an LV pattern in their meta data
     * @return array   ref_id => ['obj_id' => int, 'title' => int, 'lv_patterns' => [string, string, ...], ...]
     */
    protected function findRelevantIliasCourses()
    {
        if (!isset($this->courses)) {
            // find vhb course by catalog
            $query = "SELECT o.obj_id, o.title, m.entry FROM il_meta_identifier m " .
                " INNER JOIN object_data o ON m.obj_id = o.obj_id " .
                " WHERE m.obj_type = 'crs'" .
                " AND m.catalog = 'vhb'";
            $result = $this->db->query($query);

            $this->courses = array();
            while ($row = $this->db->fetchAssoc($result)) {
                if (ilObject::_hasUntrashedReference($row["obj_id"])) {
                    if (ilObjCourseAccess::_isActivated($row["obj_id"])) {
                        foreach (ilObject::_getAllReferences($row["obj_id"]) as $ref_id) {
                            if (!isset($courses[$ref_id])) {
                                $this->courses[$ref_id] = array(
                                    'obj_id' => $row["obj_id"],
                                    'title' => $row['title'],
                                    'lv_patterns' => array()
                                );
                            }
                            $this->courses[$ref_id]['lv_patterns'][] = $row['entry'];
                        }
                    }
                }
            }
        }

        return $this->courses;
    }

    /**
     * Print a data dump and exit
     */
    public function dumpData()
    {
        echo '<pre>';
        echo 'Extracted Shibboleth Data: ';
        print_r($this->data->getData());
        echo '';
        echo 'Raw Server Data: ';
        print_r((array) $_SERVER);
        echo '</pre>';
        exit;
    }

}