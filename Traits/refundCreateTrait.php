<?php

namespace App\Traits;

use Http;
use GuzzleHttp\Client;
use Log;
trait refundCreateTrait
{
    public function refundCreate($shop, $orderData, $transaction, $gateway)
    {
        $shopifyApiUrl = 'https://' . $shop->name . '/admin/api/2024-07/graphql.json';
        $activeItems = json_decode($orderData->active_items, true);
        $itemPayload = json_decode($orderData->item_payload, true);
        $resolution = json_decode($orderData->resolution, true);
        $fulfillmentData = $this->getFulfillLineItems($shop, $orderData->order_id);
        $whyReturn = "";
        if (is_array($itemPayload) && !empty($itemPayload)) {
            foreach ($itemPayload as $item) {
                if (isset($item['why_return'])) {
                    $whyReturn = $item['why_return'];
                }
            }
        }
        $lineItems = [];
        foreach ($activeItems as $item) {
            if (!isset($item['custom_return']) || $item['custom_return'] === null) {
                
                $lineItems[] = [
                    'lineItemId' => "gid://shopify/LineItem/" . $item['id'],
                    'quantity' => $item['quantity'],
                    'restockType' => isset($fulfillmentData['fulfillments']) ? 'RETURN' : 'NO_RESTOCK',
                    'locationId' => isset($fulfillmentData['fulfillments']) ? "gid://shopify/Location/".$fulfillmentData['fulfillments'][0]['location_id'] : null 
                ];
            }
        }
        $transactionData = [
            [
                "orderId" => "gid://shopify/Order/" . $orderData->order_id,
                "gateway" => $gateway,
                "kind" => "REFUND",
                "amount" => $transaction['price'],
                "parentId" => "gid://shopify/OrderTransaction/".$transaction['parent_id']
            ]
        ];
        $client = new Client();
        $mutation = <<<'GRAPHQL'
        mutation createRefund($input: RefundInput!) {
            refundCreate(input: $input) {
                refund {
                    id
                    order {
                        id
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'orderId' => "gid://shopify/Order/".$orderData->order_id,
                'refundLineItems' => $lineItems,
                'note' => $whyReturn,
                'notify' => true,
                'transactions' => $transactionData
            ]
        ];
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
        if (isset($responseBody['data']['refundCreate']['refund'])) {
            return[
                'status' => true,
                'refund' => $responseBody['data']['refundCreate']['refund']
            ];
        } else {
            return[
                'status' => false,
                'errors' => $responseBody['data']['refundCreate']['userErrors']
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
