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
 * core/includes/init.php
 *
 * Provides the initialization needed for all UserSpice forms and pages.
 *
 * DO NOT CHANGE THIS FILE. Instead, copy this file to local/includes/init.php
 * and make any needed changes there.
 *
 * QUESTION:
 * SO IF I DON'T CHANGE INIT.PHP, HOW DO I CONFIGURE USERSPICE?!
 * ANSWER:
 * If you are accustomed to making configuration changes in init.php (from
 * version 4 and before) then please note that most configuration has been
 * moved into the database (see DB table `settings`) and can be modified through
 * the "settings" menu on the admin dashboard. Configuration that has not
 * moved to the database is now done through editing local/config.php and any
 * files in local/ are fair game to be changed.
 */

# If we are using old-style single-script-form (not using master_form) then
# we need to find z_us_root.php and include it here...
if (!defined('US_ROOT_DIR')) {
    $curpath = __FILE__;
    $found = false;
    while ($curpath = dirname($curpath)) {
        if (file_exists($fn = $curpath.'/z_us_root.php')) {
            $found = true;
            break;
        }
    }
    if ($found) {
        require_once($fn);
    }
}

# Get a version (set in the variable $user_spice_ver)
include_once US_ROOT_DIR.'core/includes/version.php';

/*
 * If the site over-rides init.php with a copy in local/includes then include
 * that file and don't continue here.
 */
# IF YOU COPY THIS SCRIPT TO local/includes/init.php MAKE SURE THAT YOU
# GET RID OF THESE LINES (BELOW) - otherwise it is circular
if (file_exists(US_ROOT_DIR.'local/includes/init.php')) {
    require_once US_ROOT_DIR.'local/includes/init.php';
    return;
}

error_reporting(E_ALL);
ini_set("display_errors",1);

/*
 * Get the values from local/config.php and make it available via the class
 * "Config" in Classes/Config.php (as defined (class "US_Config") in
 * core/Classes/Config.php and then implemented (class "Config") in
 * local/Classes/Config.php).
 */
include_once US_ROOT_DIR.'core/Classes/Config.php';
include_once US_ROOT_DIR.'local/Classes/Config.php';
include_once US_ROOT_DIR.'local/config.php';

/*
 * require_once Classes from users/Classes/*.php
 * using PHP autoloader conflicts with Google and Facebook autoloader
 */
spl_autoload_register('us_classloader');
function us_classloader($class_name) {
    $classMap = [
        'FormField_' => 'FormFieldTypes', #FormField_Text, FormField_Select, etc.
        'FormTab_'   => 'FormTab', #FormTab_Pane, FormTab_Contents
        'Form_'   => 'FormTab', #Form_Row, Form_Col
        'StateResponse_' => 'StateResponse', #StateResponse_Login, StateResponse_DenyNoPerm, etc.
    ];
    foreach ($classMap as $k=>$c) {
        if (strncmp($k, $class_name, strlen($k)) === 0) {
            $class_name = $c;
            break;
        }
    }
    if (file_exists(US_ROOT_DIR.'core/Classes/'.$class_name.'.php')) {
        include_once US_ROOT_DIR.'core/Classes/'.$class_name . '.php';
        if (file_exists(getcwd().'/local/Classes/'.$class_name.'.php')) {
            include_once getcwd().'/local/Classes/'.$class_name . '.php';
        } else {
            include_once US_ROOT_DIR.'local/Classes/'.$class_name . '.php';
        }
    }
}

/*
 * Autoload Facebook and Google APIs/SDKs
 */
# Replacing these fb- and g-specific autoloads with a generic vendor/autoload.php
#require_once US_ROOT_DIR.'core/social_src/Google/autoload.php';
#require_once US_ROOT_DIR.'core/social_src/Facebook/autoload.php';
require_once US_ROOT_DIR.'resources/vendor/autoload.php';

/*
 * session_start() is placed after the autoloading so that class definitions
 * are loaded before any session processing
 */
session_start();
#var_dump($_SESSION);

require_once US_ROOT_DIR.'local/config.php';

/*
 * $us_tables = the tables that make up UserSpice
 * (if you update this, be sure to update $us_tables in install/initdb.php and
 * the corresponding $init_commands there.)
 */
$us_tables=['addressees', 'audit', 'dev_docs', 'field_defs', 'groups', 'groups_menus', 'groups_pages',
    'groups_roles_users', 'groups_users', 'groups_users_raw', 'grouptypes', 'lang', 'menus', 'messages',
    'pages', 'profiles', 'settings', 'users', 'users_online', 'users_session', ];

# Prepare $T[] for prefix operations (T=tableArray)
$prefix = configGet('mysql/prefix', '');
foreach ($us_tables as $t) {
    $T[$t] = $prefix.$t;
}

