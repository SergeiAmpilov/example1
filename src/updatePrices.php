<?php

// updatePrices.php
// производим обновление цен по данным из 1С


// debug +++
// $_REQUEST = array(
//     "offerId" => "99",
//     "pBase" => "1",
//     "pStock" => "3",
//     "pFran" => "22",
// );
// ---


require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php');
require_once('../config/config.php');

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

file_put_contents("../log/updatePrices.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/updatePrices.log", "\n" . "Updating prices from 1C \n", FILE_APPEND);
file_put_contents("../log/updatePrices.log", "\n" . print_r($_REQUEST, 1), FILE_APPEND);

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);

$resItemArray = array();

// розница, оптовая, франшиза
$resItemArray[] = [
    'id' => $_REQUEST["offerId"], 'prices' => [
        [ 'code' => 'base', 'price' => $_REQUEST["pBase"] ],
        [ 'code' => 'stock', 'price' => $_REQUEST["pStock"] ],
        [ 'code' => 'franchise', 'price' => $_REQUEST["pFran"] ],
    ]
];

$response = $client->request->storePricesUpload($resItemArray);
print_r($response);