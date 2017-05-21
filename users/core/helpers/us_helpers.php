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

# When doing a regex on a pathname we need to make sure that all magic chars are
# appropriately escaped and we need to make sure that any forward slash can be a
# back slash and vice versa depending on DIRECTORY_SEPARATOR
function path2regex ($path, $regex_delimiter='/') {
    $path = preg_quote($path, $regex_delimiter);
    if (DIRECTORY_SEPARATOR == '\\') {
        $find = ['\\\\', '\\/'];
        $repl = '[\\/\\\\]';
        // This weird 2-step replacement is necessary because otherwise str_replace
        // "steps on itself" because the $find is in the $repl.
        $path = str_replace($find, '{DIRECTORY_SEPARATOR}', $path);
        $path = str_replace('{DIRECTORY_SEPARATOR}', $repl, $path);
    }
    /*
    else {
        $find = '\\/';
        $repl = '\\/'; // unix filenames can have back-slashes that aren't directory separators
    }
    */
    return $path;
}

//Check if the group has admin privileges
function groupIsAdmin($id) {
	$db = DB::getInstance();
	if ($g = $db->queryById('groups', $id)->first()) {
        return ($g->admin);
    }
    return false;
}
//Check if a group ID exists in the DB
function groupIdExists($id) {
    return (boolean)fetchGroupDetails($id);
}

//Check if a user ID exists in the DB
function userIdExists($id) {
	$db = DB::getInstance();
	return $db->queryById('users', $id)->found();
}

//Retrieve information for a single group
function fetchGroupDetails($id) {
	$db = DB::getInstance();
	return $db->queryById('groups', $id)->first();
}

//Retrieve all group types
function fetchAllGroupTypes() {
	$db = DB::getInstance();
	return $db->queryAll('grouptypes')->results();
}

//Find the row where $searchProp has $searchVal in the array of
//objects in $aoo (arrayOfObject) and return the property of $rtnProp
//  (this is useful for searching results from database)
function findValInArrayOfObject($aoo, $searchProp, $searchVal, $rtnProp) {
    foreach ($aoo as $o) {
        if ($o->$searchProp == $searchVal) {
            return $o->$rtnProp;
        }
    }
    return false;
}

//Retrieve information for all users
function fetchAllUsers() {
	$db = DB::getInstance();
	return $db->queryAll('users')->results();
}

//Retrieve complete user information by username, token or ID
function fetchUserDetails($username=NULL,$token=NULL, $id=NULL) {
    global $T;
	if ($username!=NULL) {
		$column = "username";
		$data = $username;
	} elseif ($id!=NULL) {
		$column = "id";
		$data = $id;
	}
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM $T[users] WHERE $column = ? LIMIT 1", [$data]);
	$results = $query->first();
	return ($results);
}

//Retrieve list of groups a user is member of
function fetchUserGroups($user_id) {
    global $T;
	$db = DB::getInstance();
	$query = $db->query("SELECT * FROM $T[groups_users] WHERE user_id = ?",array($user_id));
	$results = $query->results();
	return ($results);
}


