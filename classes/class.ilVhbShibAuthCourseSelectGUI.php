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

        // assign the selected courses
        foreach ($this->matching->getCoursesToSelect() as $lvnr => $ref_ids) {
            $ref_id = (int) $this->form->getInput($lvnr);
            if (in_array($ref_id, $ref_ids)) {
                $this->matching->assignCourse($user->getId(), $ref_id);
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
            $radio = new ilRadioGroupInputGUI(sprintf($this->plugin->txt('course_selection_for'), $lvnr), $lvnr);
            foreach ($this->matching->findMatchingIliasCourses($lvnr) as $ref_id => $data) {
                if (in_array($ref_id, $ref_ids)) {
                    $option = new ilRadioOption($data['title'], $ref_id, $data['description'] ? $data['description'] : $this->plugin->txt('course_selection_no_description'));
                    $radio->addOption($option);
                }
            }
            // A selection should be done for the linked entitlement
            // to allow a redirection afterwards
            if ($lvnr = $_GET['deepLink']) {
                $radio->setRequired(true);
            }
            $this->form->addItem($radio);
        }

        return $this->form;
    }
}