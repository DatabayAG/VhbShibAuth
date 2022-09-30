<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * GUI for Course Selection
 *
 * @ilCtrl_IsCalledBy ilVhbShibAuthCourseSelectGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilVhbShibAuthCourseSelectGUI:
 */
class ilVhbShibAuthCourseSelectGUI
{
    /** @var ilVhbShibAuthPlugin */
    protected $plugin;

    /** @var ilVhbShibAuthConfig $config */
    protected $config;

    /** @var ilVhbShibAuthMatching */
    protected $matching;

    /** @var ilLanguage */
    protected $lng;

    /** @var ilGlobalTemplate */
    protected $tpl;

    /** @var ilCtrl */
    protected $ctrl;

   /** @var ilPropertyFormGUI */
    protected $form;

    /** @var ilObjUser  */
    protected $user;
    /**
     * Constructor
     * ilVhbShibAuthCourseSelectGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'AuthShibboleth', 'shibhk', 'VhbShibAuth');
        $this->config = $this->plugin->getConfig();
        $this->matching = $this->plugin->getMatching();
        $this->matching->loadCoursesToSelect();

        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
    }

    /**
     * Command handler
     */
    public function executeCommand()
    {
        // should be available when form is saved
        $this->ctrl->saveParameter($this, 'deepLink');
        $this->checkAcceptance();

        $cmd = $this->ctrl->getCmd('showCourseSelection');
        switch ($cmd) {
            case 'showCourseSelection':
            case 'saveCourseSelection':

            $this->$cmd();
        }
    }

    /**
     * Do an extra loop to let ILIAS present the terms of service automatically inbetween
     * Check and set the Cmd to prevent an endless loop
     */
    protected function checkAcceptance() {

        if (empty($this->ctrl->getCmd()) && $this->user->hasToAcceptTermsOfService()) {
            $this->ctrl->redirect($this, 'showCourseSelection');
        }
    }

    /**
     * Show the selection of courses
     */
    protected function showCourseSelection()
    {
        global $DIC;

        $ilMainMenu = $DIC['ilMainMenu'];
        $ilMainMenu->showLogoOnly(true);
        ilUtil::sendInfo($this->plugin->txt('course_selection_info'));

        $this->initCourseSelectForm();

        $this->tpl->setTitle($this->plugin->txt('course_selection_welcome'));
        $this->tpl->setContent($this->form->getHTML());
        $this->tpl->printToStdout();
    }


    /**
     * Save the course selection
     */
    protected function saveCourseSelection()
    {
        global $DIC;
        $user = $DIC->user();

        // check required selection for deep link
        $this->initCourseSelectForm();
        if (!$this->form->checkInput()) {
            $this->form->setValuesByPost();
            $this->showCourseSelection();
            return;
        }

//        $this->tpl->setTitle($this->plugin->txt('course_selection_welcome'));
//        $this->tpl->setContent('<pre>' . print_r($_POST, true) . '</pre>');
//        $this->tpl->printToStdout();
//        return;

        // assign the selected courses
        foreach ($this->matching->getCoursesToSelect() as $lvnr => $ref_ids) {
            $join_ref_id = (int) $this->form->getInput('join_' .$lvnr);
            $wait_ref_ids = (array) $this->form->getInput('wait_' .$lvnr);

            if (in_array($join_ref_id, $ref_ids)) {
                // direct join
                $this->matching->assignCourse($user->getId(), $join_ref_id);
                // remove pending requests
                foreach ($ref_ids as $ref_id) {
                    $this->matching->removeRequest($user->getId(), $ref_id);
                }
            }
            else {
                // update requests
                foreach ($ref_ids as $ref_id) {
                    if (in_array($ref_id, $wait_ref_ids)) {
                        $this->matching->addRequest($user->getId(), $ref_id);
                    }
                    else {
                        $this->matching->removeRequest($user->getId(), $ref_id);
                    }
                }
            }
        }

        // prepare redirection for the deep link
        if (isset($_GET['deepLink'])) {
            if ($ref_id = $this->matching->getTargetCourseRefId($user, $_GET['deepLink'])) {
                $_GET['target'] = 'crs_'. $ref_id;
            }
        }

        // redirect to course for deep link or to user's default starting page
        ilInitialisation::redirectToStartingPage();
    }

    /**
     * Initialize the form for course selection
     * @return ilPropertyFormGUI
     */
    protected function initCourseSelectForm()
    {
        if (isset($this->form)) {
            return $this->form;
        }
        $this->form = new ilPropertyFormGUI();
        $this->form->setTitle($this->plugin->txt('course_selection'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
        $this->form->addCommandButton('saveCourseSelection', $this->plugin->txt('Continue'));

        foreach ($this->matching->getCoursesToSelect() as $lvnr => $ref_ids) {
            // courses that can directly be joined via vhb (default case)
            $join = new ilRadioGroupInputGUI(sprintf($this->plugin->txt('course_join_for'), $lvnr), 'join_'. $lvnr);

            // courses where the join needs a confirmation (toggled by keyword given in plugin function getToConfirmKeyword())
            $wait = new ilCheckboxGroupInputGUI(sprintf($this->plugin->txt('course_wait_for'), $lvnr), 'wait_'. $lvnr);
            $join_has_options = false;
            $wait_has_options = false;
            $wait_on_list = [];

            foreach ($this->matching->findMatchingIliasCourses($lvnr) as $ref_id => $data) {
                if (in_array($ref_id, $ref_ids)) {
                    if ($data['to_confirm']) {
                        $option = new ilCheckboxOption($data['title'], $ref_id,!empty($data['description']) ? $data['description'] : $this->plugin->txt('course_selection_no_description'));
                        $wait->addOption($option);
                        if ($this->matching->hasRequest($this->user->getId(), $ref_id)) {
                            $wait_on_list[] = $ref_id;
                        }
                        $wait_has_options = true;
                    }
                    else {
                        $option = new ilRadioOption($data['title'], $ref_id, !empty($data['description']) ? $data['description'] : $this->plugin->txt('course_selection_no_description'));
                        $join->addOption($option);
                        $join_has_options = true;
                    }
                }
            }

            if ($join_has_options) {
                // A selection should be done for the linked entitlement
                // to allow a redirection afterwards
                if ($_GET['deepLink'] && !$wait_has_options) {
                    $join->setRequired(true);
                }
                $this->form->addItem($join);
            }

            if ($wait_has_options) {
                $wait->setValue($wait_on_list);
                $this->form->addItem($wait);
            }

        }

        return $this->form;
    }
}