# Include other class definitions as late loaders
require_once US_ROOT_DIR.'core/Classes/phpmailer/PHPMailerAutoload.php';

# Bring in the rest of the helpers, checking for localized versions where appropriate
require_once US_ROOT_DIR.'core/helpers/helpers.php';
if (file_exists($localhelp = US_ROOT_DIR.'local/helpers/helpers.php')) {
    require_once $localhelp; // site's custom functions
}
require_once US_ROOT_DIR.'core/helpers/us_helpers.php';
# Now that we have access to pathFinder() (defined in us_helpers.php) we can
# use it for all future include/require statements to ensure we are getting
# the localized version if it exists.
#dbg("abs=".$US_DOC_ROOT."<br />\n");
#dbg("url=".$us_url_root."<br />\n");
require_once pathFinder('helpers/users_online.php');
require_once pathFinder('helpers/menus.php');
#require_once pathFinder('helpers/menus.php');
require_once pathFinder('helpers/class.treeManager.php');
require_once pathFinder('helpers/Shuttle_Dumper.php');
require_once pathFinder('helpers/utilities.php');

# Pull in language data for lang() calls
require_once pathFinder('language/language.php');

# Get an instance of the database
$db = DB::getInstance();

/*
Look for "remember me" cookie
*/
if(Cookie::exists(configGet('remember/cookie_name')) && !Session::exists(configGet('session/session_name'))){
	$hash = Cookie::get(configGet('remember/cookie_name'));
	$hashCheck = $db->query("SELECT * FROM $T[users_session] WHERE hash = ? AND uagent = ?",array($hash,Session::uagent_no_version()));

	if ($hashCheck->count()) {
		$user = new User($hashCheck->first()->user_id, 'id');
		$user->login();
	}
}

/*
Instantiate user class
*/
$user = new User();

if ($user->isLoggedIn()) {
    #dbg("User IS logged in<br />\n");
	/*
	Check if user is blocked
	*/
	if ($user->data()->permissions==0){
		$user_blocked=true;
	}

	/*
	Set user time zone
	php.net/manual/en/timezones.php
	*/
	if($user->data()->timezone_string !=null){
		date_default_timezone_set($user->data()->timezone_string);
	}
	$user_id=$user->data()->id;
    $cfg->overrideSiteSettings(); // take care of any per-user or per-group overrides
} else {
    #dbg("User is NOT logged in<br />\n");
	$user_id=0;
}

if (configGet('enable_messages') && $user->isLoggedIn()) {
    $m = $db->query("SELECT count(*) as c from $T[addressees] WHERE user_id = ? AND is_read = 0", [$user->id()])->first();
    if ($m->c > 0) {
        $lang['MENU_MAIN_MESSAGE_ITEM'] = lang('MENU_MAIN_MESSAGE_WITH_UNREAD', $m->c);
    } else {
        $lang['MENU_MAIN_MESSAGE_ITEM'] = lang('MENU_MAIN_MESSAGE_WITHOUT_UNREAD', $m->c);
    }
}

# Track guests
if (configGet('track_guest')) {
    new_user_online($user_id);
}

/*
 * If user logged in and not verified, then redirect
 * Hmmm... What if it is a public page?
 */
if ($user->isLoggedIn()) {
    #dbg("Init: user IS logged in - checking if email verified<br />\n");
	if($user->data()->email_verified == 0 && $formName != 'verify.php' && $formName != 'logout.php' && $formName != 'verify_thankyou.php') {
		$user->logout();
		Redirect::to(US_URL_ROOT.'verify_resend.php');
	}
}

/*
 * Maintenance Mode?
 */
if (configGet('site_offline')==1 && (!$user->isLoggedIn() || !$user->isAdmin())) {
    $pageData = getPagerowByName([$_SERVER['PHP_SELF'], @$formName]);
    if (!$pageData || !$pageData->site_offline_access) {
        $offline_response = new StateResponse_SiteOffline;
        $offline_response->respond();
    }
}

/*
 * SSL Enforcement
 */
if (configGet('force_ssl') == 1) {
	if (!isset($_SERVER['HTTPS']) || !$_SERVER['HTTPS']) {
		// if request is not secure, redirect to secure url
		$url = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		Redirect::to($url);
		exit;
	}
}

/*
 * Session Time Out Management
 */
//if (isset($_SESSION['LAST_ACTIVITY']) && ((time()-$_SESSION['LAST_ACTIVITY']) > configGet('session_timeout')) && $user->isLoggedIn()) {
if (isset($_SESSION['LAST_ACTIVITY']) && ((time()-$_SESSION['LAST_ACTIVITY']) > configGet('session_timeout'))) {
	session_unset(); // unset $_SESSION variable for the run-time
	session_destroy(); // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

ob_start();
