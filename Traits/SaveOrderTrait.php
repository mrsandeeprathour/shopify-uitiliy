<?php 
namespace App\Traits;
use Http;
use GuzzleHttp\Client;


trait SaveOrderTrait {


    public function saveOrder($orderData, $shop)
    {

        $Order = isset($orderData->order) ?? $orderData;

        $totalQuantity = 0;

        if (isset($orderData->line_items)) {
            foreach ($orderData->line_items as $lineItem) {
                if (isset($lineItem->quantity)) {
                    $totalQuantity += $lineItem->quantity;
                }
            }
        }
        
        Orders::updateOrCreate(
            [
                'user_id' => $shop->id,
                'order_id' => $orderData->id
            ],
            [
                'order_name' => $orderData->name,
                'note' => $orderData->note,
                'date' => $orderData->created_at,
                'customer_id' => $orderData->customer->id,
                'customer_name' => $orderData->customer->first_name . ' ' . $orderData->customer->last_name,
                'customer_email' => $orderData->customer->email,
                'total' => $orderData->total_price,
                'payment_status' => $orderData->financial_status,
                'fullfilement' => $orderData->fulfillment_status,
                'item_count' => $totalQuantity,
                'delivery_status' => "",
                'delivery_method' => $orderData->shipping_lines ? $orderData->shipping_lines[0]->title : "",
            ]
        );
        
        return (['status' => true, 'message' => "Order update successfully"]);

    }
    

}