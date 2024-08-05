<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use stdClass;
use App\Models\User;
use App\Models\Order;
use App\Traits\updateInventoryTrait;
use Illuminate\Support\Collection;
use Log;

class OrdersUpdateJob implements ShouldQueue
{
    use Dispatc0hable, InteractsWithQueue, Queueable, SerializesModels;
    use updateInventoryTrait;

    /**
     * Shop's myshopify domain
     *
     * @var ShopDomain|string
     */
    public $shopDomain;

    /**
     * The webhook data
     *
     * @var object
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param string   $shopDomain The shop's myshopify domain.
     * @param stdClass $data       The webhook data (JSON decoded).
     *
     * @return void
     */
    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->shopDomain = ShopDomain::fromNative($this->shopDomain);

        $shop = User::where('name', $this->shopDomain->toNative())->first();

        $order =  $this->data;
        
        if ($order) 
        {
            $fulFilledOrder = Order::where('order_id', $order->id)->first();
            if (!$fulFilledOrder) 
            {
                if ($order->fulfillment_status === "fulfilled") 
                {
                    Order::updateOrCreate(
                        ['user_id' => $shop->id, 'order_id' => $order->id],
                        ['status' => $order->fulfillment_status]
                    );
                    foreach ($order->line_items as $lineItem) 
                    {
                        $matafieldData = $this->getmetafields($shop, $lineItem->product_id);

                        if (isset($matafieldData['metafields'])) 
                        {
                            $keys = array_column($matafieldData['metafields'], 'key');
                            $values = array_column($matafieldData['metafields'], 'value');

                            $metafieldsAssoc = array_combine($keys, $values);

                            $customField1Values = isset($metafieldsAssoc['products_bundles']) ? array_map('trim', explode(',', $metafieldsAssoc['products_bundles'])) : [];

                            // if (count($customField1Values) > 0)
                            // {
                            //     $updateInventory = $this->processOrder($shop, $customField1Values, $lineItem->quantity);

                            //     Log::Info("updateInventory", ['data' => $updateInventory]);
                                
                            // }
                        }
                    }
                }
            }
        }
    }
}
