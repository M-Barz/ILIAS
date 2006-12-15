<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

/**
* Class ilLPObjSettings
*
* @author Stefan Meyer <smeyer@databay.de>
*
* @version $Id$
*
* @package ilias-tracking
*
*/

define("LP_MODE_DEACTIVATED",0);
define("LP_MODE_TLT",1);
define("LP_MODE_VISITS",2);
define("LP_MODE_MANUAL",3);
define("LP_MODE_OBJECTIVES",4);
define("LP_MODE_COLLECTION",5);
define("LP_MODE_SCORM",6);
define("LP_MODE_TEST_FINISHED",7);
define("LP_MODE_TEST_PASSED",8);
define("LP_MODE_EXERCISE_RETURNED",9);
define("LP_MODE_EVENT",10);

define("LP_DEFAULT_VISITS",30);


class ilLPObjSettings
{
	var $db = null;

	var $obj_id = null;
	var $obj_type = null;
	var $obj_mode = null;
	var $visits = null;

	var $is_stored = false;

	function ilLPObjSettings($a_obj_id)
	{
		global $ilObjDataCache,$ilDB;

		$this->db =& $ilDB;

		$this->obj_id = $a_obj_id;

		if(!$this->__read())
		{
			$this->obj_type = $ilObjDataCache->lookupType($this->obj_id);
			$this->obj_mode = $this->__getDefaultMode($this->obj_id,$this->obj_type);
		}
	}

	function getVisits()
	{
		return (int) $this->visits ? $this->visits : LP_DEFAULT_VISITS;
	}

	function setVisits($a_visits)
	{
		$this->visits = $a_visits;
	}

	function setMode($a_mode)
	{
		$this->obj_mode = $a_mode;
	}
	function getMode()
	{
		return $this->obj_mode;
	}

	function getObjId()
	{
		return (int) $this->obj_id;
	}
	function getObjType()
	{
		return $this->obj_type;
	}

	function update()
	{
		if(!$this->is_stored)
		{
			return $this->insert();
		}
		$query = "UPDATE ut_lp_settings SET mode = '".$this->obj_mode.
			"', visits = '".$this->visits."' ".
			"WHERE obj_id = '".$this->getObjId()."'";
		$this->db->query($query);
		$this->__read();
		return true;
	}

	function insert()
	{
		$query = "INSERT INTO ut_lp_settings SET obj_id = '".$this->obj_id."', ".
			"obj_type = '".$this->obj_type."', ".
			"mode = '".$this->obj_mode."'";
		$this->db->query($query);
		$this->__read();
		return true;
	}


