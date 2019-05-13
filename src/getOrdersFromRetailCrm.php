<?php

// getOrdersFromRetailCrm.php
// получаем заказы из RetailCRM
// и возвращаем их в 1С в виде json

require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php'); // 
require_once('../config/config.php');

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

file_put_contents("../log/getOrdersFromRetailCrm.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/getOrdersFromRetailCrm.log", "\n" . "Sending Customers to 1C \n", FILE_APPEND);

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);


// тут хранится метка от которой выгружать изменения
if (file_exists("timeOrders")) {
    $timeArr = json_decode(file_get_contents("timeOrders"));
} else {
    $timeStart = time() - 365 * 24 * 60 * 60; // -1 year
    file_put_contents("timeOrders", json_encode(["startDate" => $timeStart, "sinceId" => "0"]));
    $timeArr = json_decode(file_get_contents("timeCustomers"));
}

$time = time();

// будет грузить только то, что накопилось за сутки если скрипт уже долго не запускался
if(time() - $timeArr->startDate > 24 * 60 * 60)
    $timeArr->startDate = time() - 24 * 60 * 60;

// получаем список id тех заказов, которые подверглись изменению
$idOrdersModified = [];

$isNext = true;
$nextPage = 1;
$lastModifiedId = "0";

do {
    $response = $client->request->ordersHistory([
        "startDate" => date("Y-m-d H:i:s", $timeArr->startDate),
        "endDate" => date("Y-m-d H:i:s", $time),
        // "sinceId" => "3954"
        "sinceId" => $timeArr->sinceId
    ], $nextPage);

    if ($response["pagination"]["currentPage"] >= $response["pagination"]["totalPageCount"]) {
        $isNext = false;
    } else {
        $nextPage++ ;
        $isNext = true;
    }

    foreach ($response["history"] as $key => $value) {
        $idOrdersModified[] = $value["order"]["id"];
        $lastModifiedId = $value["id"];
    }
    
} while ($isNext);

// фиксим временную метку выборки
file_put_contents("timeOrders", json_encode(["startDate" => $time, "sinceId" => $lastModifiedId]));

$idOrdersModified = array_unique($idOrdersModified);
print_r($idOrdersModified);


$resArray = []; // итоговый массив с заказами и их тов предложениями

foreach ($idOrdersModified as $curOrderId) {
    $response = $client->request->ordersGet($curOrderId, "id");
    
    
    if (!$response->isSuccessful() || empty($response["order"])) {
        // удаленные заказы ненаходятся, но есть в изменениях
        // пропускаем их
        continue;
    }    

    $currentOrder = $response["order"];
    print_r($currentOrder);

    // проверка того, что выгружаем заказы в статусах ЗакупитьТовары
    //  или СогласованСКлиентом
    if ($currentOrder["status"] !== "make-purchase" && 
        $currentOrder["status"] !== "client-confirmed") {
        continue;
    }
    

    $nStr = [];

    $nStr["id"] = $currentOrder["id"];
    $nStr["customerId"] = $currentOrder["customer"]["id"];
    $nStr["createdAt"] = $currentOrder["createdAt"];
    

    
    // items
    $nStr["items"] = [];
    foreach ($currentOrder["items"] as $valItem) {
        $nItem = [];
        $nItem["id"] = $valItem["offer"]["id"];
        $nItem["externalId"] = $valItem["offer"]["externalId"];
        $nItem["initialPrice"] = $valItem["initialPrice"];
        $nItem["discountTotal"] = $valItem["discountTotal"];
        $nItem["vatRate"] = $valItem["offer"]["vatRate"]; // НДС
        $nItem["unitsym"] = $valItem["offer"]["unit"]["sym"];
        $nItem["quantity"] = $valItem["quantity"];

        $nStr["items"][] = $nItem;
    }

    // delivery    
    if (!empty($currentOrder["delivery"])) {
        $nStr["deliveryCode"] = empty($currentOrder["delivery"]["code"]) ? "" : $currentOrder["delivery"]["code"];
        $nStr["deliveryCost"] = empty($currentOrder["delivery"]["cost"]) ? 0 : $currentOrder["delivery"]["cost"];
        if (!empty($currentOrder["delivery"]["address"]["text"])) {
            $nStr["deliveryAddress"] = $currentOrder["delivery"]["address"]["text"];
        } else {
            $nStr["deliveryAddress"] = "";
        }
        
    } else {
        $nStr["deliveryCode"] = "";
        $nStr["deliveryCost"] = 0;
        $nStr["deliveryAddress"] = "";
    }


    $resArray[] = $nStr;
    
}

echo json_encode($resArray, JSON_UNESCAPED_UNICODE); // пытаемся что-то вернуть в 1С



