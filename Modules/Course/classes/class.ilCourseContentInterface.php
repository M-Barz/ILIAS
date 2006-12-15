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
* class ilobjcourse
*
* @author Stefan Meyer <smeyer@databay.de> 
* @version $Id$
* This class is aggregated in folders, groups which have a parent course object
*
* @ilCtrl_Calls ilCourseContentInterface: ilConditionHandlerInterface, ilCourseItemAdministrationGUI

* @extends Object
*/

class ilCourseContentInterface
{
	var $cci_course_obj;
	var $cci_course_id;
	var $cci_ref_id;
	var $cci_client_class;

	var $chi_obj;
	

	function ilCourseContentInterface(&$client_class,$a_ref_id)
	{
		global $lng,$tpl,$ilCtrl,$tree,$ilUser,$ilTabs;

		$this->lng =& $lng;
		$this->tpl =& $tpl;
		$this->ctrl =& $ilCtrl;
		$this->tree =& $tree;
		$this->ilUser =& $ilUser;
		$this->tabs_gui =& $ilTabs;

		$this->cci_ref_id = $a_ref_id;
		$this->cci_read();
		$this->cci_client_class = strtolower(get_class($client_class));

		$this->cci_client_obj =& $client_class;
		$this->cci_course_obj =& ilObjectFactory::getInstanceByRefId($this->cci_course_id);
		$this->cci_course_obj->initCourseItemObject($this->cci_ref_id);

		$this->lng->loadLanguageModule('crs');
		
		return true;
	}

	function &executeCommand()
	{
		global $rbacsystem;

		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();


		switch($next_class)
		{

			case "ilconditionhandlerinterface":
				include_once './classes/class.ilConditionHandlerInterface.php';

				$new_gui =& new ilConditionHandlerInterface($this,$_GET['item_id']);
				$this->ctrl->saveParameter($this,'item_id',$_GET['item_id']);
				//$new_gui->setBackButtons(array('edit' => $this->ctrl->getLinkTarget($this,'cciEdit'),
				//							   'preconditions' => $this->ctrl->getLinkTargetByClass('ilconditionhandlerinterface',
				//																					'listConditions')));
				$this->ctrl->forwardCommand($new_gui);
				break;

			default:
				if(!$cmd)
				{
					$cmd = "view";
				}
				$this->$cmd();
					
				break;
		}
		return true;
	}

	function cci_init(&$client_class,$a_ref_id)
	{
		$this->cci_ref_id = $a_ref_id;
		$this->cci_read();
		$this->cci_client_class = strtolower(get_class($client_class));

		$this->cci_course_obj =& ilObjectFactory::getInstanceByRefId($this->cci_course_id);
		$this->cci_course_obj->initCourseItemObject($this->cci_ref_id);

		$this->lng->loadLanguageModule('crs');
		
		return true;
	}
	
