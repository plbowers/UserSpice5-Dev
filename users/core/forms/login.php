<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com
*/

ini_set("allow_url_fopen", 1);

$reCaptchaValid=FALSE;
$myForm = new Form([
    'username' => new FormField_Text([
        'dbfield' => 'users.username',
        'placeholder' => lang('USERNAME_OR_EMAIL'),
        'new_valid' => ['unique'=>'unset'], // don't require unique in users
        'extra'     => 'autofocus',
    ]),
    'password' => new FormField_Password([
        'dbfield' => 'users.password',
        'new_valid' => [ ], // accept all defaults
        'extra'     => 'autocomplete="off"',
    ]),
    'recaptcha' => new FormField_ReCaptcha([
        'dbfield' => 'recaptcha',
        'display' => lang('COMPLETE_RECAPTCHA'),
        'keep_if' => configGet('recaptcha') >= 2,
    ]),
    'remember' => new FormField_Checkbox([
        'dbfield' => 'remember',
        'display' => lang('REMEMBER_ME'),
        'keep_if' => configGet('allow_remember_me'),
    ]),
    '<div class="text-center">'."\n",
    'sign_in' => new FormField_ButtonSubmit([
        'dbfield' => 'sign_in',
        'display' => lang('SIGN_IN'),
        'Button_Icon' => 'fa fa-sign-in',
    ]),
    'forgot_password' => new FormField_ButtonAnchor([
        'dbfield' => 'forgot_password',
        'display' => lang('FORGOT_PASSWD'),
        'Link' => 'forgot_password.php',
        'Button_Icon' => 'fa fa-wrench',
    ]),
    'join' => new FormField_ButtonAnchor([
        'dbfield' => 'join',
        'display' => lang('SIGN_UP'),
        'link' => 'join.php',
        'Button_Icon' => 'fa fa-plus-square',
    ]),
    '</div>'."\n",
], [
    'title' => lang('SIGN_IN'),
    'elements' => ['Header', 'openContainer', 'openRow', 'openCol',
                    'TitleAndResults',
                    'openForm', 'CSRF', 'Fields', 'closeForm',
                    'closeCol', 'closeRow', 'closeContainer',
                    'PageFooter', 'Footer'],
    #'debug' => 5,
]);

/*
If enabled, insert google and facebook auth url generators
*/
if (false && configGet('glogin')) {
	require_once pathFinder('helpers/glogin.php');
}
if (false && configGet('fblogin')) {
	require_once pathFinder('helpers/fblogin.php');
}

checkToken();

if (Input::exists()) {
	if ($myForm->checkFieldValidation($_POST, $errors)) {
		# Log user in
		$remember = (Input::get('remember') === 'on') ? true : false;
		$user = new User();
		$login = $user->loginEmail(Input::get('username'), trim(Input::get('password')), $remember);
		if ($login) {
            $state = new StateResponse_Login();
            $state->respond();
		} else {
			$errors[]= lang('LOGIN_FAILED');
		}
	}
}

echo $myForm->getHTML(['errors'=>$errors, 'successes'=>$successes]);
