<?php
  /**************************************************************************\
  * phpGroupWare API - VFS                                                   *
  * This file written by Jason Wies (Zone) <zone@users.sourceforge.net>      *
  * This class handles file/dir access for phpGroupWare                      *
  * Copyright (C) 2001 Jason Wies		                             *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */
		/*!
		@class vfs
		@abstract virtual file system
		@description Authors: Zone
		*/

	/* Relative defines.  Used mainly by getabsolutepath () */
	define (RELATIVE_ROOT, 1);
	define (RELATIVE_USER, 2);
	define (RELATIVE_CURR_USER, 4);
	define (RELATIVE_USER_APP, 8);
	define (RELATIVE_PATH, 16);
	define (RELATIVE_NONE, 32);
	define (RELATIVE_CURRENT, 64);
	define (VFS_REAL, 1024);
	define (RELATIVE_ALL, RELATIVE_PATH);

	/* These are used in calls to extra_sql () */
	define (VFS_SQL_SELECT, 1);
	define (VFS_SQL_DELETE, 2);

	/* These are used in calls to add_journal (), and allow journal messages to be more standard */
	define (VFS_OPERATION_CREATED, 1);
	define (VFS_OPERATION_EDITED, 2);
	define (VFS_OPERATION_EDITED_COMMENT, 4);
	define (VFS_OPERATION_COPIED, 8);
	define (VFS_OPERATION_MOVED, 16);
	define (VFS_OPERATION_DELETED, 32);

	/*!
	@class path_class
	@abstract helper class for path_parts
	*/

	class path_class
	{
		var $mask;
		var $outside;
		var $fake_full_path;
		var $fake_leading_dirs;
		var $fake_extra_path;
		var $fake_name;
		var $real_full_path;
		var $real_leading_dirs;
		var $real_extra_path;
		var $real_name;
		var $fake_full_path_clean;
		var $fake_leading_dirs_clean;
		var $fake_extra_path_clean;
		var $fake_name_clean;
		var $real_full_path_clean;
		var $real_leading_dirs_clean;
		var $real_extra_path_clean;
		var $real_name_clean;
	}


class vfs
{
	var $basedir;
	var $fakebase;
	var $relative;
	var $working_id;
	var $working_lid;
	var $attributes;
	var $override_acl;
	var $linked_dirs;
	var $meta_types;

	/*!
	@function vfs
	@abstract constructor, sets up variables
	*/

	function vfs ()
	{
		global $phpgw, $phpgw_info;

		$this->basedir = $phpgw_info["server"]["files_dir"];
		$this->fakebase = "/home";
		$this->working_id = $phpgw_info["user"]["account_id"];
		$this->working_lid = $phpgw->accounts->id2name ($this->working_id);
		$this->now = date ("Y-m-d");
		$this->override_acl = 0;

		/*
		   File/dir attributes, each corresponding to a database field.  Useful for use in loops
		   If an attribute was added to the table, add it here and possibly add it to
		   set_attributes ()
		*/

		$this->attributes = array ("file_id", "owner_id", "createdby_id", "modifiedby_id", "created", "modified", "size", "mime_type", "deleteable", "comment", "app", "directory", "name", "link_directory", "link_name", "version");

		/*
		   These are stored in the MIME-type field and should normally be ignored.
		   Adding a type here will ensure it is normally ignored, but you will have to
		   explicitly add it to acl_check (), and to any other SELECT's in this file
		*/

		$this->meta_types = array ("journal", "journal-deleted");

		/* We store the linked directories in an array now, so we don't have to make the SQL call again */

		$query = $phpgw->db->query ("SELECT directory, name, link_directory, link_name FROM phpgw_vfs WHERE link_directory != '' AND link_name != ''" . $this->extra_sql (VFS_SQL_SELECT));

		$this->linked_dirs = array ();
		while ($phpgw->db->next_record ())
		{
			$this->linked_dirs[] = $phpgw->db->Record;
		}
	}

	/*!
	@function set_relative
	@abstract Set path relativity
	@param $mask Relative bitmask (see RELATIVE_ defines)
	*/

	function set_relative ($mask)
	{
		if (!$mask)
			unset ($this->relative);
		else
			$this->relative = $mask;
	}

	/*!
	@function get_relative
	@abstract Return relativity bitmask
	@discussion Returns relativity bitmask, or the default of "completely relative" if unset
	*/

	function get_relative ()
	{
		if (isset ($this->relative))
			return $this->relative;
		else
			return RELATIVE_ALL;
	}

	/*!
 	@function sanitize
 	@abstract Removes leading .'s from $string
	@discussion You should not pass all filenames through sanitize () unless you plan on rejecting
			.files.  Instead, pass the name through securitycheck () first, and if it fails,
			pass it through sanitize
	@param $string string to sanitize
	@result $string without it's leading .'s
 	*/

	function sanitize ($string)
	{
		global $phpgw, $phpgw_info;

		/* We use path_parts () just to parse the string, not translate paths */
		$p = $this->path_parts ($string, array (RELATIVE_NONE));

		return (ereg_replace ("^\.+", "", $p->fake_name));
	}

	/*!
	@function securitycheck
	@abstract Security check function
	@discussion Checks for basic violations such as ..
			If securitycheck () fails, run your string through vfs->sanitize ()
	@param $string string to check security of
	@result Boolean True/False.  True means secure, False means insecure
	*/

	function securitycheck ($string)
	{
		if (substr ($string, 0, 1) == "\\" || strstr ($string, "..") || strstr ($string, "\\..") || strstr ($string, ".\\."))
		{
			return False;
		}
		else
		{
			return True;
		}
	}

	/*!
	@function db_clean
	@abstract Clean $string for use in database queries
	@param $string String to clean
	@result Cleaned version of $string
	*/

	function db_clean ($string)
	{
		$string = ereg_replace ("'", "\'", $string);

		return $string;
	}

	/*!
	@function extra_sql
	@abstract Return extra SQL code that should be appended to certain queries
	@param $query_type The type of query to get extra SQL code for, in the form of a VFS_SQL define
	@result Extra SQL code
	*/

	function extra_sql ($query_type = VFS_SQL_SELECT)
	{
		global $phpgw, $phpgw_info;

		if ($query_type == VFS_SQL_SELECT || $query_type == VFS_SQL_DELETE)
		{
			$sql = " AND ((";

			reset ($this->meta_types);
			while (list ($num, $type) = each ($this->meta_types))
			{
				if ($num)
					$sql .= " AND ";

				$sql .= "mime_type != '$type'";
			}

			$sql .= ") OR mime_type IS NULL)";
		}

		return ($sql);
	}

