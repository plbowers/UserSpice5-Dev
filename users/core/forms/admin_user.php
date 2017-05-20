<?php
/*
UserSpice
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

checkToken();

$userId = Input::get('id');
//Check if selected user exists
if (!$userId || !userIdExists($userId)) {
    $errors[] = lang('ERROR_NO_USER_EXISTS', $userId);
    Redirect::to("admin_users.php"); die();
}

$db = DB::getInstance();
global $T;
if ($userId) {
    $userdetails = fetchUserDetails(NULL, NULL, $userId);
    $unique_validation = ['action'=>'update', 'update_id'=>$userId];
    $memberGroups =  $db->query(
        "SELECT g.*
         FROM $T[groups] g
         LEFT JOIN $T[groups_users_raw] gur
            ON (gur.group_id = g.id AND gur.user_is_group = 0)
         WHERE gur.user_id = ? ORDER BY g.name", [$userId])->results();
    $nonMemberGroups = $db->query(
        "SELECT DISTINCT g.*
         FROM $T[groups] g
         WHERE NOT EXISTS (
            SELECT *
            FROM $T[groups_users_raw] gur
            WHERE g.id = gur.group_id
            AND gur.user_is_group = 0
            AND gur.user_id = ?
         )
         ORDER BY g.name", [$userId])->results();
} else {
    $userdetails = (object)[
        'id' => null,
        'email' => '',
        'fname' => '',
        'lname' => '',
        'username' => '',
        'join_date' => '',
        'logins' => 0,
    ];
    $unique_validation = ['action'=>'add'];
    $memberGroups =  $db->queryAll('groups', 'default_for_new_users = ?', [1], 'name')->results();
    $nonMemberGroups = $db->queryAll('groups', 'default_for_new_users = ?', [0], 'name')->results();
}
$grav = get_gravatar(strtolower(trim($userdetails->email)));
$myForm = new Form([
    new Form_Col([
        'avatar' => '<img src="'.$grav.'" class="img-responsive img-thumbnail" alt="">',
        'id' => new FormField_Text([
            'readonly' => true,
        ]),
        'join_date' => new FormField_Text([
            'readonly' => true,
        ]),
        'last_login' => new FormField_Text([
            'readonly' => true,
        ]),
        'logins' => new FormField_Text([
            'readonly' => true,
        ]),

    ], [
        'Col_Class' => 'col-xs-12 col-md-3',
    ]),
    new Form_Col([
        '<h3>User Information</h3>',
        'username' => new FormField_Text([
            'dbfield' => 'users.username', // remaining validation in `field_defs`
            'valid' => $unique_validation,
        ]),
        'email' => new FormField_Text([
            'dbfield' => 'users.email', // remaining validation in `field_defs`
            'valid' => $unique_validation,
        ]),
        'fname' => new FormField_Text([
            'dbfield' => 'users.fname', // remaining validation in `field_defs`
        ]),
        'lname' => new FormField_Text([
            'dbfield' => 'users.lname', // remaining validation in `field_defs`
        ]),
        'permissions' => new FormField_Select([
            'display' => lang('BLOCK_USER'),
            'hint_text' => lang('HINT_BLOCK_USER'),
            'data' => [
                ['id'=>'0', 'name'=>lang('BLOCKED'), ],
                ['id'=>'1', 'name'=>lang('NOT_BLOCKED'), ],
            ]
        ]),
        '<h3>'.lang('GROUP_MEMBERSHIP').'</h3>',
        new Form_Panel([
            'memberGroups' => new FormField_Table([
                'isdbfield' => false,
                'data' => $memberGroups,
                'th_row' => [lang('MARK_TO_DELETE'), lang('GROUP_NAME')],
                'td_row' => ['{CHECKBOX_ID}', '{NAME}'],
                'noData' => '<h4>'.lang('NOT_MEMBER_ANY_GROUPS').'</h4>',
            ]),
        ], [
            'head' => lang('REMOVE_USER_FROM_GROUPS'),
            'table' => 'groups_users_raw',
            'sqlsave' => "DELETE FROM $T[groups_users_raw] WHERE user_id = ? AND group_id = ? AND user_is_group = 0",
            'bindvals' => [$userId, '{MEMBERGROUPS}'],
            -or-
            'save_action' => 'delete',
            'sql_where' => 'user_id = ? AND group_id = ? AND user_is_group = 0'
        ]),
        new Form_Panel([
            'nonMemberGroups' => new FormField_Table([
                'isdbfield' => false,
                'data' => $nonMemberGroups,
                'th_row' => [lang('MARK_TO_ADD'), lang('GROUP_NAME')],
                'td_row' => ['{CHECKBOX_ID}', '{NAME}'],
                'noData' => '<h4>'.lang('NO_GROUPS_TO_JOIN').'</h4>',
            ]),
        ], [
            'head' => lang('ADD_USER_TO_GROUPS'),
            'table' => 'groups_users_raw',
            'sqlsave' => "INSERT INTO $T[groups_users_raw] (user_id, group_id, user_is_group) VALUES (?, ?, 0)",
            'bindvals' => [$userId, '{NONMEMBERGROUPS}'],
            -or-
            'save_action' => 'insert',
            'fields' => ['user_id' => $userId, 'group_id' => '{MEMBERGROUPS}', 'user_is_group' => 0],
        ]),
        'save' => new FormField_ButtonSubmit,
    ], [
        'Col_Class' => 'col-xs-12 col-md-9',
    ]),
], [
    'dbtable' => 'users',
    'default' => 'processing',
]);

/*
 * Process the form (cannot be done with autosave)
 */
if (Input::exists('post')) {

  $validation->check($_POST);
  if ($validation->passed()) {
		foreach (['username', 'fname', 'lname', 'permissions', 'email'] as $f) {
			$fields[$f] = Input::get($f, 'post');
		}
    $db->update('users',$userId,$fields);
    $successes[] = lang("ACCOUNT_DETAILS_UPDATED");
  } else {
		$errors = $validation->stackErrorMessages($errors);
  }

  //Remove group(s)
  if ($remove = Input::get('removeGroup')) {
    if ($deletion_count = deleteGroupsUsers($remove, $userId)) {
      $successes[] = lang("ACCOUNT_GROUP_REMOVED", array ($deletion_count));
    } else {
      $errors[] = lang("SQL_ERROR");
    }
  }

  if ($add = Input::get('addGroup')) {
    if ($addition_count = addGroupsUsers_raw($add, $userId, 'user')) {
      $successes[] = lang("ACCOUNT_GROUP_ADDED", array ($addition_count));
    } else {
      $errors[] = "SQL error";
    }
  }

  $userdetails = fetchUserDetails(NULL, NULL, $userId);
}

$userGroups = fetchUserGroups($userId);
$groupsData = fetchAllGroups();
