<?php

checkToken();

delete_user_online(60*60*24*7); // Deletes sessions older than 1 week

//Find users who have logged in in X amount of time.
$timeframes = [
    'in the last hour' => date("Y-m-d H:i:s", strtotime("-1 hour")),
    'today' => date("Y-m-d H:i:s", strtotime("-1 day")),
    'in the last week' => date("Y-m-d H:i:s", strtotime("-1 week")),
    'in the last month' => date("Y-m-d H:i:s", strtotime("-1 month")),
];

$last24=time()-86400;
$recentUsers = $db->query("SELECT uo.*, IF(u.username IS NULL, 'guest', u.username) AS username
                            FROM $T[users_online] uo
                            LEFT JOIN $T[users] u ON (uo.user_id = u.id)
                            WHERE timestamp > ?
                            ORDER BY timestamp DESC", [$last24])->results();

$sql = "SELECT COUNT(*) AS c FROM $T[users] WHERE last_login > ?";
foreach ($timeframes as $k=>$v) {
    $q = $db->query($sql, [$v])->first();
    $recentLogins[$k] = $q->c;
}
if (!$minutes = Input::get('timeframe')) {
    $minutes = 30;
}
$uniqueVisitorTimeSpan = $minutes * 60;
?>

<div class="row "> <!-- rows for Info Panels -->
	<div class="col-xs-12">
        <?php include_once pathFinder("includes/admin_dashboard.php"); ?>
    	<?php require_once pathFinder("includes/admin_nav.php"); ?>
	</div>
	<div class="col-xs-12">
	<h2>Information</h2>
	</div>
	<div class="col-xs-12 col-md-6">
	<div class="panel panel-default">
	<div class="panel-heading"><strong>All Users</strong> <span class="small">(Who have logged in)</span></div>
	<div class="panel-body text-center">
	<div class="row">
        <?php foreach ($recentLogins as $k=>$v) { ?>
		<div class="col-xs-3 "><h3><?= $v ?></h3><p><?= $k ?></p></div>
        <?php } ?>
	</div>
	</div>
	</div><!--/panel-->

	<div class="panel panel-default">
	<div class="panel-heading"><strong>All Visitors</strong> <span class="small">(Whether logged in or not)</span></div>
	<div class="panel-body">
	<?php if (configGet('track_guest') == 1) { ?>
	<form>
        <?="In the last <input type=\"text\" name=\"timeframe\" value=\"$minutes\" size=\"5\" /> minutes, the unique visitor count was ".count_users($uniqueVisitorTimeSpan);?>
        <input type="submit" value="refresh" /><br />
    </form>
	<?php } else { ?>
	<p>Guest tracking off. Turn "Track Guests" on below for detailed tracking statistics.</p>
	<?php } ?>
	</div>
	</div><!--/panel-->
	</div> <!-- /col -->

	<div class="col-xs-12 col-md-6">
	<div class="panel panel-default">
	<div class="panel-heading"><strong>Logged In Users</strong> <span class="small">(past 24 hours)</span></div>
	<div class="panel-body">
	<div class="uvistable table-responsive">
	<?php
    if (configGet('track_guest') != 1) {
        echo '<p>Guest tracking off. <a href="admin_site_settings.php">Turn "Track Guests" on</a> for detailed tracking statistics.</p>';
    } else {
    ?>
    	<table class="table">
        	<thead><tr><th>Username</th><th>IP</th><th>Last Activity</th></tr></thead>
        	<tbody>
        	<?php
                foreach($recentUsers as $v1) {
                    $timestamp = date('Y-m-d H:i:s', $v1->timestamp);
            		if ($user_id==0) {
        			    echo "<tr><td>{$v1->username}</td><td>{$v1->ip}</td><td>$timestamp</td></tr>\n";
        		    } else {
        			    echo "<tr><td><a href=\"admin_user.php?id={$v1->user_id}\">{$v1->username}</a></td><td>{$v1->ip}</td><td>$timestamp</td></tr>\n";
        		    }
        	    }
            ?>
        	</tbody>
    	</table>
	<?php
    }
    ?>
	</div>
	</div>
	</div> <!--/panel-->
	</div> <!-- /col2/2 -->
</div> <!-- /row -->