	/*!
	@function add_journal
	@abstract Add a journal entry after completing an operation, and increment the version number
		  This function should be used internally only
	@discussion Note that $state_one and $state_two are optional, and are only used for some
		    VFS_OPERATION's and not for any "custom" operation
	@param $string File or directory to add entry for
	@param $relatives Relativity array
	@param $operation The operation that was performed.  Either a VFS_OPERATION define or
			  a non-integer descriptive text string
	@param $state_one The first "state" of the file or directory.  Can be a file name, size,
			  location, whatever is appropriate for the specific operation
	@param $state_two The second "state" of the file or directory
	@param $incversion Boolean True/False.  Increment the version for the file?  Note that this is
			   handled automatically for the VFS_OPERATION defines.
			   i.e. VFS_OPERATION_EDITED would increment the version, VFS_OPERATION_COPIED
			   would not
	@result Boolean True/False
	*/

	function add_journal ($string, $relatives = array (RELATIVE_CURRENT), $operation, $state_one, $state_two, $incversion = True)
	{
		global $phpgw, $phpgw_info;

		$p = $this->path_parts ($string, array ($relatives[0]));

		/* We check that they have some sort of access to the file other than read */
		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_WRITE) && !$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_EDIT) && !$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_DELETE))
		{
			return False;
		}

		if (!$this->file_exists ($p->fake_full_path, array ($p->mask)))
		{
			return False;
		}

		$ls_array = $this->ls ($p->fake_full_path, array ($p->mask), False, False, True);
		$file_array = $ls_array[0];

		$sql = "INSERT INTO phpgw_vfs (";
		$sql2 .= " VALUES (";

		for ($i = 0; list ($attribute, $value) = each ($file_array); $i++)
		{
			if ($attribute == "file_id")
			{
				continue;
			}

			if ($i > 1)
			{
				$sql .= ", ";
				$sql2 .= ", ";
			}

			if ($attribute == "mime_type")
			{
				$value = "journal";
			}

			if ($attribute == "comment")
			{
				switch ($operation)
				{
					case VFS_OPERATION_CREATED:
						$value = "Created";
						$incversion = True;
						break;
					case VFS_OPERATION_EDITED:
						$value = "Edited";
						$incversion = True;
						break;
					case VFS_OPERATION_EDITED_COMMENT:
						$value = "Edited comment";
						$incversion = False;
						break;
					case VFS_OPERATION_COPIED:
						if (!$state_one)
							$state_one = $p->fake_full_path;
						$value = "Copied $state_one to $state_two";
						$incversion = False;
						break;
					case VFS_OPERATION_MOVED:
						if (!$state_one)
							$state_one = $p->fake_full_path;
						$value = "Moved $state_one to $state_two";
						$incversion = False;
						break;
					case VFS_OPERATION_DELETED:
						$value = "Deleted";
						$incversion = False;
						break;
					default:
						$value = $operation;
						break;
				}
			}

			/*
			   Let's increment the version for the file itself.  We keep the current
			   version when making the journal entry, because that was the version that
			   was operated on.  The maximum numbers for each part in the version string:
			   none.99.9.9
			*/
			if ($attribute == "version" && $incversion)
			{
				$version_parts = split ("\.", $value);
				$newnumofparts = $numofparts = count ($version_parts);

				if ($version_parts[3] >= 9)
				{
					$version_parts[3] = 0;
					$version_parts[2]++;
					$version_parts_3_update = 1;
				}
				elseif (isset ($version_parts[3]))
				{
					$version_parts[3]++;
				}

				if ($version_parts[2] >= 9 && $version_parts[3] == 0 && $version_parts_3_update)
				{
					$version_parts[2] = 0;
					$version_parts[1]++;
				}

				if ($version_parts[1] > 99)
				{
					$version_parts[1] = 0;
					$version_parts[0]++;
				}

				for ($i = 0; $i < $newnumofparts; $i++)
				{
					if (!isset ($version_parts[$i]))
						break;

					if ($i)
						$newversion .= ".";

					$newversion .= $version_parts[$i];
				}

				$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("version" => $newversion));
			}

			$sql .= "$attribute";
			$sql2 .= "'$value'";
		}

		$sql .= ")";
		$sql2 .= ")";

		$sql .= $sql2;

		$query = $phpgw->db->query ($sql, __LINE__, __FILE__);

		if ($operation == VFS_OPERATION_DELETED)
		{
			$query = $phpgw->db->query ("UPDATE phpgw_vfs SET mime_type='journal-deleted' WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean' AND mime_type='journal'");
		}

		return True;
	}

	/*!
	@function path_parts
	@abstract take a real or fake pathname and return an array of its component parts
	@param $string full real or fake path
	@param $relatives Relativity array
	@param $object True returns an object instead of an array
	@param $nolinks Don't check for links (made with make_link ()).  Used internally to prevent recursion
	@result $rarray/$robject Array or object containing the fake and real component parts of the path
	@discussion Returned values are:
			mask
			outside
			fake_full_path
			fake_leading_dirs
			fake_extra_path		BROKEN
			fake_name
			real_full_path
			real_leading_dirs
			real_extra_path		BROKEN
			real_name
			fake_full_path_clean
			fake_leading_dirs_clean
			fake_extra_path_clean	BROKEN
			fake_name_clean
			real_full_path_clean
			real_leading_dirs_clean
			real_extra_path_clean	BROKEN
			real_name_clean
		"clean" values are run through vfs->db_clean () and
		are safe for use in SQL queries that use key='value'
		They should be used ONLY for SQL queries, so are used
		mostly internally
		mask is either RELATIVE_NONE or RELATIVE_NONE|VFS_REAL,
		and is used internally
		outside is boolean, True if $relatives contains VFS_REAL
	*/

	function path_parts ($string, $relatives = array (RELATIVE_CURRENT), $object = True, $nolinks = False)
	{
		global $phpgw, $phpgw_info;
		$sep = SEP;

		$rarray["mask"] = RELATIVE_NONE;

		if (!($relatives[0] & VFS_REAL))
		{
			$rarray["outside"] = False;
			$fake = True;
		}
		else
		{
			$rarray["outside"] = True;
			$rarray["mask"] |= VFS_REAL;
		}

		$string = $this->getabsolutepath ($string, array ($relatives[0]), $fake);

		if ($fake)
		{
			$base_sep = "/";
			$base = "/";

			$opp_base = $this->basedir . $sep;

			$rarray["fake_full_path"] = $string;
		}
		else
		{
			$base_sep = $sep;
			if (ereg ("^$this->basedir" . $sep, $string))
			{
				$base = $this->basedir . $sep;
			}
			else
			{
				$base = $sep;
			}

			$opp_base = "/";

			$rarray["real_full_path"] = $string;
		}

		/* This is needed because of substr's handling of negative lengths */
		$baselen = strlen ($base);
		$lastslashpos = strrpos ($string, $base_sep);
		$lastslashpos < $baselen ? $length = 0 : $length = $lastslashpos - $baselen;

		$extra_path = $rarray["fake_extra_path"] = $rarray["real_extra_path"] = substr ($string, strlen ($base), $length);
		$name = $rarray["fake_name"] = $rarray["real_name"] = substr ($string, strrpos ($string, $base_sep) + 1);
	
		if ($fake)
		{
			$rarray["real_extra_path"] ? $dispsep = $sep : $dispsep = "";
			$rarray["real_full_path"] = $opp_base . $rarray["real_extra_path"] . $dispsep . $rarray["real_name"];
			if ($extra_path)
			{
				$rarray["fake_leading_dirs"] = $base . $extra_path;
				$rarray["real_leading_dirs"] = $opp_base . $extra_path;
			}
			elseif (strrpos ($rarray["fake_full_path"], $sep) == 0)
			{
				/* If there is only one $sep in the path, we don't want to strip it off */
				$rarray["fake_leading_dirs"] = $sep;
				$rarray["real_leading_dirs"] = substr ($opp_base, 0, strlen ($opp_base) - 1);
			}
			else
			{
				/* These strip the ending / */
				$rarray["fake_leading_dirs"] = substr ($base, 0, strlen ($base) - 1);
				$rarray["real_leading_dirs"] = substr ($opp_base, 0, strlen ($opp_base) - 1);
			}
		}
		else
		{
			$rarray["fake_full_path"] = $opp_base . $rarray["fake_extra_path"] . "/" . $rarray["fake_name"];
			if ($extra_path)
			{
				$rarray["fake_leading_dirs"] = $opp_base . $extra_path;
				$rarray["real_leading_dirs"] = $base . $extra_path;
			}
			else
			{
				$rarray["fake_leading_dirs"] = substr ($opp_base, 0, strlen ($opp_base) - 1);
				$rarray["real_leading_dirs"] = substr ($base, 0, strlen ($base) - 1);
			}
		}

		/* We check for linked dirs made with make_link ().  This could be better, but it works */
		if (!$nolinks)
		{
			reset ($this->linked_dirs);
			while (list ($num, $link_info) = each ($this->linked_dirs))
			{
				if (ereg ("^$link_info[directory]/$link_info[name](/|$)", $rarray["fake_full_path"]))
				{
					$rarray["real_full_path"] = ereg_replace ("^$this->basedir", "", $rarray["real_full_path"]);
					$rarray["real_full_path"] = ereg_replace ("^$link_info[directory]" . SEP . "$link_info[name]", $link_info["link_directory"] . SEP . $link_info["link_name"], $rarray["real_full_path"]);

					$p = $this->path_parts ($rarray["real_full_path"], array (RELATIVE_NONE|VFS_REAL), True, True);

					$rarray["real_leading_dirs"] = $p->real_leading_dirs;
					$rarray["real_extra_path"] = $p->real_extra_path;
					$rarray["real_name"] = $p->real_name;
				}
			}
		}

		/*
		   We have to count it before because new keys will be added,
		   which would create an endless loop
		*/
		$count = count ($rarray);
		reset ($rarray);
		for ($i = 0; (list ($key, $value) = each ($rarray)) && $i != $count; $i++)
		{
			$rarray[$key . "_clean"] = $this->db_clean ($value);
		}

		if ($object)
		{
			$robject = new path_class;

			reset ($rarray);
			while (list ($key, $value) = each ($rarray))
			{
				$robject->$key = $value;
			}
		}

/*
		echo "<br>fake_full_path: $rarray[fake_full_path]
			<br>fake_leading_dirs: $rarray[fake_leading_dirs]
			<br>fake_extra_path: $rarray[fake_extra_path]
			<br>fake_name: $rarray[fake_name]
			<br>real_full_path: $rarray[real_full_path]
			<br>real_leading_dirs: $rarray[real_leading_dirs]
			<br>real_extra_path: $rarray[real_extra_path]
			<br>real_name: $rarray[real_name]";
*/

		if ($object)
		{
			return ($robject);
		}
		else
		{
			return ($rarray);
		}
	}

	/*!
	@function getabsolutepath
	@abstract get the absolute path
	@param $target defaults to False, directory/file to get path of, relative to $relatives[0]
	@param $mask Relativity bitmask (see RELATIVE_ defines).  RELATIVE_CURRENT means use $this->relative
	@param $fake Returns the "fake" path, ie /home/user/dir/file (not always possible.  use path_parts () instead)
	@result $basedir Full fake or real path
	*/      

	function getabsolutepath ($target = False, $relatives = array (RELATIVE_CURRENT), $fake = True)
	{
		global $phpgw, $phpgw_info;

		$currentdir = $this->pwd (False);

		if (!is_array ($relatives))
		{
			$relatives = array (RELATIVE_CURRENT);
		}

		/* If they supply just VFS_REAL, we assume they want current relativity */
		if ($relatives[0] == VFS_REAL)
		{
			$relatives[0] |= RELATIVE_CURRENT;
		}

		if (!$this->securitycheck ($target))
		{
			return False;
		}

		if ($relatives[0] & RELATIVE_NONE)
		{
			return $target;
		}

		if ($fake)
		{
			$sep = "/";
		}
		else
		{
			$sep = SEP;
		}

		/* if RELATIVE_CURRENT, retrieve the current mask */
		if ($relatives[0] & RELATIVE_CURRENT)
		{
			$mask = $relatives[0];
			/* Respect any additional masks by re-adding them after retrieving the current mask*/
			$relatives[0] = $this->get_relative () + ($mask - RELATIVE_CURRENT);
		}

		if ($fake)
		{
			$basedir = "/";
		}
		else
		{
			$basedir = $this->basedir . $sep;

			/* This allows all requests to use /'s */
			$target = preg_replace ("|/|", $sep, $target);
		}

		if (($relatives[0] & RELATIVE_PATH) && $currentdir)
		{
			$basedir = $basedir . $currentdir . $sep;
		}
		elseif (($relatives[0] & RELATIVE_USER) || ($relatives[0] & RELATIVE_USER_APP))
		{
			$basedir = $basedir . $this->fakebase . $sep;
		}

		if ($relatives[0] & RELATIVE_CURR_USER)
		{
			$basedir = $basedir . $this->working_lid . $sep;
		}

		if (($relatives[0] & RELATIVE_USER) || ($relatives[0] & RELATIVE_USER_APP))
		{
			$basedir = $basedir . $phpgw_info["user"]["account_lid"] . $sep;
		}

		if ($relatives[0] & RELATIVE_USER_APP)
		{
			$basedir = $basedir . "." . $phpgw_info["flags"]["currentapp"] . $sep;
		}

		/* Don't add target if it's a /, just for aesthetics */
		if ($target && $target != $sep)
			$basedir = $basedir . $target;

		/* Let's not return // */
		while (ereg ($sep . $sep, $basedir))
		{
			$basedir = ereg_replace ($sep . $sep, $sep, $basedir);
		}

		$basedir = ereg_replace ("$sep$", "", $basedir);

		return $basedir;
	}

	/*!
	@function acl_check
	@abstract Check ACL access to $file for $this->account_id
	@param $file File to check access of
	@param $relatives Standard relativity array
	@param $operation Operation to check access to.  In the form of a PHPGW_ACL defines bitmask.  Default is read
	@param $must_exist Boolean.  Set to True if $file must exist.  Otherwise, we check the parent directory as well
	@result Boolean.  True if access is ok, False otherwise
	*/

	function acl_check ($file, $relatives = array (RELATIVE_CURRENT), $operation = PHPGW_ACL_READ, $must_exist = False)
	{
		global $phpgw, $phpgw_info;

		/* Accommodate special situations */
		if ($this->override_acl)
		{
			return True;
		}

		$account_id = $phpgw_info["user"]["account_id"];
		$account_lid = $phpgw->accounts->id2name ($phpgw_info["user"]["account_id"]);

		$p = $this->path_parts ($file, array ($relatives[0]));

		/* Temporary, until we get symlink type files set up */
		if ($p->outside)
		{
			return True;
		}

		/* If the file doesn't exist, we get ownership from the parent directory */
		if (!$this->file_exists ($p->fake_full_path, array ($p->mask)))
		{
			if ($must_exist)
			{
				return False;
			}

			$file = $p->fake_leading_dirs;
			$p2 = $this->path_parts ($file, array ($p->mask));

			if (!$this->file_exists ($file, array ($p->mask)))
			{
				return False;
			}
		}
		else
		{
			$p2 = $p;
		}

		/* Read access is always allowed here, but nothing else is */
		if ($file == "/" || $file == $this->fakebase)
		{
			if ($operation == PHPGW_ACL_READ)
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		/*
		   We don't use ls () to get owner_id as we normally would,
		   because ls () calls acl_check (), which would create an infinite loop
		*/
		$query = $phpgw->db->query ("SELECT owner_id FROM phpgw_vfs WHERE directory='$p2->fake_leading_dirs_clean' AND name='$p2->fake_name_clean'" . $this->extra_sql (VFS_SQL_SELECT), __LINE__, __FILE__);
		$phpgw->db->next_record ();
		$group_id = $phpgw->db->Record["owner_id"];

		/* They always have access to their own files */
		if ($group_id == $account_id)
		{
			return True;
		}

		/* Check if they're in the group.  If so, they have access */
		$memberships = $phpgw->accounts->memberships ($account_id);

		reset ($memberships);
		while (list ($num, $group_array) = each ($memberships))
		{
			if ($group_id == $phpgw->accounts->name2id ($group_array["account_name"]))
			{
				$group_ok = 1;
				break;
			}
		}

		if (!$group_id)
		{
			if (!$group_id = $this->account_id)
			{
				$group_id = 0;
			}
		}

		$acl = CreateObject ("phpgwapi.acl", $group_id);
		$acl->account_id = $group_id;
		$acl->read_repository ();

		$rights = $acl->get_rights ($account_id);
		if ($rights & $operation)
		{
			return True;
		}
		elseif (!$rights && $group_ok)
		{
			return True;
		}
		else
		{
			return False;
		}
	}
	
	/*!
	@function cd
	@abstract Change directory
	@discussion To cd to the files root "/", use cd ("/", False, array (RELATIVE_NONE));
	@param $target default "/".  directory to cd into.  if "/" and $relative is True, uses "/home/<working_lid>";
	@param $relative default True/relative means add target to current path, else pass $relative as mask to getabsolutepath()
	*/

	function cd ($target = "/", $relative = True, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw, $phpgw_info;

		if ($relatives[0] & VFS_REAL)
		{
			$sep = SEP;
		}
		else
		{
			$sep = "/";
		}

		if ($relative == "relative" || $relative == True)
		{
			/* if $target is "/" and $relative is set, we cd to the user/group home dir */
			if ($target == "/")
			{
				$relatives[0] = RELATIVE_USER;
				$basedir = $this->getabsolutepath (False, array ($relatives[0]), True);
			}
			else
			{
				$currentdir = $phpgw->common->appsession ();
				$basedir = $this->getabsolutepath ($currentdir . $sep . $target, array ($relatives[0]), True);
			}
		}
		else
		{
			$basedir = $this->getabsolutepath ($target, array ($relatives[0]));
		}

		$phpgw->common->appsession ($basedir);

		return True;
	}

	/*!
	@function pwd
	@abstract current working dir
	@param $full default True returns full fake path, else just the extra dirs (false strips the leading /)
	@result $currentdir currentdir
	*/

	function pwd ($full = True)
	{
		global $phpgw;

		$currentdir = $phpgw->common->appsession ();

		if (!$full)
		{
			$currentdir = ereg_replace ("^/", "", $currentdir);
		}

		if ($currentdir == "" && $full)
		{
			$currentdir = "/";
		}

		return $currentdir;
	}

	/*!
	@function read
	@abstract return file contents
	@param $file filename
	@param $relatives Relativity array
	@result $contents Contents of $file, or False if file cannot be read
	*/

	function read ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_READ))
		{
			return False;
		}

		if ($fp = fopen ($p->real_full_path, "r"))
		{
			$contents = fread ($fp, filesize ($p->real_full_path));
			fclose ($fp);

			return $contents;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function write
	@abstract write to a file
	@param $file file name
	@param $relatives Relativity array
	@param $contents contents
	@result Boolean True/False
	*/		

	function write ($file, $relatives = array (RELATIVE_CURRENT), $contents)
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		if ($this->file_exists ($p->fake_full_path, array ($p->mask)))
		{
			$operation = PHPGW_ACL_EDIT;
		}
		else
		{
			$operation = PHPGW_ACL_ADD;
		}

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), $operation))
		{
			return False;
		}

		umask(000);

		/*
		   If $file doesn't exist, touch () creates both the file and the database entry
		   If $file does exist, touch () sets the modification time and modified by
		*/
		$this->touch ($p->fake_full_path, array ($p->mask));

		if ($fp = fopen ($p->real_full_path, "w"))
		{
			fwrite ($fp, $contents, strlen ($contents));
			fclose ($fp);

			$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("size" => filesize ($p->real_full_path)));

			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function touch
	@abstract Create blank file $file or set the modification time and modified by of $file to current time and user
	@param $file File to touch or set modifies
	@param $relatives Relativity array
	@result Boolean True/False
	*/

	function touch ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw, $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];
		$currentapp = $phpgw_info["flags"]["currentapp"];

		$p = $this->path_parts ($file, array ($relatives[0]));

		umask (000);

		/*
		   PHP's touch function will automatically decide whether to
		   create the file or set the modification time
		*/
		$rr = touch ($p->real_full_path);

		if ($p->outside)
		{
			return $rr;
		}

		/* We, however, have to decide this ourselves */
		if ($this->file_exists ($p->fake_full_path, array ($p->mask)))
		{
			if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_EDIT))
			{
				return False;
			}

			$vr = $this->set_attributes ($p->fake_full_path, array ($p->mask), array ("modifiedby_id" => $account_id, "modified" => date ("Y-m-d")));
		}
		else
		{
			if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_ADD))
			{
				return False;
			}

			$query = $phpgw->db->query ("INSERT INTO phpgw_vfs (owner_id, directory, name) VALUES ($this->working_id, '$p->fake_leading_dirs_clean', '$p->fake_name_clean')", __LINE__, __FILE__);

			$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("createdby_id" => $account_id, "created" => $this->now, "size" => 0, "deleteable" => "Y", "app" => $currentapp));
			$this->correct_attributes ($p->fake_full_path, array ($p->mask));

			$this->add_journal ($p->fake_full_path, array ($p->mask), VFS_OPERATION_CREATED);
		}

		if ($rr || $vr || $query)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function cp
	@abstract copy file
	@param $from from file/directory
	@param $to to file/directory
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function cp ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];

		$f = $this->path_parts ($from, array ($relatives[0]));
		$t = $this->path_parts ($to, array ($relatives[1]));

		if (!$this->acl_check ($f->fake_full_path, array ($f->mask), PHPGW_ACL_READ))
		{
			return False;
		}

		if ($this->file_exists ($t->fake_full_path, array ($t->mask)))
		{
			if (!$this->acl_check ($t->fake_full_path, array ($t->mask), PHPGW_ACL_EDIT))
			{
				return False;
			}
		}
		else
		{
			if (!$this->acl_check ($t->fake_full_path, array ($t->mask), PHPGW_ACL_ADD))
			{
				return False;
			}

		}

		umask(000);

		if ($this->file_type ($from, array ($relatives[0])) != "Directory")
		{
			if (!copy ($f->real_full_path, $t->real_full_path))
			{
				return False;
			}

			if ($t->outside)
			{
				return True;
			}

			$size = filesize ($t->real_full_path);

			$ls_array = $this->ls ($f->fake_full_path, array ($f->mask), False, False, True);
			$record = $ls_array[0];

			if ($this->file_exists ($to, array ($relatives[1])))
			{
				$phpgw->db->query ("UPDATE phpgw_vfs SET owner_id='$this->working_id', directory='$t->fake_leading_dirs_clean', name='$t->fake_name_clean' WHERE owner_id='$this->working_id' AND directory='$t->fake_leading_dirs_clean' AND name='$t->fake_name_clean'", __LINE__, __FILE__);

				$this->set_attributes ($t->fake_full_path, array ($t->mask), array ("createdby_id" => $account_id, "created" => $this->now, "size" => $size, "mime_type" => $record["mime_type"], "deleteable" => $record["deleteable"], "comment" => $record["comment"], "app" => $record["app"]));

				$this->add_journal ($t->fake_full_path, array ($t->mask), VFS_OPERATION_EDITED);
			}
			else
			{
				$this->touch ($t->fake_full_path, array ($t->mask));

				$this->set_attributes ($t->fake_full_path, array ($t->mask), array ("createdby_id" => $account_id, "created" => $this->now, "size" => $size, "mime_type" => $record["mime_type"], "deleteable" => $record["deleteable"], "comment" => $record["comment"], "app" => $record["app"]));
			}

			$this->correct_attributes ($t->fake_full_path, array ($t->mask));
		}
		else	/* It's a directory */
		{
			/* First, make the initial directory */
			$this->mkdir ($to, array ($relatives[1]));

			/* Next, we create all the directories below the initial directory */
			$ls = $this->ls ($f->fake_full_path, array ($f->mask), True, "Directory");

			while (list ($num, $entry) = each ($ls))
			{
				$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry["directory"]);
				$this->mkdir ("$newdir/$entry[name]", array ($t->mask));
			}

			/* Lastly, we copy the files over */
			$ls = $this->ls ($f->fake_full_path, array ($f->mask));

			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] == "Directory")
				{
					continue;
				}

				$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry["directory"]);
				$this->cp ("$entry[directory]/$entry[name]", "$newdir/$entry[name]", array ($f->mask, $t->mask));
			}
		}

		if (!$f->outside)
		{
			$this->add_journal ($f->fake_full_path, array ($f->mask), VFS_OPERATION_COPIED, NULL, $t->fake_full_path);
		}

		return True;
	}

	function copy ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		umask (000);
		return $this->cp ($from, $to);
	}

	/*!
	@function mv
	@abstract move file/directory
	@param $from from file/directory
	@param $to to file/directory
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function mv ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];

		$f = $this->path_parts ($from, array ($relatives[0]));
		$t = $this->path_parts ($to, array ($relatives[1]));

		if (!$this->acl_check ($f->fake_full_path, array ($f->mask), PHPGW_ACL_READ) || !$this->acl_check ($f->fake_full_path, array ($f->mask), PHPGW_ACL_DELETE))
		{
			return False;
		}

		if (!$this->acl_check ($t->fake_full_path, array ($t->mask), PHPGW_ACL_ADD))
		{
			return False;
		}

		if ($this->file_exists ($t->fake_full_path, array ($t->mask)))
		{
			if (!$this->acl_check ($t->fake_full_path, array ($t->mask), PHPGW_ACL_EDIT))
			{
				return False;
			}
		}

		umask (000);

		/* We can't move directories into themselves */
		if (($this->file_type ($f->fake_full_path, array ($f->mask)) == "Directory") && ereg ("^$f->fake_full_path", $t->fake_full_path))
		{
			if (($t->fake_full_path == $f->fake_full_path) || substr ($t->fake_full_path, strlen ($f->fake_full_path), 1) == "/")
			{
				return False;
			}
		}

		if ($this->file_exists ($f->fake_full_path, array ($f->mask)))
		{
			/* We get the listing now, because it will change after we update the database */
			$ls = $this->ls ($f->fake_full_path, array ($f->mask));

			$this->rm ($t->fake_full_path, array ($t->mask));

			/*
			   We add the journal entry now, before we delete.  This way the mime_type
			   field will be updated to 'journal-old' when the file is actually deleted
			*/
			if (!$f->outside)
			{
				$this->add_journal ($f->fake_full_path, array ($f->mask), VFS_OPERATION_MOVED, $f->fake_full_path, $t->fake_full_path);
			}

			/*
			   If the from file is outside, it won't have a database entry,
			   so we have to touch it and find the size
			*/
			if ($f->outside)
			{
				$size = filesize ($f->real_full_path);

				$this->touch ($t->fake_full_path, array ($t->mask));
				$query = $phpgw->db->query ("UPDATE phpgw_vfs SET size=$size WHERE directory='$t->fake_leading_dirs_clean' AND name='$t->fake_name_clean'");
			}
			elseif (!$t->outside)
			{
				$query = $phpgw->db->query ("UPDATE phpgw_vfs SET name='$t->fake_name_clean', directory='$t->fake_leading_dirs_clean' WHERE directory='$f->fake_leading_dirs_clean' AND name='$f->fake_name_clean'", __LINE__, __FILE__);
			}

			$this->set_attributes ($t->fake_full_path, array ($t->mask), array ("modifiedby_id" => $account_id, modified => $this->now));
			$this->correct_attributes ($t->fake_full_path, array ($t->mask));

			$rr = rename ($f->real_full_path, $t->real_full_path);

			/*
			   This removes the original entry from the database
			   The actual file is already deleted because of the rename () above
			*/
			if ($t->outside)
			{
				$this->rm ($f->fake_full_path, $f->mask);
			}
		}
		else
		{
			return False;
		}

		if ($this->file_type ($t->fake_full_path, array ($t->mask)) == "Directory")
		{
			/* We got $ls from above, before we renamed the directory */
			while (list ($num, $entry) = each ($ls))
			{
				$newdir = ereg_replace ("^$f->fake_full_path", $t->fake_full_path, $entry["directory"]);
				$newdir_clean = $this->db_clean ($newdir);

				$query = $phpgw->db->query ("UPDATE phpgw_vfs SET directory='$newdir_clean' WHERE file_id='$entry[file_id]'", __LINE__, __FILE__);
				$this->correct_attributes ("$newdir/$entry[name]", array ($t->mask));
			}
		}

		$this->add_journal ($t->fake_full_path, array ($t->mask), VFS_OPERATION_MOVED, $f->fake_full_path, $t->fake_full_path);

		return True;
	}

	/*!
	@function move
	@abstract shortcut to mv
	*/

	function move ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		umask (000);
		return $this->mv ($from, $to, $relatives);
	}

	/*!
	@function rm
	@abstract delete file/directory
	@param $string file/directory to delete
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function rm ($string, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($string, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_DELETE))
		{
			return False;
		}

		if (!$this->file_exists ($string, array ($relatives[0])))
		{
			$rr = unlink ($p->real_full_path);

			if ($rr)
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		if ($this->file_type ($string, array ($relatives[0])) != "Directory")
		{
			$this->add_journal ($p->fake_full_path, array ($p->mask), VFS_OPERATION_DELETED);

			$query = $phpgw->db->query ("DELETE FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (VFS_SQL_DELETE), __LINE__, __FILE__);
			$rr = unlink ($p->real_full_path);

			if ($query || $rr)
			{
				return True;
			}
			else
			{
				return False;
			}
		}
		else
		{
			$ls = $this->ls ($p->fake_full_path, array ($p->mask));

			/* First, we cycle through the entries and delete the files */
			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] == "Directory")
				{
					continue;
				}

				$this->rm ("$entry[directory]/$entry[name]", array ($p->mask));
			}

			/* Now we cycle through again and delete the directories */
			reset ($ls);
			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] != "Directory")
				{
					continue;
				}

				/* Only the best in confusing recursion */
				$this->rm ("$entry[directory]/$entry[name]", array ($p->mask));
			}

			/* If the directory is linked, we delete the placeholder directory */
			$ls_array = $this->ls ($p->fake_full_path, array ($p->mask), False, False, True);
			$link_info = $ls_array[0];

			if ($link_info["link_directory"] && $link_info["link_name"])
			{
				$path = $this->path_parts ($link_info["directory"] . "/" . $link_info["name"], array ($p->mask), True, True);
				rmdir ($path->real_full_path);
			}

			/* Last, we delete the directory itself */
			$this->add_journal ($p->fake_full_path, array ($p->mask), VFS_OPERATION_DELETED);

			$query = $phpgw->db->query ("DELETE FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (VFS_SQL_DELETE), __LINE__, __FILE__);

			rmdir ($p->real_full_path);

			return True;
		}
	}

	/*!
	@function delete
	@abstract shortcut to rm
	*/

	function delete ($string, $relatives = array (RELATIVE_CURRENT))
	{
		return $this->rm ($string, $relatives);
	}

	/*!
	@function mkdir
	@abstract make a new directory
	@param $dir Directory name
	@param $relatives Relativity array
	@result boolean True on success
	*/

	function mkdir ($dir, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];
		$currentapp = $phpgw_info["flags"]["currentapp"];

		$p = $this->path_parts ($dir, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_ADD))
		{
			return False;
		}

		/* We don't allow /'s in dir names, of course */
		if (ereg ("/", $p->fake_name))
		{
			return False;
		}

		umask (000);

		if (!mkdir ($p->real_full_path, 0770))
		{
			return False;
		}

		if (!$this->file_exists ($p->fake_full_path, array ($p->mask)))
		{
			$query = $phpgw->db->query ("INSERT INTO phpgw_vfs (owner_id, name, directory) VALUES ($this->working_id, '$p->fake_name_clean', '$p->fake_leading_dirs_clean')", __LINE__, __FILE__);

			$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("createdby_id" => $account_id, "size" => 1024, "mime_type" => "Directory", "created" => $this->now, "modified" => "NULL", deleteable => "Y", "app" => $currentapp));

			$this->correct_attributes ($p->fake_full_path, array ($p->mask));

			$this->add_journal ($p->fake_full_path, array ($p->mask), VFS_OPERATION_CREATED);
		}
		else
		{
			return False;
		}

		return True;
	}

	/*!
	@function make_link
	@abstract Make a link from virtual directory $vdir to real directory $rdir
	@discussion Making a link from $vdir to $rdir will cause path_parts () to substitute $rdir for the real
			path variables when presented with $rdir
	@param $vdir Virtual dir to make link from
	@param $rdir Real dir to make link to
	@param $relatives Relativity array
	@result Boolean True/False
	*/

	function make_link ($vdir, $rdir, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];
		$currentapp = $phpgw_info["flags"]["currentapp"];

		$vp = $this->path_parts ($vdir, array ($relatives[0]));
		$rp = $this->path_parts ($rdir, array ($relatives[1]));

		if (!$this->acl_check ($vp->fake_full_path, array ($vp->mask), PHPGW_ACL_ADD))
		{
			return False;
		}

		if ((!$this->file_exists ($rp->real_full_path, array ($rp->mask))) && !mkdir ($rp->real_full_path, 0770))
		{
			return False;
		}

		if (!$this->mkdir ($vp->fake_full_path, array ($vp->mask)))
		{
			return False;
		}

		$size = $this->get_size ($rp->real_full_path, array ($rp->mask));

		$this->set_attributes ($vp->fake_full_path, array ($vp->mask), array ("link_directory" => $rp->real_leading_dirs, "link_name" => $rp->real_name, "size" => $size));

		$this->correct_attributes ($vp->fake_full_path, array ($vp->mask));

		return True;
	}

	/*!
	@function set_attributes
	@abstract Update database entry for $file with the attributes in $attributes
	@param $file file/directory to update
	@param $relatives Relativity array
	@param $attributes keyed array of attributes.  key is attribute name, value is attribute value
	@result Boolean True/False
	@discussion Valid attributes are:
			owner_id
			createdby_id
			modifiedby_id
			created
			modified
			size
			mime_type
			deleteable
			comment
			app
			link_directory
			link_name
			version
	*/

	function set_attributes ($file, $relatives = array (RELATIVE_CURRENT), $attributes = array ())
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		/*
		   This is kind of trivial, given that set_attributes () can change owner_id,
		   size, etc.
		*/
		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_EDIT))
		{
			return False;
		}

		if (!$this->file_exists ($file, array ($relatives[0])))
		{
			return False;
		}

		/*
		   All this voodoo just decides which attributes to keep, and which to update
		   depending on if the attribute was supplied in the $attributes array
		*/

		$ls_array = $this->ls ($p->fake_full_path, array ($p->mask), False, False, True);
		$record = $ls_array[0];

		$attribute_names = array ("owner_id", "createdby_id", "modifiedby_id", "created", "modified", "size", "mime_type", "deleteable", "comment", "app", "link_directory", "link_name", "version");

		while (list ($num, $attribute) = each ($attribute_names))
		{
			if (isset ($attributes[$attribute]))
			{
				$$attribute = $attributes[$attribute];

				if ($attribute == "comment")
				{
					$edited_comment = 1;
				}
			}
			else
			{
				$$attribute = $record[$attribute];
			}

			$$attribute = $this->db_clean ($$attribute);
		}

		$sql = "UPDATE phpgw_vfs SET ";

		reset ($attribute_names);
		while (list ($num, $attribute) = each ($attribute_names))
		{
			if ($num)
				$sql .= ", ";

			$sql .= "$attribute='" . $$attribute . "'";
		}

		$sql .= " WHERE file_id='$record[file_id]'";

		$query = $phpgw->db->query ($sql, __LINE__, __FILE__);

		if ($query) 
		{
			if ($edited_comment)
			{
				$this->add_journal ($p->fake_full_path, array ($p->mask), VFS_OPERATION_EDITED_COMMENT);
			}

			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function correct_attributes
	@abstract Set the correct attributes for $string (e.g. owner)
	@param $string File/directory to correct attributes of
	@param $relatives Relativity array
	@result Boolean True/False
	*/

	function correct_attributes ($string, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;

		$p = $this->path_parts ($string, array ($relatives[0]));

		if ($p->fake_leading_dirs != $this->fakebase && $p->fake_leading_dirs != "/")
		{
			$ls_array = $this->ls ($p->fake_leading_dirs, array ($p->mask), False, False, True);
			$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("owner_id" => $ls_array[0]["owner_id"]));

			return True;
		}
		elseif (preg_match ("+^$this->fakebase\/(.*)$+U", $p->fake_full_path, $matches))
		{
			$this->set_attributes ($p->fake_full_path, array ($p->mask), array ("owner_id" => $phpgw->accounts->name2id ($matches[1])));

			return True;
		}
		else
		{
			$this->set_attributes ($p->fake_full_name, array ($p->mask), array ("owner_id" => 0));

			return True;
		}
	}

	/*!
	@function file_type
	@abstract return file/dir type (MIME or other)
	@param $file File or directory path (/home/user/dir/dir2/dir3, /home/user/dir/dir2/file)
	@param $relatives Relativity array
	@result MIME type, "Directory", or nothing if MIME type is not known
	*/

	function file_type ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;

		$p = $this->path_parts ($file, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_READ, True))
		{
			return False;
		}

		/*
		   We don't use ls () because it calls file_type () to determine if it has been
		   passed a directory
		*/
		$query = $phpgw->db->query ("SELECT mime_type FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (VFS_SQL_SELECT), __LINE__, __FILE__);
		$phpgw->db->next_record ();
		$mime_type = $phpgw->db->Record["mime_type"];

		return ($mime_type);
	}

	/*!
	@function file_exists
	@abstract check if file/directory exists
	@param $string file/directory to check existance of
	@param $relatives Relativity array
	@result Boolean True/False
	*/

	function file_exists ($string, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;

		$p = $this->path_parts ($string, array ($relatives[0]));

		if ($p->outside)
		{
			$rr = file_exists ($p->real_full_path);

			return $rr;
		}

		$query = $phpgw->db->query ("SELECT name FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (VFS_SQL_SELECT), __LINE__, __FILE__);

		if ($phpgw->db->next_record ())
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function get_size
	@abstract Return size of $string
	@param $string file/directory to get size of
	@param $relatives Relativity array
	@param $checksubdirs Boolean, recursively add the size of all sub directories as well?
	@result Size of $string in bytes
	*/

	function get_size ($string, $relatives = array (RELATIVE_CURRENT), $checksubdirs = True)
	{
		global $phpgw, $phpgw_info;

		$p = $this->path_parts ($string, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_READ, True))
		{
			return False;
		}

		/*
		   WIP - this should run through all of the subfiles/directories in the directory and tally up
		   their sizes.  Should modify ls () to be able to return a list for files outside the virtual root
		*/
		if ($p->outside)
		{
			$size = filesize ($p->real_full_path);

			return $size;
		}

		$ls_array = $this->ls ($p->fake_full_path, array ($p->mask), $checksubdirs, False, !$checksubdirs);

		while (list ($num, $file_array) = each ($ls_array))
		{
			/*
			   Make sure the file is in the directory we want, and not
			   some deeper nested directory with a similar name
			*/
			if (!ereg ("^$p->fake_full_path", $file_array["directory"]))
			{
				continue;
			}

			$size += $file_array["size"];
		}

		if ($checksubdirs)
		{
			$query = $phpgw->db->query ("SELECT size FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (VFS_SQL_SELECT));
			$phpgw->db->next_record ();
			$size += $phpgw->db->Record[0];
		}

		return $size;
	}

	/*!
	@function checkperms
	@abstract Check if $this->working_id has write access to create files in $dir
	@discussion Simple call to acl_check
	@param $dir Directory to check access of
	@param $relatives Relativity array
	@result Boolean True/False
	*/
		
	function checkperms ($dir, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw, $phpgw_info;

		$p = $this->path_parts ($dir, array ($relatives[0]));

		if (!$this->acl_check ($p->fake_full_path, array ($p->mask), PHPGW_ACL_ADD))
		{
			return False;
		}
		else
		{
			return True;
		}
	}

	/*!
	@function ls
	@abstract get directory listing or info about a single file
	@discussion Note: The entries are not guaranteed to be returned in any logical order
		    Note: The size for directories does not include subfiles/subdirectories.
			  If you need that, use $this->get_size ()
	@param $dir File or Directory
	@param $relatives Relativity array
	@param $checksubdirs Boolean, recursively list all sub directories as well?
	@param $mime_type Only return entries matching MIME-type $mime_type.  Can be any MIME-type, "Directory" or "\ " for those without MIME types
	@param $nofiles Boolean.  True means you want to return just the information about the directory $dir.  If $dir is a file, $nofiles is implied.  This is the equivalent of 'ls -ld $dir'
	@param $orderby How to order results
	@result array of arrays.  Subarrays contain full info for each file/dir.
	*/

	function ls ($dir = False, $relatives = array (RELATIVE_CURRENT), $checksubdirs = True, $mime_type = False, $nofiles = False, $orderby = "Directory")
	{
		global $phpgw, $phpgw_info;

		$p = $this->path_parts ($dir, array ($relatives[0]));
		$dir = $p->fake_full_path;

		/* If they pass us a file or $nofiles is set, return the info for $dir only */
		if ((($type = $this->file_type ($dir, array ($p->mask))) != "Directory") || ($nofiles))
		{
			$p = $this->path_parts ($dir, array ($p->mask));

			$sql = "SELECT ";

			reset ($this->attributes);
			while (list ($num, $attribute) = each ($this->attributes))
			{
				if ($num)
					$sql .= ", ";

				$sql .= "$attribute";
			}

			$sql .= " FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'";
			$sql .= $this->extra_sql (VFS_SQL_SELECT);

			$query = $phpgw->db->query ($sql, __LINE__, __FILE__);

			$phpgw->db->next_record ();
			$record = $phpgw->db->Record;

			/* We return an array of one array to maintain the standard */
			$rarray = array ();
			reset ($this->attributes);
			while (list ($num, $attribute) = each ($this->attributes))
			{
				$rarray[0][$attribute] = $record[$attribute];
			}

			return $rarray;
		}

		$sql = "SELECT ";

		reset ($this->attributes);
		while (list ($num, $attribute) = each ($this->attributes))
		{
			if ($num)
				$sql .= ", ";

			$sql .= "$attribute";
		}

		$dir_clean = $this->db_clean ($dir);
		$sql .= " FROM phpgw_vfs WHERE directory LIKE '$dir_clean%'";
		$sql .= $this->extra_sql (VFS_SQL_SELECT);

		if ($mime_type)
		{
			$sql .= " AND mime_type='$mime_type'";
		}

		$sql .= " ORDER BY $orderby";

		$query = $phpgw->db->query ($sql, __LINE__, __FILE__);

		$rarray = array ();
		for ($i = 0; $phpgw->db->next_record (); $i++)
		{
			$record = $phpgw->db->Record;

			/* Further checking on the directory.  This makes sure /home/user/test won't match /home/user/test22 */
			if (!ereg ("^$dir(/|$)", $record["directory"]))
			{
				continue;
			}

			/* If they want only this directory, then $dir should end without a trailing / */
			if (!$checksubdirs && ereg ("^$dir/", $record["directory"]))
			{
				continue;
			}

			reset ($this->attributes);
			while (list ($num, $attribute) = each ($this->attributes))
			{
				$rarray[$i][$attribute] = $record[$attribute];
			}
		}

		return $rarray;
	}

	/*!
	@function dir
	@abstract shortcut to ls
	*/

	function dir ($dir = False, $relatives = array (RELATIVE_CURRENT), $checksubdirs = True, $mime_type = False, $nofiles = False)
	{
		return $this->ls ($dir, $relatives, $checksubdirs, $mime_type, $nofiles);
	}
}

?>
