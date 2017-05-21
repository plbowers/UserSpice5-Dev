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

# Check CSRF token
checkToken();

$master = $db->query("SELECT * FROM $T[settings] WHERE (user_id IS NULL OR user_id <= 0) AND (group_id IS NULL OR group_id <= 0)")->first();
$db->errorSetMessage($errors);
if ($mode == 'SITE' && !Input::get('id')) {
    $_GET['id'] = $master->id;
    $_REQUEST['id'] = $_GET['id'];
}
if (!isset($mode) || !in_array($mode, ['USER', 'GROUP', 'SITE'])) {
    $errors[] = 'DEV ERROR: UNKNOWN mode='.@$mode;
    Redirect::to("Some Other Place - gotta look up the appropriate redirect facility");
}

# Calculate lists of CSS files for various selection fields
$tmp = str_replace(US_ROOT_DIR, '', array_merge(
    glob(US_ROOT_DIR.'core/css/color_schemes/*.css'),
    glob(US_ROOT_DIR.'local/css/color_schemes/*.css')
));
$css1 = [];
foreach ($tmp as $x) {
    $css1[] = ['id'=>$x, 'name'=>$x];
}
$tmp = str_replace(US_ROOT_DIR, '', array_merge(
    glob(US_ROOT_DIR.'core/css/*.css'),
    glob(US_ROOT_DIR.'local/css/*.css')
));
$css2 = [];
foreach ($tmp as $x) {
    $css2[] = ['id'=>$x, 'name'=>$x];
}
$tmp = str_replace(US_ROOT_DIR, '', array_merge(
    glob(US_ROOT_DIR.'core/css/*.css'),
    glob(US_ROOT_DIR.'local/css/*.css')
));
$css3 = [];
foreach ($tmp as $x) {
    $css3[] = ['id'=>$x, 'name'=>$x];
}

# Now look up the files in the language dir to allow that choice
$tmp = array_merge(
    configGet('language_files'),
    glob(US_ROOT_DIR.'core/language/*.php'),
    glob(US_ROOT_DIR.'local/language/*.php')
);
$langs = [];
$alreadySet = [];
foreach ($tmp as $k=>$x) {
    $l = basename($x);
    if ($l != 'language.php' && !isset($alreadySet[$l])) {
        $langs[] = ['id'=>(is_numeric($k)?$l:$k), 'name'=>$l.(is_numeric($k)?'':" ($k)")];
        $alreadySet[$l] = true;
    }
}

# Test email if requested
if (Input::get('test_email')) {
	$email = Input::get('email');
	$subject = 'Testing Your Email Settings!';
	$body = 'This is the body of your test email';
    $emailDebugVerbose = Input::get('emailDebugVerbose');
	list($email_results, $email_debug) = email($email,$subject,$body,false,$emailDebugVerbose);
} else {
    $email_results = $email_debug = false;
}

# Now set up the possible options for the actions to take upon successful save
$multiRowSaveOpts = [
    ['id'=>'1', 'name' => lang('CONTINUE_IN_SAME_PAGE')],
    ['id'=>'2', 'name' => lang('RETURN_TO_BREADCRUMB_PARENT')],
];
$singleRowDelOpts = [
    # can't continue on same page - the row we were looking at is gone
    ['id'=>'2', 'name' => lang('RETURN_TO_BREADCRUMB_PARENT')],
];
$singleRowCreateOpts = [
    ['id'=>'1', 'name' => lang('CONTINUE_IN_SAME_PAGE')],
    ['id'=>'2', 'name' => lang('RETURN_TO_BREADCRUMB_PARENT')],
    ['id'=>'4', 'name' => lang('CREATE_ANOTHER_ROW')],
];
$singleRowEditOpts = [
    ['id'=>'1', 'name' => lang('CONTINUE_IN_SAME_PAGE')],
    ['id'=>'2', 'name' => lang('RETURN_TO_BREADCRUMB_PARENT')],
    ['id'=>'4', 'name' => lang('CREATE_ANOTHER_ROW')], // weird... leave it in?
];
$yesOrNo = [
    ['id'=>1, 'name'=>lang('YES')],
    ['id'=>0, 'name'=>lang('NO')],
];
$overrideOrNot = [
    ['id'=>1, 'name'=>lang('YES')],
    ['id'=>0, 'name'=>lang('NO')],
];

