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
* Class ilObjNoteFolder
*
* @author M.Maschke
* @version $Id$
*  
* @extends ilObject
* @package ilias-core
*/

require_once "class.ilObject.php";

// TODO: note class need complete redesign since to user trees are saved to main tree table.
// is a tree actually useful to administrate user settings?
class ilObjNoteFolder extends ilObject
{
	var $m_usr_id;
	
	var $m_tree;

	var $m_notefId ;
	
	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id of user
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	function ilObjNoteFolder($user_id = 0,$a_call_by_reference = true)
	{
		$this->type = "notf";
		$this->ilObject($user_id,$a_call_by_reference);
		
		$this->m_usr_id = $user_id;
	}

	/**
	* create new note folder object
	*
	* note: title and description must be set
	*/
	function create()
	{
		parent::create();

		//$this->m_tree = new ilTree(0,0,$this->m_usr_id);
		
		// TODO: method needs revision
		//$this->m_notefId = $this->m_tree->getNodeDataByType("notf");
	}

	/**
	* add note to notefolder 
	*
	* @param	string  note_id
	* @param	string  group_id = obj_id of group
	* @access	public
	*/
	function addNote($note_id, $group="")
	{

		global $rbacreview;
		//getgroupmembers
		$grp_members = $rbacreview->assignedUsers($group);
	
		if(!empty($group))
		{
			foreach($grp_members as $member)
			{	
				$myTree = new ilTree(0, 0, $member); 

				//get parent_id of usersettingfolder...	
				$rootid =  $myTree->getRootId();
	
				$node_data = $myTree->getNodeDataByType("notf");

				$myTree->insertNode($note_id, $node_data[0]["obj_id"], $rootid["child"]);
			
			}
		}
		//insert the note in ones own notefolder
		$myTree = new ilTree(0, 0, $this->m_usr_id); 
	
		//get parent_id of usersettingfolder...	
		$rootid =  $myTree->getRootId();
	
		$node_data = $myTree->getNodeDataByType("notf");

		$myTree->insertNode($note_id, $node_data[0]["obj_id"], $rootid["child"]);
	} 

	/**
	* delete one specific note 
	* TODO: 
	* @param	array  note_ids !!!
	* @access	public
	*/
	function deleteNotes($notes)
	{
		global $rbacsystem;
		$myTree = new ilTree($this->m_notefId[0]["obj_id"], 0, 0, $this->m_usr_id); 	

		foreach ($notes as $note)
		{
			//delete note in note_data, only owner can delete notes 
			//$note_obj = getObject($note);
			$noteObj =& $this->ilias->obj_factory->getInstanceByObjId($note);
			if ($noteObj->getOwner() == $this->m_usr_id)
			{
				$query = "DELETE FROM note_data WHERE note_id ='".$note."'";
				$res = $this->ilias->db->query($query);
				//TODO: delete entry in object_data and all dependencies, references 
				//getgroupmembers and delete the note in members notefolder
			}

			//get note_data of note folder
			$node_data1 = $myTree->getNodeDataByType("notf");		
			$note_data2 = $myTree->getNodeData($note);	
			$myTree->deleteTree($note_data2);				
		}
	}

	/**
	* returns all notes of a specific notefolder
	*
	* @param	string  title of learning object, i.e
	* @param	string  short description of note
	* @return	array 	data of notes [note_id|lo_id|text|create_date]
	* @access	public
	*/
	function getNotes($note_id = "")
	{
		$notes = array();
		$myTree = new ilTree($this->m_notefId[0]["obj_id"], 0, 0, $this->m_usr_id);

		$nodes = $myTree->getNodeDataByType("note");

		if($note_id == "")
		{
			foreach($nodes as $node_data)
			{
				//$note_data = getObject($node_data["child"]);
				$noteObj =& $this->ilias->obj_factory->getInstanceByObjId($node_data["child"]);
				// todo: full object handling (getNodes should return real objects)
				$note_data = array(	"obj_id" => $noteObj->getId(),
									"type" => $noteObj->getType(),
									"description" => $noteObj->getDescription(),
									"owner" => $noteObj->getOwner(),
									"create_date" => $noteObj->getCreateDate(),
									"last_update" => $noteObj->getCreateDate());
				array_push($notes, $note_data);
			}

		}
		else
		{
			//$node_data["child"] = $note_id;
			//$notes = getObject($node_data["child"]);
			$noteObj =& $this->ilias->obj_factory->getInstanceByObjId($note_id);
			// todo: full object handling (getNodes should return real objects)
			$notes = array(	"obj_id" => $noteObj->getId(),
							"type" => $noteObj->getType(),
							"description" => $noteObj->getDescription(),
							"owner" => $noteObj->getOwner(),
							"create_date" => $noteObj->getCreateDate(),
							"last_update" => $noteObj->getCreateDate());

		}
		return $notes;
	}

	function viewNote($note_id)
	{
		$note = array();
		$myTree = new ilTree($this->m_notefId[0]["obj_id"], 0, $this->m_usr_id);

		$nodes = $myTree->getNodeDataByType("note");
		$node_data["child"] = $note_id;
		$note = NoteObject::viewObject($node_data["child"]);
		return $note;	
	}

}


?>
