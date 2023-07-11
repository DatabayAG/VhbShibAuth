<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * vhb Shibboleth Authentication configuration user interface class
 */
class ilvhbShibAuthConfigGUI extends ilPluginConfigGUI
{
	/** @var ilVhbShibAuthPlugin $plugin */
	protected $plugin;

	/** @var ilVhbShibAuthConfig $config */
	protected $config;

    /** @var ilLanguage */
	protected $lng;

	/** @var ilGlobalTemplate */
    protected $tpl;

    /** @var ilCtrl */
    protected $ctrl;

    /**
	 * Handles all commands, default is "configure"
	 */
	public function performCommand($cmd): void
	{
        global $DIC;

        // this can't be in constructor
        $this->plugin = $this->getPluginObject();
        $this->config = $this->plugin->getConfig();
        $this->lng = $DIC->language();
        $this->tpl = $DIC['tpl'];
        $this->ctrl =$DIC->ctrl();

		switch ($cmd)
		{
			case "configure":
            case "saveSettings":
				$this->$cmd();
				break;
		}
	}

	/**
	 * Show configuration screen screen
	 */
	protected function configure()
	{
		$form = $this->initConfigurationForm();
		$this->tpl->setContent($form->getHTML());
	}


	/**
	 * Initialize the configuration form
	 * @return ilPropertyFormGUI form object
	 */
	protected function initConfigurationForm()
	{
		require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));

        foreach ($this->config->getParams() as $name => $param)
        {
            $title = $param->title;
            $description = $param->description;
            $postvar = $name;

            switch($param->type)
            {
                case ilVhbShibAuthParam::TYPE_HEAD:
                    $input = new ilFormSectionHeaderGUI();
                    $input->setTitle($title);
                    break;
                case ilVhbShibAuthParam::TYPE_INT:
                    $input = new ilNumberInputGUI($title, $postvar);
                    $input->allowDecimals(false);
                    $input->setSize(10);
                    $input->setValue($param->value);
                    break;
                case ilVhbShibAuthParam::TYPE_BOOLEAN:
                    $input = new ilCheckboxInputGUI($title, $postvar);
                    $input->setChecked($param->value);
                    break;
                case ilVhbShibAuthParam::TYPE_FLOAT:
                    $input = new ilNumberInputGUI($title, $postvar);
                    $input->allowDecimals(true);
                    $input->setSize(10);
                    $input->setValue($param->value);
                    break;
                case ilVhbShibAuthParam::TYPE_SELECT:
                    $input = new ilSelectInputGUI($title, $postvar);
                    $input->setOptions($param->options);
                    $input->setValue($param->value);
                    break;
                case ilVhbShibAuthParam::TYPE_TEXT:
                default:
                    $input = new ilTextInputGUI($title, $postvar);
                    $input->setValue($param->value);
                    break;
            }
            $input->setInfo($description);
            $form->addItem($input);
        }


		$form->addCommandButton("saveSettings", $this->lng->txt("save"));
		return $form;
	}

	/**
	 * Save the settings
	 */
	protected function saveSettings()
	{
		$form = $this->initConfigurationForm();
		if ($form->checkInput())
		{
		    foreach (array_keys($this->config->getParams()) as $name)
            {
                $this->config->set($name, $form->getInput($name));
            }
            $this->config->write();

            $this->tpl->setOnScreenMessage('success', $this->lng->txt("settings_saved"), true);
			$this->ctrl->redirect($this, 'configure');
		}
		else
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHtml());
		}
	}
}