//Retrieve list of users/groups who are members of a given group (NO NESTING)
function fetchGroupMembers_raw($opts) {
    global $T;
    if (!$group_id = $opts['group_id']) {
        return false;
    }
	$db = DB::getInstance();
    $sql = '';
    $bindvals = [];
    if (@$opts['users']) {
		$sql .= "SELECT user_id as id, CONCAT(fname, ' ', lname, ' (', username, ')') AS name, 'user' AS group_or_user
    			 FROM $T[groups_users_raw]
    			 JOIN $T[users] users ON (user_id = users.id)
    			 WHERE user_is_group = 0
    			 AND group_id = ?";
        $bindvals[] = $group_id;
    }
    if (@$opts['users'] && @$opts['groups']) {
        $sql .= " UNION ";
    }
    if (@$opts['groups']) {
		$sql .= "SELECT user_id AS id, `name` AS `name`, 'group' AS group_or_user
			     FROM $T[groups_users_raw]
			     JOIN $T[groups] groups ON (user_id = groups.id)
			     WHERE user_is_group = 1
			     AND group_id = ? ";
    	$bindvals[] = $group_id;
    }
	#dbg("group_id = $group_id, sql=$sql<br />\n");
	$query = $db->query($sql,$bindvals);
	#echo "DEBUG: count=".$query->count()."<br />\n";
	return $query->results();
}
//Retrieve list of users who are members of a given group
function fetchUsersByGroup($group_id) {
    global $T;
	$db = DB::getInstance();
	$sql = "SELECT user_id, fname, lname, username
			FROM $T[groups_users]
			JOIN $T[users] users ON (user_id = users.id)
			WHERE group_id = ?";
	#echo "DEBUG: group_id = $group_id, sql=$sql<br />\n";
	return $db->query($sql,array($group_id))->results();
}

// Delete row from grouptypes
function deleteGrouptypes($grouptype_ids, &$errors, &$successes) {
    global $T;
	$db = DB::getInstance();
    $count = 0;
    foreach ((array)$grouptype_ids as $id) {
        if ($db->query("SELECT id FROM $T[groups] WHERE grouptype_id = ?", [$id])->count()) {
            $errors[] = lang('GROUPTYPE_IN_USE');
            $errors[] = lang('GROUPTYPE_DELETE_FAILED');
        } else {
            $db->deleteById('grouptypes', $id);
            if (!$db->count()) {
                $errors[] = lang('GROUPTYPE_DELETE_FAILED');
            }
            $count += $db->count();
        }
    }
    if ($count) {
        $successes[] = lang('GROUPTYPE_DELETE_SUCCESSFUL', $count);
    }
    return $count;
}
//Remove user(s) from group(s)
// $user_is_group is provided programmatically (never from a form) so doesn't
// need to be bound
//To maintain data integrity, also delete from groups_roles_users
function deleteGroupsUsers($groups, $users, $user_is_group=0) {
    global $T;
	$db = DB::getInstance();
    $count = 0;
	$sql = "DELETE FROM $T[groups_users_raw]
            WHERE user_is_group = $user_is_group
            AND group_id = ?
			AND user_id = ?";
    $sql2 = "DELETE FROM $T[groups_roles_users]
            WHERE group_id = ?
            AND user_id = ?";
    foreach ((array)$groups as $g) {
        foreach ((array)$users as $u) {
        	$count += $db->query($sql, [$g, $u])->count();
            if ($user_is_group == 0) {
                $db->query($sql2, [$g, $u]);
            }
        }
    }
    return $count;
}

// like deleteGroupsUsers() above except data integrity is NOT
// enforced - no delete is done from groups_roles_users
function deleteGroupsUsers_raw($groups, $users, $user_is_group=0) {
    global $T;
	$db = DB::getInstance();
    $count = 0;
	$sql = "DELETE FROM $T[groups_users_raw]
            WHERE user_is_group = $user_is_group
            AND group_id = ?
			AND user_id = ?";
    foreach ((array)$groups as $g) {
        foreach ((array)$users as $u) {
        	$count += $db->query($sql, [$g, $u])->count();
        }
    }
    return $count;
}

//Delete a page from the DB
function deletePages($pages) {
    global $T;
	$db = DB::getInstance();
    $count=0;
    $sql = "DELETE FROM $T[pages] WHERE id = ?";
    $sql2 = "DELETE FROM $T[menus] WHERE page_id = ?";
    foreach ((array)$pages as $p) {
    	if ($query = $db->query($sql, [$p])) {
            $db->query($sql2, [$p]);
    		$count++;
    	}
    }
    return $count;
}

function fetchResults($sql, $bindvals=[], $queryType=0) {
    $db = DB::getInstance();
    return $db->query($sql, $bindvals)->results($queryType);
}

