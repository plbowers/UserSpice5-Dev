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
/*
 * FORM: admin_group.php (also admin_role.php)
 *  - when called with ?id=n it edits that row
 *  - when called without ?id=n it creates a new row
 *
 * This script makes possible:
 * - modifying/deleting existing rows in table `groups`
 * - adding/deleting rows in table `groups_users_raw`
 * - adding/deleting rows in table `groups_pages`
 *
 * "Roles" are a special kind of "Group" and are accessed via a special
 * $mode=='ROLE' mode of operation. If there is no $mode=='ROLE' then
 * "normal groups" are acted upon; if there is a $mode=='ROLE' then the
 * special "role groups" are acted upon. Typically $mode='ROLE' is executed
 * from admin_role.php which then includes this file.
 *
 * For a description of the difference between "normal groups" and "role groups"
 * please see the comments at the top of core/forms/admin_role.php
 *
 * If you are coming from UserSpice4 or before then you are used to calling
 * "groups" by their old name, "permissions". All references, table names,
 * column names have been changed from "permissions" to "groups".
 *
 * Groups are conceptually very similar to user-groups in many operating systems.
 * Authorizations are granted to groups and all members of that group are granted
 * that authorization. Groups can be members of other groups (nested to 4 levels
 * deep) and all members of a child group are considered to be members of the
 * parent group.
 */
/*
 * {STANDARD FORM COMMENT - DONT EDIT CORE}
 */

checkToken();

/*
 * Set up variables to track whether we are in ROLE or GROUP mode
 * The $mode (either 'ROLE' or 'GROUP') is used throughout this form to determine
 * which messages to display, what sections of the form to display, etc.
 */
if (isset($mode) && $mode == 'ROLE') {
    $parentPage = 'admin_roles.php';
    $currentPage = 'admin_role.php';
} else {
    $mode = 'GROUP';
    $parentPage = 'admin_groups.php';
    $currentPage = 'admin_group.php';
}

if (!($group_id = Input::get('id')) || !groupIdExists($group_id)) {
    $group_id = null;
    $grouptype_id = null;
    $creating = true;
} else {
    $groupRow = $db->queryById('groups', $group_id)->first();
    $grouptype_id = $groupRow->grouptype_id;
    $creating = false;
}
$roleCount = $db->query("SELECT COUNT(*) AS c FROM $T[groups] WHERE is_role = 1")->first();
$rolesAreUsed = $roleCount->c;

