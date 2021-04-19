<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Load language file
 */
function cloudcpartner_LoadLanguage()
{
    global $_LANG, $CONFIG;

    $langDir                = __DIR__ . '/lang/';
    $availableLangsFullPath = glob( $langDir . '*.php' );
    $availableLangs         = array();
    foreach ( $availableLangsFullPath as $availableLang ) {
        $availableLangs[] = strtolower( basename( $availableLang ) );
    }

    if ( empty( $lang ) ) {
        if ( isset( $_SESSION['Language'] ) ) {
            $language = $_SESSION['Language'];
        } else if ( isset( $_SESSION['adminlang'] ) ) {
            $language = $_SESSION['adminlang'];
        } else {
            $language = $CONFIG['Language'];
        }
    } else {
        $language = $lang;
    }

    $language = strtolower( $language ) . '.php';

    if ( ! in_array( $language, $availableLangs ) ) {
        $language = 'english.php';
    }
    require_once( $langDir . $language );
}
cloudcpartner_LoadLanguage();

/**
 * Make cURL HTTP request
 */
function cloudcpartner_SendRequest($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

/**
 * Output JSON
 */
function cloudcpartner_Respond($status, $message, $data = false)
{
    header('Content-Type: application/json');
    die(json_encode(array(
        'status' => $status,
        'message' => $message,
        '__data' => $data
    )));
}

/**
 * Get brand list
 */
function cloudcpartner_BrandHostnamesLoader($params)
{
    $hostnames = [];

    if (empty($params['serverusername']) || empty($params['serverpassword']) || empty($params['serveraccesshash'])) {
        throw new Exception('Please fill in the correct API details on the server/module settings page');
    }

    if (!is_numeric($params['serverusername'])) {
        throw new Exception('Invalid API username on the server/module settings page');
    }

    $partner_api = new CloudConePartnerAPI($params['serverusername'], $params['serverpassword'], $params['serveraccesshash']);
    $brands = json_decode($partner_api->getBrands(), true);

    if (!$brands['status']) {
        throw new Exception('Unable to retrieve brand list.');
    }

    if (isset($brands['__data']) && !empty($brands['__data'])) {
        foreach ($brands['__data'] as $brand) {
            $hostnames[$brand['hostname']] = $brand['hostname'];
        }
    }
    
    return $hostnames;
}

/**
 * Get list of features available as config options
 */
function cloudcpartner_GetFeatureConfigOptions()
{
    $response = json_decode(cloudcpartner_SendRequest('https://clients.onthecloud.app/webhook/features'), true);

    if (isset($response['status']) && $response['status']) {
        return $response['__data'];
    }
}

/**
 * Get scripts and styles for the product config page
 */
function cloudcpartner_GetConfigOptionsScripts()
{
    global $_LANG;

    $css = "";

    $js = "<script>
    function getQueryParam(param) {
        location.search.substr(1)
            .split(\"&\")
            .some(function(item) { // returns first occurence and stops
                return item.split(\"=\")[0] == param && (param = item.split(\"=\")[1])
            })
        return param
    }

    $('.module-settings tr .fieldlabel:contains(\"Configurable Options\") + .fieldarea input[type=text]').remove();
	$('.module-settings tr .fieldlabel:contains(\"Configurable Options\") + .fieldarea').prepend('<a href=\"#\"id=\"ccone-generate-config\" class=\"btn btn-default\">{$_LANG['cconep']['generate_options']}</a> <strong>{$_LANG['cconep']['important']}:</strong> {$_LANG['cconep']['generate_config_notice']}');

	$('form[name=\"packagefrm\"] div.tab-content div#tab3').on('click', 'a#ccone-generate-config', function (e) {
		$.post(\"../modules/servers/cloudcpartner/actions.php\", {product_id: getQueryParam('id'), cloudcpartner_action: 'cloudcpartner_configure'}, function (data) {
            if (data.__data.reload !== undefined) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
        e.preventDefault();
    });
	</script>";
    return $css . $js;
}

/**
 * Get the cloudconeid custom field id for a product
 */
function cloudcpartner_GetInstanceIDFieldID($productid)
{
    $pdo = Capsule::connection()->getPdo();
    $q = $pdo->prepare("SELECT id FROM tblcustomfields WHERE type = 'product' AND relid = ? AND fieldname = 'cloudconeid|CloudCone ID (updated automatically)'");
    $q->execute(array($productid));

    if ($q->rowCount() > 0) {
        return $q->fetchObject()->id;
    } else {
        return false;
    }
}

/**
 * Insert or update the CloudCone instance ID for a given service ID
 */
function cloudcpartner_SetInstanceID($productid, $serviceid, $instanceid)
{
    $pdo = Capsule::connection()->getPdo();

    try {
        $fieldid = cloudcpartner_GetInstanceIDFieldID($productid);

        if (!$fieldid) {
            throw new Exception('Unable to find required custom field.');
        }

        $q = $pdo->prepare("DELETE FROM tblcustomfieldsvalues WHERE fieldid = ? AND relid = ?");
        $q->execute(array($fieldid, $serviceid));

        $q = $pdo->prepare("INSERT INTO tblcustomfieldsvalues(fieldid, relid, value) VALUES(?, ?, ?)");
        $q->execute(array($fieldid, $serviceid, strval($instanceid)));
    } catch (Exception $e) {
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
}

/**
 * Get CloudCone instance ID from a given service ID
 */
function cloudcpartner_GetInstanceID($productid, $serviceid)
{
    $pdo = Capsule::connection()->getPdo();

    $fieldid = cloudcpartner_GetInstanceIDFieldID($productid);

    if (!$fieldid) {
        throw new Exception('Unable to find required custom field.');
    }
    
    $q = $pdo->prepare("SELECT value FROM tblcustomfieldsvalues WHERE fieldid = ? AND relid = ?");
    $q->execute(array($fieldid, $serviceid));

    if ($q && $q->rowCount() > 0) {
        return $q->fetchObject()->value;
    } else {
        return false;
    }
}