	// Static
	function _lookupVisits($a_obj_id)
	{
		global $ilDB;

		#echo $a_obj_id;

		$query = "SELECT visits FROM ut_lp_settings ".
			"WHERE obj_id = '".$a_obj_id."'";

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->visits;
		}
		return LP_DEFAULT_VISITS;
	}

	function _isContainer($a_mode)
	{
		return $a_mode == LP_MODE_COLLECTION or
			$a_mode == LP_MODE_SCORM or
			$a_mode == LP_MODE_OBJECTIVES;
	}
		

	function _delete($a_obj_id)
	{
		global $ilDB;

		$query = "DELETE FROM ut_lp_settings WHERE obj_id = '".$a_obj_id."'";
		$ilDB->query($query);

		return true;
	}

	function _lookupMode($a_obj_id)
	{
		global $ilDB,$ilObjDataCache;

		if(ilLPObjSettings::_checkObjectives($a_obj_id))
		{
			return LP_MODE_OBJECTIVES;
		}

		$query = "SELECT mode FROM ut_lp_settings ".
			"WHERE obj_id = '".$a_obj_id."'";

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->mode;
		}
		
		// no db entry exists => return default mode by type
		return ilLPObjSettings::__getDefaultMode($a_obj_id,$ilObjDataCache->lookupType($a_obj_id));
	}

	function getValidModes()
	{
		global $lng;

		switch($this->obj_type)
		{
			case 'crs':
				if(ilLPObjSettings::_checkObjectives($this->getObjId()))
				{
					return array(LP_MODE_OBJECTIVES => $lng->txt('trac_mode_objectives'));
				}

				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
							 LP_MODE_MANUAL => $lng->txt('trac_mode_manual'),
							 #LP_MODE_OBJECTIVES => $lng->txt('trac_mode_objectives'),
							 LP_MODE_COLLECTION => $lng->txt('trac_mode_collection'));

				break;

			case 'dbk':
			case 'lm':
				return array(LP_MODE_MANUAL => $lng->txt('trac_mode_manual'),
							 LP_MODE_VISITS => $lng->txt('trac_mode_visits'),
							 LP_MODE_TLT => $lng->txt('trac_mode_tlt'),
							 LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'));

			case 'htlm':
				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
							 LP_MODE_MANUAL => $lng->txt('trac_mode_manual'));

			case 'sahs':
				include_once './Services/Tracking/classes/class.ilLPCollections.php';

				if(ilLPCollections::_getCountPossibleSAHSItems($this->getObjId()))
				{
					return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
								 LP_MODE_SCORM => $lng->txt('trac_mode_scorm_aicc'));
				}
				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'));

			case 'tst':
				return array(LP_MODE_TEST_FINISHED => $lng->txt('trac_mode_test_finished'),
							 LP_MODE_TEST_PASSED => $lng->txt('trac_mode_test_passed'),
							 LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'));

			case 'exc':
				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
							 LP_MODE_EXERCISE_RETURNED => $lng->txt('trac_mode_exercise_returned'));

			case 'grp':
				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
							 LP_MODE_MANUAL => $lng->txt('trac_mode_manual'),
							 LP_MODE_COLLECTION => $lng->txt('trac_mode_collection'));

			case 'fold':
				return array(LP_MODE_DEACTIVATED => $lng->txt('trac_mode_deactivated'),
							 LP_MODE_COLLECTION => $lng->txt('trac_mode_collection'));

			default:
				return array();
		}
	}

	function _mode2Text($a_mode)
	{
		global $lng;

		switch($a_mode)
		{
			case LP_MODE_DEACTIVATED:
				return $lng->txt('trac_mode_deactivated');

			case LP_MODE_TLT:
				return $lng->txt('trac_mode_tlt');

			case LP_MODE_VISITS:
				return $lng->txt('trac_mode_visits');
				
			case LP_MODE_MANUAL:
				return $lng->txt('trac_mode_manual');

			case LP_MODE_OBJECTIVES:
				return $lng->txt('trac_mode_objectives');

			case LP_MODE_COLLECTION:
				return $lng->txt('trac_mode_collection');

			case LP_MODE_SCORM:
				return $lng->txt('trac_mode_scorm');

			case LP_MODE_TEST_FINISHED:
				return $lng->txt('trac_mode_test_finished');

			case LP_MODE_TEST_PASSED:
				return $lng->txt('trac_mode_test_passed');

			case LP_MODE_EXERCISE_RETURNED:
				return $lng->txt('trac_mode_exercise_returned');

		}
	}
							 
				


	// Private
	function _checkObjectives($a_obj_id)
	{
		global $ilDB,$ilObjDataCache;

		// Return deactivate for course with objective view
		if($ilObjDataCache->lookupType($a_obj_id) == 'crs')
		{
			include_once 'Modules/Course/classes/class.ilObjCourse.php';

			if(ilObjCourse::_lookupViewMode($a_obj_id) == IL_CRS_VIEW_OBJECTIVE)
			{
				return true;
			}
		}
		return false;
	}
		


	function __read()
	{
		$res = $this->db->query("SELECT * FROM ut_lp_settings WHERE obj_id = '".$this->db->quote($this->obj_id)."'");
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$this->is_stored = true;
			$this->obj_type = $row->obj_type;
			$this->obj_mode = $row->mode;
			$this->visits = $row->visits;

			if(ilLPObjSettings::_checkObjectives($this->obj_id))
			{
				$this->obj_mode = LP_MODE_OBJECTIVES;
			}

			return true;
		}

		return false;
	}

	function __getDefaultMode($a_obj_id,$a_type)
	{
		global $ilDB;

		#$type = strlen($a_type) ? $a_type : $this->obj_type;

		switch($a_type)
		{
			case 'crs':
				// If objectives are enabled return deactivated
				if(ilLPObjSettings::_checkObjectives($a_obj_id))
				{
					return LP_MODE_OBJECTIVES;
				}
				return LP_MODE_MANUAL;

			case 'dbk':
			case 'lm':
			case 'htlm':
				return LP_MODE_MANUAL;

			case 'sahs':
				return LP_MODE_DEACTIVATED;

			case 'dbk':
				return LP_MODE_MANUAL;

			case 'tst':
				return LP_MODE_TEST_PASSED;

			case 'exc':
				return LP_MODE_EXERCISE_RETURNED;

			case 'grp':
				return LP_MODE_DEACTIVATED;

			case 'fold':
				return LP_MODE_DEACTIVATED;
					
			default:
				return LP_MODE_UNDEFINED;
		}
	}
}
?>