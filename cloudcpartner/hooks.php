<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\View\Menu\Item as MenuItem;

/**
 * Rename client area "Information" sidebar link to "Manage"
 */
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar) {
    global $_LANG;
    
    $navItem = $primarySidebar->getChild('Service Details Overview');
    if (is_null($navItem)) {
        return;
    }

    $navItem = $navItem->getChild('Information');
    if (is_null($navItem)) {
        return;
    }

    $navItem->setLabel($_LANG['cconep']['clientarea_manage_title']);
});