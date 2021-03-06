<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
 // UserSpice Specific Functions
function testUS() {
	echo "<br>";
	echo "UserSpice Functions have been properly included";
	echo "<br>";
}


function get_gravatar($email, $s = 120, $d = 'mm', $r = 'pg', $img = false, $atts = array() ) {
	$url = 'https://www.gravatar.com/avatar/';
	$url .= md5( strtolower( trim( $email ) ) );
	$url .= "?s=$s&d=$d&r=$r";
	if ( $img ) {
		$url = '<img src="' . $url . '"';
		foreach ( $atts as $key => $val )
		$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}
	return $url;
}

//Check if a group ID exists in the DB
function groupIdExists($id) {
	$db = DB::getInstance();
	return (boolean)$db->findById('groups', $id);
}

//Check if a user ID exists in the DB
function userIdExists($id) {
	$db = DB::getInstance();
	return (boolean)$db->findById('users', $id);
}

//Retrieve information for a single group
function fetchGroupDetails($id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT id, name FROM groups WHERE id = ? LIMIT 1",array($id));
	$results = $query->first();
	$row = array('id' => $results->id, 'name' => $results->name);
	return ($row);
}

//Change a group's name
function updateGroupName($id, $name) {
	$db = DB::getInstance();
	$fields=array('name'=>$name);
	$db->update('groups',$id,$fields);
}

//Checks if a username exists in the DB
function usernameExists($username)   {
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM users WHERE username = ?",array($username));
	$results = $query->results();
	return ($results);
}

//Retrieve information for all users
function fetchAllUsers() {
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM users");
	$results = $query->results();
	return ($results);
}

//Retrieve complete user information by username, token or ID
function fetchUserDetails($username=NULL,$token=NULL, $id=NULL) {
	if ($username!=NULL) {
		$column = "username";
		$data = $username;
	} elseif ($id!=NULL) {
		$column = "id";
		$data = $id;
	}
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM users WHERE $column = $data LIMIT 1");
	$results = $query->first();
	return ($results);
}

//Retrieve list of groups a user is member of
function fetchUserGroups($user_id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM groups_users WHERE user_id = ?",array($user_id));
	$results = $query->results();
	return ($results);
}


//Retrieve list of users/groups who are members of a given group (NO NESTING)
// or (if $reverse_logic == true) who are NOT members of a given group
function fetchGroupMembers_raw($group_id, $reverse_logic=false) {
	$db = DB::getInstance();
	if ($reverse_logic) {
		$sql = "SELECT users.id as id, username AS name, 'user' AS group_or_user
						FROM users
						WHERE NOT EXISTS (
							SELECT *
							FROM groups_users_raw
							WHERE users.id = user_id
							AND user_is_group = 0
							AND group_id = ?
						)
						UNION
						SELECT groups.id as id, groups.name as name, 'group' AS group_or_user
						FROM groups
						WHERE id != ?
						AND NOT EXISTS (
							SELECT *
							FROM groups_users_raw
							WHERE groups.id = user_id
							AND user_is_group = 1
							AND group_id = ?
						)
						";
		$bindvals = array($group_id,$group_id,$group_id);
	} else {
		$sql = "SELECT user_id as id, username AS name, 'user' AS group_or_user
						FROM groups_users_raw
						JOIN users ON (user_id = users.id)
						WHERE user_is_group = 0
						AND group_id = ?
						UNION
						SELECT user_id as id, name AS name, 'group' AS group_or_user
						FROM groups_users_raw
						JOIN groups ON (user_id = groups.id)
						WHERE user_is_group = 1
						AND group_id = ?
						";
		$bindvals = array($group_id,$group_id);
	}
	#echo "DEBUG: group_id = $group_id, sql=$sql<br />\n";
	$query = $db->query($sql,$bindvals);
	#echo "DEBUG: count=".$query->count()."<br />\n";
	return $query->results();
}

//Retrieve list of users who are members of a given group
// or (if $reverse_logic == true) who are NOT members of a given group
function fetchGroupUsers($group_id, $reverse_logic=false) {
	$db = DB::getInstance();
	if ($reverse_logic) {
		$sql = "SELECT users.id as user_id, username
						FROM users
						WHERE NOT EXISTS (
							SELECT *
							FROM groups_users
							WHERE users.id = user_id
							AND group_id = ?
						)";
	} else {
		$sql = "SELECT user_id, username
						FROM groups_users
						JOIN users ON (user_id = users.id)
						WHERE group_id = ?";
	}
	echo "DEBUG: group_id = $group_id, sql=$sql<br />\n";
	$query = $db->query($sql,array($group_id));
	echo "DEBUG: count=".$query->count()."<br />\n";
	return $query->results();
}

