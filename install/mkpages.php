<?php
#include_once("../users/us_core/include/init.php");
include_once('../src/z_us_root.php');
echo US_ROOT_DIR.'us_core/forms/*.php';
$us_pages = glob(US_ROOT_DIR.'us_core/forms/*.php');
foreach ($us_pages as $p) {
    $p = basename($p);
    $contents = <<<EOF
<?php
\$formName = '$p';
#\$enableMasterHeaders = \$enableMasterFooters = true;
require_once $z_us_root_path.'z_us_root.php';
require_once US_ROOT_DIR.'us_core/master_form.php';
EOF;
    if (file_put_contents(US_ROOT_DIR.$p, $contents) === false) {
        echo "ERROR creating $p<br />\n";
    } else {
        echo "Created $p<br />\n";
    }
}
