<?php

// updateOrderPayments.php
// обновляем статус оплат по переданному заказу

// debug +++
// $_REQUEST = array(
//     "id" => "1109",
//     "sum" => "6490",
//     "type" => "bank-transfer",
//     "paidAt" => "2019-05-06 12:44:49"

// );
// ---


require_once('../vendor/autoload.php');
require_once('../classes/commonScripts.php'); // 
require_once('../config/config.php');

set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

file_put_contents("../log/updateOrderPayments.log", "\n" . date('l jS \of F Y h:i:s A'), FILE_APPEND);
file_put_contents("../log/updateOrderPayments.log", "\n" . "Updating payment status from 1C \n", FILE_APPEND);
file_put_contents("../log/updateOrderPayments.log", print_r($_REQUEST, 1), FILE_APPEND);
file_put_contents("../log/updateOrderPayments.log", "\n", FILE_APPEND);


$orderId = $_REQUEST["id"];
$sum = $_REQUEST["sum"];
$type = $_REQUEST["type"];
$paidAt = $_REQUEST["paidAt"];

if ($orderId == "0") {
    // пришел некорректный заказ из 1С
    die();
}

$client = new \RetailCrm\ApiClient(
    'https://' . subdomain . '.retailcrm.ru',
    retailCrmKey,
    \RetailCrm\ApiClient::V5
);

clearPaymentsByOrderId($orderId);

if ($sum == "0") {
    die();
}

// pay status
$res = $client->request->ordersGet($orderId, 'id');


if (!empty($res['order']['totalSumm'])) {
    $payStatus = ($res['order']['totalSumm'] > $sum ? "partpaid" : "paid");
} else {
    $payStatus = "paid";
}

$res = $client->request->ordersPaymentCreate(['order' => ['id' => $orderId], 
        'amount' => $sum, 'type' => $type, 'status' => $payStatus, "paidAt" => $paidAt], 
        "shopbarn-ru");
print_r($res);


function clearPaymentsByOrderId($orderId) {
    $client = new \RetailCrm\ApiClient(
        'https://' . subdomain . '.retailcrm.ru',
        retailCrmKey,
        \RetailCrm\ApiClient::V5
    );

    $res = $client->request->ordersGet($orderId, 'id');
    
    if (empty($res['order']['payments'])) {
        return;
    }

    foreach ($res['order']['payments'] as $curPayment) {
        print_r($curPayment);
        $resDel = $client->request->ordersPaymentDelete($curPayment["id"]);
    }
}