//Fetch information on all pages
function fetchAllPages($queryType=0) {
    global $T;
    return fetchResults("SELECT id, page, private FROM $T[pages] ORDER BY page ASC", [], $queryType);
}
function fetchPublicPages($junk=false) {
    global $T;
    return fetchResults("SELECT id, page FROM $T[pages] WHERE private = 0 ORDER BY page ASC");
}

//Fetch information for a specific page
function fetchPageDetails($id) {
	$db = DB::getInstance();
	$query = $db->queryAll('pages', 'id = ?', array($id));
	$row = $query->first();
	return $row;
}

//Check if a page ID exists
function pageIdExists($id) {
    global $T;
	$db = DB::getInstance();
	$query = $db->query("SELECT private FROM $T[pages] WHERE id = ? LIMIT 1",array($id));
	$num_returns = $query->count();
	if ($num_returns > 0) {
		return true;
	} else {
		return false;
	}
}

//Set private/public setting of a page
# THIS FUNCTION CAN BE DELETED
function updatePrivate($id, $private) {
    global $T;
	$db = DB::getInstance();
	return $db->update($T['pages'], $id, ['private'=>$private]);
}

//Add a page to the DB
function createPages($pages) {
    global $T;
	$db = DB::getInstance();
    $count = 0;
	foreach($pages as $page) {
		$fields=array('page'=>$page, 'private'=>'0');
		$db->insert($T['pages'],$fields);
        $count++;
	}
    return $count;
}

//Add authorization for a group to access page(s) (add to groups_pages)
function addGroupsPages($pages, $groups) {
    global $T;
	$db = DB::getInstance();
	$i = 0;
	$insSql = "INSERT INTO $T[groups_pages]
                (group_id, page_id)
                VALUES (?, ?)";
    $findSql = "SELECT id FROM $T[groups_pages]
                WHERE group_id = ?
                AND page_id = ? ";
	foreach((array)$groups as $group_id) {
		foreach((array)$pages as $page_id) {
            if ($db->query($findSql, [$group_id, $page_id])->count() == 0) {
    			$db->query( $insSql, [$group_id, $page_id]);
    			$i++;
            }
		}
	}
	return $i;
}

//Retrieve list of groups that can access a page
function fetchGroupsByPage($page_id) {
    global $T;
	$db = DB::getInstance();
	$sql = "SELECT gp.id, gp.group_id, g.name, g.short_name
            FROM $T[groups_pages] gp
            LEFT JOIN $T[groups] g ON (gp.group_id = g.id)
            WHERE page_id = ?
            ORDER BY g.name, g.short_name";
	return $db->query($sql, [$page_id])->results();
}

//Retrieve list of groups that can NOT access a page
function fetchGroupsByNotPage($page_id) {
    global $T;
	$db = DB::getInstance();
	$sql = "SELECT id, name, short_name
            FROM $T[groups] g
            WHERE NOT EXISTS
                (SELECT *
                 FROM $T[groups_pages] gp
                 WHERE gp.group_id = g.id
                 AND gp.page_id = ?)
            ORDER BY name, short_name";
	return $db->query($sql, [$page_id])->results();
}

