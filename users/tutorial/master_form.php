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
 * tutorial/master_form.php
 * This script is included by the tutorial pages of the site and in turn includes
 * all necessary includes/classes/helpers and finally the form itself (usually found
 * either in UserSpice/forms or in UserSpice/local/forms)
 */

# Read in all initial values, include helpers, classes, config, etc.
if (file_exists(US_ROOT_DIR.'local/includes/init.php')) {
    include_once(US_ROOT_DIR.'local/includes/init.php');
} else {
    include_once(US_ROOT_DIR.'core/includes/init.php');
}

# Make sure $formName is set
if (isset($formName)) {
    $pageName = $formName;
} else {
    $pageName = $_SERVER['PHP_SELF'];
    $formName = basename($pageName);
}
# Security - make sure user is allowed to access this page
if (!securePage($pageName)) {
    $login_response = new StateResponse_DenyNoPerm;
    $login_response->respond();
    die();
}

# Load headers and navigation unless $enableMasterHeaders (plural) is explicitly set to false
if (isset($enableMasterHeaders) && $enableMasterHeaders) {
    # Load header unless $enableMasterHeader (singular) is explicitly set to false
    if (!isset($enableMasterHeader) || $enableMasterHeader) {
        require_once pathFinder('includes/header.php');
    }
    # Load navigation unless $enableMasterNavigation is explicitly set to false
    if (!isset($enableMasterNavigation) || $enableMasterNavigation) {
        require_once pathFinder('includes/navigation.php');
    }
}

#
# Find the actual simplified form (without the headers and footers
# supplied here in master_form.php) and include it
#
# If it is a UserSpice form the simplified form will be found
# under forms/ - either core/forms/ or local/forms/ - but
# 3rd party developers will typically just specify a directory
# during installation that doesn't have the forms/ sub-directory.
# Thus we search first for simple $formname and then for the
# UserSpice standard of forms/$formname. Whichever one we find
# first, use that.
#
# pathFinder() here will look in the paths defined in config.php
# in configGet('forms_path') for $formName
#
$found = false;
foreach ([$formName, basename($_SERVER['PHP_SELF'])] as $fn) {
    if ($formPath = pathFinder($fn, '', 'forms_path',
            [US_ROOT_DIR.'local/forms/', US_ROOT_DIR.'core/forms/'])) {
        $found = true;
    }
}
if ($found) {
    $successes = $errors = []; # some convenient initializations
    require_once $formPath;
} else {
    die("SYSTEM ERROR: Cannot find `$formName`");
}

# Load footers unless $enableMasterFooters is explicitly set to false
if (isset($enableMasterFooters) && $enableMasterFooters) {
    if (!isset($enableMasterPageFooters) || $enableMasterPageFooters) {
        require_once pathFinder('includes/page_footer.php'); // the final html footer copyright row + the external js calls
    }
    if (!isset($enableMasterHTMLFooters) || $enableMasterHTMLFooters) {
        require_once pathFinder('includes/html_footer.php'); // currently just the closing /body and /html
    }
}
