<?php
checkToken();

$parentPage = 'admin_pages.php';
$pageId = Input::get('id');

//Check if selected pages exist
if (!pageIdExists($pageId)) {
    Redirect::to($parentPage);
    die();
}

$redirectOpts = [
    ['id'=>'0', 'name' => lang('USE_DEFAULT_SITE_SETTING')],
    ['id'=>'1', 'name' => lang('CONTINUE_IN_SAME_PAGE')],
    ['id'=>'2', 'name' => lang('RETURN_TO_BREADCRUMB_PARENT')],
    ['id'=>'3', 'name' => lang('REDIRECT_TO_CUSTOM_DESTINATION')],
    ['id'=>'4', 'name' => lang('CREATE_ANOTHER_ROW')],
];
$pageList = $db->query("SELECT id, page FROM $T[pages] ORDER BY page")->results();
array_unshift($pageList, (object)['id'=>null, 'page'=>'(No Parent Page Specified)']);

$pageGroups = fetchGroupsByPage($pageId); // groups with auth to access page
$myForm = new Form ([
    'toc' => new FormField_TabToc(),
    new FormTab_Contents([
        'generalPane' => new FormTab_Pane([
            'page' => new FormField_Text([
                'display' => lang('PAGE_PATH'),
                'disabled' => true,
                'hint_text' => lang('READ_ONLY'),
            ]),
            'private' => new FormField_Select([
                'display' => lang('PUBLIC_OR_PRIVATE'),
                'hint_text' => lang('HINT_PUBLIC_PRIVATE_PAGE'),
                'repeat' => [
                    ['id'=>1, 'name'=>lang('PRIVATE')],
                    ['id'=>0, 'name'=>lang('PUBLIC')],
                ],
            ]),
            'title_token' => new FormField_Text([
                'display' => lang('ADMIN_PAGE_TITLE_TOKEN'),
                'hint_text' => lang('ADMIN_PAGE_TITLE_TOKEN_HINT'),
            ]),
            'breadcrumb_parent_page_id' => new FormField_Select([
                'display' => lang('BREADCRUMB_PARENT'),
                'hint_text' => lang('HINT_BREADCRUMB_PARENT'),
                'repeat' => $pageList,
            ])
        ], [
            'title' => lang('PAGE_INFO_TITLE'),
            'active_tab' => 'active',
            'tab_id'=>'generalPane',
        ]),
        'actionPane' => new FormTab_Pane([
            'after_create' => new FormField_Select([
                'display' => lang('ACTION_AFTER_CREATE'),
                'hint_text' => lang('HINT_ACTION_AFTER_CREATE'),
                'repeat' => $redirectOpts,
            ]),
            'after_create_redirect' => new FormField_Text([
                'display' => lang('CUSTOM_REDIRECT_AFTER_CREATE'),
                'hint_text' => lang('HINT_CUSTOM_REDIRECT_AFTER_CREATE'),
                'placeholder' => lang('HINT_CUSTOM_REDIRECT_AFTER_CREATE'),
            ]),
            'after_edit' => new FormField_Select([
                'display' => lang('ACTION_AFTER_EDIT'),
                'hint_text' => lang('HINT_ACTION_AFTER_EDIT'),
                'repeat' => $redirectOpts,
            ]),
            'after_edit_redirect' => new FormField_Text([
                'display' => lang('CUSTOM_REDIRECT_AFTER_EDIT'),
                'hint_text' => lang('HINT_CUSTOM_REDIRECT_AFTER_EDIT'),
                'placeholder' => lang('HINT_CUSTOM_REDIRECT_AFTER_EDIT'),
            ]),
            'after_delete' => new FormField_Select([
                'display' => lang('ACTION_AFTER_DELETE'),
                'hint_text' => lang('HINT_ACTION_AFTER_DELETE'),
                'repeat' => $redirectOpts,
            ]),
            'after_delete_redirect' => new FormField_Text([
                'display' => lang('CUSTOM_REDIRECT_AFTER_DELETE'),
                'hint_text' => lang('HINT_CUSTOM_REDIRECT_AFTER_DELETE'),
                'placeholder' => lang('HINT_CUSTOM_REDIRECT_AFTER_DELETE'),
            ]),
        ], [
            'title' => lang('PAGE_ACTION_TITLE'),
            'tab_id'=>'actionPane',
        ]),
        'accessPane' => new FormTab_Pane([
            'accessRow' => new Form_Row([
                'remove' => new Form_Panel([
                    'removeGroup' => new FormField_Table([
                        'table_head_cells' => '<th>'.lang('MARK_TO_DELETE').'</th>'.
                            '<th>'.lang('GROUP').'</th>',
                        'table_data_cells' => '<td>{CHECKBOX_ID}</td>'.
                            '<td><a href="admin_group.php?id={GROUP_ID}">{NAME}</a></td>',
                        #'checkbox_label' => lang('MARK_TO_DELETE'),
                        'nodata' => '<p>'.lang('NO_GROUP_ACCESS_PAGE').'</p>',
                        'table_class' => 'table-condensed table-hover',
                        'isdbfield' => false,
                        'sql' => "SELECT gp.id, gp.group_id, g.name, g.short_name
                                    FROM $T[groups_pages] gp
                                    JOIN $T[groups] g ON (g.id = gp.group_id)
                                    WHERE gp.page_id = ?
                                    ORDER BY g.name, g.short_name, g.id",
                        'bindvals' => [$pageId],
                    ], [
                        'dbtable' => 'groups_pages',
                        'action' => 'delete',
                        'msgtoken_success' => 'PAGE_ACCESS_REMOVED',
                    ]),
                ], [
                    'head' => '<h4>'.lang('PAGE_DEL_GROUP_ACCESS').'</h4>',
                    'Panel_Class' => 'panel-default col-xs-12 col-sm-6',
                ]),
                'add' => new Form_Panel([
                    'addGroup' => new FormField_Table([
                        'table_head_cells' => '<th>'.lang('MARK_TO_ADD').'</th>'.
                            '<th>'.lang('GROUP').'</th>',
                        'table_data_cells' => '<td>{CHECKBOX_ID}</td>'.
                            '<td><a href="admin_group.php?id={ID}">{NAME}</a></td>',
                        #'checkbox_label' => lang('MARK_TO_ADD'),
                        'nodata' => '<p>'.lang('NO_GROUP_WITHOUT_ACCESS_PAGE').'</p>',
                        'table_class' => 'table-condensed table-hover',
                        'isdbfield' => false,
                        'sql' => "SELECT id, name, short_name
                            FROM $T[groups] g
                            WHERE NOT EXISTS (
                                SELECT * FROM $T[groups_pages] gp
                                WHERE gp.group_id = g.id
                                  AND gp.page_id = ?
                            )
                            ORDER BY name, short_name, id",
                        'bindvals' => [$pageId],
                        # repeating data will be loaded below
                    ], [
                        'dbtable' => 'groups_pages',
                        'action' => 'insert',
                        'button' => ['save', 'save_and_return'],
                        'fields' => ['group_id' => '{id}', 'page_id'=>$pageId],
                        'msgtoken_success' => 'PAGE_ACCESS_ADDED',
                    ])
                ], [
                    'head' => '<h4>'.lang('PAGE_ADD_GROUP_ACCESS').'</h4>',
                    'Panel_Class' => 'panel-default col-xs-12 col-sm-6',
                ]),
            ]),
        ], [
            'title' => lang('PAGE_ACCESS'),
            'tab_id'=>'accessPane',
        ]),
    ]),
    'btnRow' => new Form_Row([
        'save' => new FormField_ButtonSubmit([
            'field' => 'save',
            'display' => lang('PAGE_SAVE'),
        ]),
        'save_and_return' => new FormField_ButtonSubmit([
            'field' => 'save_and_return',
            'display' => lang('PAGE_SAVE_AND_RETURN'),
        ]),
    ]),
], [
    'dbtable' => 'pages',
    'default' => 'process',
    'Keep_AdminDashBoard' => true,
]);