//Remove user(s) from group(s)
// $user_is_group is provided programmatically (never from a form) so doesn't
// need to be bound
function deleteGroupsUsers_raw($groups, $users, $user_is_group=0) {
	$db = DB::getInstance();
	$bindvals = array();
	$sql = "DELETE FROM groups_users_raw WHERE user_is_group = $user_is_group AND "
	 				. $db->calcInOrEqual('group_id', $groups, $bindvals)
					. " AND "
	 				. $db->calcInOrEqual('user_id', $users, $bindvals);
	$q = $db->query($sql,$bindvals);
    return $q->count();
}

//Retrieve a list of all .php files in root files folder
function getPathPhpFiles($absRoot,$urlRoot,$fullPath) {
	$directory = $absRoot.$urlRoot.$fullPath;
	//bold ($directory);
	$pages = glob($directory . "*.php");

	foreach ($pages as $page) {
		$fixed = str_replace($absRoot.$urlRoot,'',$page);
		$row[$fixed] = $fixed;
	}
	return $row;
}

//Retrieve a list of all .php files in root files folder
function getPageFiles() {
	$directory = "../";
	$pages = glob($directory . "*.php");
	foreach ($pages as $page) {
		$fixed = str_replace('../','/'.US_URL_ROOT,$page);
		$row[$fixed] = $fixed;
	}
	return $row;
}

//Retrive a list of all .php files in users/ folder
function getUSPageFiles() {
	$directory = "../users/";
	$pages = glob($directory . "*.php");
	foreach ($pages as $page) {
		$fixed = str_replace('../users/','/'.US_URL_ROOT.'users/',$page);
		$row[$fixed] = $fixed;
	}
	return $row;
}

//Delete a page from the DB
function deletePages($pages) {
	$db = DB::getInstance();
	if (!$query = $db->query("DELETE FROM pages WHERE id IN ({$pages})")) {
		throw new Exception('There was a problem deleting pages.');
	} else {
		return true;
	}
}

//Fetch information on all pages
function fetchAllPages() {
	$db = DB::getInstance();
	$query = $db->query("SELECT id, page, private FROM pages ORDER BY page ASC");
	$pages = $query->results();
	//return $pages;

	if (isset($row)) {
		return ($row);
	} else {
		return $pages;
	}
}

//Fetch information for a specific page
function fetchPageDetails($id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT id, page, private FROM pages WHERE id = ?",array($id));
	$row = $query->first();
	return $row;
}


//Check if a page ID exists
function pageIdExists($id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT private FROM pages WHERE id = ? LIMIT 1",array($id));
	$num_returns = $query->count();
	if ($num_returns > 0) {
		return true;
	} else {
		return false;
	}
}

//Toggle private/public setting of a page
function updatePrivate($id, $private) {
	$db = DB::getInstance();
	$result = $db->query("UPDATE pages SET private = ? WHERE id = ?",array($private,$id));
	return $result;
}

//Add a page to the DB
function createPages($pages) {
	$db = DB::getInstance();
	foreach($pages as $page) {
		$fields=array('page'=>$page, 'private'=>'0');
		$db->insert('pages',$fields);
	}
}

//Add authorization for a group to access page(s) (add to groups_pages)
function addGroupsPages($pages, $groups) {
	$db = DB::getInstance();
	$i = 0;
	foreach((array)$groups as $group_id) {
		foreach((array)$pages as $page_id) {
			$query = $db->query(
				"INSERT INTO groups_pages (group_id, page_id) VALUES (?, ?)",
				array($group_id, $page_id));
			$i++;
		}
	}
	return $i;
}

//Retrieve list of groups that can access a page
function fetchGroupsByPage($page_id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT id, group_id FROM groups_pages WHERE page_id = ? ",array($page_id));
	$results = $query->results();
	return($results);
}

//Retrieve list of groups that can access a menu
function fetchGroupsByMenu($menu_id) {
	$db = DB::getInstance();
	$query = $db->query("SELECT id, group_id FROM groups_menus WHERE menu_id = ? ",array($menu_id));
	$results = $query->results();
	return($results);
}

