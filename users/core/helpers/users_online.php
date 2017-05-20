<?php
function usersOnline () {
  $timestamp = time();
  $ip = ipCheck();
}

function ipCheck() {
    $ip = false;
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'] as $env) {
        if ($ip = getenv($env)) {
            break;
        }
    }
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function new_user_online($user_id=0) {
    #dbg("new_user_online($user_id): Entering");
    global $T;
	$db = DB::getInstance();
	$ip = ipCheck();
	$timestamp = time();
	$checkUserId = $db->query("SELECT id FROM $T[users_online] WHERE user_id = ?",array($user_id));
	$countUserId = $checkUserId->count();
	$fields = [ 'timestamp'=>$timestamp, 'ip'=>$ip, 'user_id'=>$user_id ];

	if ($countUserId == 0) {
		$db->insert('users_online', $fields);
	} else {
		if ($user_id==0) {
			$checkQ = $db->query("SELECT id FROM $T[users_online] WHERE user_id = 0 AND ip = ?",array($ip));
			if ($checkQ->count()==0) {
				$db->insert('users_online', $fields);
			} else {
				$to_update = $checkQ->first();
				$db->update('users_online', $to_update->id, $fields);
			}
		} else {
			$to_update = $checkUserId->first();
			$db->update('users_online', $to_update->id, $fields);
		}
	}
}

# Delete rows in `users_online` that were updated more then $timeout seconds ago
function delete_user_online($timeout = 86400) {
    global $T;
    $db = DB::getInstance();
    $delete = $db->query("DELETE FROM $T[users_online] WHERE timestamp < (UNIX_TIMESTAMP() - $timeout)");
}

function count_users($timeout = 1800) {
    global $T;
    $db = DB::getInstance();
    return $db->query("SELECT COUNT(*) AS c FROM $T[users_online] WHERE timestamp > (UNIX_TIMESTAMP()-$timeout)")->first()->c;
}
