<?php
// Copyright (c) 2020-2023 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

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
     * @var ilRecommendedContentManager
     */
    protected $recommendedContentManager;

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

        $this->data = ilVhbShibAuthData::getInstance()->configure($this->config, $this->plugin);

        $this->recommendedContentManager = new ilRecommendedContentManager();
    }

    /**
     * Get a matching user object
     * @return
     */
    public function getMatchedUser()
    {
        return ilVhbShibAuthUser::buildInstance($this->data)->configure($this->config);
    }

    /**
     * Check if the user has access
     *
     * @param ilVhbShibAuthUser $user
     */
    public function checkAccess(ilVhbShibAuthUser $user)
    {
        if ($this->config->get('check_vhb_access') && !$this->hasVhbAccess()) {
            $this->plugin->raiseError($this->plugin->txt('err_no_vhb_access'));
        }

        if ($user->isNew() && $this->hasVhbAccess() && empty($this->getEntitledVhbCourses())) {
            $this->plugin->raiseError($this->plugin->txt('err_no_vhb_entitlement'));
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
            $course_data = $this->findMatchingIliasCourses($lvnr);
            $course_refs = array_keys($course_data);

            // more courses found
            // students should get a course selection
            if (count($course_refs) > 1 && $role == 'student') {
                if (!$this->getParticipationRefId($user->getId(), $course_refs)) {
                    $this->coursesToSelect[$lvnr] = $course_refs;
                }
                continue;
            }

            // only one course is found
            foreach ($course_data as $ref_id => $data) {
                if ($data['to_confirm']) {
                    $this->coursesToSelect[$lvnr] = [$ref_id];
                }
                else {
                    $this->assignCourse($user->getId(), $ref_id, $role);
                }
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
     * Add a subscription request for a course
     * @param integer $user_id
     * @param integer $ref_id
     */
    public function addRequest($user_id, $ref_id)
    {
        $obj_id = ilObject::_lookupObjId($ref_id);
        if ($this->plugin->isInStudOn()) {
            $cw = new ilCourseWaitingList($obj_id);
            $cw->addToList($user_id, '', 1); // REQUEST:TO_CONFIRM
        }
        else {
            $cp = new ilCourseParticipants($obj_id);
            $cp->addSubscriber($user_id);
        }
    }

    /**
     * Remove a subscription request for a course
     * @param integer $user_id
     * @param integer $ref_id
     */
    public function removeRequest($user_id, $ref_id)
    {
        $obj_id = ilObject::_lookupObjId($ref_id);
        if ($this->plugin->isInStudOn()) {
            $cw = new ilCourseWaitingList($obj_id);
            $cw->removeFromList($user_id);
        }
        else {
            $cp = new ilCourseParticipants($obj_id);
            $cp->deleteSubscriber($user_id);
        }
    }

    /**
     * Chec if a user has a subscription request for a course
     * @param integer $user_id
     * @param integer $ref_id
     */
    public function hasRequest($user_id, $ref_id)
    {
        $obj_id = ilObject::_lookupObjId($ref_id);
        if ($this->plugin->isInStudOn()) {
            $cw = new ilCourseWaitingList($obj_id);
            return $cw->isOnList($user_id);
        }
        else {
            $cp = new ilCourseParticipants($obj_id);
            return $cp->isSubscriber($user_id);
        }
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
                    $cp->add($user_id, ilParticipants::IL_CRS_MEMBER);
                    $this->recommendedContentManager->addObjectRecommendation($user_id, $ref_id);
                    break;

                case 'evaluation':
                    $pattern = $this->config->get('evaluator_role');
                    $this->assignMatchingCourseRole($user_id, $ref_id, $pattern);
                    $this->recommendedContentManager->addObjectRecommendation($user_id, $ref_id);

                    break;

                case 'appr':
                    $pattern = $this->config->get('guest_role');
                    $this->assignMatchingCourseRole($user_id, $ref_id, $pattern);
                    $this->recommendedContentManager->addObjectRecommendation($user_id, $ref_id);
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
            $role = $parts[5] ?? '';
            $scope = $parts[6] ?? '';
            $lvnr = $parts[7] ?? '';

            if ($scope == $this->config->get('local_scope') && !empty($role) && !empty($lvnr))
            {
                $courses[$lvnr] = $role;
            }
        }

        return $courses;
    }

    /**
     * Find ILIAS courses that match a certain LV number
     * @param string $lvnr
     * @return array    ref_id => ['obj_id' => int, 'title' => string, 'description' => string, 'lv_patterns' => [string, string, ...] 'to_confirm' => bool]
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
     * @return array   ref_id => ['obj_id' => int, 'title' => string, 'description' => string, 'lv_patterns' => [string, string, ...], 'to_confirm' => bool]
     */
    protected function findRelevantIliasCourses()
    {
        if (!isset($this->courses)) {
            $this->courses = array();


            $queries = [
                // find vhb course by LV number in catalog entry (ILIAS 5.4)
                "SELECT o.obj_id, o.title, o.description, m.entry FROM il_meta_identifier m " .
                " INNER JOIN object_data o ON m.obj_id = o.obj_id " .
                " INNER JOIN crs_settings s ON s.obj_id = o.obj_id " .
                " WHERE m.obj_type = 'crs'" .
                " AND m.catalog = 'vhb'" .
                " AND s.activation_type > 0"
                ,
                // find vhb course by LV number in keyword (ILIAS 7 and higher)
                "SELECT o.obj_id, o.title, o.description, m.keyword entry FROM il_meta_keyword m " .
                " INNER JOIN object_data o ON m.obj_id = o.obj_id " .
                " INNER JOIN crs_settings s ON s.obj_id = o.obj_id " .
                " WHERE m.obj_type = 'crs'" .
                " AND m.keyword LIKE 'LV_%'" .
                " AND s.activation_type > 0"
                ];

            foreach ($queries as $query) {

                $result = $this->db->query($query);

                while ($row = $this->db->fetchAssoc($result)) {
                    if (ilObject::_hasUntrashedReference($row["obj_id"])) {
                        if (!ilObjCourseAccess::_isOffline($row["obj_id"])) {
                            foreach (ilObject::_getAllReferences($row["obj_id"]) as $ref_id) {
                                if (!isset($courses[$ref_id])) {
                                    $this->courses[$ref_id] = array(
                                        'obj_id' => $row["obj_id"],
                                        'title' => $row['title'],
                                        'description' => $row['description'],
                                        'lv_patterns' => array(),
                                        'to_confirm' => $this->isToConfirm($ref_id)
                                    );
                                }
                                $this->courses[$ref_id]['lv_patterns'][] = $row['entry'];
                            }
                        }
                    }
                }
            }
         }

        return $this->courses;
    }

    /**
     * Check if a course needs confirmation
     * @param integer $ref_id
     * @return bool
     */
    protected function isToConfirm($ref_id)
    {
        $query = "SELECT r.ref_id FROM il_meta_keyword m " .
            " INNER JOIN object_reference r ON m.obj_id = r.obj_id " .
            " WHERE r.ref_id = " . $this->db->quote($ref_id, 'integer') .
            " AND m.keyword = " . $this->db->quote($this->plugin->getToConfirmKeyword(), 'text');

        $result = $this->db->query($query);
        if (!empty($this->db->fetchAssoc($result))) {
            return true;
        }
        return false;
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
        print_r($_SERVER, true),
        '$_GET: ',
        print_r($_GET, true),
        '$_POST: ',
        print_r($_POST, true),
        '$_COOKIE: ',
        print_r($_COOKIE,true)
        ]));
    }

}