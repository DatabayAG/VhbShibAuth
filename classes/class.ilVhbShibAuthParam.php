<?php
// Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilVhbShibAuthParam
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 */
class ilVhbShibAuthParam
{
	/**
	 * Defined parameter types
	 */
	const TYPE_HEAD = 'head';
    const TYPE_TEXT = 'text';
    const TYPE_BOOLEAN = 'bool';
    const TYPE_INT = 'int';
	const TYPE_FLOAT = 'float';


	/**
	 * @var string		name of the parameter (should be unique within an evaluation class)
	 */
	public $name;

	/**
     * @var string     title of the parameter
     */
	public $title;


    /**
     * @var string     description of the parameter
     */
    public $description;


    /**
	 * @var string		type of the parameter
	 */
	public $type;

	/**
	 * @var mixed 		actual value
	 */
	public $value;


    /**
     * Create a parameter
     *
     * @param string $a_name
     * @param string $a_title
     * @param string $a_description
     * @param string $a_type
	 * @param mixed $a_value
     * @return ilVhbShibAuthParam
     */
    public static function _create($a_name, $a_title, $a_description, $a_type = self::TYPE_TEXT, $a_value = null)
    {
        $param = new self;
		$param->name = $a_name;
		$param->title = $a_title;
		$param->description = $a_description;
		$param->type = $a_type;
		$param->value = $a_value;
		
		return $param;
    }
}