<#1>
<?php
// Copyright (c) 2018-2023 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Database creation script.
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 */
?>
<#2>
<?php
if (!$ilDB->tableExists('vhbshib_config'))
{
    $fields = array(
        'param_name' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
        ),
        'param_value' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => null
        )
    );
    $ilDB->createTable("vhbshib_config", $fields);
    $ilDB->addPrimaryKey("vhbshib_config", array("param_name"));
}
?>
