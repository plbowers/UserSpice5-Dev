<?php
#echo "INITIAL INIT TIME: ".(microtime()-$startTime)."<br />\n";
#$startTime = microtime();
#ini_set("allow_url_fopen", 1);

checkToken();

/*
 * Dynamically create the script to run after Google Signin based on settings
 */
$afterExternalSignInScript =
    '<script type="text/javascript" >
    function afterExternalSignIn(userInfo) {
            // any of these that do not exist will just be ignored silently - that is how jquery works
            $(\'input[name="username"]\').val(userInfo.email);
            $(\'input[name="email"]\').val(userInfo.email);
            //var name = userInfo.name;
            //var names = name.split(/[ ,]+/);
            $(\'input[name="fname"]\').val(userInfo.fname);
            $(\'input[name="lname"]\').val(userInfo.lname);
            $(\'input[name="image_url"]\').val(userInfo.imageUrl);
            $(\'input[name="password"]\').attr(\'required\', false);
            $(\'input[name="confirm"]\').attr(\'required\', false);
            $(\'input[name="email"]\').prop(\'readonly\', true); // otherwise we could get a confirmed bad email';
if (configGet('external_join_confirm')) {
    $afterExternalSignInScript .= '
            $(\'.g-signin2\').html("'.lang('JOIN_GOOGLE_SIGNIN_REPLACEMENT').'")';
    if (!configGet('external_join_fields')) {
        $afterExternalSignInScript .= '
            $(\'.other_fields\').hide();';
    }
    if (!configGet('external_join_recaptcha')) {
        $afterExternalSignInScript .= '
            $(\'#g-recaptcha\').hide();';
    }
    if (!configGet('external_join_terms')) {
        $afterExternalSignInScript .= '
            $(\'#outer-terms\').hide();
            $(\'#outer-terms_checkbox\').hide();';
    }
} else { // no confirmation - just submit the form
    $afterExternalSignInScript .= '
            $(\'#save\').click();';
}
$afterExternalSignInScript .= '
    }
    </script>';

# Calculate how wide the columns should be for the external logins
$loginBtns = (configGet('glogin')?1:0)+(configGet('fblogin')?1:0);
if ($loginBtns <= 1) {
    $colClass = 'col-xs-12';
} elseif ($loginBtns == 2) {
    $colClass = 'col-xs-12 col-sm-6';
} elseif ($loginBtns == 3) { // not yet implemented...
    $colClass = 'col-xs-12 col-md-4';
}

/*
 * Create the form
 */
#echo "INIT TIME: ".(microtime()-$startTime)."<br />\n";
#$startTime = microtime();
$joinForm = new Form([
    new Form_Row([
        new Form_Col([
            'glogin' => new FormField_SigninGoogle([
                'isdbfield' => false,
                'keep_if' => configGet('glogin'),
                'script' => $afterExternalSignInScript,
            ]),
        ], ['Col_Class' => $colClass]),
        new Form_Col([
            'fblogin' => new FormField_SigninFacebook([
                'isdbfield' => false,
                'keep_if' => configGet('fblogin'),
                'script' => $afterExternalSignInScript,
            ]),
        ], ['Col_Class' => $colClass]),
    ], [ 'Row_Class' => 'external-logins' ]),
    'users.username' => new FormField_Text([
        'placeholder' => lang('CHOOSE_USERNAME'),
        'extra_class' => 'other_fields',
    ]),
    'users.fname' => new FormField_Text([
        'placeholder' => lang('FNAME'),
        'extra_class' => 'other_fields',
    ]),
    'users.lname' => new FormField_Text([
        'placeholder' => lang('LNAME'),
        'extra_class' => 'other_fields',
    ]),
    'users.email' => new FormField_Text([
        'placeholder' => lang('EMAIL'),
        'extra_class' => 'other_fields',
    ]),
    'users.password' => new FormField_Password([
        'placeholder' => lang('PASSWORD'),
        'extra_class' => 'other_fields',
        # 'required' will be turned off in Javascript and below in manual processing if necessary
        'required' => true,
        'strengthmeter' => configGet('min_pw_score'),
    ]),
    'confirm' => new FormField_Password([
        'isdbfield' => false,
        'extra_class' => 'other_fields',
        'placeholder' => lang('CONFIRM_PASSWD'),
        # 'required' will be turned off in Javascript and below in manual processing if necessary
    ]),
    'terms' => new FormField_Textarea([
        'isdbfield' => false,
        'display' => lang('JOIN_TERMS_CONDITIONS'),
        'extra_class' => 'other_fields',
        'value' => configGet('terms'),
        'disabled' => true,
        'keep_if' => configGet('terms_display') >= 2
    ]),
    'terms_checkbox' => new FormField_Checkbox([
        'isdbfield' => false,
        'extra_class' => 'other_fields',
        'display' => lang('CHECK_ACCEPT_TERMS'),
        'required' => true,
        'valid' => [
            'display' => lang('CHECK_ACCEPT_TERMS'),
            'required' => true
        ],
        'keep_if' => configGet('terms_display') >= 2
    ]),
    'g-recaptcha' => new FormField_ReCaptcha([
        'display' => lang('JOIN_RECAPTCHA'),
        'keep_if' => configGet('recaptcha'),
        'valid' => new ReCaptchaValidate,
    ]),
    'implicit_terms_accept' => new FormField_HTML([
        'display' => lang('JOIN_TERMS_CONDITIONS'),
        'value' => lang('JOIN_TERMS_IMPLICIT_ACCEPT', lang('SIGN_UP')),
        'keep_if' => configGet('terms_display') == 1,
    ]),
    'save' => new FormField_ButtonSubmit([
        'display' => '<span class="fa fa-plus-square"></span>'.lang('SIGN_UP'),
    ]),
    'image_url' => new FormField_Hidden([
        'value' => '',
    ]),
    'permissions' => new FormField_Hidden([
        'value' => 1,
    ]),
    'account_owner' => new FormField_Hidden([
        'value' => 1,
    ]),
    'stripe_cust_id' => new FormField_Hidden([
        'value' => '',
    ]),
    'join_date' => new FormField_Hidden([
        'value' => date("Y-m-d H:i:s"),
    ]),
    'company' => new FormField_Hidden([
        'value' => configGet('company'),
    ]),
    'email_verified' => new FormField_Hidden([
        'value' => !configGet('email_act'),
    ]),
    'active' => new FormField_Hidden([
        'value' => 1,
    ]),
    'vericode' => new FormField_Hidden([
        'value' => rand(100000,999999),
    ]),
], [
    'dbtable' => 'users',
    #'default' => 'process',
    #'autoshow' => false,
    'autoloadnew' => true,
    'autoload' => true,
    'form_mode' => 'INSERT',
    'autoredirect' => false,
    'savefunc' => 'saveUser',
]);
#echo "FORM INIT TIME: ".(microtime()-$startTime)."<br />\n";
#$startTime = microtime();

/*
 * join validation/processing is too complicated to handle
 * with 'default'=>'process' -- sometimes it is social logins,
 * sometimes it is normal registration, etc. which means sometimes
 * passwords are required, sometimes not, etc.
 */
$saved = false;
if (Input::get('save')) {
    $doingGoogle = $googleIdToken = Input::get('glogin');
    $doingFacebook = $facebookIdToken = Input::get('fblogin');
    if ($doingGoogle || $doingFacebook) {
        if (!Input::get('password')) {
            # password and confirm fields are normally required for validation, but if logging in
            # by oAuth or some other (non-pw-based) means then the pw is optional
            $joinForm->getField('password')->setRequired(false); // pw not required if doing google
            $joinForm->getField('confirm')->setRequired(false); // pw not required if doing google
        }
        if (!configGet('external_join_confirm') || !configGet('external_join_recaptcha')) {
            if ($f = $joinForm->getField('g-recaptcha')) {
                $f->setRequired(false);
            }
        }
        if (!configGet('external_join_confirm') || !configGet('external_join_terms')) {
            if ($f = $joinForm->getField('terms_checkbox')) {
                $f->setRequired(false);
            }
        }
    }
dbg("DO NOT REMOVE THIS UNTIL YOU HAVE FIGURED OUT SETTING EMAIL VERIFIED STATUS");
dbg("DO NOT REMOVE THIS UNTIL YOU ARE LOOKING UP EMAIL ADDRESS (to ensure verified) AND VERIFYING ID_TOKEN FROM BOTH GOOGLE AND FACEBOOK");
dbg("NOT SAVING FOR DEBUG PURPOSES");
    #$saved = $joinForm->saveDbData();
$joinForm->checkFieldValidation($_POST, $errors);
#dbg("googleSigninPayload follows");
#pre_r($GLOBALS['googleSigninPayload']);
#pre_r($googleSigninPayload);
/*
#verifying an auth_token from FB
GET graph.facebook.com/debug_token?
     input_token={token-to-inspect}
     &access_token={app-token-or-admin-token}
The response of the API call is a JSON array containing data about the inspected token. For example:
{
    "data": {
        "app_id": 138483919580948,
        "application": "Social Cafe",
        "expires_at": 1352419328,
        "is_valid": true,
        "issued_at": 1347235328,
        "metadata": {
            "sso": "iphone-safari"
        },
        "scopes": [
            "email",
            "publish_actions"
        ],
        "user_id": 1207059
    }
}
The app_id and user_id fields help your app verify that the access token is valid for the person and for your app.
NOTE: It's a good idea to request a specific version when accessing the graph API - otherwise you could end up with an unexpected change
#converting FB access ID token from short-term (a couple hours) to long-term (60 days)
GET /oauth/access_token?
   grant_type=fb_exchange_token&amp;
   client_id={app-id}&amp;
   client_secret={app-secret}&amp;
   fb_exchange_token={short-lived-token}
This returns a new access token which will be long-lived
 */
    if (($doingGoogle || $doingFacebook) && !$saved) {
        $joinForm->getField('password')->setRequired(true); // turn back on for form display
        $joinForm->getField('confirm')->setRequired(true); // turn back on for form display
        $errors[] = lang("MUST_REDO_EXTERNAL_SIGNIN");
        if ($f = $joinForm->getField('g-recaptcha')) {
            $f->setRequired(true);
        }
        if ($f = $joinForm->getField('terms_checkbox')) {
            $f->setRequired(true);
        }
    }
}
if (!$saved) {
    echo $joinForm->getHTML();
} else {
	echo '<div class="jumbotron text-center">'."\n";
	echo '<h2>'.lang('WELCOME', configGet('site_name')).'</h2>'."\n";
	if (configGet('email_act')==0) {
		echo '<p>'.lang('THANKS').'</p>'."\n";
		echo '<a href="login.php" class="btn btn-primary">'.lang('SIGN_IN')."</a>\n";
    } else {
		echo '<p>'.lang('THANKS_VERIFY')."</p>\n";
    }
	echo "</div>\n";
}
#echo "FORM PROCESS TIME: ".(microtime()-$startTime)."<br />\n";

function saveUser($mode, $dbTable, &$id, &$data, $args, &$errors, &$successes) {
    global $user;
    if (isset($data['id'])) {
        unset($data['id']);
    }
    if (!is_a($user, 'User')) {
        $user = new User;
    }
    $id = $user->create($data);
    return Form::UPDATE_SUCCESS;
}
