<?php

namespace App\Traits;

use Http;
use GuzzleHttp\Client;
use Log;
trait returnCreateTrait
{
    public function returnCreate($shop, $orderData)
    {
        $shopifyApiUrl = 'https://' . $shop->name . '/admin/api/2024-07/graphql.json';
        $activeItems = json_decode($orderData->active_items, true);
        $resolution = json_decode($orderData->resolution, true);
        $item_payload = json_decode($orderData->item_payload, true);
        $fulfillmentData = $this->getFulfillLineItems($shop, $orderData->order_id);
        // dd($activeItems, $resolution, $item_payload);

        $lineItems = [];
        $fulfillmentLineItems = [];
        $ReturnReasonArray = [
            "Others" => "OTHER",
            "Poor quality/Faulty" => "NOT_AS_DESCRIBED",
            "Incorrect item received" => "WRONG_ITEM",
            "Wrong Size / Exchange for different" => "SIZE_TOO_LARGE"

        ];
        foreach ($activeItems as $item) {
            if (!isset($item['custom_return']) || $item['custom_return'] === null) {
                $lineItems[] = [
                    'variantId' => "gid://shopify/ProductVariant/" . $resolution['replaceItems'][$item['product_id']],
                    'quantity' => $item['quantity']
                ];
                if (isset($fulfillmentData['fulfillments'])) {

                    $filteredItems = array_filter($fulfillmentData['fulfillments'][0]['line_items'], function ($fullfill) use ($item) {
                        return $fullfill['variant_id'] === $item['variant_id'];
                    });
                    foreach ($filteredItems as $filteredItem) {
                        $fulfillmentLineItems[] = [
                            'fulfillmentLineItemId' => "gid://shopify/FulfillmentLineItem/".$filteredItem['fulfillment_line_item_id'],
                            "quantity" => $filteredItem['quantity'],
                            "returnReason" => $ReturnReasonArray[$item_payload[$filteredItem['variant_id']]['why_return']],
                            "returnReasonNote" => $item_payload[$filteredItem['variant_id']]['why_return']
                        ];
                    }
                }
            }
        }


        $client = new Client();
        $variables =  [
            "returnInput" => [
                "exchangeLineItems" => $lineItems,
                "notifyCustomer" => true,
                "orderId" => "gid://shopify/Order/".$orderData->order_id,
                "returnLineItems" => $fulfillmentLineItems,
            ]
        ];
    
        $mutation = <<<GRAPHQL
        mutation returnCreate(\$returnInput: ReturnInput!) {
          returnCreate(returnInput: \$returnInput) {
                return {
                    id
                    status
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        // $mutation = <<<'GRAPHQL'
        // mutation returnCreate($returnInput: ReturnInput!) {
        //   returnCreate(returnInput: $returnInput) {
        //         return {
        //             id
        //             status
        //         }
        //         userErrors {
        //             field
        //             message
        //         }
        //     }
        // }
        // GRAPHQL;

        $response = $client->post($shopifyApiUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $shop->password,
            ],
            'json' => [
                'query' => $mutation,
                'variables' => $variables
            ]
        ]);

        $responseBody = json_decode($response->getBody(), true);
        Log::info("Exchange product with same", ['data' => $responseBody]);
        if (isset($responseBody['data']['returnCreate']['return'])) {
            return [
                'status' => true,
                'return' => $responseBody['data']['returnCreate']['return']
            ];
        } else {
            return [
                'status' => false,
                'errors' => $responseBody['data']['returnCreate']['userErrors']
            ];
        }
    }
    public function getFulfillLineItems($shop, $orderId) {
        $query = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->password,
            'Content-Type' => 'application/json',
        ])->get("https://". $shop->name ."/admin/api/". env('SHOPIFY_API_VERSION')."/orders/".$orderId."/fulfillments.json");
        $fulfillments = $query->json();
        return $fulfillments;
    }
}