	function cci_objectives_ask_reset()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.crs_objectives_ask_reset.html","course");
		$this->tabs_gui->setTabActive('content');

		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this->cci_client_obj));
		$this->tpl->setVariable("INFO_STRING",$this->lng->txt('crs_objectives_reset_sure'));
		$this->tpl->setVariable("TXT_CANCEL",$this->lng->txt('cancel'));
		$this->tpl->setVariable("TXT_RESET",$this->lng->txt('reset'));

		return true;
	}
	
	/*
	 * set container (gui) object (e.g. instance of ilObjCourseGUI, ilObjGroupGUI, ...)
	 *
	 * @param	object		container gui object
	 */
	function cci_setContainer(&$a_container)
	{
		$this->container =& $a_container;
	}

	function cci_view()
	{
		global $objDefinition;

		include_once "./classes/class.ilRepositoryExplorer.php";
		include_once "./payment/classes/class.ilPaymentObject.php";
		include_once './Modules/Course/classes/class.ilCourseStart.php';
		include_once './classes/class.ilObjectListGUIFactory.php';

		global $rbacsystem;
		global $ilias;
		global $ilUser;

		$this->cci_client_obj->showPossibleSubObjects();

		$write_perm = $rbacsystem->checkAccess("write",$this->cci_ref_id);
		$enabled_objectives = $this->cci_course_obj->enabledObjectiveView();
		$view_objectives = ($enabled_objectives and ($this->cci_ref_id == $this->cci_course_obj->getRefId()));

		// Jump to start objects if there is one
		$start_obj =& new ilCourseStart($this->cci_course_obj->getRefId(),$this->cci_course_obj->getId());
		if(count($this->starter = $start_obj->getStartObjects()) and 
		   !$start_obj->isFullfilled($ilUser->getId()) and 
		   !$write_perm)
		{
			$this->cci_start_objects();

			return true;
		}

		// Jump to objective view if selected or user is only member
		if(($view_objectives and !$write_perm) or ($_SESSION['crs_viewmode'] == 'objectives' and $write_perm))
		{
			$this->cci_objectives();

			return true;
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.crs_view.html","course");

		if($this->cci_client_obj->object->getType()=='crs'){
			include_once('Services/Feedback/classes/class.ilFeedbackGUI.php');
			$feedbackGUI = new ilFeedbackGUI();
			$feedbackHTML = $feedbackGUI->getCRSFeedbackListHTML();
			$this->tpl->setVariable("FEEDBACK",$feedbackHTML);
		}

		if($write_perm and $enabled_objectives)
		{
			$this->tabs_gui->setTabActive('edit_content');
			$items = $this->cci_course_obj->items_obj->getAllItems();
		}
		elseif($write_perm)
		{
			// (do not set tab, if we are in folder/group)
			if (strtolower(get_class($this->container)) == "ilobjcoursegui")
			{
				$this->tabs_gui->setTabActive('content');
			}
			$items = $this->cci_course_obj->items_obj->getAllItems();
		}
		else
		{
			// (do not set tab, if we are in folder/group)
			if (strtolower(get_class($this->container)) == "ilobjcoursegui")
			{
				$this->tabs_gui->setTabActive('content');
			}
			$items = $this->cci_course_obj->items_obj->getAllItems();
		}
		// NO ITEMS FOUND
		if(!count($items))
		{
			sendInfo($this->lng->txt("crs_no_items_found"));
			$this->tpl->addBlockFile("CONTENT_TABLE", "content_tab", "tpl.container_page.html");
			$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this->container));
			$this->tpl->setVariable("CONTAINER_PAGE_CONTENT", "");
			$this->container->showAdministrationPanel($this->tpl);
			return true;
		}

		$tpl =& new ilTemplate("tpl.table.html", true, true);

		$maxcount = count($items);

		#$cont_arr = array_slice($items, $_GET["offset"], $_GET["limit"]);
		// no limit
		$cont_arr = $items;

		$tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.crs_content_row.html","course");
		$cont_num = count($cont_arr);
		
		$this->container->clearAdminCommandsDetermination();

		// render table content data
		if ($cont_num > 0)
		{
			// counter for rowcolor change
			$num = 0;
			foreach ($cont_arr as $cont_data)
			{
				$conditions_ok = ilConditionHandler::_checkAllConditionsOfTarget($cont_data['obj_id']);

				#if ($rbacsystem->checkAccess('read',$cont_data["ref_id"]) and 
				#	($conditions_ok or $rbacsystem->checkAccess('write',$cont_data['ref_id'])))

				$tpl->setCurrentBlock("tbl_content");

				//}
				//$tpl->setVariable("DESCRIPTION", $cont_data["description"]);

				// ACTIVATION
				$buyable = ilPaymentObject::_isBuyable($this->cci_ref_id);
				if (($rbacsystem->checkAccess('write',$this->cci_ref_id) ||
					 $buyable == false) &&
					$cont_data["timing_type"] != IL_CRS_TIMINGS_ACTIVATION)
				{
					//$activation = $this->lng->txt("crs_unlimited");
					$activation = "";
				}
				else if ($buyable)
				{
					if (is_array($activation = ilPaymentObject::_getActivation($this->cci_ref_id)))
					{
						$activation = $this->lng->txt("crs_from")." ".

						$activation = $this->lng->txt("crs_from")." ".strftime("%Y-%m-%d %R",$activation["activation_start"]).
							" ".$this->lng->txt("crs_to")." ".strftime("%Y-%m-%d %R",$activation["activation_end"]);
					}
					else
					{
						$activation = "N/A";
					}
				}
				elseif($cont_data['timing_type'] == IL_CRS_TIMINGS_ACTIVATION)
				{
					$activation = $this->lng->txt("crs_from").' '.ilFormat::formatUnixTime($cont_data['timing_start'],true).' '.
						$this->lng->txt("crs_to").' '.ilFormat::formatUnixTime($cont_data['timing_end'],true);
				}
				//$tpl->setVariable("ACTIVATION_END",$activation);
				
				// get item list gui object
				if (!is_object ($this->list_gui[$cont_data["type"]]))
				{
					$item_list_gui =& ilObjectListGUIFactory::_getListGUIByType($cont_data["type"]);
					$item_list_gui->setContainerObject($this->container);
					
					// Enable/disable subscription depending on course settings
					$item_list_gui->enableSubscribe($this->cci_course_obj->getAboStatus());

					$this->list_gui[$cont_data["type"]] =& $item_list_gui;
				}
				else
				{
					$item_list_gui =& $this->list_gui[$cont_data["type"]];
				}
				
				// show administration command buttons (or not)
				if (!$this->container->isActiveAdministrationPanel())
				{
					$item_list_gui->enableDelete(false);
					$item_list_gui->enableLink(false);
					$item_list_gui->enableCut(false);
				}
				
				// add activation custom property
				if ($activation != "")
				{
					$item_list_gui->addCustomProperty($this->lng->txt("activation"), $activation,
						false, true);
				}
				
				if($write_perm)
				{
					$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"ref_id",
													 $this->cci_client_obj->object->getRefId());
					$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"item_id",
													 $cont_data['child']);

					$item_list_gui->addCustomCommand($this->ctrl->getLinkTargetByClass('ilCourseItemAdministrationGUI',
																					   'edit'),
													 'activation');
				}
				
				$html = $item_list_gui->getListItemHTML($cont_data['ref_id'],
					$cont_data['obj_id'], $cont_data['title'], $cont_data['description']);
					
				$this->container->determineAdminCommands($cont_data['ref_id'],
					$item_list_gui->adminCommandsIncluded());

				if(strlen($html))
				{
					$tpl->setVariable("ITEM_HTML", $html);
				}

				// OPTIONS
				if($write_perm)
				{
					$images = array();
					if($this->cci_course_obj->getOrderType() == $this->cci_course_obj->SORT_MANUAL)
					{
						if($num != 0)
						{
							$tmp_array["gif"] = ilUtil::getImagePath("a_up.gif");
							$tmp_array["lng"] = $this->lng->txt("crs_move_up");

							$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"ref_id",
															 $this->cci_client_obj->object->getRefId());
							$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"item_id",
															 $cont_data['child']);
							$tmp_array['lnk'] = $this->ctrl->getLinkTargetByClass('ilcourseitemadministrationgui','moveUp');
							$tmp_array["tar"] = "";

							$images[] = $tmp_array;
						}
						if($num != count($cont_arr) - 1)
						{
							$tmp_array["gif"] = ilUtil::getImagePath("a_down.gif");
							$tmp_array["lng"] = $this->lng->txt("crs_move_down");
							$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"ref_id",
															 $this->cci_client_obj->object->getRefId());
							$this->ctrl->setParameterByClass('ilcourseitemadministrationgui',"item_id",
															 $cont_data['child']);
							$tmp_array['lnk'] = $this->ctrl->getLinkTargetByClass('ilcourseitemadministrationgui','moveDown');
							
							$images[] = $tmp_array;
						}
					}
										
					foreach($images as $key => $image)
					{
						$tpl->setCurrentBlock("img");
						$tpl->setVariable("IMG_TYPE",$image["gif"]);
						$tpl->setVariable("IMG_ALT",$image["lng"]);
						$tpl->setVariable("IMG_LINK",$image["lnk"]);
						$tpl->setVariable("IMG_TARGET",$image["tar"]);
						$tpl->parseCurrentBlock();
					}
					unset($images);
					
					$tpl->setCurrentBlock("options");
					$tpl->setVariable("OPT_ROWCOL", ilUtil::switchColor($num,"tblrow1","tblrow2"));
					$tpl->parseCurrentBlock();
 				} // END write perm

				if(strlen($html))
				{
					if ($this->container->isActiveAdministrationPanel())
					{
						$tpl->setCurrentBlock("block_row_check");
						$tpl->setVariable("ITEM_ID", $cont_data['ref_id']);
						$tpl->parseCurrentBlock();
						//$nbsp = false;
					}

					// change row color
					$tpl->setVariable("ROWCOL", ilUtil::switchColor($num,"tblrow1","tblrow2"));
					$tpl->setVariable("TYPE_IMG", ilUtil::getImagePath("icon_".$cont_data["type"].".gif"));
					$tpl->setVariable("ALT_IMG", $this->lng->txt("obj_".$cont_data["type"]));
					$tpl->setCurrentBlock("tbl_content");
					$tpl->parseCurrentBlock();
					$num++;
				}
			}
		}

		// create table
		include_once "./Services/Table/classes/class.ilTableGUI.php";
		$tbl = new ilTableGUI();

		// title & header columns
		$tbl->setTitle($this->lng->txt("crs_content"),"icon_crs.gif",$this->lng->txt("courses"));
		$tbl->setHelp("tbl_help.php","icon_help.gif",$this->lng->txt("help"));

		if($write_perm)
		{
			$tbl->setHeaderNames(array($this->lng->txt("type"),$this->lng->txt("title"),
									   ""));
			$tbl->setHeaderVars(array("type","title","options"), 
								array("ref_id" => $this->cci_course_obj->getRefId(),
									  "cmdClass" => "ilobjcoursegui",
									  "cmdNode" => $_GET["cmdNode"]));
			$tbl->setColumnWidth(array("1px","100%","24px"));
			$tbl->disable("header");
		}
		else
		{
			$tbl->setHeaderNames(array($this->lng->txt("type"),$this->lng->txt("title")));
			$tbl->setHeaderVars(array("type","title"), 
								array("ref_id" => $this->cci_course_obj->getRefId(),
									  "cmdClass" => "ilobjcoursegui",
									  "cmdNode" => $_GET["cmdNode"]));
			$tbl->setColumnWidth(array("1px",""));
			$tbl->disable("header");
		}

		$tbl->setLimit($_GET["limit"]);
		$tbl->setOffset($_GET["offset"]);
		$tbl->setMaxCount($maxcount);

		// footer
		$tbl->disable("footer");
		$tbl->disable('sort');
		$tbl->disable("form");

		// render table
		$tbl->setTemplate($tpl);
		$tbl->render();

		$this->tpl->addBlockFile("CONTENT_TABLE", "content_tab", "tpl.container_page.html");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this->container));
		$this->tpl->setVariable("CONTAINER_PAGE_CONTENT", $tpl->get());

		$this->container->showAdministrationPanel($this->tpl);
		
		return true;
	}
	
	function cci_start_objects()
	{
		include_once './Modules/Course/classes/class.ilCourseLMHistory.php';
		include_once './classes/class.ilRepositoryExplorer.php';

		global $rbacsystem,$ilias,$ilUser;

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.crs_start_view.html","course");
		#$this->__showButton('view',$this->lng->txt('refresh'));
		
		$this->tpl->setVariable("INFO_STRING",$this->lng->txt('crs_info_start'));
		$this->tpl->setVariable("TBL_TITLE_START",$this->lng->txt('crs_table_start_objects'));
		$this->tpl->setVariable("HEADER_NR",$this->lng->txt('crs_nr'));
		$this->tpl->setVariable("HEADER_DESC",$this->lng->txt('description'));
		$this->tpl->setVariable("HEADER_EDITED",$this->lng->txt('crs_objective_accomplished'));


		$lm_continue =& new ilCourseLMHistory($this->cci_ref_id,$ilUser->getId());
		$continue_data = $lm_continue->getLMHistory();

		$counter = 0;
		foreach($this->starter as $start)
		{
			$tmp_obj =& ilObjectFactory::getInstanceByRefId($start['item_ref_id']);

			$conditions_ok = ilConditionHandler::_checkAllConditionsOfTarget($tmp_obj->getId());

			$obj_link = ilRepositoryExplorer::buildLinkTarget($tmp_obj->getRefId(),$tmp_obj->getType());
			$obj_frame = ilRepositoryExplorer::buildFrameTarget($tmp_obj->getType(),$tmp_obj->getRefId(),$tmp_obj->getId());
			$obj_frame = $obj_frame ? $obj_frame : '';

			// Tmp fix for tests
			$obj_frame = $tmp_obj->getType() == 'tst' ? '' : $obj_frame;

			$contentObj = false;

			if(ilRepositoryExplorer::isClickable($tmp_obj->getType(),$tmp_obj->getRefId(),$tmp_obj->getId()))
			{
				$this->tpl->setCurrentBlock("start_read");
				$this->tpl->setVariable("READ_TITLE_START",$tmp_obj->getTitle());
				$this->tpl->setVariable("READ_TARGET_START",$obj_frame);
				$this->tpl->setVariable("READ_LINK_START", $obj_link.'&crs_show_result='.$this->cci_ref_id);
				$this->tpl->parseCurrentBlock();
			}
			else
			{
				$this->tpl->setCurrentBlock("start_visible");
				$this->tpl->setVariable("VISIBLE_LINK_START",$tmp_obj->getTitle());
				$this->tpl->parseCurrentBlock();
			}
			// add to desktop link
			if(!$ilias->account->isDesktopItem($tmp_obj->getRefId(),$tmp_obj->getType()) and 
			   ($this->cci_course_obj->getAboStatus() == $this->cci_course_obj->ABO_ENABLED))
			{
				if ($rbacsystem->checkAccess('read',$tmp_obj->getRefId()))
				{
					$this->tpl->setCurrentBlock("start_desklink");
					#$this->tpl->setVariable("DESK_LINK_START", "repository.php?cmd=addToDeskCourse&ref_id=".$this->cci_ref_id.
					#						"&item_ref_id=".$tmp_obj->getRefId()."&type=".$tmp_obj->getType());

					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_ref_id',$tmp_obj->getRefId());
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_id',$tmp_obj->getRefId());
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'type',$tmp_obj->getType());
					
					$this->tpl->setVariable("DESK_LINK_START",$this->ctrl->getLinkTarget($this->cci_client_obj,'addToDesk'));

					$this->tpl->setVariable("TXT_DESK_START", $this->lng->txt("to_desktop"));
					$this->tpl->parseCurrentBlock();
				}
			}

			// CONTINUE LINK
			if(isset($continue_data[$tmp_obj->getRefId()]))
			{
				$this->tpl->setCurrentBlock("start_continuelink");
				$this->tpl->setVariable("CONTINUE_LINK_START",'./ilias.php?baseClass=ilLMPresentationGUI&ref_id='.$tmp_obj->getRefId().'&obj_id='.
										$continue_data[$tmp_obj->getRefId()]['lm_page_id']);

				//$target = $ilias->ini->readVariable("layout","view_target") == "frame" ? 
				//	'' :
				//	'ilContObj'.$cont_data[$obj_id]['obj_page_id'];
				$target = '';
					
				$this->tpl->setVariable("CONTINUE_LINK_TARGET",$target);
				$this->tpl->setVariable("TXT_CONTINUE_START",$this->lng->txt('continue_work'));
				$this->tpl->parseCurrentBlock();
			}

			// Description
			if(strlen($tmp_obj->getDescription()))
			{
				$this->tpl->setCurrentBlock("start_description");
				$this->tpl->setVariable("DESCRIPTION_START",$tmp_obj->getDescription());
				$this->tpl->parseCurrentBlock();
			}

			switch($tmp_obj->getType())
			{
				case 'tst':
					include_once './Modules/Test/classes/class.ilObjTestAccess.php';
					$accomplished = ilObjTestAccess::_checkCondition($tmp_obj->getId(),'finished','') ? 'accomplished' : 'not_accomplished';
					break;

				case 'sahs':
					include_once './Services/Tracking/classes/class.ilLPStatusSCORM.php';
					$completed = ilLPStatusSCORM::_getCompleted($tmp_obj->getId());
					$accomplished = in_array($ilUser->getId(),$completed) ? 'accomplished' : 'not_accomplished';
					break;

				default:
					$accomplished = isset($continue_data[$tmp_obj->getRefId()]) ? 'accomplished' : 'not_accomplished';
					break;
			}
			$this->tpl->setCurrentBlock("start_row");
			$this->tpl->setVariable("EDITED_IMG",ilUtil::getImagePath('crs_'.$accomplished.'.gif'));
			$this->tpl->setVariable("EDITED_ALT",$this->lng->txt('crs_objective_'.$accomplished));
			$this->tpl->setVariable("ROW_CLASS",'option_value');
			$this->tpl->setVariable("ROW_CLASS_CENTER",'option_value_center');
			$this->tpl->setVariable("OBJ_NR_START",++$counter.'.');
			$this->tpl->parseCurrentBlock();
		}			
		return true;
	}

	function cci_objectives()
	{
		include_once "./Modules/Course/classes/class.ilCourseStart.php";

		global $rbacsystem,$ilUser,$ilBench;

		$ilBench->start('Objectives','Objectives_view');

		// Jump to start objects if there is one

		$ilBench->start('Objectives','Objectives_start_objects');
		if(!$_SESSION['objectives_fullfilled'][$this->cci_course_obj->getId()])
		{
			$start_obj =& new ilCourseStart($this->cci_course_obj->getRefId(),$this->cci_course_obj->getId());
			if(count($this->starter = $start_obj->getStartObjects()) and !$start_obj->isFullfilled($ilUser->getId()))
			{
				$this->cci_start_objects();
				
				return true;
			}
			$_SESSION['objectives_fullfilled'][$this->cci_course_obj->getId()] = true;
		}
		$ilBench->stop('Objectives','Objectives_start_objects');

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.crs_objective_view.html","course");
		$this->__showButton('cciObjectivesAskReset',$this->lng->txt('crs_reset_results'));

		$ilBench->start('Objectives','Objectives_read');

		$ilBench->start('Objectives','Objectives_read_accomplished');
		$this->__readAccomplished();
		$ilBench->stop('Objectives','Objectives_read_accomplished');

		$ilBench->start('Objectives','Objectives_read_suggested');
		$this->__readSuggested();
		$ilBench->stop('Objectives','Objectives_read_suggested');

		$ilBench->start('Objectives','Objectives_read_status');
		$this->__readStatus();
		$ilBench->stop('Objectives','Objectives_read_status');

		$ilBench->stop('Objectives','Objectives_read');

		// (1) show infos
		$this->__showInfo();

		// (2) show objectives
		$ilBench->start('Objectives','Objectives_objectives');
		$this->__showObjectives();
		$ilBench->stop('Objectives','Objectives_objectives');

		// (3) show lm's
		$ilBench->start('Objectives','Objectives_lms');
		$this->__showLearningMaterials();
		$ilBench->stop('Objectives','Objectives_lms');

		// (4) show tests
		$ilBench->start('Objectives','Objectives_tests');
		$this->__showTests();
		$ilBench->stop('Objectives','Objectives_tests');

		// (5) show other resources
		$ilBench->start('Objectives','Objectives_or');
		$this->__showOtherResources();
		$ilBench->stop('Objectives','Objectives_or');

		$ilBench->stop('Objectives','Objectives_view');

		$ilBench->save();

		return true;
	}

	// PRIVATE
	function __showHideLinks($a_part)
	{
		if($_GET['show_hide_'.$a_part] == 1)
		{
			unset($_SESSION['crs_hide_'.$a_part]);
		}
		if($_GET['show_hide_'.$a_part] == 2)
		{
			$_SESSION['crs_hide_'.$a_part] = true;
		}

		$this->ctrl->setParameter($this->cci_client_obj,'show_hide_'.$a_part,$_SESSION['crs_hide_'.$a_part] ? 1 : 2);
		$this->tpl->setVariable("LINK_HIDE_SHOW_".strtoupper($a_part),$this->ctrl->getLinkTarget($this->cci_client_obj,'cciObjectives'));
		$this->tpl->setVariable("TXT_HIDE_SHOW_".strtoupper($a_part),$_SESSION['crs_hide_'.$a_part] ? 
								$this->lng->txt('crs_show_link_'.$a_part) :
								$this->lng->txt('crs_hide_link_'.$a_part));

		$this->ctrl->setParameter($this->cci_client_obj,'show_hide_'.$a_part,'');

		$this->tpl->setVariable("HIDE_SHOW_IMG_".strtoupper($a_part),$_SESSION['crs_hide_'.$a_part] ? 
								ilUtil::getImagePath('a_down.gif') :
								ilUtil::getImagePath('a_up.gif'));

		return true;
	}
	
	function __getAllLearningMaterials()
	{
		foreach($items = $this->cci_course_obj->items_obj->getItems() as $node)
		{
			switch($node['type'])
			{
				case 'lm':
				case 'htlm':
				case 'alm':
				case 'sahs':
					$all_lms[] = $node['ref_id'];
					break;
			}
		}
		return $all_lms ? $all_lms : array();
	}

	function __getAllTests()
	{
		foreach($items = $this->cci_course_obj->items_obj->getItems() as $node)
		{
			switch($node['type'])
			{
				case 'tst':
					$tests[] = $node['ref_id'];
					break;
			}
		}
		return $tests ? $tests : array();
	}

	function __getOtherResources()
	{
		foreach($items = $this->cci_course_obj->items_obj->getItems() as $node)
		{
			switch($node['type'])
			{
				case 'lm':
				case 'htlm':
				case 'sahs':
				case 'tst':
					continue;

				default:
					$all_lms[] = $node['ref_id'];
					break;
			}
		}
		return $all_lms ? $all_lms : array();
	}

	function __showInfo()
	{
		include_once './Modules/Course/classes/class.ilCourseObjective.php';

		if(!count($objective_ids = ilCourseObjective::_getObjectiveIds($this->cci_course_obj->getId())))
		{
			return true;
		}

		$this->tpl->addBlockfile('INFO_BLOCK','info_block','tpl.crs_objectives_view_info_table.html','course');
		$this->tpl->setVariable("INFO_STRING",$this->lng->txt('crs_objectives_info_'.$this->objective_status));
		
		return true;
	}
		

	function __showOtherResources()
	{
		global $ilias,$rbacsystem,$ilObjDataCache;

		if(!count($ors = $this->__getOtherResources()))
		{
			return false;
		}

		$this->tpl->addBlockfile('RESOURCES_BLOCK','resources_block','tpl.crs_objectives_view_or_table.html','course');
		$this->tpl->setVariable("TBL_TITLE_OR",$this->lng->txt('crs_other_resources'));


		$this->__showHideLinks('or');

		if(isset($_SESSION['crs_hide_or']))
		{
			return true;
		}

		$this->tpl->setCurrentBlock("tbl_header_columns_or");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_OR","5%");
		$this->tpl->setVariable("TBL_HEADER_NAME_OR",$this->lng->txt('type'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_or");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_OR","75%");
		$this->tpl->setVariable("TBL_HEADER_NAME_OR",$this->lng->txt('description'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_or");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_OR","20%");
		$this->tpl->setVariable("TBL_HEADER_NAME_OR",$this->lng->txt('actions'));
		$this->tpl->parseCurrentBlock();

		$counter = 1;
		foreach($ors as $or_id)
		{
			$obj_id = $ilObjDataCache->lookupObjId($or_id);
			$obj_type = $ilObjDataCache->lookupType($obj_id);

			
			$conditions_ok = ilConditionHandler::_checkAllConditionsOfTarget($obj_id);
				
			$obj_link = ilRepositoryExplorer::buildLinkTarget($or_id,$obj_type);
			$obj_frame = ilRepositoryExplorer::buildFrameTarget($obj_type,$or_id,$obj_id);
			$obj_frame = $obj_frame ? $obj_frame : '';

			if(ilRepositoryExplorer::isClickable($obj_type,$or_id,$obj_id))
			{
				$this->tpl->setCurrentBlock("or_read");
				$this->tpl->setVariable("READ_TITLE_OR",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->setVariable("READ_TARGET_OR",$obj_frame);
				$this->tpl->setVariable("READ_LINK_OR", $obj_link);
				$this->tpl->parseCurrentBlock();
			}
			else
			{
				$this->tpl->setCurrentBlock("or_visible");
				$this->tpl->setVariable("VISIBLE_LINK_OR",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->parseCurrentBlock();
			}
				// add to desktop link
			if(!$ilias->account->isDesktopItem($or_id,$obj_type) and 
			   ($this->cci_course_obj->getAboStatus() == $this->cci_course_obj->ABO_ENABLED))
			{
				if ($rbacsystem->checkAccess('read',$or_id))
				{
					$this->tpl->setCurrentBlock("or_desklink");
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_ref_id',$or_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_id',$or_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'type',$obj_type);
					
					$this->tpl->setVariable("DESK_LINK_OR",$this->ctrl->getLinkTarget($this->cci_client_obj,'addToDesk'));

					$this->tpl->setVariable("TXT_DESK_OR", $this->lng->txt("to_desktop"));
					$this->tpl->parseCurrentBlock();
				}
			}
			
			$this->tpl->setCurrentBlock("or_row");
			$this->tpl->setVariable("OBJ_TITLE_OR",$ilObjDataCache->lookupTitle($obj_id));
			$this->tpl->setVariable("IMG_TYPE_OR",ilUtil::getImagePath('icon_'.$obj_type.'.gif'));
			$this->tpl->setVariable("TXT_IMG_OR",$this->lng->txt('obj_'.$obj_type));
			$this->tpl->setVariable("OBJ_CLASS_CENTER_OR",'option_value_center');
			$this->tpl->setVariable("OBJ_CLASS_OR",'option_value');
			$this->tpl->parseCurrentBlock();

			unset($tmp_or);
			++$counter;
		}
	}


	function __showLearningMaterials()
	{
		global $rbacsystem,$ilias,$ilUser,$ilObjDataCache;

		include_once './Modules/Course/classes/class.ilCourseObjectiveLM.php';
		include_once './classes/class.ilRepositoryExplorer.php';
		include_once './Modules/Course/classes/class.ilCourseLMHistory.php';

		if(!count($lms = $this->__getAllLearningMaterials()))
		{
			return false;
		}
		if($this->details_id)
		{
			$objectives_lm_obj =& new ilCourseObjectiveLM($this->details_id);
		}

		$lm_continue =& new ilCourseLMHistory($this->cci_ref_id,$ilUser->getId());
		$continue_data = $lm_continue->getLMHistory();

		$this->tpl->addBlockfile('LM_BLOCK','lm_block','tpl.crs_objectives_view_lm_table.html','course');
		$this->tpl->setVariable("TBL_TITLE_LMS",$this->lng->txt('crs_learning_materials'));


		$this->__showHideLinks('lms');

		if(isset($_SESSION['crs_hide_lms']))
		{
			return true;
		}

		$this->tpl->setCurrentBlock("tbl_header_columns_lms");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_LMS","5%");
		$this->tpl->setVariable("TBL_HEADER_NAME_LMS",$this->lng->txt('crs_nr'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_lms");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_LMS","75%");
		$this->tpl->setVariable("TBL_HEADER_NAME_LMS",$this->lng->txt('description'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_lms");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_LMS","20%");
		$this->tpl->setVariable("TBL_HEADER_NAME_LMS",$this->lng->txt('actions'));
		$this->tpl->parseCurrentBlock();

		$counter = 1;
		foreach($lms as $lm_id)
		{
			$obj_id = $ilObjDataCache->lookupObjId($lm_id);
			$obj_type = $ilObjDataCache->lookupType($obj_id);

			$conditions_ok = ilConditionHandler::_checkAllConditionsOfTarget($obj_id);
				
			$obj_link = ilRepositoryExplorer::buildLinkTarget($lm_id,$ilObjDataCache->lookupType($obj_id));
			$obj_frame = ilRepositoryExplorer::buildFrameTarget($ilObjDataCache->lookupType($obj_id),$lm_id,$obj_id);
			$obj_frame = $obj_frame ? $obj_frame : '';
			$contentObj = false;

			if(ilRepositoryExplorer::isClickable($obj_type,$lm_id,$obj_id))
			{
				$this->tpl->setCurrentBlock("lm_read");
				$this->tpl->setVariable("READ_TITLE_LMS",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->setVariable("READ_TARGET_LMS",$obj_frame);
				$this->tpl->setVariable("READ_LINK_LMS", $obj_link);
				$this->tpl->parseCurrentBlock();
			}
			else
			{
				$this->tpl->setCurrentBlock("lm_visible");
				$this->tpl->setVariable("VISIBLE_LINK_LMS",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->parseCurrentBlock();
			}
			// add to desktop link
			if(!$ilias->account->isDesktopItem($lm_id,$obj_type) and 
			   ($this->cci_course_obj->getAboStatus() == $this->cci_course_obj->ABO_ENABLED))
			{
				if ($rbacsystem->checkAccess('read',$lm_id))
				{
					$this->tpl->setCurrentBlock("lm_desklink");
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_ref_id',$lm_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_id',$lm_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'type',$obj_type);
					
					$this->tpl->setVariable("DESK_LINK_LMS",$this->ctrl->getLinkTarget($this->cci_client_obj,'addToDesk'));

					$this->tpl->setVariable("TXT_DESK_LMS", $this->lng->txt("to_desktop"));
					$this->tpl->parseCurrentBlock();
				}
			}

			// CONTINUE LINK
			if(isset($continue_data[$lm_id]))
			{
				$this->tpl->setCurrentBlock("lm_continuelink");
				$this->tpl->setVariable("CONTINUE_LINK_LMS",'ilias.php?baseClass=ilLMPresentationGUI&ref_id='.$lm_id.'&obj_id='.
										$continue_data[$lm_id]['lm_page_id']);

				$target = '';
				$this->tpl->setVariable("CONTINUE_LINK_TARGET",$obj_frame);
				$this->tpl->setVariable("TXT_CONTINUE_LMS",$this->lng->txt('continue_work'));
				$this->tpl->parseCurrentBlock();
			}

			// Description
			if(strlen($ilObjDataCache->lookupDescription($obj_id)))
			{
				$this->tpl->setCurrentBlock("lms_description");
				$this->tpl->setVariable("DESCRIPTION_LMS",$ilObjDataCache->lookupDescription($obj_id));
				$this->tpl->parseCurrentBlock();
			}
			// LAST ACCESS
			if(isset($continue_data["$lm_id"]))
			{
				$this->tpl->setVariable("TEXT_INFO_LMS",$this->lng->txt('last_access'));
				$this->tpl->setVariable("INFO_LMS",date('Y-m-d H:i:s',$continue_data["$lm_id"]['last_access']));
			}
			else
			{
				$this->tpl->setVariable("INFO_LMS",$this->lng->txt('not_accessed'));
			}
			
			if($this->details_id)
			{
				$objectives_lm_obj->setLMRefId($lm_id);
				if($objectives_lm_obj->checkExists())
				{
					$objectives_lm_obj =& new ilCourseObjectiveLM($this->details_id);
					
					if($conditions_ok)
					{
						foreach($objectives_lm_obj->getChapters() as $lm_obj_data)
						{
							if($lm_obj_data['ref_id'] != $lm_id)
							{
								continue;
							}

							include_once './content/classes/class.ilLMObject.php';
							
						
							$this->tpl->setCurrentBlock("chapters");
							$this->tpl->setVariable("TXT_CHAPTER",$this->lng->txt('chapter'));
							$this->tpl->setVariable("CHAPTER_LINK_LMS","ilias.php?baseClass=ilLMPresentationGUI&ref_id=".
													$lm_obj_data['ref_id'].
													'&obj_id='.$lm_obj_data['obj_id']);
							$this->tpl->setVariable("CHAPTER_LINK_TARGET_LMS",$obj_frame);
							$this->tpl->setVariable("CHAPTER_TITLE",ilLMObject::_lookupTitle($lm_obj_data['obj_id']));
							$this->tpl->parseCurrentBlock();
						}
					}
					$this->tpl->setVariable("OBJ_CLASS_CENTER_LMS",'option_value_center_details');
					$this->tpl->setVariable("OBJ_CLASS_LMS",'option_value_details');
				}
				else
				{
					$this->tpl->setVariable("OBJ_CLASS_CENTER_LMS",'option_value_center');
					$this->tpl->setVariable("OBJ_CLASS_LMS",'option_value');
				}
			}
			else
			{
				$this->tpl->setVariable("OBJ_CLASS_CENTER_LMS",'option_value_center');
				$this->tpl->setVariable("OBJ_CLASS_LMS",'option_value');
			}
			$this->tpl->setCurrentBlock("lm_row");
			$this->tpl->setVariable("OBJ_NR_LMS",$counter.'.');
			$this->tpl->parseCurrentBlock();

			++$counter;
		}
	}

	function __showTests()
	{
		global $ilias,$rbacsystem,$ilObjDataCache;

		include_once './Modules/Course/classes/class.ilCourseObjectiveLM.php';

		if(!count($tests = $this->__getAllTests()))
		{
			return false;
		}

		$this->tpl->addBlockfile('TEST_BLOCK','test_block','tpl.crs_objectives_view_tst_table.html','course');
		$this->tpl->setVariable("TBL_TITLE_TST",$this->lng->txt('tests'));


		$this->__showHideLinks('tst');

		if(isset($_SESSION['crs_hide_tst']))
		{
			return true;
		}

		$this->tpl->setCurrentBlock("tbl_header_columns_tst");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_TST","5%");
		$this->tpl->setVariable("TBL_HEADER_NAME_TST",$this->lng->txt('crs_nr'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_tst");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_TST","75%");
		$this->tpl->setVariable("TBL_HEADER_NAME_TST",$this->lng->txt('description'));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("tbl_header_columns_tst");
		$this->tpl->setVariable("TBL_HEADER_WIDTH_TST","20%");
		$this->tpl->setVariable("TBL_HEADER_NAME_TST",$this->lng->txt('actions'));
		$this->tpl->parseCurrentBlock();

		$counter = 1;
		foreach($tests as $tst_id)
		{
			$obj_id = $ilObjDataCache->lookupObjId($tst_id);
			$obj_type = $ilObjDataCache->lookupType($obj_id);

			#$tmp_tst = ilObjectFactory::getInstanceByRefId($tst_id);

			$conditions_ok = ilConditionHandler::_checkAllConditionsOfTarget($obj_id);
				
			$obj_link = ilRepositoryExplorer::buildLinkTarget($tst_id,$obj_type);
			$obj_link = "ilias.php?baseClass=ilObjTestGUI&ref_id=".$tst_id."&cmd=infoScreen";

			#$obj_frame = ilRepositoryExplorer::buildFrameTarget($tmp_tst->getType(),$tmp_tst->getRefId(),$tmp_tst->getId());
			#$obj_frame = $obj_frame ? $obj_frame : 'bottom';
			// Always open in frameset
			$obj_frame = '';

			if(ilRepositoryExplorer::isClickable($obj_type,$tst_id,$obj_id))
			{
				$this->tpl->setCurrentBlock("tst_read");
				$this->tpl->setVariable("READ_TITLE_TST",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->setVariable("READ_TARGET_TST",$obj_frame);
				$this->tpl->setVariable("READ_LINK_TST", $obj_link.'&crs_show_result='.$this->cci_ref_id);
				$this->tpl->parseCurrentBlock();
			}
			else
			{
				$this->tpl->setCurrentBlock("tst_visible");
				$this->tpl->setVariable("VISIBLE_LINK_TST",$ilObjDataCache->lookupTitle($obj_id));
				$this->tpl->parseCurrentBlock();
			}
				// add to desktop link
			if(!$ilias->account->isDesktopItem($tst_id,$obj_type) and 
			   ($this->cci_course_obj->getAboStatus() == $this->cci_course_obj->ABO_ENABLED))
			{
				if ($rbacsystem->checkAccess('read',$tst_id))
				{
					$this->tpl->setCurrentBlock("tst_desklink");
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_ref_id',$tst_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'item_id',$tst_id);
					$this->ctrl->setParameterByClass(get_class($this->cci_client_obj),'type',$obj_type);
					
					$this->tpl->setVariable("DESK_LINK_TST",$this->ctrl->getLinkTarget($this->cci_client_obj,'addToDesk'));


					$this->tpl->setVariable("TXT_DESK_TST", $this->lng->txt("to_desktop"));
					$this->tpl->parseCurrentBlock();
				}
			}
			
			$this->tpl->setCurrentBlock("tst_row");
			$this->tpl->setVariable("OBJ_TITLE_TST",$ilObjDataCache->lookupTitle($obj_id));
			$this->tpl->setVariable("OBJ_NR_TST",$counter.'.');

			$this->tpl->setVariable("OBJ_CLASS_CENTER_TST",'option_value_center');
			$this->tpl->setVariable("OBJ_CLASS_TST",'option_value');
			$this->tpl->parseCurrentBlock();

			unset($tmp_tst);
			++$counter;
		}
	}

	function __showObjectives()
	{
		include_once './Modules/Course/classes/class.ilCourseObjective.php';

		if(!count($objective_ids = ilCourseObjective::_getObjectiveIds($this->cci_course_obj->getId())))
		{
			return false;
		}
		// TODO
		if($_GET['details'])
		{
			$_SESSION['crs_details_id'] = $_GET['details'];
		}
		$this->details_id = $_SESSION['crs_details_id'] ? $_SESSION['crs_details_id'] : $objective_ids[0];

		// TODO get status for table header
		switch($this->objective_status)
		{
			case 'none':
				$status = $this->lng->txt('crs_objective_accomplished');
				break;

			case 'pretest':
			case 'pretest_non_suggest':
				$status = $this->lng->txt('crs_objective_pretest');
				break;

			default:
				$status = $this->lng->txt('crs_objective_result');
		}

		// show table
		$this->tpl->addBlockfile('OBJECTIVE_BLOCK','objective_block','tpl.crs_objectives_view_table.html','course');

		$this->tpl->setVariable("TBL_TITLE_OBJECTIVES",$this->lng->txt('crs_objectives'));

		$this->__showHideLinks('objectives');

		if(isset($_SESSION['crs_hide_objectives']))
		{
			return true;
		}

		// show table header
		for($i = 0; $i < 1; ++$i)
		{
			$this->tpl->setCurrentBlock("tbl_header_columns");
			$this->tpl->setVariable("TBL_HEADER_WIDTH_OBJECTIVES","5%");
			$this->tpl->setVariable("TBL_HEADER_NAME_OBJECTIVES",$this->lng->txt('crs_nr'));
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("tbl_header_columns");
			$this->tpl->setVariable("TBL_HEADER_WIDTH_OBJECTIVES","35%");
			$this->tpl->setVariable("TBL_HEADER_NAME_OBJECTIVES",$this->lng->txt('description'));
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("tbl_header_columns");
			$this->tpl->setVariable("TBL_HEADER_WIDTH_OBJECTIVES","10%");
			$this->tpl->setVariable("TBL_HEADER_NAME_OBJECTIVES",$status);
			$this->tpl->parseCurrentBlock();
		}

		//$max = count($objective_ids) % 2 ? count($objective_ids) + 1 : count($objective_ids); 
		$max = count($objective_ids); 
		for($i = 0; $i < $max; ++$i)
		{
			$tmp_objective =& new ilCourseObjective($this->cci_course_obj,$objective_ids[$i]);

			$this->tpl->setCurrentBlock("objective_row");

			if($this->details_id == $objective_ids[$i])
			{
				$this->tpl->setVariable("OBJ_CLASS_1_OBJECTIVES",'option_value_details');
				$this->tpl->setVariable("OBJ_CLASS_1_CENTER_OBJECTIVES",'option_value_center_details');
			}
			else
			{
				$this->tpl->setVariable("OBJ_CLASS_1_OBJECTIVES",'option_value');
				$this->tpl->setVariable("OBJ_CLASS_1_CENTER_OBJECTIVES",'option_value_center');
			}				
			$this->tpl->setVariable("OBJ_NR_1_OBJECTIVES",($i + 1).'.');

			$this->ctrl->setParameter($this->cci_client_obj,'details',$objective_ids[$i]);
			$this->tpl->setVariable("OBJ_LINK_1_OBJECTIVES",$this->ctrl->getLinkTarget($this->cci_client_obj,'cciObjectives'));
			$this->tpl->setVariable("OBJ_TITLE_1_OBJECTIVES",$tmp_objective->getTitle());

			$img = !$this->suggested["$objective_ids[$i]"] ? 
				ilUtil::getImagePath('icon_ok.gif') :
				ilUtil::getImagePath('icon_not_ok.gif');

			$txt = !$this->suggested["$objective_ids[$i]"] ? 
				$this->lng->txt('crs_objective_accomplished') :
				$this->lng->txt('crs_objective_not_accomplished');

			$this->tpl->setVariable("OBJ_STATUS_IMG_1_OBJECTIVES",$img);
			$this->tpl->setVariable("OBJ_STATUS_ALT_1_OBJECTIVES",$txt);


			if(isset($objective_ids[$i + $max / 2]))
			{
				$tmp_objective =& new ilCourseObjective($this->cci_course_obj,$objective_ids[$i + $max / 2]);

				$this->tpl->setCurrentBlock("objective_row");
				if($this->details_id == $objective_ids[$i + $max / 2])
				{
					$this->tpl->setVariable("OBJ_CLASS_2_OBJECTIVES",'option_value_details');
					$this->tpl->setVariable("OBJ_CLASS_2_CENTER_OBJECTIVES",'option_value_center_details');
				}
				else
				{
					$this->tpl->setVariable("OBJ_CLASS_2_OBJECTIVES",'option_value');
					$this->tpl->setVariable("OBJ_CLASS_2_CENTER_OBJECTIVES",'option_value_center');
				}				
				$this->tpl->setVariable("OBJ_NR_2_OBJECTIVES",($i + $max / 2 + 1).'.');
				$this->ctrl->setParameter($this->cci_client_obj,'details',$objective_ids[$i + $max / 2]);
				$this->tpl->setVariable("OBJ_LINK_2_OBJECTIVES",$this->ctrl->getLinkTarget($this->cci_client_obj,'cciObjectives'));
				$this->tpl->setVariable("OBJ_TITLE_2_OBJECTIVES",$tmp_objective->getTitle());


				$objective_id = $objective_ids[$i + $max / 2];
				$img = !$this->suggested[$objective_id] ? 
					ilUtil::getImagePath('icon_ok.gif') :
					ilUtil::getImagePath('icon_not_ok.gif');

				$txt = !$this->suggested[$objective_id] ? 
					$this->lng->txt('crs_objective_accomplished') :
					$this->lng->txt('crs_objective_not_accomplished');

				$this->tpl->setVariable("OBJ_STATUS_IMG_2_OBJECTIVES",$img);
				$this->tpl->setVariable("OBJ_STATUS_ALT_2_OBJECTIVES",$txt);
			}
	
			$this->tpl->parseCurrentBlock();
			unset($tmp_objective);
		}
		$this->ctrl->setParameter($this->cci_client_obj,'details','');
	}

	function __readAccomplished()
	{
		global $ilUser;

		if(isset($_SESSION['accomplished'][$this->cci_course_obj->getId()]))
		{
			return $this->accomplished = $_SESSION['accomplished'][$this->cci_course_obj->getId()];
		}


		include_once './Modules/Course/classes/class.ilCourseObjectiveResult.php';
		include_once './Modules/Course/classes/class.ilCourseObjective.php';

		$tmp_obj_res =& new ilCourseObjectiveResult($ilUser->getId());
		
		if(!count($objective_ids = ilCourseObjective::_getObjectiveIds($this->cci_course_obj->getId())))
		{
			return $this->accomplished = array();
		}
		$this->accomplished = array();
		foreach($objective_ids as $objective_id)
		{
			if($tmp_obj_res->hasAccomplishedObjective($objective_id))
			{
				$this->accomplished["$objective_id"] = true;
			}
			else
			{
				$this->accomplished["$objective_id"] = false;
			}
		}
		$_SESSION['accomplished'][$this->cci_course_obj->getId()] = $this->accomplished;
	}
	function __readSuggested()
	{
		global $ilUser;

		if(isset($_SESSION['objectives_suggested'][$this->cci_course_obj->getId()]))
		{
			return $this->suggested = $_SESSION['objectives_suggested'][$this->cci_course_obj->getId()];
		}

		include_once './Modules/Course/classes/class.ilCourseObjectiveResult.php';

		$tmp_obj_res =& new ilCourseObjectiveResult($ilUser->getId());

		$this->suggested = array();
		foreach($this->accomplished as $objective_id => $ok)
		{
			if($ok)
			{
				$this->suggested["$objective_id"] = false;
			}
			else
			{
				$this->suggested["$objective_id"] = $tmp_obj_res->isSuggested($objective_id);
			}
		}

		return $_SESSION['objectives_suggested'][$this->cci_course_obj->getId()] = $this->suggested;

	}

	function __readStatus()
	{
		global $ilUser;

		if(isset($_SESSION['objectives_status'][$this->cci_course_obj->getId()]))
		{
			return $this->objective_status = $_SESSION['objectives_status'][$this->cci_course_obj->getId()];
		}
		$all_success = true;

		foreach($this->accomplished as $id => $success)
		{
			if(!$success)
			{
				$all_success = false;
			}
		}
		if($all_success)
		{
			// set status passed
			include_once 'Modules/Course/classes/class.ilCourseMembers.php';

			ilCourseMembers::_setPassed($this->cci_course_obj->getId(),$ilUser->getId());

			$this->objective_status = 'finished';
			$_SESSION['objectives_status'][$this->cci_course_obj->getId()] = $this->objective_status;

			return true;
		}
		include_once './Modules/Course/classes/class.ilCourseObjectiveResult.php';
		include_once './Modules/Course/classes/class.ilCourseObjective.php';

		$tmp_obj_res =& new ilCourseObjectiveResult($ilUser->getId());

		$this->objective_status = $tmp_obj_res->getStatus($this->cci_course_obj->getId());

		if($this->objective_status == 'pretest')
		{
			$none_suggested = true;
			foreach($this->suggested as $value)
			{
				if($value)
				{
					$_SESSION['objectives_status'][$this->cci_course_obj->getId()] = $this->objective_status;
					return true;
				}
			}
			$this->objective_status = 'pretest_non_suggest';
		}
		$_SESSION['objectives_status'][$this->cci_course_obj->getId()] = $this->objective_status;

		return true;
	}

	function __showButton($a_cmd,$a_text,$a_target = '')
	{
		$this->tpl->addBlockfile("BUTTONS", "buttons", "tpl.buttons.html");
		
		// display button
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK",$this->ctrl->getLinkTarget($this->cci_client_obj,$a_cmd));
		$this->tpl->setVariable("BTN_TXT",$a_text);

		if($a_target)
		{
			$this->tpl->setVariable("BTN_TARGET",$a_target);
		}

		$this->tpl->parseCurrentBlock();
	}



	function cci_read()
	{
		global $tree;

		if(!$this->cci_course_id = $tree->checkForParentType($this->cci_ref_id,'crs'))
		{
			echo "ilCourseContentInterface: Cannot find course object";
			exit;
		}
		return true;
	}

	function cciToUnix($a_time_arr)
	{
		return mktime($a_time_arr["hour"],
					  $a_time_arr["minute"],
					  $a_time_arr["second"],
					  $a_time_arr["month"],
					  $a_time_arr["day"],
					  $a_time_arr["year"]);
	}
	function cciGetDateSelect($a_type,$a_varname,$a_selected)
	{
		switch($a_type)
		{
			case "minute":
				for($i=0;$i<=60;$i++)
				{
					$days[$i] = $i < 10 ? "0".$i : $i;
				}
				return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

			case "hour":
				for($i=0;$i<24;$i++)
				{
					$days[$i] = $i < 10 ? "0".$i : $i;
				}
				return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

			case "day":
				for($i=1;$i<32;$i++)
				{
					$days[$i] = $i < 10 ? "0".$i : $i;
				}
				return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);
			
			case "month":
				for($i=1;$i<13;$i++)
				{
					$month[$i] = $i < 10 ? "0".$i : $i;
				}
				return ilUtil::formSelect($a_selected,$a_varname,$month,false,true);

			case "year":
				for($i = date("Y",time());$i < date("Y",time()) + 3;++$i)
				{
					$year[$i] = $i;
				}
				return ilUtil::formSelect($a_selected,$a_varname,$year,false,true);
		}
	}
}
?>
