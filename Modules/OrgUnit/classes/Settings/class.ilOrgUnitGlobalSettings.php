<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Global settings for org units
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de> 
 *
 */
class ilOrgUnitGlobalSettings
{
	/**
	 * @var ilOrgUnitGlobalSettings
	 */
	private static $instance = null;
	
	/**
	 * @var ilObjectDefinition
	 */
	protected $object_definition = null;
	
	
	/**
	 * @var ilOrgUnitObjectTypePositionSetting[]
	 */
	private $position_settings = [];
	
	/**
	 * Array with key obj_id => active status
	 * @var array
	 */
	private $object_position_cache = [];
	
	/**
	 * Singelton constructor
	 */
	protected function __construct()
	{
		$this->object_definition = $GLOBALS['DIC']['objDefinition'];
		$this->readSettings();
	}
	
	/**
	 * Get instance
	 * @return ilOrgUnitGlobalSettings
	 */
	public static function getInstance()
	{
		if(!self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Get object position settings by type
	 * @param string $a_obj_type
	 * @return ilOrgUnitObjectTypePositionSetting
	 * @throws \InvalidArgumentException
	 */
	public function getObjectPositionSettingsByType($a_obj_type)
	{
		if(!isset($this->position_settings[$a_obj_type]))
		{
			throw new \InvalidArgumentException('Object type passed does not support position settings: ' . $a_obj_type);
		}
		return $this->position_settings[$a_obj_type];
	}
	
	/**
	 * Check of position access is activate for object
	 * @param int $a_obj_id
	 * @return bool
	 */
	public function isPositionAccessActiveForObject($a_obj_id)
	{
		if(isset($this->object_position_cache[$a_obj_id]))
		{
			return $a_obj_id;
		}
		$type = ilObject::_lookupType($a_obj_id);
		try {
			$type_settings = $this->getObjectPositionSettingsByType($type);
		}
		catch(\InvalidArgumentException $invalid_type_exception) {
			$this->object_position_cache[$a_obj_id] = false;
			return false;
		}
		
		if(!$type_settings->isActive()) {
			$this->object_position_cache[$a_obj_id] = false;
			return false;
		}
		if(!$type_settings->isChangeableForObject()) {
			$this->object_position_cache[$a_obj_id] = false;
			return false;
		}
		$object_position = new ilOrgUnitObjectPositionSetting($a_obj_id);
		$this->object_position_cache[$a_obj_id] = $object_position->isActive();
		return $this->object_position_cache[$a_obj_id];
	}
	
	/**
	 * read settings
	 */
	protected function readSettings()
	{
		foreach($this->object_definition->getOrgUnitPermissionTypes() as $type)
		{
			$this->position_settings[$type] = new ilOrgUnitObjectTypePositionSetting($type);
		}
	}
}
?>