$myForm = new Form([
    'toc' => new FormField_TabToc, // table of contents for tab panes
    'tabRow' => new Form_Row ([
        #'tabCol' => new Form_Col ([
            new FormTab_Contents ([
                'groupInfo' => new FormTab_Pane ([
                    'is_role' => new FormField_Hidden ([
                        'value' => 1,
                        'keep_if' => ($creating && $mode == 'ROLE'),
                    ]),
                    'groups.name' => new FormField_Text ([
                        'display' => lang($mode.'_NAME'),
                        'new_valid' => [
                            'action' => ($creating ? 'add' : 'update'),
                            'update_id' => $group_id,
                        ],
                    ]),
                    'groups.short_name' => new FormField_Text ([
                        'display' => lang($mode.'_SHORT_NAME'),
                        'new_valid' => [
                            'action' => ($creating ? 'add' : 'update'),
                            'update_id' => $group_id,
                        ],
                    ]),
                    'groups.grouptype_id' => new FormField_Select([
                        'display' => lang($mode.'_GROUPTYPE'),
                        'sql' => "SELECT * FROM $T[grouptypes] ORDER BY name",
                        #'repeat' => $grouptypesData,
                        'placeholder_row' => ['id'=>null, 'name'=>lang('CHOOSE_FROM_LIST_BELOW')],
                        'new_valid' => [], // in case they make it required
                        'delete_if_empty' => true, // don't show if no group types are set up
                        #'debug' => 5,
                    ]),
                    'admin' => new FormField_Select([
                        'display' => lang('GROUP_IS_ADMIN'),
                        'repeat' => [
                            ['id'=>0, 'name'=>lang('GROUP_NOT_ADMIN')],
                            ['id'=>1, 'name'=>lang('GROUP_MEMBERS_ARE_ADMIN')],
                        ],
                    ]),
                    'default_for_new_user' => new FormField_Select([
                        'display' => lang('GROUP_DEFAULT_NEW_USERS'),
                        'repeat' => [
                            ['id'=>0, 'name'=>lang('GROUP_NOT_DEFAULT_NEW_USERS')],
                            ['id'=>1, 'name'=>lang('GROUP_IS_DEFAULT_NEW_USERS')],
                        ],
                    ]),
                    'deleteRolesWell' => new Form_Well([
                        'deleteRoles' => new FormField_Table ([
                            'table_head_cells' => [
                                lang('MARK_TO_DELETE'),
                                lang('GROUP_ROLE_NAME_USER'),
                            ],
                            'table_data_cells' => [
                                '{CHECKBOX_ID}',
                                '{NAME} ({SHORT_NAME}): {FNAME} {LNAME} ({USERNAME})',
                            ],
                            'sql' => "SELECT *
                                      FROM $T[groups_roles_users] gru
                                      JOIN $T[groups] groups ON (gru.group_id = groups.id)
                                      JOIN $T[users] users ON (gru.user_id = users.id)
                                      WHERE gru.group_id = ?",
                            'bindvals' => [$group_id],
                            'nodata' => '<p>'.lang('NO_ROLES_ASSIGNED').'</p>',
                            'delete_if_empty' => true,
                        ], [
                            'action' => 'delete',
                            'dbtable' => 'groups_roles_users',
                            'button' => 'save',
                            'nothing_message' => '',
                        ]),
                    ], [
                        'Well_Class'=>'well-sm',
                        #'debug' => 4,
                        'title'=>lang('DELETE_ROLE_ASSIGNMENTS'),
                        'delete_if' => ($mode == 'ROLE' || !$rolesAreUsed),
                        'delete_if_empty' => true,
                    ]),
                    'newRoleWell' => new Form_Well([
                        'newRole' => new FormField_Select([
                            'field' => 'newRole',
                            'display' => lang('CHOOSE_ROLE'),
                            'sql' => "SELECT id, name, short_name
                                      FROM $T[groups] groups
                                      WHERE is_role > 0
                                      AND (grouptype_id = ?
                                         OR grouptype_id IS NULL)",
                            'bindvals' => [$grouptype_id],
                            'placeholder_row' => ['id'=>null, 'name'=>lang('CHOOSE_ROLE')],
                            'nodata' => '<p>'.lang('NO_ROLES_SETUP_GROUPTYPE').'</p>',
                            'isdbfield' => false,
                            'Hint_Text' => lang('GROUP_NEED_ROLE_AND_USER'),
                        ]),
                        'newRoleUser' => new FormField_Select([
                            'field' => 'newRoleUser',
                            'display' => lang('CHOOSE_GROUP_MEMBER'),
                            'sql' => "SELECT gru.id, gru.role_group_id, groups.name,
                                        groups.short_name, gru.user_id, users.username,
                                        users.fname, users.lname
                                      FROM $T[groups_roles_users] gru
                                      JOIN $T[groups] groups ON (gru.role_group_id = groups.id)
                                      JOIN $T[users] users ON (gru.user_id = users.id)
                                      WHERE gru.group_id = ?",
                            'bindvals' => [$group_id],
                            'idfield' => 'user_id',
                            'placeholder_row' => ['user_id'=>null, 'name'=>lang('CHOOSE_GROUP_MEMBER')],
                            'nodata' => '<p>'.lang('NO_MEMBERS_OF_GROUP').'</p>',
                            'isdbfield' => false,
                            'Hint_Text' => lang('GROUP_NEED_ROLE_AND_USER'),
                        ]),
                        'addNewRole' => new FormField_ButtonSubmit([
                            'display' => lang('GROUP_ADD_NEW_ROLE')
                        ]),
                    ], [
                        'Well_Class' => 'well-sm',
                        'title' => lang('ASSIGN_NEW_ROLE_TO_GROUP'),
                        'delete_if' => ($mode == 'ROLE' || $creating || !$rolesAreUsed),
                        'dbtable' => 'groups_roles_users',
                        'save_button' => 'addNewRole',
                        'form_mode' => 'INSERT' // we're only adding to gru, never updating
                    ]),
                ], [
                    'active_tab'=>'active',
                    'tab_id'=>'groupInfo',
                    'title'=>lang($mode.'_INFORMATION_TITLE'),
                ]),
                'groupMembers'=> new FormTab_Pane ([
                    '<h2>'.lang('GROUP_MEMBERSHIP').'</h2>',
                    new Form_Col ([
                        '<h3>'.lang('SELECT_MEMBERS_TO_DELETE').'</h3>',
                        '<h4>'.lang($mode.'_SELECT_USER_DEL_MEMBERS').'</h4>',
                        'removeGroupUsers' => new FormField_Table ([
                            'Table_Class'=>'table-condensed',
                            'isdbfield' => false,
                            #'debug' => 0,
                            'table_head_cells' => '<th>'.
                                lang('SELECT_USER_BELOW').'</th><th>'.
                                lang('NAME_USERS_MEMBERS').'</th>',
                            'table_data_cells' => '<td>{CHECKBOX_ID}</td><td>{NAME}</td>',
                            'nodata' => '<p>'.lang('NO_USER_MEMBERS_OF_GROUP').'</p>',
                            'datafunc' => 'fetchGroupMembers_raw',
                            'datafuncargs' => ['group_id' => $group_id, 'groups'=>false, 'users'=>true],
                        ], [
                            'action' => 'delete',
                            'dbtable' => 'groups_users_raw',
                            'where' => [
                                'user_id' => '{id}',
                                'group_id' => $group_id,
                                'user_is_group' => 0,
                            ],
                            'nothing_message' => '',
                        ]),
                        new Form_Row ([
                            '<h4>'.lang('SELECT_GROUP_DEL_MEMBERS').'</h4>',
                            'removeGroupGroups' => new FormField_Table ([
                                'Table_Class'=>'table-condensed',
                                'isdbfield' => false,
                                #'debug' => 0,
                                'table_head_cells' => '<th>'.
                                    lang('SELECT_GROUP_BELOW').'</th><th>'.
                                    lang('NAME_GROUPS_MEMBERS').'</th>',
                                'table_data_cells' => '<td>{CHECKBOX_ID}</td><td>{NAME}</td>',
                                'nodata' => '<p>'.lang('NO_GROUP_MEMBERS_OF_GROUP').'</p>',
                                'delete_if' => ($mode == 'ROLE'),
                                'datafunc' => 'fetchGroupMembers_raw',
                                'datafuncargs' => ['group_id' => $group_id, 'groups'=>true, 'users'=>false],
                            ], [
                                'action' => 'delete',
                                'dbtable' => 'groups_users_raw',
                                'where' => [
                                    'user_id' => '{id}',
                                    'group_id' => $group_id,
                                    'user_is_group' => 1,
                                ],
                                'nothing_message' => '',
                            ]),
                        ], ['delete_if' => ($mode == 'ROLE') ]),
                    ], [
                        'Col_Class'=>'col-xs-12 col-sm-6']
                    ),
                    new Form_Col ([
                        '<h3>'.lang('SELECT_NONMEMBERS_TO_ADD').'</h3>',
                        '<h4>'.lang('SELECT_USER_ADD_MEMBERS').'</h4>',
                        'addGroupUsers' => new FormField_Table ([
                            'field' => 'addGroupUsers',
                            'Table_Class'=>'table-condensed',
                            'isdbfield' => false,
                            #'debug' => 0,
                            'table_head_cells' => '<th>'.
                                lang('SELECT_USER_BELOW').'</th><th>'.
                                lang('NAME_USERS_NON_MEMBERS').'</th>',
                            'table_data_cells' => '<td>{CHECKBOX_ID}</td><td>{NAME}</td>',
                            'nodata' => '<p>'.lang('NO_USERS_TO_ADD').'</p>',
                            # repeating data will be set below
                    		'sql_cols' => "users.id as id, CONCAT(fname, ' ', lname, ' (', username, ')') AS name, 'user' AS group_or_user",
            				'sql_from' => "$T[users] users",
            				'sql_where'=> "NOT EXISTS (
                    						SELECT *
                    						FROM $T[groups_users_raw]
                    						WHERE users.id = user_id
                    						AND user_is_group = 0
                    						AND group_id = ?)",
                            'sql_order'=> "fname, lname, id",
                            'sql_bindvals'=> [$group_id],
                            #'pageItems' => 3,
                            #'pageVarName' => 'aguPage',
                        ], [
                            'action' => 'insert',
                            'dbtable' => 'groups_users_raw',
                            'fields' => [
                                'user_id' => '{id}',
                                'group_id' => $group_id,
                                'user_is_group' => 0,
                            ],
                            'nothing_message' => '',
                        ]),
                        '<h4>'.lang('SELECT_GROUP_ADD_MEMBERS').'</h4>',
                        'addGroupGroups' => new FormField_Table ([
                            'field' => 'addGroupGroups',
                            'Table_Class'=>'table-condensed',
                            'isdbfield' => false,
                            'table_head_cells' => '<th>'.
                                lang('SELECT_GROUP_BELOW').'</th><th>'.
                                lang('NAME_GROUPS_NON_MEMBERS').'</th>',
                            'table_data_cells' => '<td>{CHECKBOX_ID}</td><td>{NAME}</td>',
                            'nodata' => '<p>'.lang('NO_GROUPS_TO_ADD').'</p>',
                            # repeating data will be set below
                            #'debug' =>  0,
                    		'sql_cols' => "groups.id as id, groups.name as name, 'group' AS group_or_user",
            				'sql_from' => "$T[groups] groups ",
            				'sql_where'=> "id != ?
                        					AND NOT EXISTS (
                        						SELECT *
                        						FROM $T[groups_users_raw]
                        						WHERE groups.id = user_id
                        						AND user_is_group = 1
                        						AND group_id = ?
                        					) ",
                            'sql_order'=> "name, id",
                            'sql_bindvals'=> [$group_id, $group_id],
                            #'pageItems' => 3,
                            #'pageVarName' => 'aggPage',
                            'delete_if' => ($mode == 'ROLE'),
                        ], [
                            'action' => 'insert',
                            'dbtable' => 'groups_users_raw',
                            'fields' => [
                                'user_id' => '{id}',
                                'group_id' => $group_id,
                                'user_is_group' => 1,
                            ],
                            'nothing_message' => '',
                        ]),
                    ], [
                        'Col_Class'=>'col-xs-12 col-sm-6',
                        'delete_if' => ($mode == 'ROLE'),
                    ]),
                ], [
                    'tab_id'=>'groupMembers',
                    'title'=>lang('GROUP_MEMBERSHIP')
                ]),
                'groupAccess' => new FormTab_Pane ([
                    '<h2>'.lang('GROUP_ACCESS_PAGES').'</h2>',
                    new Form_Col ([
                        '<h3>'.lang('REMOVE_PAGE_ACCESS_GROUP').'</h3>',
                        'removePage' => new FormField_Table ([
                            'Table_Class'=>'table-sm',
                            'isdbfield' => false,
                            #'debug' => 0,
                            'table_head_cells' => '<th>'.
                                lang('SELECT_PAGE_BELOW').'</th><th>'.
                                lang('NAME_ACCESSIBLE_PAGES').'</th>',
                            'table_data_cells' =>
                                '<td>{CHECKBOX_ID}</td>'.
                                '<td><a href="admin_page.php?id={ID}">{PAGE}</a></td>',
                            'nodata' => '<p>'.lang('GROUP_NO_ACCESS_PAGES').'</p>',
                            'datafunc' => 'fetchPagesByGroup',
                            'datafuncargs' => $group_id,
                        ], [
                            'action' => 'delete',
                            'dbtable' => 'groups_pages',
                            'where' => [
                                'page_id' => '{ID}',
                                'group_id' => $group_id,
                            ],
                            'nothing_message' => '',
                        ]),
                    ],  ['Col_Class'=>'col-xs-12 col-sm-6 col-md-4']),
                    new Form_Col ([
                        '<h3>'.lang('ADD_PAGE_ACCESS_GROUP').'</h3>',
                        'addPage' => new FormField_Table ([
                            'field' => 'addPage',
                            'table_class' => 'table-sm',
                            'isdbfield' => false,
                            #'debug' => 0,
                            'table_head_cells' => '<th>'.
                                lang('SELECT_PAGE_BELOW').'</th><th>'.
                                lang('NAME_INACCESSIBLE_PAGES').'</th>',
                            'table_data_cells' => '<td>{CHECKBOX_ID}</td>'.
                                '<td><a href="admin_page.php?id={ID}">{PAGE}</a></td>',
                            'nodata' => '<p>'.lang('GROUP_NO_PAGES_TO_ADD').'</p>',
                            'sql' => "SELECT id, page, private
                                        FROM $T[pages] p
                                        WHERE p.private != 0
                                          AND NOT EXISTS
                                          (SELECT * FROM $T[groups_pages] gp
                                           WHERE gp.group_id = ?
                                           AND p.id = gp.page_id)",
                            'bindvals' => [$group_id],
                        ], [
                            'action' => 'insert',
                            'dbtable' => 'groups_pages',
                            'fields' => [
                                'page_id' => '{ID}',
                                'group_id' => $group_id,
                            ],
                            'nothing_message' => '',
                        ]),
                    ],  ['Col_Class'=>'col-xs-12 col-sm-6 col-md-4']),
                    new Form_Col ([
                        '<h3>'.lang('PUBLIC_PAGES').'</h3>',
                        'publicPages' => new FormField_Table ([
                            'field' => 'publicPages',
                            'Table_Class' => 'table-sm',
                            'isdbfield' => false,
                            #'debug' => 0,
                            'table_head_cells' => '<th>'.
                                lang('PUBLIC_PAGES').'</th>',
                            'table_data_cells' =>
                                '<td><a href="admin_page.php?id={ID}">{PAGE}</a></td>',
                            'nodata' => '<p>'.lang('GROUP_NO_PUBLIC_PAGES').'</p>',
                            'datafunc' => 'fetchPublicPages',
                            'datafuncargs' => 0,
                        ]),
                    ],  ['Col_Class'=>'col-xs-12 col-sm-6 col-md-4']),
                ], [
                    'tab_id' => 'groupAccess',
                    'title' => lang('GROUP_PAGE_ACCESS_TITLE'),
                ]),
            ]),
        #]),
    ]),
    'btnRow' => new Form_Row ([
        'btnCol' => new Form_Col ([
            'save' => new FormField_ButtonSubmit([
                    'field' => 'save',
                    'display' => lang($mode.'_SAVE')
                ]),
            'save_and_new' => new FormField_ButtonSubmit([
                    'field' => 'save_and_new',
                    'display' => lang($mode.'_SAVE_AND_NEW')
                ]),
            'save_and_return' => new FormField_ButtonSubmit([
                    'field' => 'save_and_return',
                    'display' => lang($mode.'_SAVE_AND_RETURN')
                ]),
            'deleteGroup' => new FormField_ButtonDelete([
                    'field' => 'deleteGroup',
                    'display' => lang('DELETE_THIS_'.$mode),
                    'value' => $group_id,
                    'delete_if' => $creating,
                ]),
        ]),
    ]),
], [
    'table' => 'groups',
    'title' => ($creating ?
                    // CREATING_GROUP_TITLE -or- CREATING_ROLE_TITLE
                    lang('CREATING_'.$mode.'_TITLE') :
                    // ADMIN_GROUP_TITLE -or- ADMIN_ROLE_TITLE
                    lang('ADMIN_'.$mode.'_TITLE')),
    'form_action' => $currentPage.'?id='.$group_id,
    'Keep_AdminDashBoard' => true,
    'default' => 'process',
    'single_update_nothing_msg' => '',
    #'debug' => 5,
]);
