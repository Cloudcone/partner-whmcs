<?php
/**
 * CloudCone Partner WHMCS Module
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

require_once 'loader.php';

function cloudcpartner_MetaData()
{
    return array(
        'DisplayName' => 'CloudCone Partner',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    );
}

function cloudcpartner_ConfigOptions()
{
    global $_LANG;

    $cc_scripts = cloudcpartner_GetConfigOptionsScripts();
    
    return array(
        'brand_hostname' => array(
            'FriendlyName' => $_LANG['cconep']['brand_hostname'],
            'Type' => 'text',
            'Size' => '64',
            'Loader' => 'cloudcpartner_BrandHostnamesLoader',
            'SimpleMode' => true,
        ),
        'cpu' => array(
            'FriendlyName' => $_LANG['cconep']['cpu'],
            'Type' => 'text',
            'Size' => '2',
            'Description' => $_LANG['cconep']['cpu_description'],
            'SimpleMode' => true,
        ),
        'ram' => array(
            'FriendlyName' => $_LANG['cconep']['ram'],
            'Type' => 'text',
            'Size' => '6',
            'Description' => $_LANG['cconep']['ram_description'],
            'SimpleMode' => true,
        ),
        'disk' => array(
            'FriendlyName' => $_LANG['cconep']['disk'],
            'Type' => 'text',
            'Size' => '6',
            'Description' => $_LANG['cconep']['disk_description'],
            'SimpleMode' => true,
        ),
        'ip_count' => array(
            'FriendlyName' => $_LANG['cconep']['ips'],
            'Type' => 'text',
            'Size' => '6',
            'Default' => '1',
            'Description' => $_LANG['cconep']['ips_description'],
            'SimpleMode' => true,
        ),
        'backups' => array(
            'FriendlyName' => $_LANG['cconep']['backups'],
            'Type' => 'yesno',
            'Description' => $_LANG['cconep']['backups_description'],
            'SimpleMode' => true,
        ),
        'snapshots' => array(
            'FriendlyName' => $_LANG['cconep']['snapshots'],
            'Type' => 'yesno',
            'Description' => $_LANG['cconep']['snapshots_description'],
            'SimpleMode' => true,
        ),
        'cloud_view' => array(
            'FriendlyName' => $_LANG['cconep']['cloud_view'],
            'Type' => 'yesno',
            'Description' => $_LANG['cconep']['cloud_view_description'],
            'SimpleMode' => true,
        ),
        'firewall' => array(
            'FriendlyName' => $_LANG['cconep']['firewall'],
            'Type' => 'yesno',
            'Description' => $_LANG['cconep']['firewall_description'],
            'SimpleMode' => true,
        ),
        'configurable_options' => array(
            'FriendlyName' => $_LANG['cconep']['configurable_options'],
            'Type' => 'text',
            'Description' => $cc_scripts,
            'SimpleMode' => true,
        ),
    );
}

function cloudcpartner_CreateAccount(array $params)
{
    try {
        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $response = json_decode($partner_api->computeCreate(
            (int)$params['configoption2'], // cpu
            (int)$params['configoption3'], // ram
            (int)$params['configoption4'], // disk
            (int)$params['configoption5'], // ip_count
            (int)$params['configoptions']['Operating System'],
            $params['customfields']['ccpartnerhost'],
            array(
                'backups' => (isset($params['configoption6'])) ? filter_var($params['configoption6'], FILTER_VALIDATE_BOOLEAN) : false,
                'snapshots' => (isset($params['configoption7'])) ? filter_var($params['configoption7'], FILTER_VALIDATE_BOOLEAN) : false,
                'cloudview' => (isset($params['configoption8'])) ? filter_var($params['configoption8'], FILTER_VALIDATE_BOOLEAN) : false,
                'firewall' => (isset($params['configoption9'])) ? filter_var($params['configoption9'], FILTER_VALIDATE_BOOLEAN) : false,
            ),
        ), true);

        $instance_ip = $response['__data']['main_ip'];
        $instanceid = $response['__data']['id'];

        $pdo = Capsule::connection()->getPdo();

        $q = $pdo->prepare("UPDATE tblhosting SET username = 'root', dedicatedip = ? WHERE id = ?");
        $q->execute(array($instance_ip, $params['serviceid']));

        cloudcpartner_SetInstanceID($params['pid'], $params['serviceid'], $instanceid);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function cloudcpartner_SuspendAccount(array $params)
{
    try {
        $instanceid = cloudcpartner_GetInstanceID($params['pid'], $params['serviceid']);
        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $partner_api->computeSuspend($instanceid);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function cloudcpartner_UnsuspendAccount(array $params)
{
    try {
        $instanceid = cloudcpartner_GetInstanceID($params['pid'], $params['serviceid']);
        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $partner_api->computeUnsuspend($instanceid);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function cloudcpartner_TerminateAccount(array $params)
{
    try {
        $instanceid = cloudcpartner_GetInstanceID($params['pid'], $params['serviceid']);
        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $partner_api->computeDestroy($instanceid);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function cloudcpartner_ChangePackage(array $params)
{
    try {
        $instanceid = cloudcpartner_GetInstanceID($params['pid'], $params['serviceid']);

        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $partner_api->computeResize(
            $instanceid,
            (int)$params['configoption2'], // cpu
            (int)$params['configoption3'], // ram
            (int)$params['configoption4'], // disk
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function cloudcpartner_ServiceSingleSignOn(array $params)
{
    try {
        $instanceid = cloudcpartner_GetInstanceID($params['pid'], $params['serviceid']);
        $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
        $response = json_decode($partner_api->createAccessToken($instanceid), true);

        if (isset($response['status']) && $response['status']) {
            $brand_hostname = rtrim($params['configoption1'], '/');
            $redirect_url = $brand_hostname . '/compute?token=' . urlencode($response['__data']['token']);
            $redirect_url = (parse_url($redirect_url, PHP_URL_SCHEME) === null) ? 'https://' . $redirect_url : $redirect_url;
            
            return array(
                'success' => true,
                'redirectTo' => $redirect_url,
            );
        } else {
            throw new Exception($response['__data']['errors']);
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}

function cloudcpartner_ClientArea(array $params)
{
    try {
        return array(
            'tabOverviewReplacementTemplate' => 'templates/overview.tpl',
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ),
        );
    }
}