//Retrieve list of pages that a group can access
function fetchPagesByGroup($group_id) {
    global $T;
	$db = DB::getInstance();
	$query = $db->query(
        "SELECT p.id, p.page, p.private
         FROM $T[groups_pages] gp
         INNER JOIN $T[pages] p ON (gp.page_id = p.id)
         WHERE gp.group_id = ?",[$group_id]);
	return $query->results();
}

//Remove authorization for group(s) to access page(s) (delete from groups_pages)
function deleteGroupsPages($pages, $groups) {
    global $T;
	$db = DB::getInstance();
	$count = 0;
	$sql = "DELETE FROM $T[groups_pages] WHERE group_id = ? AND page_id = ?";
    foreach ((array)$pages as $p) {
        foreach ((array)$groups as $g) {
        	$q = $db->query($sql, [$g, $p]);
            $count++;
        }
    }
	return $count;
}

//Delete a defined array of users
function deleteUsers($users) {
    global $T;
	$db = DB::getInstance();
	$i = 0;
	foreach((array)$users as $id) {
		$query1 = $db->deleteById('users',$id);
		$query2 = $db->query("DELETE FROM $T[groups_users_raw] WHERE user_id = ?",array($id));
		$query3 = $db->query("DELETE FROM $T[profiles] WHERE user_id = ?",array($id));
		$i++;
	}
	return $i;
}

//Delete rows from groups_roles_users
// optional 2nd arg allows to delete from groups_users_raw if needed
function deleteGroupsRolesUsers($gru_ids, $fix_groups_users_too=false) {
    global $T;
	$db = DB::getInstance();
	$i = 0;
	foreach((array)$gru_ids as $id) {
        if ($fix_groups_users_too) {
            # Check if this is the last group for which this user holds this role
            if ($results = $db->queryById('groups_roles_users', $id)->first()) {
                $sql = "SELECT id
                        FROM $T[groups_roles_users]
                        WHERE role_group_id = ?
                        AND user_id = ?
                        AND id != ?";
                if ($db->query($sql, [$results->role_group_id, $results->user_id, $id])->count() < 1) {
                    deleteGroupsUsers_raw($results->role_group_id, $results->user_id);
                }
            }
        }
		$i += $db->deleteById("groups_roles_users",$id)->count();
	}
	return $i;
}

//Check token, die if bad
function checkToken($name='csrf', $method='post') {
	if (Input::exists($method)) {
		if (!Token::check(Input::get($name))) {
			die(lang('TOKEN'));
		}
	}
}

// calculate the redirect destination after a page is saved
// uses settings table, perhaps overridden by page table
// ARGS:
//   $action - either 'edit' or 'create' or 'delete' - what type of save just occurred
//   $pageName - current page, no ?id=n in the URL (usually from $_SERVER['PHP_SELF'])
//   $multiRowPage - true=this is a list page; false=this is a single-row editing page
//   $lastId - if we just created a row this ID tells us which row was added
// RETURN VALUE:
//   false - error (but usually you can just treat it as null)
//   null - don't redirect anywhere - the current page is just fine
//   anything else - a destination to which you will redirect
// VALUES FOR pages.after_(create|edit|delete) and settings.(multi|single)_row_after_(create|edit|delete):
//   0=no override (only used in page)
//   1=stay on current page (if creating a new record, must redirect to current
//     page with ?id=n in URL)
//   2=return to breadcrumb parent after successful save
//   3=redirect to specified (custom) destination (only used in pages, not settings)
//     (The custom destination is then obtained from pages.after_(create|edit|delete)_redirect)
//   4=edit a new row (redirect to same page but without ?id=n in URL)
function redirDestAfterSave($action, $pageNames, $multiRowPage=false, $lastId=null) {
    #dbg("redirDestAfterSave(action=$action, pageName=$pageName, multiRowPage=$multiRowPage, lastId=$lastId): entering");
    $actionRenames = ['update'=>'edit', 'insert'=>'create', 'add'=>'create'];
    if (isset($actionRenames[$action])) {
        $action = $actionRenames[$action]; // making life easy for forgetful developers
    }
    if (!in_array($action, ['edit', 'create', 'delete'])) {
        #dbg("Bad action=$action");
        return false; // we don't know how to handle any other $action
    }
    $destType = 0; // assume no page override
    $pageRow = null;
    #dbg("redirDestAfterSave(): Checking page settings");
    $pageNames = (array)$pageNames;
    $pageName = $pageNames[0]; // default redirect for $destType==1 will be 1st element in array
    if ($pageRow = getPagerowByName($pageNames)) {
        $fieldName = 'after_'.$action; // after_edit, after_delete, etc.
        $destType = $pageRow->$fieldName;
        if ($destType == 3) { // custom redirect - not valid in settings below
            $fieldName .= '_redirect'; // after_edit_redirect, after_create_redirect, etc.
            if ($pageRow->$fieldName) {
                return $pageRow->$fieldName;
            } else { // mis-configured - treat it as if no setting at all in pages
                $destType = 0;
                $pageRow = null;
            }
        }
    }
    #dbg("redirDestAfterSave(): destType=$destType");
    if ($destType == 0) {
        #dbg("redirDestAfterSave(): Checking configGet");
        if ($multiRowPage) {
            $fieldName = 'multi_row_after_';
        } else {
            $fieldName = 'single_row_after_';
        }
        $fieldName .= $action;
        $destType = configGet($fieldName);
    }
    #dbg("redirDestAfterSave(): Switching on destType=$destType");
    switch ($destType) {
        case 1: // stay where you are (if creating then edit just-added record)
            if ($action == 'create') {
                return $pageName.'?id='.$lastId; // redirect to edit current record
            } else {
                return null; // just stay where you are - no redirect
            }
        case 2: // try to go to breadcrumb parent
            if ($pageRow && $pageRow->breadcrumb_parent_page_id) {
                $db = DB::getInstance();
                $pageRow = $db->findById('pages', $pageRow->breadcrumb_parent_page_id)->first();
                #dbg("Returning ".$pageRow->page);
                return $pageRow->page;
            } elseif ($action == 'edit') { // can't find page or no breadcrumb parent specified
                return null; // equivalent of $destType == 1 as above
            }
        #case 3: // handled up above -- only valid if on a specific page
        case 4: // insert a new record
            return $pageName; // assume it has no '?id=n' in the URL
    }
    dbg("ERROR: Unknown destination #$destType. Redirection will not work as expected.");
}

// find a file in a (configurable) list of paths
function pathFinder($file, $root=null, $configPathToken=null, $defaultPath=null) {
    if (is_null($root)) {
        $root = US_ROOT_DIR;
    }
    $paths = null;
    if ($configPathToken) {
        $paths = configGet($configPathToken, $defaultPath);
    }
    if (empty($paths)) {
        if (empty($defaultPath)) {
            $paths = configGet('us_script_path', [getcwd().'/local/', 'local/', 'core/']);
        } else {
            $paths = $defaultPath;
        }
    }
    if (!is_array($paths)) {
        $paths = preg_split('/[:;]/', $paths);
    }
    $root = trim($root);
    #dbg(substr($root, -1, 1));
    if ($root && substr($root, -1, 1) != '/') {
        #dbg("Adding<br />\n");
        $root .= '/';
    }
    foreach ($paths as $p) {
        #dbg("pathFinder($file): Checking '$p'<br />\n");
        $p = trim($p);
        #if (!$p) continue; // use "." if you want cwd
        if ($p && substr($p, -1, 1) != '/') {
            $p .= '/';
        }
        #dbg($root.$p.$file);
        if (file_exists($root.$p.$file)) {
            #dbg("pathFinder($file): Returning ".$root.$p.$file);
            return $root.$p.$file;
        }
    }
    #dbg("pathFinder($file): Failure<br />\n");
    return false;
}

# configGet(us_page_path) (set in local/config.php) can be (1) an array of
# multiple paths or (2) a semi-colon (or colon) delimited string of multiple
# paths or (3) a simple single path. If not set it defaults to US_URL_ROOT.
# Always returns an array.
# The paths returned represent potential location where pages might be found.
function getPagePath() {
    $path = configGet('us_page_path', US_URL_ROOT);
    if (!is_array($path)) {
        foreach ([':', ';'] as $delim) {
            if (strpos($path, $delim) !== false) {
                $path = explode($path, $delim);
                break;
            }
        }
    }
    return (array)$path;
}

function getPageLocation($page) {
    $path = getPagePath();
    foreach ((array)$path as $p) {
        if (file_exists(US_ROOT_DIR.$p.$page)) {
            return $p.$page;
        }
    }
    return $page; // last-ditch, desperate measure...
}

function getPagerowByName($pages) {
    global $T;
    $db = DB::getInstance();
    $found = false;
    foreach ((array)$pages as $page) {
    	$query = $db->queryAll('pages', 'page = ?', [$page]);
        if ($query->count() > 0) {
            $found = true;
            break;
        }
    }
	if (!$found) {
        return false;
    }
	return $query->first();
}

//Check if a user has access to a page
function securePage($uri=null) {
	global $user, $T;

	# If user is NEVER allowed or ALWAYS allowed then return that status without
	# checking/calculating anything that requires (relatively slow) access to the DB
	// dnd($user);
	if (isset($user) && $user->data() != null) {
		if ($user->data()->permissions==0) {
            $blocked_response = new StateResponse_Blocked;
            $blocked_response->respond();
		}
		if ($user->isAdmin())
			return true;
	}

	$db = DB::getInstance();

    if (!is_null($uri)) {
        $pages = [$uri];
    } else {
        $pages = [];
    }
    $save_uri = $uri;
    if (($uri = $_SERVER['PHP_SELF']) != $save_uri) {
        $pages[] = $uri;
    }
    if (substr($uri, 0, strlen(US_URL_ROOT)) == US_URL_ROOT) {
    	$pages[] = substr($uri,strlen(US_URL_ROOT));
    }
	//bold($page);

	//retrieve page details, whichever is the first to be found
    // normal priority: (1) $formName, (2) PHP_SELF, (3) PHP_SELF without US_URL_ROOT
	if (!$results = getPagerowByName($pages)) {
        bold($uri);
		bold(lang('SECURE_PAGE_PAGE_NOT_EXIST'));
		die();
	}

	$pageID = $results->id;

	if (!$results->private) { //If page is public, allow access
		return true;
	} elseif (!$user->isLoggedIn()) { //If user is not logged in, deny access
    	$_SESSION['securePageRequest']= $uri;
        $nologin_response = new StateResponse_DenyNoLogin;
        $nologin_response->respond();
		return false;
	} elseif (userHasPageAuth($pageID, $user->data()->id)) {
        return true;
    } else {
    	# We've tried everything - send them to the default page
        unset($_SESSION['securePageRequest']);
        $deny_response = new StateResponse_DenyNoPerm;
        $deny_response->respond();
        return false;
    }
}

function userHasPageAuth($page_id, $user_id=null) {
	global $user, $T;
	if (is_null($user_id)) {
        $user_id = $user->data()->id;
    }
	$db = DB::getInstance();
	$sql = "SELECT gp.group_id
			FROM $T[groups_pages] gp
			JOIN $T[groups_users] gu ON (gu.group_id = gp.group_id)
			WHERE gu.user_id = ?
			AND gp.page_id = ?";
	return $db->query($sql, [$user_id, $page_id])->count();
}

function checkMenu($menu_id, $user_id=null) {
	global $user, $T;
	$db = DB::getInstance();
	//Grant access if master user
	if ($user->isAdmin())
		return true;

	# Check if this menu has unrestricted access (group_id==0)
	$query = $db->query("SELECT id FROM $T[groups_menus] WHERE group_id = 0 AND menu_id = ?",array($menu_id));
	if ($query->count()) {
		return true;
	}

	# If a user_id was passed in, see if that user is part of a group which has access to this menu item
	if (!is_null($user_id)) {
		$sql = "SELECT gu.group_id
				FROM $T[groups_menus] gm
				INNER JOIN $T[groups_users] gu ON (gm.group_id = gu.group_id)
				WHERE menu_id = ?
				AND user_id = ? ";
		$query = $db->query($sql,array($menu_id, $user_id));
		if ($query->count()) {
			return true;
		}
	}
    return false;
}

//Retrieve information for all groups
function fetchAllGroups() {
    global $T;
	$db = DB::getInstance();
    $sql = "SELECT groups.*, gt.name AS grouptype_name
            FROM $T[groups] groups
            LEFT JOIN $T[grouptypes] gt ON (gt.id = groups.grouptype_id)
            ORDER BY name";
	return $db->query($sql)->results();
}

function fetchRolesByGroup($group_id) {
    global $T;
	$db = DB::getInstance();
    $sql = "SELECT gru.id, gru.role_group_id, groups.name, groups.short_name,
                    gru.user_id, users.username, users.fname, users.lname
            FROM $T[groups_roles_users] gru
            JOIN $T[groups] groups ON (gru.role_group_id = groups.id)
            JOIN $T[users] users ON (gru.user_id = users.id)
            WHERE gru.group_id = ?";
	return $db->query($sql, [$group_id])->results();
}

//Displays error and success messages
function resultBlock($errors,$successes) {
    #dbg("resultBlock(): entering");
    // If any errors or successes were saved off in the $_SESSION prior to redirecting to
    // this page, prepend them to the current $errors/$successes before displaying
    #var_dump($_SESSION);
    if (!empty($_SESSION['errors'])) {
        #dbg("resultBlock(): had errors in session: ".print_r($_SESSION['errors'], true));
        $errors = array_merge($_SESSION['errors'], $errors);
        $_SESSION['errors'] = [];
    }
    if (!empty($_SESSION['successes'])) {
        #dbg("resultBlock(): had successes in session: ".print_r($_SESSION['successes'], true));
        $successes = array_merge($_SESSION['successes'], $successes);
        $_SESSION['successes'] = [];
    }
    if (configGet('site_offline')) {
        $errors = array_merge([lang('SITE_OFFLINE_ADMIN_ONLY')], $errors);
    }

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

function hasLang($key) {
	global $lang;
    return isset($lang[$key]);
}
//Inputs language strings from selected language.
function lang($key,$markers = NULL,$dflt='') {
	global $lang, $T;
    /* part 1 - getting from `lang` table in multiple small queries
    $db = DB::getInstance();
    #dbg('LANGUAGE: '.configGet('site_language')." key=$key");
    $row = $db->query("SELECT message FROM $T[lang] WHERE lang = ? AND token = ?", [configGet('site_language'), $key])->first();
    if ($db->count() > 0) {
        #dbg("FOUND lang entry");
        if ($row->message) {
            $str = $row->message;
        } else {
            // this is separated for (slight) performance gains
            $row = $db->query("SELECT long_message FROM $T[lang] WHERE lang = ? AND token = ?", [configGet('site_language'),  $key])->first();
            $str = $row->long_message;
        }
    } else {
    */
        #dbg("NO lang entry");
    	$str = isset($lang[$key]) ? $lang[$key] : "";
        if (!$str && $dflt) {
            $str = $dflt;
        }
    /* part 2...
    }
    */
	if ($str && $markers !== NULL) {
        //Replace any dynamic markers
        $iteration = 1;
		foreach((array)$markers as $marker) {
			$str = str_replace("%m".$iteration."%",$marker,$str);
			$iteration++;
		}
	}
	// During development give a message to help id if missing the token
	if ($str == "" && configGet('debug_mode') ) {
        #dbg(substr($key, -6));
		return ("DEBUG: No language key found: $key");
	} else {
		return $str;
	}
}

function addGroupsRolesUsers($group_ids, $role_ids, $user_ids) {
    global $T;
    $db = DB::getInstance();
	$i = 0;
    $findsql = "SELECT id
                FROM $T[groups_roles_users]
                WHERE group_id = ?
                AND role_group_id = ?
                AND user_id = ?";
	$inssql = "INSERT INTO $T[groups_roles_users] (group_id,role_group_id,user_id)
                VALUES (?,?,?)";
	foreach((array)$group_ids as $group_id) {
		foreach((array)$role_ids as $role_id) {
    		foreach((array)$user_ids as $user_id) {
                # Check if it already exists - if not, add it
                $db->query($findsql, [$group_id, $role_id, $user_id]);
                if ($db->count() == 0) {
        			if($db->query($inssql,[$group_id,$role_id,$user_id])) {
        				$i++;
        			}
                }
            }
		}
	}
	return $i;
}

//Add all groups/users to the groups_users_raw mapping table
function addGroupsUsers_raw($group_ids, $user_ids, $user_is_group=0) {
    global $T;
	$db = DB::getInstance();
	$i = 0;
	$sql = "INSERT INTO $T[groups_users_raw] (group_id,user_id,user_is_group) VALUES (?,?,?)";
    $findsql = "SELECT id FROM $T[groups_users_raw] WHERE group_id = ? AND user_id = ? AND user_is_group = ?";
	foreach((array)$group_ids as $group_id){
		foreach((array)$user_ids as $user_id){
			#echo "<pre>DEBUG: AGU: group_id=$group_id, user_id=$user_id</pre><br />\n";
            $db->query($findsql, [$group_id, $user_id, $user_is_group]);
            if ($db->count() == 0) {
    			if($db->query($sql,[$group_id,$user_id,$user_is_group])) {
    				$i++;
    			}
            }
		}
	}
	return $i;
}

//Delete all authorized groups for the given menu(s) and then add from args
function updateGroupsMenus($group_ids, $menu_ids) {
    global $T;
	$db = DB::getInstance();
	$sql = "DELETE FROM $T[groups_menus] WHERE menu_id = ?";
	foreach((array)$menu_ids as $menu_id) {
		#echo "<pre>DEBUG: UGM: group_id=$group_id, menu_id=$menu_id</pre><br />\n";
		$db->query($sql,[$menu_id]);
	}
	return addGroupsMenus($group_ids, $menu_ids);
}

//Add all groups/menus to the groups_menus mapping table
function addGroupsMenus($group_ids, $menu_ids) {
    global $T;
	$db = DB::getInstance();
	$i = 0;
	$sql = "INSERT INTO $T[groups_menus] (group_id,menu_id) VALUES (?,?)";
	foreach((array)$group_ids as $group_id){
		foreach((array)$menu_ids as $menu_id){
			#echo "<pre>DEBUG: AGM: group_id=$group_id, menu_id=$menu_id</pre><br />\n";
			if($db->insert($T['groups_menus'], ['group_id'=>$group_id,'menu_id'=>$menu_id])) {
				$i++;
			}
		}
	}
	return $i;
}


//Delete group(s) from the `groups` table
function deleteGroups($groups, &$errors) {
	global $T;
	$i = 0;
	$db = DB::getInstance();
	foreach((array)$groups as $id) {
		if ($id == configGet('newuser_default_group', 1)) {
            $errors[] = lang("CANNOT_DELETE_NEWUSERS");
		} elseif (groupIsAdmin($id)) {
			$errors[] = lang("CANNOT_DELETE_ADMIN");
			$errors[] = lang("HOW_TO_DELETE_ADMIN");
			$errors[] = lang("BE_CAREFUL_DELETE_ADMIN");
		} else {
			$query1 = $db->query("DELETE FROM $T[groups] WHERE id = ?",array($id));
			$query2 = $db->query("DELETE FROM $T[groups_users_raw] WHERE group_id = ?",array($id));
			$query3 = $db->query("DELETE FROM $T[groups_users_raw] WHERE user_id = ? AND user_is_group = 1",array($id));
			$query4 = $db->query("DELETE FROM $T[groups_pages] WHERE group_id = ?",array($id));
			$i++;
		}
	}
	return $i;
}

// include a file and RETURN that value
// This allows the contents of included scripts to be manipulated as strings
function getInclude($fileName, $vars=[]) {
    ob_start();
    foreach ($vars as $var=>$val) {
        $$var = $val;
    }
    include($fileName);
    return ob_get_clean();
}

function pre_r($str) {
    echo "<pre>".print_r($str,true)."</pre>\n";
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
