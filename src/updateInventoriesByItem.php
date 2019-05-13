<?php

// updateInventoriesByItem.php
// производит обновление остаков товарных предложений


// debug +++
// $_REQUEST = array(
//     "offerId" => "99",
//     "quantity" => "43",
// );
// ---

require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php');
require_once('../config/config.php');

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

file_put_contents("../log/updateInventoriesByItem.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/updateInventoriesByItem.log", "\n" . "Updating inventories from 1C \n", FILE_APPEND);
file_put_contents("../log/updateInventoriesByItem.log", "\n" . print_r($_REQUEST, 1), FILE_APPEND);

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);

$resItemArray = array();

$resItemArray[] = ['id' => $_REQUEST["offerId"], 'stores' => [
            ['code' => 'store_1', 'available' => $_REQUEST["quantity"]] 
            ]];
    

$response = $client->request->storeInventoriesUpload($resItemArray);
print_r($response);





