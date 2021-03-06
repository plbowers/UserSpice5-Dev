<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com
*/

require_once 'init.php';
require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/header.php';

/*
Secures the page...required for page permission management
*/
if (!securePage($_SERVER['PHP_SELF'])){die();}

/*
Query available menus
*/
$navs_all = $db->query("SELECT DISTINCT menu_title FROM menus");
$navs_all = $navs_all->results();

?>
<div class="row"> <!-- row for Users, Permissions, Pages, Email settings panels -->
	<div class="col-xs-12">
	<h1 class="text-center">UserSpice Dashboard <?=configGet('version')?></h1>
	<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/admin_nav.php'; ?>
	</div>
</div> <!-- /.row -->

<div class="row">
	<div class="col-xs-12">
	<h2>Menus</h2>


	
<div class="table-responsive">
	<table class="table table-bordered table-hover">
		<thead><tr><th>Menu Title</th><th>Item Count</th></tr></thead>
		<tbody>
		<?php
		foreach ($navs_all as $nav){
		?>
			<tr><td><a href="admin_menu.php?menu_title=<?=$nav->menu_title?>"><?=$nav->menu_title?></a></td><td>Number</td></tr>
		<?php
		}
		?>
		</tbody>
	</table>
</div>



</div>
</div>

<!-- footers -->
<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<!-- Place any per-page javascript here -->

<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>
