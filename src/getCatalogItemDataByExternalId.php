<?php

// getCatalogItemDataByExternalId.php


require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php'); 
require_once('../config/config.php');


// debug +++
// $_REQUEST["externalId"] = "835#533-287";
// $_REQUEST["externalId"] = "237_11 см.";
// $_REQUEST["externalId"] = "835";
// $_REQUEST["externalId"] = "835#37 литров.";
// debug -

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');


file_put_contents("../log/getCatalogItemDataByExternalId.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/getCatalogItemDataByExternalId.log", "\n" . "Get data by external id {$_REQUEST["externalId"]} 1C \n", FILE_APPEND);

// парсим входные данные
$offerExternalId = getOfferExternalId($_REQUEST["externalId"]); // чистый ext id
$propertyName = getPropertyName($_REQUEST["externalId"]); // чистое prop name
//

$data = array(); // массив с результатом

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);

$filterArr = array(
    "active" => 1,
    "offerExternalId" => $offerExternalId
    // "minPrice" => "1000", // debug
    // "externalId" => "1827" // debug
);

$response = $client->request->storeProducts($filterArr);
// print_r($response);


if (!$response->isSuccessful()) {
    return $data;
}

// не нашел
if (empty($response["products"][0]["offers"])) {
    return $data;
}

// ищем id торг предложения по переданным данным
$offersIdFound = "";
foreach ($response["products"][0]["offers"] as $key => $curOffer) {

    // если не передали значение свойства, то просто возвращаем
    // id первого торг предложения
    if ($propertyName === "" && $key === 0 ) {
        $offersIdFound = $curOffer["id"];
        break;
    }

    // сравниваем значение свойств 
    foreach ($curOffer["properties"] as $keyProp => $valueProp) {
        if ($propertyName === $valueProp) {
            $offersIdFound = $curOffer["id"];
            break;
        }
    }
}


$data = \commonScripts\getCatalogItemFromRetailCrmById($offersIdFound);
echo json_encode($data, JSON_UNESCAPED_UNICODE); // пытаемся что-то вернуть в 1С


function getOfferExternalId($data) {
    $arr = explode("_", $data);    
    return $arr[0];
}

function getPropertyName($data) {
    $arr = explode("_", $data);    
    return empty($arr[1]) ? "" : $arr[1];    
}