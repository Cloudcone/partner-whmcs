<?php
require_once '../../../init.php';
require_once 'loader.php';

if (!isset($_SESSION['adminid'])) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Handle the action request
 */
if (isset($_POST['cloudcpartner_action'])) {
    switch ($_POST['cloudcpartner_action']) {
        case 'cloudcpartner_configure':
            if (isset($_POST['product_id'])) {
                cloudcpartner_SetConfigurableOptions($_POST['product_id'], $params);
            } else {
                cloudcpartner_Respond(0, 'Invalid product ID: ' . $_POST['product_id']);
            }
            break;
    }
}

/**
 * Create the configurable options group for cloudcpartner
 * and assign it to the product
 */
function cloudcpartner_SetConfigurableOptions($product_id)
{
    try {
        if (is_numeric($product_id)) {
            $pdo = Capsule::connection()->getPdo();

            $group_id = $pdo->query("SELECT servergroup FROM tblproducts WHERE id = $product_id")->fetchObject()->id;

            if(!$group_id)
            {
                $server = cloudcpartner_GetServerDetails();
            }
            else
            {
                $server = cloudcpartner_GetServerDetailsFromGroup($group_id);
            }

            $q = $pdo->query("SELECT id FROM tblproductconfiggroups WHERE name = 'cloudcpartner'");
            if ($q && $q->rowCount() > 0) {
                $config_group_id = $q->fetchObject()->id;
            } else {
                $q = $pdo->query("INSERT INTO tblproductconfiggroups (name, description) VALUES ('cloudcpartner', 'Auto generated - CloudCone Partner configurable options')");
                if ($q) {
                    $config_group_id = $pdo->lastInsertId();
                }
            }

            if ($config_group_id) {
                $config_options = json_decode(cloudcpartner_SendRequest("https://clients.onthecloud.app/webhook/{$server['partner_id']}/whmcs"), true);

                foreach ($config_options as $config_option) {
                    $q = $pdo->query("SELECT id FROM tblproductconfigoptions WHERE gid = $config_group_id AND optionname = '" . $config_option['optionname'] . "'");
                    if ($q && $q->rowCount() < 1) {
                        $q = $pdo->prepare("INSERT INTO tblproductconfigoptions (gid, optionname, optiontype, qtyminimum, qtymaximum, hidden) VALUES ($config_group_id, :optionname, :optiontype, :qtyminimum, :qtymaximum, :hidden)");
                        $q->execute(array(
                            ':optionname' => $config_option['optionname'],
                            ':optiontype' => $config_option['optiontype'],
                            ':qtyminimum' => (isset($config_option['qtyminimum'])) ? $config_option['qtyminimum'] : 0,
                            ':qtymaximum' => (isset($config_option['qtymaximum'])) ? $config_option['qtymaximum'] : 0,
                            ':hidden' => (isset($config_option['hidden'])) ? $config_option['hidden'] : 0,
                        ));
                        if ($q) {
                            $option_id = $pdo->lastInsertId();
                        }
                    } else {
                        $option_id = $q->fetchObject()->id;
                    }

                    $q = $pdo->query("DELETE FROM tblpricing WHERE type = 'configoptions' AND relid IN ( SELECT * FROM ( SELECT id FROM tblproductconfigoptionssub WHERE configid = $option_id) AS subquery )");
                    $q = $pdo->query("DELETE FROM tblproductconfigoptionssub WHERE configid = $option_id");

                    $q = $pdo->prepare("INSERT INTO tblproductconfigoptionssub (configid, optionname, hidden) VALUES (:configid, :optionname, :hidden)");
                    foreach ($config_option['sub_options'] as $sub_option) {
                        $q->execute([
                            ':configid' => $option_id,
                            ':optionname' => (isset($sub_option['rawName'])) ? $sub_option['rawName'] : $sub_option['name'],
                            ':hidden' => (isset($sub_option['hidden'])) ? $sub_option['hidden'] : 0
                        ]);

                        $sub_option_id = $pdo->lastInsertId();
                        $q2 = $pdo->query("INSERT INTO tblpricing (id, type, currency, relid, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, tsetupfee, monthly, quarterly, semiannually, annually, biennially, triennially) VALUES (NULL, 'configoptions', '1', $sub_option_id, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0')");
                    }
                }

                $q = $pdo->prepare("SELECT gid FROM tblproductconfiglinks WHERE gid = $config_group_id AND pid = ?");
                $q->execute(array($product_id));

                if ($q && $q->rowCount() < 1) {
                    $q = $pdo->prepare("INSERT INTO tblproductconfiglinks (gid, pid) VALUES (?, ?)");
                    $q->execute(array($config_group_id, $product_id));
                }

                // Create customfields
                $q = $pdo->query("SELECT id FROM tblcustomfields WHERE relid = $product_id AND fieldname = 'ccpartnerhost|Hostname'");
                if ($q->rowCount() < 1) {
                    $q = $pdo->query("INSERT INTO tblcustomfields (type, relid, fieldname, fieldtype, description, fieldoptions, regexpr, adminonly, required, showorder, showinvoice, sortorder) VALUES ('product', $product_id, 'ccpartnerhost|Hostname', 'text', 'Server FQDN', '', '', '', 'on', 'on', 'on', '0')");
                }

                $q = $pdo->query("SELECT id FROM tblcustomfields WHERE relid = $product_id AND fieldname = 'cloudconeid|CloudCone ID (updated automatically)'");
                if ($q->rowCount() < 1) {
                    $q = $pdo->query("INSERT INTO tblcustomfields (type, relid, fieldname, fieldtype, description, fieldoptions, regexpr, adminonly, required, showorder, showinvoice, sortorder) VALUES ('product', $product_id, 'cloudconeid|CloudCone ID (updated automatically)', 'text', 'CloudCone Instance ID', '', '', 'on', '', '', '', '0')");
                }

                cloudcpartner_Respond(1, 'Configurable options set-up successful', array('reload' => true));
            }
        } else {
            cloudcpartner_Respond(0, 'Invalid product ID_: ' . $product_id);
        }
    } catch (Exception $e) {
        cloudcpartner_Respond(0, $e->getMessage());
    }
}

function cloudcpartner_GetServerDetails()
{
    $server = [];
    
    try {
        $pdo = Capsule::connection()->getPdo();
        $q = $pdo->query("SELECT username, password, accesshash FROM tblservers WHERE type = 'cloudcpartner' LIMIT 1");

        if ($q && $q->rowCount() > 0) {
            $server_data = $q->fetchObject();
            
            $server['partner_id'] = $server_data->username;
            $server['api_key'] = decrypt($server_data->password);
            $server['api_hash'] = $server_data->accesshash;

            return $server;
        }
    } catch (Exception $e) {
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return false;
}

function cloudcpartner_GetServerDetailsFromGroup($group_id)
{
    $server = [];

    try {
        $pdo = Capsule::connection()->getPdo();
        $q = $pdo->query("SELECT username, password, accesshash FROM tblservers, tblservergroupsrel WHERE tblservergroupsrel.serverid = tblservers.id AND tblservergroupsrel.groupid = $group_id");

        if ($q && $q->rowCount() > 0) {
            $server_data = $q->fetchObject();

            $server['partner_id'] = $server_data->username;
            $server['api_key'] = decrypt($server_data->password);
            $server['api_hash'] = $server_data->accesshash;

            return $server;
        }
    } catch (Exception $e) {
        logModuleCall(
            'cloudcpartner',
            __FUNCTION__,
            $group_id,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return false;
}