$myForm = new Form([
    'toc' => new FormField_TabToc(['TocType'=>'tab']),
    'tabs' => new FormTab_Contents([
        'tab_general' => new FormTab_Pane([
            'site_name' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SITE_NAME'),
                    'hint_text' => lang('SETTINGS_SITE_NAME_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'site_url' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SITE_URL'),
                    'hint_text' => lang('SETTINGS_SITE_URL_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'master_site_language' =>
                new FormField_Select([
                    'display' => lang('MASTER_SETTINGS_SITE_LANGUAGE'),
                    'isdbfield' => false,
                    'hint_text' => '',
                    'value' => (isset($master->site_language) ? $master->site_language : 0),
                    'repeat' => $langs,
                    'disabled' => true,
                    'keep_if' => $mode != 'SITE',
                ]),
            'override_site_language' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_SITE_LANGUAGE'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_SITE_LANGUAGE_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'site_language' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SITE_LANGUAGE'),
                    'hint_text' => lang('SETTINGS_SITE_LANGUAGE_HINT'),
                    'repeat' => $langs,
                ]),
            'master_date_fmt' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_DATE_FMT'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_DATE_FMT_HINT'),
                    'value' => (isset($master->date_fmt) ? $master->date_fmt : 0),
                    'readonly' => true,
                    'keep_if' => $mode != 'SITE',
                ]),
            'override_date_fmt' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_DATE_FMT'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_DATE_FMT_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'date_fmt' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_DATE_FMT'),
                    'hint_text' => lang('SETTINGS_DATE_FMT_HINT'),
                ]),
            'master_time_fmt' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TIME_FMT'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TIME_FMT_HINT'),
                    'value' => (isset($master->time_fmt) ? $master->time_fmt : 0),
                    'readonly' => true,
                    'keep_if' => $mode != 'SITE',
                ]),
            'override_time_fmt' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_TIME_FMT'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_TIME_FMT_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'time_fmt' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TIME_FMT'),
                    'hint_text' => lang('SETTINGS_TIME_FMT_HINT'),
                ]),
            'install_location' =>
                new FormField_Text([
                    'dbfield' => 'install_location',
                    'display' => lang('SETTINGS_INSTALL_LOCATION'),
                    'hint_text' => lang('CURRENTLY_UNUSED'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'copyright_message' =>
                new FormField_Textarea([
                    'dbfield' => 'copyright_message',
                    'display' => lang('SETTINGS_COPYRIGHT_MESSAGE'),
                    'hint_text' => lang('SETTINGS_COPYRIGHT_MESSAGE_HINT'),
                    'editable' => true,
                    'keep_if' => $mode == 'SITE',
                ]),
            'site_offline' =>
                new FormField_Select([
                    'dbfield' => 'site_offline',
                    'display' => lang('SETTINGS_SITE_OFFLINE'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_SITE_OFFLINE_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'master_debug_mode' =>
                new FormField_Select([
                    'display' => lang('MASTER_SETTINGS_DEBUG_MODE'),
                    'isdbfield' => false,
                    'data' => $yesOrNo,
                    #'hint_text' => lang('MASTER_SETTINGS_DEBUG_MODE_HINT'),
                    'value' => (isset($master->debug_mode) ? $master->debug_mode : 0),
                    'disabled' => true,
                    'keep_if' => $mode != 'SITE',
                ]),
            'override_debug_mode' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_DEBUG_MODE'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_DEBUG_MODE_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'debug_mode' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_DEBUG_MODE'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_DEBUG_MODE_HINT'),
                ]),
            'track_guest' =>
                new FormField_Select([
                    'dbfield' => 'track_guest',
                    'display' => lang('SETTINGS_TRACK_GUESTS'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_TRACK_GUESTS_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'master_enable_messages' =>
                new FormField_Select([
                    'display' => lang('MASTER_SETTINGS_ENABLE_MESSAGES'),
                    'isdbfield' => false,
                    'data' => $yesOrNo,
                    #'hint_text' => lang('SETTINGS_ENABLE_MESSAGES_HINT'),
                    'value' => (isset($master->enable_messages) ? $master->enable_messages : 0),
                    'keep_if' => $mode != 'SITE',
                    'disabled' => true,
                ]),
            'override_enable_messages' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_ENABLE_MESSAGES'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_ENABLE_MESSAGES_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'enable_messages' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_ENABLE_MESSAGES'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_ENABLE_MESSAGES_HINT'),
                ]),
            'upload_dir' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_UPLOAD_DIR'),
                    'hint_text' => lang('SETTINGS_UPLOAD_DIR'),
                    'valid' => [
                        'regex' => ':^$|\/$:', // either blank or must end with slash
                        'regex_display' => lang('REGEX_ENDS_WITH_SLASH'),
                    ],
                    'keep_if' => $mode == 'SITE',
                ]),
            'upload_allowed_ext' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_UPLOAD_ALLOWED_EXT'),
                    'hint_text' => lang('SETTINGS_UPLOAD_ALLOWED_EXT_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'upload_max_size' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_UPLOAD_MAX_SIZE'),
                    'hint_text' => lang('SETTINGS_UPLOAD_MAX_SIZE_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            /* this is edited/used in admin_backup.php rather than here
            'backup_dest' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_BACKUP_DEST'),
                    'hint_text' => lang('SETTINGS_BACKUP_DEST_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            */
            'allow_username_change' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_ALLOW_USERNAME_CHANGE'),
                    'hint_text' => lang('SETTINGS_ALLOW_USERNAME_CHANGE_HINT'),
                    'data' => $yesOrNo,
                    'keep_if' => $mode == 'SITE',
                ]),
        ], [
            'title'=>lang('SETTINGS_GENERAL_TITLE'),
        ]),
        'tab_security' => new FormTab_Pane([
            'force_ssl' =>
                new FormField_Select([
                    'dbfield' => 'settings.force_ssl',
                    'display' => lang('SETTINGS_FORCE_SSL'),
                    'data' => $yesOrNo,
                    'keep_if' => $mode == 'SITE',
                ]),
            # zxcvbn implementation
            'min_pw_score' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_MIN_PW_SCORE'),
                    'repeat' => [
                        ['id'=>-1, 'name'=>lang('SETTINGS_MIN_PW_DISABLED')],
                        ['id'=>0, 'name'=>lang('SETTINGS_MIN_PW_VERY_WEAK')],
                        ['id'=>1, 'name'=>lang('SETTINGS_MIN_PW_WEAK')],
                        ['id'=>2, 'name'=>lang('SETTINGS_MIN_PW_OK')],
                        ['id'=>3, 'name'=>lang('SETTINGS_MIN_PW_STRONG')],
                        ['id'=>4, 'name'=>lang('SETTINGS_MIN_PW_VERY_STRONG')],
                    ],
                    'hint_text' => lang('SETTINGS_MIN_PW_SCORE_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'session_timeout' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SESSION_TIMEOUT'),
                    'new_valid' => [
                        'is_numeric' => true,
                    ],
                    'hint_text' => lang('SETTINGS_SESSION_TIMEOUT_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'allow_remember_me' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_ALLOW_REMEMBER_ME'),
                    'data' => $yesOrNo,
                    'keep_if' => $mode == 'SITE',
                ]),
        ], [
            'title' => lang('SETTINGS_SECURITY_TITLE'),
            'keep_if' => $mode == 'SITE',
        ]),
        'tab_css' => new FormTab_Pane ([
            'css_sample' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SHOW_CSS_SAMPLES'),
                    'repeat' => [
                        ['id'=>1, 'name'=>lang('ENABLED')],
                        ['id'=>0, 'name'=>lang('DISABLED')],
                    ],
                ]),
            'css1' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_CSS1'),
                    'repeat' => $css1,
                ]),
            'css2' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_CSS2'),
                    'repeat' => $css2,
                ]),
            'css3' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_CSS3'),
                    'repeat' => $css3,
                ]),
            'sampleRow' =>
                new Form_Row([
                    'sampleCol' =>
                        new Form_Col([
                        	'<h2>Bootstrap Class Examples (for SAVED settings)</h2>',
                        	'<hr />',
                        	'<button type="button" name="button" class="btn btn-primary">primary</button>',
                        	'<button type="button" name="button" class="btn btn-info">info</button>',
                        	'<button type="button" name="button" class="btn btn-warning">warning</button>',
                        	'<button type="button" name="button" class="btn btn-danger">danger</button>',
                        	'<button type="button" name="button" class="btn btn-success">success</button>',
                        	'<button type="button" name="button" class="btn btn-default">default</button>',
                        	'<hr />',
                        	'<div class="jumbotron"><h1>Jumbotron</h1></div>',
                        	'<div class="well"><p>well</p></div>',
                        	'<h1>This is H1</h1>',
                        	'<h2>This is H2</h2>',
                        	'<h3>This is H3</h3>',
                        	'<h4>This is H4</h4>',
                        	'<h5>This is H5</h5>',
                        	'<h6>This is H6</h6>',
                        	'<p>This is paragraph</p>',
                        	'<a href="#">This is a link</a><br><br>',
                        ], ['col_class' => 'text-center']),
                ], ['delete_if' =>  !configGet('css_sample')]),
        ], [
            'title' => lang("SETTINGS_CSS_TITLE"),
            'keep_if' => $mode == 'SITE',
        ]),
        'tab_editor' => new FormTab_Pane ([
            'tinymce_url' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_URL'),
                    'hint_text' => lang('SETTINGS_TINYMCE_URL_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'tinymce_apikey' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_APIKEY'),
                    'hint_text' => lang('SETTINGS_TINYMCE_APIKEY_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'override_tinymce' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_OVERRIDE_TINYMCE'),
                    'data' => $overrideOrNot,
                    'hint_text' => lang('SETTINGS_OVERRIDE_TINYMCE_HINT'),
                    'keep_if' => $mode != 'SITE',
                ]),
            'master_tinymce_plugins' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_PLUGINS'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_PLUGINS_HINT'),
                    'value' => (isset($master->tinymce_plugins) ? $master->tinymce_plugins : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_plugins' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_PLUGINS'),
                    'hint_text' => lang('SETTINGS_TINYMCE_PLUGINS_HINT'),
                ]),
            'master_tinymce_height' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_HEIGHT'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_HEIGHT_HINT'),
                    'value' => (isset($master->tinymce_height) ? $master->tinymce_height : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_height' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_HEIGHT'),
                    'hint_text' => lang('SETTINGS_TINYMCE_HEIGHT_HINT'),
                ]),
            'master_tinymce_menubar' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_MENUBAR'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_MENUBAR_HINT'),
                    'value' => (isset($master->tinymce_menubar) ? $master->tinymce_menubar : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_menubar' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_MENUBAR'),
                    'hint_text' => lang('SETTINGS_TINYMCE_MENUBAR_HINT'),
                ]),
            'master_tinymce_toolbar' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_TOOLBAR'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_TOOLBAR_HINT'),
                    'value' => (isset($master->tinymce_toolbar) ? $master->tinymce_toolbar : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_toolbar' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_TOOLBAR'),
                    'hint_text' => lang('SETTINGS_TINYMCE_TOOLBAR_HINT'),
                ]),
            'master_tinymce_skin' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_SKIN'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_SKIN_HINT'),
                    'value' => (isset($master->tinymce_skin) ? $master->tinymce_skin : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_skin' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_SKIN'),
                    'hint_text' => lang('SETTINGS_TINYMCE_SKIN_HINT'),
                ]),
            'master_tinymce_theme' =>
                new FormField_Text([
                    'display' => lang('MASTER_SETTINGS_TINYMCE_THEME'),
                    'isdbfield' => false,
                    #'hint_text' => lang('MASTER_SETTINGS_TINYMCE_THEME_HINT'),
                    'value' => (isset($master->tinymce_theme) ? $master->tinymce_theme : 0),
                    'keep_if' => $mode != 'SITE',
                    'readonly' => true,
                ]),
            'tinymce_theme' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_TINYMCE_THEME'),
                    'hint_text' => lang('SETTINGS_TINYMCE_THEME_HINT'),
                ]),
        ], [
            'title'=>lang('SETTINGS_EDITOR_TITLE'),
        ]),
        'tab_redirects' => new FormTab_Pane ([
            'redirect_login' =>
                new FormField_Text([
                    'dbfield' => 'redirect_login',
                    'display' => lang('SETTINGS_REDIRECT_LOGIN'),
                    'hint_text' => lang('SETTINGS_REDIRECT_LOGIN_HINT'),
                ]),
            'redirect_logout' =>
                new FormField_Text([
                    'dbfield' => 'redirect_logout',
                    'display' => lang('SETTINGS_REDIRECT_LOGOUT'),
                    'hint_text' => lang('SETTINGS_REDIRECT_LOGOUT_HINT'),
                ]),
            'redirect_deny_nologin' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_REDIRECT_DENY_NOLOGIN'),
                    'hint_text' => lang('SETTINGS_REDIRECT_DENY_NOLOGIN_HINT'),
                ]),
            'redirect_deny_noperm' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_REDIRECT_DENY_NOPERM'),
                    'hint_text' => lang('SETTINGS_REDIRECT_DENY_NOPERM_HINT'),
                ]),
            'redirect_site_offline' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_REDIRECT_SITE_OFFLINE'),
                    'hint_text' => lang('SETTINGS_REDIRECT_SITE_OFFLINE_HINT'),
                ]),
            'multi_row_after_create' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_MULTI_ROW_AFTER_CREATE'),
                    'repeat' => $multiRowSaveOpts,
                    'hint_text' => lang('SETTINGS_MULTI_ROW_AFTER_CREATE_HINT'),
                ]),
            'multi_row_after_edit' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_MULTI_ROW_AFTER_EDIT'),
                    'repeat' => $multiRowSaveOpts,
                    'hint_text' => lang('SETTINGS_MULTI_ROW_AFTER_EDIT_HINT'),
                ]),
            'multi_row_after_delete' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_MULTI_ROW_AFTER_DELETE'),
                    'repeat' => $multiRowSaveOpts,
                    'hint_text' => lang('SETTINGS_MULTI_ROW_AFTER_DELETE_HINT'),
                ]),
            'single_row_after_create' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SINGLE_ROW_AFTER_CREATE'),
                    'repeat' => $singleRowCreateOpts,
                    'hint_text' => lang('SETTINGS_SINGLE_ROW_AFTER_CREATE_HINT'),
                ]),
            'single_row_after_edit' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SINGLE_ROW_AFTER_EDIT'),
                    'repeat' => $singleRowEditOpts,
                    'hint_text' => lang('SETTINGS_SINGLE_ROW_AFTER_EDIT_HINT'),
                ]),
            'single_row_after_delete' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SINGLE_ROW_AFTER_DELETE'),
                    'repeat' => $singleRowDelOpts,
                    'hint_text' => lang('SETTINGS_SINGLE_ROW_AFTER_DELETE_HINT'),
                ]),
            'redirect_referrer_login' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_REDIRECT_REFERRER_LOGIN'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_REDIRECT_REFERRER_LOGIN_HINT'),
                ]),
        ], [
            'title'=>lang('SETTINGS_REDIRECTS_TITLE'),
            'keep_if' => $mode == 'SITE',
        ]),
        'tab_email' => new FormTab_Pane ([
            'mail_method' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_MAIL_METHOD'),
                    'data' => [
                        [ 'id'=>'smtp', lang('SETTINGS_SMTP_SERVER_OPT') ],
                        [ 'id'=>'sendmail', lang('SETTINGS_SENDMAIL_OPT') ],
                        [ 'id'=>'phpmail', lang('SETTINGS_PHP_MAIL_OPT') ],
                    ],
                    'hint_text' => lang('SETTINGS_MAIL_METHOD_HINT'),
                ]),
            'smtp_server' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SMTP_SERVER'),
                    'hint_text' => lang('SETTINGS_SMTP_SERVER_HINT'),
                ]),
            'smtp_port' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SMTP_PORT'),
                    'hint_text' => lang('SETTINGS_SMTP_PORT_HINT'),
                ]),
            'smtp_transport' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_SMTP_TRANSPORT'),
                    'data' => [
                        [ 'id'=>'tls', lang('SETTINGS_SMTP_TRANSPORT_TLS') ],
                        [ 'id'=>'ssl', lang('SETTINGS_SMTP_TRANSPORT_SSL') ],
                    ],
                    'hint_text' => (extension_loaded('openssl')?lang('SETTINGS_SSL_AVAILABLE'):lang('SETTINGS_SSL_NOT_AVAILABLE')),
                ]),
            'email_login' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SMTP_EMAIL_LOGIN'),
                    'hint_text' => lang('SETTINGS_SMTP_EMAIL_LOGIN_HINT'),
                ]),
            'email_pass' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_SMTP_EMAIL_PASSWD'),
                    'hint_text' => lang('SETTINGS_SMTP_EMAIL_PASSWD_HINT'),
                ]),
            'from_name' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_FROM_NAME'),
                    'hint_text' => lang('SETTINGS_FROM_NAME_HINT'),
                ]),
            'from_email' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_FROM_EMAIL'),
                    'hint_text' => lang('SETTINGS_FROM_EMAIL_HINT'),
                ]),
            'test_panel' => new Form_Panel([
                'test_email_explain' => lang('SETTINGS_TEST_EMAIL_EXPLAIN'),
                'email' =>
                    new FormField_Text([
                        'isdbfield' => false,
                        'value' => Input::get('email'),
                        'display' => lang('SETTINGS_TEST_EMAIL_ADDRESS'),
                        'hint_text' => lang('SETTINGS_TEST_EMAIL_ADDRESS_HINT'),
                    ]),
                'emailDebugVerbose' =>
                    new FormField_Select([
                        'isdbfield' => false,
                        'display' => lang('SETTINGS_TEST_EMAIL_DEBUG'),
                        'value' => Input::get('emailDebugVerbose'),
                        'data' => [
                            [ 'id'=>'0', lang('SETTINGS_TEST_EMAIL_DEBUG_0') ],
                            [ 'id'=>'1', lang('SETTINGS_TEST_EMAIL_DEBUG_1') ],
                            [ 'id'=>'2', lang('SETTINGS_TEST_EMAIL_DEBUG_2') ],
                            [ 'id'=>'3', lang('SETTINGS_TEST_EMAIL_DEBUG_3') ],
                            [ 'id'=>'4', lang('SETTINGS_TEST_EMAIL_DEBUG_4') ],
                        ],
                        'hint_text' => lang('SETTINGS_TEST_EMAIL_DEBUG_HINT'),
                    ]),
                'test_email' =>
                    new FormField_ButtonSubmit([
                        'display' => lang('SETTINGS_TEST_EMAIL_BUTTON'),
                    ]),
                'email_results' =>
                    new FormField_HTML([
                        'display' => lang('SETTINGS_EMAIL_TEST_RESULTS'),
                        'value' => $email_results ? lang('SETTINGS_EMAIL_TEST_SUCCESS') : lang('SETTINGS_EMAIL_TEST_FAILURE'),
                        'keep_if' => Input::get('test_email'),
                    ]),
                'email_debug' =>
                    new FormField_HTML([
                        'display' => lang('SETTINGS_EMAIL_TEST_DEBUG'),
                        'value' => $email_debug,
                        'keep_if' => Input::get('test_email'),
                    ]),
            ], [
                'head' => lang('SETTINGS_TEST_EMAIL_TITLE'),
                #'foot' => ???,
            ]),
        ], [
            'title'=>lang('SETTINGS_EMAIL_TITLE'),
            'keep_if' => $mode == 'SITE',
        ]),
    ]),
    'save' =>
        new FormField_ButtonSubmit ([
            'field' => 'save',
            'display' => lang('SAVE_SITE_SETTINGS'),
        ])
], [
    'table' => 'settings',
    'default' => 'process',
    #'debug' => 3,
]);
