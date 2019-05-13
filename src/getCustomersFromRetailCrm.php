<?php

// getCustomersFromRetailCrm.php
// получаем контргаентов из RetailCRM
// и возвращаем их в 1С в виде json

require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php'); 
require_once('../config/config.php');

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

file_put_contents("../log/getCustomersFromRetailCrm.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/getCustomersFromRetailCrm.log", "\n" . "Sending Customers to 1C \n", FILE_APPEND);

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);

// тут хранится метка от которой выгружать изменения
if (file_exists("timeCustomers")) {
    $timeArr = json_decode(file_get_contents("timeCustomers"));
} else {
    $timeStart = time() - 365 * 24 * 60 * 60; // -1 year
    file_put_contents("timeCustomers", json_encode(["startDate" => $timeStart, "sinceId" => "0"]));
    $timeArr = json_decode(file_get_contents("timeCustomers"));
}

$time = time();

// будет грузить только то, что накопилось за сутки если скрипт уже долго не запускался
if(time() - $timeArr->startDate > 24 * 60 * 60)
    $timeArr->startDate = time() - 24 * 60 * 60;


// получаем список id тех контрагентов, которые подверглись изменению
$idCustomersModified = [];

$isNext = true;
$nextPage = 1;
$lastModifiedId = "0";


do {

    $response = $client->request->customersHistory([
        "startDate" => date("Y-m-d H:i:s", $timeArr->startDate),
        "endDate" => date("Y-m-d H:i:s", $time),
        // "sinceId" => "3954"
        "sinceId" => $timeArr->sinceId
    ], $nextPage);

    // print_r($response);
    
    if ($response["pagination"]["currentPage"] >= $response["pagination"]["totalPageCount"]) {
        $isNext = false;
    } else {
        $nextPage++ ;
        $isNext = true;
    }

    foreach ($response["history"] as $key => $value) {
        $idCustomersModified[] = $value["customer"]["id"];
        $lastModifiedId = $value["id"];
    }
} while ($isNext);

// фиксим временную метку выборки
file_put_contents("timeCustomers", json_encode(["startDate" => $time, "sinceId" => $lastModifiedId]));


// получаем данные для выгрузки
$resArray = []; // итоговый массив с контрагентами и их данными
$idCustomersModified = array_unique($idCustomersModified);

foreach ($idCustomersModified as $key => $valueCustomer) {

    $nStr = \commonScripts\getCustomersDataById($valueCustomer);
    $resArray[] = $nStr;    
    
}

echo json_encode($resArray, JSON_UNESCAPED_UNICODE); // пытаемся что-то вернуть в 1С