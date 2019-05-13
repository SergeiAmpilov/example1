<?php

namespace commonScripts;

require_once('../vendor/autoload.php');
require_once('../config/config.php');


function buildRunQuery($method, $filterArr = [], $pagination = 1) {

    $client = new \RetailCrm\ApiClient(
        'https://' . subdomain . '.retailcrm.ru',
        retailCrmKey,
        \RetailCrm\ApiClient::V5
    );


    if (empty($filterArr) || !is_array($filterArr) || count($filterArr) === 0) {
        $filterString = "";
    } else {

        $strArr = [];

        foreach ($filterArr as $key => $value) {
            // print_r($key);
            // print_r("\n");
            // print_r($value);
            // print_r("\n");
            $strArr[] = "filter[{$key}]={$value}";
        }
        
        $filterString = implode("&" , $strArr);        
    }

    $runString = "https://" . subdomain . ".retailcrm.ru/api/v5/" . $method .
         "?" . $filterString . "&page={$pagination}&" . "apiKey=" . retailCrmKey;

    try {
        $res = file_get_contents($runString);
    } catch (\RetailCrm\Exception\CurlException $e) {
        return "Connection error: " . $e->getMessage();
    }
    
    $resArray = json_decode($res, true);
    return $resArray;
}

function getCustomersDataById($customerId) {
    $data = array(); // массив с результатом

    
    $client = new \RetailCrm\ApiClient(
        'https://' . subdomain . '.retailcrm.ru',
        retailCrmKey,
        \RetailCrm\ApiClient::V5
    );

    $customersData = $client->request->customersGet($customerId, "id");    

    if (!$customersData->isSuccessful() || !isset($customersData["customer"])) {
        return $data;
    }

    $currentCustomer = $customersData["customer"];

    // print_r($currentCustomer);

    $data["id"] = $currentCustomer["id"];
    $data["name"] = $currentCustomer["firstName"];
    $data["sex"] = isset($currentCustomer["presumableSex"]) ? $currentCustomer["presumableSex"] : "";
    $data["email"] = empty($currentCustomer["email"]) ? "" : $currentCustomer["email"];

    $data["phoneNumber"] = empty($currentCustomer["phones"][0]) ? "" : $currentCustomer["phones"][0]["number"];
    $data["type"] = empty($currentCustomer["contragent"]) ? "individual" : $currentCustomer["contragent"]["contragentType"] ;

    $data["address"] = empty($currentCustomer["address"]["text"]) ? "" : $currentCustomer["address"]["text"];
    $data["index"] = empty($currentCustomer["address"]["index"]) ? "" : $currentCustomer["address"]["index"];
    $data["region"] = empty($currentCustomer["address"]["region"]) ? "" : $currentCustomer["address"]["region"];
    $data["city"] = empty($currentCustomer["address"]["city"]) ? "" : $currentCustomer["address"]["city"];

    return $data;
}


function getCatalogItemFromRetailCrmById($id) {
    $data = array(); // массив с результатом

    $client = new \RetailCrm\ApiClient(
        'https://' . subdomain . '.retailcrm.ru',
        retailCrmKey,
        \RetailCrm\ApiClient::V5
    );

    $filterArr = array(
        "active" => 1,
        "offerIds" => [$id]
        // "minPrice" => "1000", // debug
        // "externalId" => "1827" // debug
    );    

    $response = $client->request->storeProducts($filterArr);

    if (!$response->isSuccessful()) {        
        return $data;
    }

    foreach ($response["products"] as $key => $valueNom) {        
        foreach ($valueNom["offers"] as $key2 => $valueOffer) {

            if ($id != $valueOffer["id"]) {
                continue;
            } 

            $data["name"] = $valueOffer["name"];
            $data["unit"] = $valueOffer["unit"]["name"];
            $data["unitSym"] = $valueOffer["unit"]["sym"];            
            // $data["url"] = $valueOffer["url"];
            $data["id"] = $valueOffer["id"];
            $data["externalId"] = $valueOffer["externalId"];
            // $data["image"] = $valueOffer["images"][0];

            
        }
    }

    return $data;
}

function getExternalIdByOfferId($offerId = 0) {

    if ($offerId === 0) {
        return 0;
    }

    $client = new \RetailCrm\ApiClient(
        'https://' . subdomain . '.retailcrm.ru',
        retailCrmKey,
        \RetailCrm\ApiClient::V5
    );

    $filterArr = array(
        "active" => 1,
        "offerIds" => [ $offerId ]
    );

    $response = $client->request->storeProducts($filterArr);
    print_r($response);

    if (empty($response["products"][0]["offers"])) {
        return 0;
    }

    foreach ($response["products"][0]["offers"] as $curOffer) {
        if ($curOffer["id"] == $offerId) {
           return $curOffer["externalId"];
        }
    }

    return 0;
}