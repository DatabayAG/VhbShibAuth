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
     * List of courses that have to be selected
     * @var array lvnr => ref_ids[]
     */
    protected $coursesToSelect = [];

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
        $this->data = ilVhbShibAuthData::getInstance()->configure($this->config, $this->plugin);
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
     * Check if the user has access
     *
     * @param ilVhbShibAuthUser $user
     */
    public function checkAccess(ilVhbShibAuthUser $user)
    {
        global $DIC;
        /** @var ilErrorHandling $ilErr */
        $ilErr = $DIC['ilErr'];

        if ($this->config->get('check_vhb_access') && !$this->hasVhbAccess()) {
            $ilErr->raiseError($this->plugin->txt('err_no_vhb_access'));
        }

        if ($user->isNew() && empty($this->getEntitledVhbCourses())) {
            $ilErr->raiseError($this->plugin->txt('err_no_vhb_entitlement'));
        }
    }

    /**
     * Check if the vhb access attribute is set
     */
    public function hasVhbAccess()
    {
        return (strpos($_SERVER['eduPersonEntitlement'], 'urn:mace:vhb.org:entitlement:vhb-access') !== false);
    }


    /**
     * Get the list of courses that need a selection
     * @param string $lvnr
     * @return array $lvnr => ref_ids
     */
    public function getCoursesToSelect($lvnr = null)
    {
        if (empty($lvnr)) {
            // no deep link => offer all that need selection
            return $this->coursesToSelect;
        }
        elseif (isset($this->coursesToSelect[$lvnr])) {
            // deep link needs selection => offer only this
            return [$lvnr => $this->coursesToSelect[$lvnr]];
        }
        else {
            // deep link does not need a selection => dont' offer a selection
            return [];
        }
    }

    /**
     * Save the list of courses that need a selection
     * @param string $lvnr
     */
    public function saveCoursesToSelect($lvnr = null)
    {
        $_SESSION['ilVhbShibAuth']['coursesToSelect'] = $this->getCoursesToSelect($lvnr);
    }

    /**
     * Load the list of courses that need a selection
     */
    public function loadCoursesToSelect()
    {
        $this->coursesToSelect = (array)  $_SESSION['ilVhbShibAuth']['coursesToSelect'];
    }

    /**
     * Get the ref_id of a target course
     * @param ilObjUser $user
     * @param string $lvnr
     * @return int
     */
    public function getTargetCourseRefId($user, $lvnr)
    {
        $ref_ids = array_keys($this->findMatchingIliasCourses($lvnr));
        return $this->getParticipationRefId($user->getId(), $ref_ids);
    }


    /**
     * Assign the ILIAS courses to the user that match the entitled vhb course
     * Remember courses that need a selection by students because more match an entitlement
     *
     * @param ilObjUser $user
     */
    public function assingMatchingCourses($user)
    {
        $this->coursesToSelect = [];

        foreach ($this->getEntitledVhbCourses() as $lvnr => $role)
        {
            $course_refs = array_keys($this->findMatchingIliasCourses($lvnr));

            // more courses found
            // students should get a course selection
            if (count($course_refs) > 1 && $role == 'student') {
                if (!$this->getParticipationRefId($user->getId(), $course_refs)) {
                    $this->coursesToSelect[$lvnr] = $course_refs;
                }
                continue;
            }

            foreach ($course_refs as $ref_id)
            {
                $this->assignCourse($user->getId(), $ref_id, $role);
            }
        }
    }

    /**
     * Chose the first found ref_id of a course in which the user already participates
     * @param int $user_id
     * @param int[] $ref_ids
     * @return int
     */
    public function getParticipationRefId($user_id, $ref_ids=[])
    {
        foreach ($ref_ids as $ref_id) {
            if (ilParticipants::_isParticipant($ref_id, $user_id)) {
                return $ref_id;
            }
        }
        return false;
    }

    /**
     * Assign a course to a user
     * @param int $user_id
     * @param int $ref_id
     * @param string $role role as given by shibboleth
     */
    public function assignCourse($user_id, $ref_id, $role = 'student')
    {
        /** @var ilCourseParticipants $cp */
        $obj_id = ilObject::_lookupObjId($ref_id);
        $cp = ilCourseParticipants::_getInstanceByObjId($obj_id);
        if (!$cp->isAssigned($user_id))
        {
            switch($role)
            {
                case 'student':
                    $cp->add($user_id, IL_CRS_MEMBER);
                    break;

                case 'evaluation':
                    $pattern = $this->config->get('evaluator_role');
                    $this->assignMatchingCourseRole($user_id, $ref_id, $pattern);
                    $cp->addDesktopItem($user_id);
                    break;

                case 'appr':
                    $pattern = $this->config->get('guest_role');
                    $this->assignMatchingCourseRole($user_id, $ref_id, $pattern);
                    $cp->addDesktopItem($user_id);
                    break;
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
     * @return array    ref_id => ['obj_id' => int, 'title' => string, 'description' => string, 'lv_patterns' => [string, string, ...], ...]
     */
    public function findMatchingIliasCourses($lvnr)
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
     * @return array   ref_id => ['obj_id' => int, 'title' => string, 'description' => string, 'lv_patterns' => [string, string, ...], ...]
     */
    protected function findRelevantIliasCourses()
    {
        if (!isset($this->courses)) {
            // find vhb course by catalog
            $query = "SELECT o.obj_id, o.title, o.description, m.entry FROM il_meta_identifier m " .
                " INNER JOIN object_data o ON m.obj_id = o.obj_id " .
                " INNER JOIN crs_settings s ON s.obj_id = o.obj_id " .
                " WHERE m.obj_type = 'crs'" .
                " AND m.catalog = 'vhb'" .
                " AND s.activation_type > 0";
            $result = $this->db->query($query);

            $this->courses = array();
            while ($row = $this->db->fetchAssoc($result)) {
                if (ilObject::_hasUntrashedReference($row["obj_id"])) {
                    if (!ilObjCourseAccess::_isOffline($row["obj_id"])) {
                        foreach (ilObject::_getAllReferences($row["obj_id"]) as $ref_id) {
                            if (!isset($courses[$ref_id])) {
                                $this->courses[$ref_id] = array(
                                    'obj_id' => $row["obj_id"],
                                    'title' => $row['title'],
                                    'description' => $row['description'],
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
    public function getDataDump()
    {
        return (implode("\n", [
        'Extracted Shibboleth Data: ',
        print_r($this->data->getData(), true),
        '',
        '$_SERVER: ',
        print_r((array) $_SERVER, true),
        '$_GET: ',
        print_r((array) $_GET, true),
        '$_POST: ',
        print_r((array) $_POST, true),
        '$_COOKIE: ',
        print_r((array) $_COOKIE,true)
        ]));
    }

}