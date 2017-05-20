<?php
function getMenu($menuTitle, $logo=null, $stub=false) {
    global $footerSnippets;
    $menuSQL = menuSQL($menuTitle, $bindVals);
    $menuItems = prepMenu($menuSQL, $bindVals);
    # This enables SmartMenus on this menu
    /* TURNS OUT THIS IS POINTLESS WHEN COMBINED WITH SMARTMENUS BOOTSTRAP ADDON
    $footerSnippets[] = '
        <script>
            $(function() {
                $("#navbar_'.$menuTitle.'").smartmenus({
                    noMouseOver:true,
                    showOnClick: true,
                    subIndicators: false
                });
            });
        </script>';
    */
    return getMenuHTML($menuTitle, $menuItems, $logo, $stub);
}
function menuSQL($menuTitle, &$bindVals, $forceAll=false) {
    global $T, $user, $lang;
    if ($user->id()) {
        $userId = $user->id();
    } else {
        $userId = -1;
    }
    if ($forceAll) {
        $where = '';
    } else {
        if (isset($user) && $user->isLoggedIn()) {
            $where = ' AND (logged_in >= 0)';
        } else {
            $where = ' AND (logged_in <= 0)';
        }
        if (isset($user) && $user->isAdmin()) {
            $where .= " AND (admin >= 0)";
        } else {
            $where .= " AND (admin <= 0)";
        }
        if (isset($user) && $user->data() && $user->data()->email_verified) {
            $where .= " AND (email_verified >= 0)";
        } else {
            $where .= " AND (email_verified <= 0)";
        }
        if (isset($user) && $user->data() && $user->data()->active) {
            $where .= " AND (active >= 0)";
        } else {
            $where .= " AND (active <= 0)";
        }
    }
    $sql = "SELECT menus.id, menu_title, label_token, parent,
                logged_in, config_key,
                display_order, link_target, icon_class,
                CONCAT(IF(link <> '', link, pages.page), link_args) AS link
              FROM $T[menus] menus
              JOIN $T[pages] pages ON (pages.id = menus.page_id)
             WHERE menu_title = ? ";
    $bindVals = [$menuTitle];
    if (!$forceAll && !$user->isAdmin()) {
        $sql .= "AND (pages.private = 0 OR
                   (pages.private = 1 AND EXISTS
                        (SELECT *
                          FROM $T[groups_pages] gp
                          JOIN $T[groups_users] gu
                                ON (gp.group_id = gu.group_id)
                        WHERE gp.page_id = menus.page_id
                          AND gu.user_id = ?))) ";
        $bindVals[] = $userId;
        $sql .= "AND (menus.private = 0 OR
                   (menus.private = 1 AND EXISTS
                        (SELECT *
                          FROM $T[groups_menus] gm
                          JOIN $T[groups_users] gu
                                ON (gm.group_id = gu.group_id)
                        WHERE gm.menu_id = menus.id
                          AND gu.user_id = ?))) ";
        $bindVals[] = $userId;
    }
    $sql .= " $where
            UNION
            SELECT menus.id, menu_title, label_token, parent,
                logged_in, config_key,
                display_order, link_target, icon_class,
                CONCAT(link, link_args) AS link
              FROM $T[menus] menus
             WHERE menu_title = ?
               AND (page_id IS NULL OR page_id = 0)
               $where
            ORDER BY parent, display_order";
    $bindVals[] = $menuTitle;
    return $sql;
}
function prepMenu($menuSQL, $bindVals) {
    global $user, $lang;
    #pre_r( "SQL=$sql<br />\n");
    #dbg("menuTitle=$menuTitle, userId=$userId");
    $db = DB::getInstance();
    if ($db->query($menuSQL, $bindVals)->error()) {
        throw new Exception("ERROR: ".$db->errorString());
        die;
    }
    /*
     * check if config_key removes any menu Items
     */
    $rows = [];
    foreach ($db->results(true) as $row) {
        if (empty($row['config_key']) || configGet($row['config_key'])) {
            $rows[] = $row;
        }
    }
    foreach ($rows as $row) {
        #dbg("LABEL: ".$row['label_token']);
        $row['children'] = []; // default
        $subs[$row['parent']][] = $row;
    }
    if (!isset($subs)) {
        return []; // default to an empty menu
    }
    #dbg("SUBS");
    #var_dump($subs);
    $menu = [];
    foreach ((array)@$subs[-1] as $sub) { // menus without a parent
        $menu[$sub['id']] = $sub;
        $lookup[$sub['id']] = &$menu[$sub['id']];
    }
    unset($subs[-1]);
    $toDel = [];
    $lastCount = -1;
    while ($subs && $lastCount != sizeof($subs)) {
        foreach ($subs as $parent => $s) {
            #dbg("parent=$parent, s=");
            #var_dump($s);
            if (isset($lookup[$parent])) {
                $lookup[$parent]['children'] = $s;
                foreach ($lookup[$parent]['children'] as &$c) {
                    $lookup[$c['id']] = &$c;
                }
                $toDel[] = $parent;
            }
        }
        if (!$toDel) {
            break; // didn't assign any - just get out and ignore the rest
        }
        #var_dump($toDel);
        $lastCount = sizeof($subs);
        foreach ($toDel as $d) {
            unset($subs[$d]);
        }
        $toDel = [];
    }
    #dbg("Returning menu $menuTitle");
    #var_dump($menu);
    return $menu;
}
function getMenuHTML($menuTitle, $menuItems, $logo=null, $stub=false) {
    global $lang, $user;

    # Setting this is faster than going through another replace
    $lang['MENU_MAIN_USERNAME_MACRO'] = $user->username();

    $html = '
        <nav class="navbar navbar-default">
        <div class="container-fluid">
          <div class="navbar-header">
        	<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar_'.$menuTitle.'" aria-expanded="false" aria-controls="navbar">
        		<span class="sr-only">Toggle navigation</span>
        		<span class="icon-bar"></span>
        		<span class="icon-bar"></span>
        		<span class="icon-bar"></span>
        	</button>';
    if ($logo) {
        $html .= '<a href="'.US_URL_ROOT.'"><img src="'.$logo.'"></img></a>';
    }
    $html .= '
          </div>
          <div id="navbardiv_'.$menuTitle.'" class="navbar-collapse collapse">
        	<ul id="navbar_'.$menuTitle.'" class="sm nav navbar-nav navbar-right">';
    foreach ($menuItems as $key => $value) {
        $html .= getMenuItemHTML($value, 0, $stub);
    }
    $html .= '
        	</ul>
          </div><!--/.nav-collapse -->
        </div><!--/.container-fluid -->
        </nav>';
    return $html;
}
function getMenuItemHTML($menuItem, $level, $stub=false) {
    $menuText = '<span class="'.$menuItem['icon_class'].'"></span> '.lang($menuItem['label_token']);
    $target = $menuItem['link_target'] ? 'target="'.$menuItem['link_target'].'"' : '';
    if ($menuItem['children']) {
    	$itemString ='<li class="dropdown">';
    	$itemString.='<a href="'.$menuItem['link'].'" '.$target.' class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">'.$menuText.' <span class="caret"></span></a>';
    	$itemString.='<ul class="dropdown-menu">';
    	foreach ($menuItem['children'] as $childItem) {
    		$itemString .= getMenuItemHTML($childItem, $level+1, $stub);
    	}
    	$itemString.='</ul></li>';
    	return $itemString;
    } else {
        if ($stub) {
        	$itemString='<li><a onclick="alert(\'Would have gone to `'.$menuItem['link'].'`\'); return false;" href="'.$menuItem['link'].'"><span class="'.$menuItem['icon_class'].'"></span> '.lang($menuItem['label_token']).'</a></li>';
        } else {
        	$itemString='<li><a href="'.$menuItem['link'].'" '.$target.'>'.$menuText.'</a></li>';
        }
    	return $itemString;
    }
}