/*
$pageDetails = fetchPageDetails($pageId); //Fetch information specific to page
$myForm->setFieldValues(null, $pageDetails);
$myForm->setMacro('Form_Title', lang('ADMIN_PAGE_FORM_TITLE', basename($pageDetails->page)));
// Update the database from $_POST
if (Input::exists()) {

    // update columns from `pages`
    $myForm->setNewValues($_POST);
    if ($myForm->saveDbData($pageId, $errors) == Form::UPDATE_SUCCESS) {
        $successes[] = lang('PAGE_UPDATE_SUCCESSFUL');
        $pageDetails = fetchPageDetails($pageId); //Fetch information specific to page
        #var_dump($pageDetails);
        $myForm->setFieldValues($pageDetails);
    }

	//Remove group's access to page
	if ($remove = Input::get('removeGroup')) {
		if ($delCount = deleteGroupsPages($pageId, $remove)) {
			$successes[] = lang('PAGE_ACCESS_REMOVED', $delCount);
		} else {
			$errors[] = lang('SQL_ERROR');
		}
	}

	//give group access to page
	if ($add = Input::get('addGroup')) {
		if ($addCount = addGroupsPages($pageId, $add) > 0) {
			$successes[] = lang('PAGE_ACCESS_ADDED', $addCount);
		}
	}
	$pageDetails = fetchPageDetails($pageId);

    if (Input::get('save_and_return')) {
        Redirect::to($parentPage);
    }
}
$pageGroups = fetchGroupsByPage($pageId); // groups with auth to access page
$notPageGroups = fetchGroupsByNotPage($pageId); // groups with NO auth to access page
$groupData = fetchAllGroups();
$myForm->getField('removeGroup')->setRepData($pageGroups);
$myForm->getField('addGroup')->setRepData($notPageGroups);
echo $myForm->getHTML(['errors'=>$errors, 'successes'=>$successes]);
*/
