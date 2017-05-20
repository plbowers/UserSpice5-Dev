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

$mode = 'SITE';
#dbg("mode=$mode");
$master = $db->query("SELECT * FROM $T[settings] WHERE (user_id IS NULL OR user_id <= 0) AND (group_id IS NULL OR group_id <= 0)")->first();
if (!Input::get('id')) {
    $_GET['id'] = $master->id;
    $_REQUEST['id'] = $_GET['id'];
}
$db->errorSetMessage($errors);
if (!isset($mode) || !in_array($mode, ['USER', 'GROUP', 'SITE'])) {
    $errors[] = 'DEV ERROR: UNKNOWN mode='.@$mode;
    Redirect::to("Some Other Place - gotta look up the appropriate redirect facility");
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
        'tab_registration' => new FormTab_Pane([
            'email_act' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_REQUIRE_EMAIL_VERIFY'),
                    'data' => $yesOrNo,
                    'hint_text' => lang('SETTINGS_REQUIRE_EMAIL_VERIFY_HINT'),
                ]),
            'email_verify_template' =>
                new FormField_Textarea([
                    'rows' => '10',
                    'display' => lang('SETTINGS_VERIFY_TEMPLATE'),
                    'hint_text' => lang('SETTINGS_VERIFY_TEMPLATE_HINT'),
                ]),
            'forgot_password_template' =>
                new FormField_Textarea([
                    'rows' => '10',
                    'display' => lang('SETTINGS_FORGOT_PASSWD_TEMPLATE'),
                    'hint_text' => lang('SETTINGS_FORGOT_PASSWD_TEMPLATE_HINT'),
                ]),
            'terms_display' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_TERMS_DISPLAY'),
                    'data' => [
                        [ 'id'=>'0', lang('SETTINGS_TERMS_DISPLAY_NONE') ],
                        [ 'id'=>'1', lang('SETTINGS_TERMS_DISPLAY_IMPLICIT') ],
                        [ 'id'=>'2', lang('SETTINGS_TERMS_DISPLAY_EXPLICIT') ],
                    ],
                    'hint_text' => lang('SETTINGS_TERMS_DISPLAY_HINT'),
                ]),
            'terms' =>
                new FormField_Textarea([
                    'rows' => '10',
                    'display' => lang('SETTINGS_TERMS_AND_CONDITIONS'),
                    'hint_text' => lang('SETTINGS_TERMS_AND_CONDITIONS_HINT'),
                ]),
            'external_join_confirm' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_EXTERNAL_JOIN_CONFIRM'),
                    'data' => [
                        [ 'id'=>'0', lang('SETTINGS_EXTERNAL_JOIN_CONFIRM_NO') ],
                        [ 'id'=>'1', lang('SETTINGS_EXTERNAL_JOIN_CONFIRM_YES') ],
                    ],
                    'hint_text' => lang('SETTINGS_EXTERNAL_JOIN_CONFIRM_HINT'),
                ]),
            'external_join_recaptcha' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_EXTERNAL_JOIN_RECAPTCHA'),
                    'data' => [
                        [ 'id'=>'0', lang('SETTINGS_EXTERNAL_JOIN_RECAPTCHA_NO') ],
                        [ 'id'=>'1', lang('SETTINGS_EXTERNAL_JOIN_RECAPTCHA_YES') ],
                    ],
                    'hint_text' => lang('SETTINGS_EXTERNAL_JOIN_RECAPTCHA_HINT'),
                ]),
            'external_join_terms' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_EXTERNAL_JOIN_TERMS'),
                    'data' => [
                        [ 'id'=>'0', lang('SETTINGS_EXTERNAL_JOIN_TERMS_NO') ],
                        [ 'id'=>'1', lang('SETTINGS_EXTERNAL_JOIN_TERMS_YES') ],
                    ],
                    'hint_text' => lang('SETTINGS_EXTERNAL_JOIN_TERMS_HINT'),
                ]),
            'external_join_fields' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_EXTERNAL_JOIN_FIELDS'),
                    'data' => [
                        [ 'id'=>'0', lang('SETTINGS_EXTERNAL_JOIN_FIELDS_NO') ],
                        [ 'id'=>'1', lang('SETTINGS_EXTERNAL_JOIN_FIELDS_YES') ],
                    ],
                    'hint_text' => lang('SETTINGS_EXTERNAL_JOIN_FIELDS_HINT'),
                ]),
        ], [
            'title'=>lang('SETTINGS_REGISTRATION_TITLE'),
            'keep_if' => $mode == 'SITE',
        ]),
        'tab_security' => new FormTab_Pane([
            'recaptcha' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_RECAPTCHA'),
                    'repeat' => [
                        ['id'=>0, 'name'=>lang('DISABLED')],
                        ['id'=>1, 'name'=>lang('RECAPTCHA_FOR_REGISTRATION')],
                        ['id'=>2, 'name'=>lang('RECAPTCHA_ENABLED')],
                    ],
                    'keep_if' => $mode == 'SITE',
                ]),
            'recaptcha_noscript' =>
                new FormField_Select([
                    'display' => lang('SETTINGS_RECAPTCHA_NOSCRIPT'),
                    'hint_text' => lang('SETTINGS_RECAPTCHA_NOSCRIPT_HINT'),
                    'repeat' => [
                        ['id'=>0, 'name'=>lang('DISABLED')],
                        ['id'=>1, 'name'=>lang('RECAPTCHA_NOSCRIPT_ENABLED')],
                    ],
                    'keep_if' => $mode == 'SITE',
                ]),
            'recaptcha_site_key' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_RECAPTCHA_SITE_KEY'),
                    'hint_text' => lang('SETTINGS_RECAPTCHA_SITE_KEY_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
            'recaptcha_secret_key' =>
                new FormField_Text([
                    'display' => lang('SETTINGS_RECAPTCHA_SECRET_KEY'),
                    'hint_text' => lang('SETTINGS_RECAPTCHA_SECRET_KEY_HINT'),
                    'keep_if' => $mode == 'SITE',
                ]),
        ], [
            'title' => lang('SETTINGS_RECAPTCHA_TITLE'),
            'keep_if' => $mode == 'SITE',
        ]),
        'tab_social' => new FormTab_Pane ([
            'google_panel' => new Form_Panel([
                'glogin' =>
                    new FormField_Select([
                        'display' => lang('SETTINGS_GLOGIN_STATE'),
                        'repeat' => [
                            ['id'=>0, 'name'=>lang('DISABLED')],
                            ['id'=>1, 'name'=>lang('ENABLED')],
                        ],
                        'hint_text' => lang('SETTINGS_GLOGIN_STATE_HINT'),
                    ]),
                'glogin_client_id' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_GLOGIN_CLIENT_ID'),
                        'hint_text' => lang('SETTINGS_GLOGIN_CLIENT_ID_HINT'),
                    ]),
                /* UNUSED with google signin
                'gsecret' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_GLOGIN_SECRET'),
                        'hint_text' => lang('SETTINGS_GLOGIN_SECRET_HINT'),
                    ]),
                'gcallback' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_GLOGIN_CALLBACK'),
                        'hint_text' => lang('SETTINGS_GLOGIN_CALLBACK_HINT'),
                    ]),
                */
            ], [
                'title'=>lang('SETTINGS_GOOGLE_TITLE'),
            ]),
            'facebook_panel' => new Form_Panel ([
                'fblogin' =>
                    new FormField_Select([
                        'display' => lang('SETTINGS_FBLOGIN_STATE'),
                        'repeat' => [
                            ['id'=>0, 'name'=>lang('DISABLED')],
                            ['id'=>1, 'name'=>lang('ENABLED')],
                        ],
                        'hint_text' => lang('SETTINGS_FBLOGIN_STATE_HINT'),
                    ]),
                'fbid' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_FBLOGIN_CLIENT_ID'),
                        'hint_text' => lang('SETTINGS_FBLOGIN_CLIENT_ID_HINT'),
                    ]),
                'fbsecret' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_FBLOGIN_SECRET'),
                        'hint_text' => lang('SETTINGS_FBLOGIN_SECRET_HINT'),
                    ]),
                'fbcallback' =>
                    new FormField_Text([
                        'display' => lang('SETTINGS_FBLOGIN_CALLBACK'),
                        'hint_text' => lang('SETTINGS_FBLOGIN_CALLBACK_HINT'),
                    ]),
            ], [
                'title' => lang('SETTINGS_FACEBOOK_TITLE'),
            ]),
        ], [
            'title' => lang('SETTINGS_SOCIAL_TITLE'),
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