//Retrieve list of pages that a group can access
function fetchPagesByGroup($group_id) {
	$db = DB::getInstance();

	$query = $db->query(
        "SELECT m.id as id, m.page_id as page_id, p.page as page, p.private as private
            FROM groups_pages AS m
            INNER JOIN pages AS p ON m.page_id = p.id
            WHERE m.group_id = ?",[$group_id]);
	$results = $query->results();
	return ($results);
}

//Remove authorization for a group to access page(s) (delete from groups_pages)
function deleteGroupsPages($pages, $groups) {
	$db = DB::getInstance();
	$bindvals = array();
	$sql = "DELETE FROM groups_pages WHERE "
	 				. $db->calcInOrEqual('group_id', $groups, $bindvals)
					. " AND "
	 				. $db->calcInOrEqual('page_id', $pages, $bindvals);
	$q = $db->query($sql,$bindvals);
	return $q->count();
}

//Delete a defined array of users
function deleteUsers($users) {
	$db = DB::getInstance();
	$i = 0;
	foreach($users as $id) {
		$query1 = $db->query("DELETE FROM users WHERE id = ?",array($id));
		$query2 = $db->query("DELETE FROM groups_users_raw WHERE user_id = ?",array($id));
		$query3 = $db->query("DELETE FROM profiles WHERE user_id = ?",array($id));
		$i++;
	}
	return $i;
}

function defaultPage($type) {
	if ($page = configGet('userspice/default_'.$type.'_page'))
		return $page;
	# this (below) needs work -- I didn't see any "global $site_settings;" anywhere so didn't know how it was working
	if ($page = $site_settings[$type])
		return $page;
	switch ($type) {
		case 'blocked':
			$page = 'users/blocked.php';
			break;
		case 'nologin':
		 	$page = 'users/nologin.php';
			break;
		default:
		 	$page = 'index.php';
			break;
	}
	return $page;
}

//Check token, die if bad
function checkToken($name='csrf', $method='post') {
	if (Input::exists($method)) {
		if (!Token::check(Input::get($name))) {
			die(lang('TOKEN'));
		}
	}
}

//Check if a user has access to a page
function securePage($uri) {
	global $user;
	/*
	Write the requested $uri to the session variable.
	This will preserve the request for a secured page.
	*/
	$_SESSION['securePageRequest']= $uri;

	# If user is NEVER allowed or ALWAYS allowed then return that status without
	# checking/calculating anything that requires (relatively slow) access to the DB
	// dnd($user);
	if (isset($user) && $user->data() != null) {
		if ($user->data()->permissions==0) {
			Redirect::to(US_URL_ROOT.defaultPage('blocked'));
		}
		if ($user->isAdmin())
			return true;
	}

	$db = DB::getInstance();

	# Load site wide settings
	$site_settings_results = $db->query("SELECT * FROM settings");
	$site_settings = $site_settings_results->first();

	$urlRootLength=strlen(US_URL_ROOT);
	$page=substr($uri,$urlRootLength,strlen($uri)-$urlRootLength);
	//bold($page);

	$id = null;
	$private = null;
	//retrieve page details
	$query = $db->query("SELECT id, page, private FROM pages WHERE page = ?",[$page]);
	$count = $query->count();
	if ($count==0) {
		bold('<br><br>You must go into the Admin Panel and click the Manage Pages button to add this page to the database. Doing so will make this error go away.');
		die();
	}
	$results = $query->first();

	$pageID = $results->id;

	if ($results->private == 0) { //If page is public, allow access
		return true;
	} elseif (!$user->isLoggedIn()) { //If user is not logged in, deny access
		Redirect::to(US_URL_ROOT.defaultPage('nologin'));
		return false;
	} elseif (userHasPageAuth($pageID, $user->data()->id)) {
        return true;
    }

	# We've tried everything - send them to the default page
    Redirect::to(US_URL_ROOT.configGet('redirect_deny_noperm'));
    return false;
}

function userHasPageAuth($page_id, $user_id=null) {
	global $user;
	if (is_null($user_id)) $user_id = $user->data()->id;
	$db = DB::getInstance();
	$sql = "SELECT groups_pages.group_id
					FROM groups_pages
					JOIN groups_users ON (groups_users.group_id = groups_pages.group_id)
					WHERE groups_users.user_id = ?
					AND groups_pages.page_id = ?";
	$query = $db->query($sql, [$user_id, $page_id]);
	return ($query->count() > 0);
}

function checkMenu($menu_id, $user_id=null) {
	global $user;
	$db = DB::getInstance();
	//Grant access if master user
	if ($user->isAdmin())
		return true;

	# Check if this menu has unrestricted access (group_id==0)
	$query = $db->query("SELECT id FROM groups_menus WHERE group_id = 0 AND menu_id = ?",array($menu_id));
	if ($query->count()) {
		return true;
	}

	# If a user_id was passed in, see if that user is part of a group which has access to this menu item
	if (!is_null($user_id)) {
		$sql = "SELECT groups_users.group_id
				FROM groups_menus
				INNER JOIN groups_users ON (groups_menus.group_id = groups_users.group_id)
				WHERE menu_id = ?
				AND user_id = ? ";
		$query = $db->query($sql,array($menu_id, $user_id));
		if ($query->count()) {
			return true;
		}
	}
}

//Retrieve information for all groups
function fetchAllGroups() {
	$db = DB::getInstance();
	return $db->findAll('groups')->results();
}

//Displays error and success messages
function resultBlock($errors,$successes) {
	//Error block
	if (count($errors) > 0) {
		echo "<div class='alert alert-danger alert-dismissible' role='alert'> <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
		<ul>";
		foreach($errors as $error) {
			echo "<li>".$error."</li>";
		}
		echo "</ul>";
		echo "</div>";
	}

	//Success block
	if (count($successes) > 0) {
		echo "<div class='alert alert-success alert-dismissible' role='alert'> <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
		<ul>";
		foreach($successes as $success) {
			echo "<li>".$success."</li>";
		}
		echo "</ul>";
		echo "</div>";
	}
}

//Inputs language strings from selected language.
function lang($key,$markers = NULL) {
	global $lang;
	$str = isset($lang[$key]) ? $lang[$key] : "No language key found: $key";
	if ($markers !== NULL) {
        //Replace any dynamic markers
        $iteration = 1;
		foreach((array)$markers as $marker) {
			$str = str_replace("%m".$iteration."%",$marker,$str);
			$iteration++;
		}
	}
	//Ensure we have something to return
	if ($str == "") {
		return ("No language key found: $key");
	} else {
		return $str;
	}
}

//Add all groups/users to the groups_users_raw mapping table
function addGroupsUsers_raw($group_ids, $users, $user_is_group=0) {
	$db = DB::getInstance();
	$i = 0;
	$sql = "INSERT INTO groups_users_raw (user_id,group_id,user_is_group) VALUES (?,?,?)";
	foreach((array)$group_ids as $group_id){
		foreach((array)$users as $user_id){
			#echo "<pre>DEBUG: AGU: group_id=$group_id, user_id=$user_id</pre><br />\n";
			if($db->query($sql,[$user_id,$group_id,$user_is_group])) {
				$i++;
			}
		}
	}
	return $i;
}

//Delete all authorized groups for the given menu(s) and then add from args
function updateGroupsMenus($group_ids, $menu_ids) {
	$db = DB::getInstance();
	$sql = "DELETE FROM groups_menus WHERE menu_id = ?";
	foreach((array)$menu_ids as $menu_id) {
		#echo "<pre>DEBUG: UGM: group_id=$group_id, menu_id=$menu_id</pre><br />\n";
		$db->query($sql,[$menu_id]);
	}
	return addGroupsMenus($group_ids, $menu_ids);
}

//Add all groups/menus to the groups_menus mapping table
function addGroupsMenus($group_ids, $menu_ids) {
	$db = DB::getInstance();
	$i = 0;
	$sql = "INSERT INTO groups_menus (group_id,menu_id) VALUES (?,?)";
	foreach((array)$group_ids as $group_id){
		foreach((array)$menu_ids as $menu_id){
			#echo "<pre>DEBUG: AGM: group_id=$group_id, menu_id=$menu_id</pre><br />\n";
			if($db->query($sql,[$group_id,$menu_id])) {
				$i++;
			}
		}
	}
	return $i;
}


//Delete group(s) from the DB
function deleteGroups($groups) {
	global $errors;
	$i = 0;
	$db = DB::getInstance();
	foreach((array)$groups as $id) {
		if ($id == 1) {
            $errors[] = lang("CANNOT_DELETE_NEWUSERS");
		}
		elseif ($id == 2) {
			$errors[] = lang("CANNOT_DELETE_ADMIN");
		} else {
			$query1 = $db->query("DELETE FROM groups WHERE id = ?",array($id));
			$query2 = $db->query("DELETE FROM groups_users_raw WHERE group_id = ?",array($id));
			$query3 = $db->query("DELETE FROM groups_users_raw WHERE user_id = ? AND user_is_group = 1",array($id));
			$query4 = $db->query("DELETE FROM groups_pages WHERE group_id = ?",array($id));
			$i++;
		}
	}
	return $i;
}

//Checks if an email is valid
function isValidEmail($email) {
	if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return true;
	}
	else {
		return false;
	}
}

//Check if an email exists in the DB
function emailExists($email) {
	$db = DB::getInstance();
	$query = $db->query("SELECT email FROM users WHERE email = ?",array($email));
	$num_returns = $query->count();
	if ($num_returns > 0) {
		return true;
	} else {
		return false;
	}
}

//Update a user's email
function updateEmail($id, $email) {
	$db = DB::getInstance();
	$fields=array('email'=>$email);
	$db->update('users',$id,$fields);

	return true;
}

function dbg($str, $level=1) {
    global $debugLevel;
    if (!isset($debugLevel)) $debugLevel = 3;
    if ($level < $debugLevel) {
    	echo "<text padding='1em' align='center'><h4><span style='background:white'>";
        echo "DEBUG: ".$str;
    	echo "</h4></span></text>";
    }
}
