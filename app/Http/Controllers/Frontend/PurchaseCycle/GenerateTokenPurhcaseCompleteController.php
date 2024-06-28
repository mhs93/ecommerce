<?php

namespace App\Http\Controllers\Frontend\PurchaseCycle;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase_Cycle\OrderIDSerialIndex;
use App\Models\Purchase_Cycle\TrxToken;
use Carbon\Carbon;
use Exception;
use Haruncpi\LaravelIdGenerator\IdGenerator as IDGen;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GenerateTokenPurhcaseCompleteController extends Controller
{
    /**
     * Afreed Bin Haque
     * Senior softwere Engineer
     * Creative Tech Agency
     *01839194860
     */
    protected $status;
    protected $code;
    protected $error;
    protected $msg;
    protected $systemConfig;

    public function __construct()
    {
        $this->status = true;
        $this->code   = 200;
        $this->error  = null;
        $this->msg    = null;
        $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
        set_time_limit($database_save_timer);
    }

    public function VerifyAndGenerateToken(Request $req)
    {
        try {
            $body = $req->all();
            if (empty($body['purchase'])) {
                $this->status = false;
                $this->code = 400;
                $this->error = "bad-request";
                $this->msg = "Please provide correct purchase history";
                $data = (object)[
                    'trx_id' => null,
                    'buyable_products' => null,
                    'stockout_products' => null,
                    'grand_total' => 0,
                ];
            } else {
                $total_buyable = collect();
                $total_stockout = collect();
                if (!empty($body['purchase']['with_variation'])) {
                    $validate_product = $this->validateProductStockWithVariation($body['purchase']['with_variation']);
                    $total_buyable->push([
                        'cart' => $validate_product['buyable_products'],
                        'grand_total' => $validate_product['grand_total'],
                    ]);
                    $total_stockout->push([
                        'cart' => $validate_product['stockout_products'],
                    ]);
                }
                if (!empty($body['purchase']['without_variation'])) {
                    $validate_product = $this->validateProductStockWithoutVariation($body['purchase']['without_variation']);
                    $total_buyable->push([
                        'buy_now' => $validate_product['buyable_products'],
                        'grand_total' => $validate_product['grand_total'],
                    ]);
                    $total_stockout->push([
                        'buy_now' => $validate_product['stockout_products'],
                    ]);
                }
                $appName = config('System.SystemConfig.system.app_primary_info.app_name');
                $words = explode(' ', $appName);
                $prefix = '';
                foreach ($words as $word) {
                    $prefix .= substr($word, 0, 2);
                }
                $prefix = strtoupper($prefix);
                $serial = IDGen::generate(['table' => 'trx_tokens', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                $ran_sl = Str::random(5);
                $dateWithMilliseconds = date('ymdhms') . sprintf("%03d", round(microtime(true) * 1000) % 1000);
                $trx_id = str_replace(' ', '', $prefix) . 'trx' . hexdec(uniqid()) . $ran_sl . $dateWithMilliseconds . $serial;

                $client_details = $body['client_details'] ? json_encode($body['client_details']) : json_encode((object)[]);

                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);

                $trx_history = json_encode(collect()->push([
                    "trx_id" => $trx_id,
                    "status" => "waiting for payment",
                    "time" => $time,
                ]));

                $save_trx = new TrxToken();
                $save_trx->trx_id  = $trx_id;
                $save_trx->serial = $serial;
                $save_trx->client_details = $client_details;
                $save_trx->buyable_products = json_encode($total_buyable);
                $save_trx->stockout_products = json_encode($total_stockout);
                $save_trx->grand_total = floatval($total_buyable->sum('grand_total'));
                $save_trx->trx_history = $trx_history;
                $save_trx->time = $time;
                $save_trx->save();

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = $validate_product;
                $data = (object)[
                    'trx_id' => $trx_id,
                    'buyable_products' => $total_buyable,
                    'stockout_products' => $total_stockout,
                    'grand_total' => floatval($total_buyable->sum('grand_total'))
                ];
            }
            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => "Trx id generated successfully",
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            $this->status = false;
            $this->code = 422;
            $this->error = "Unprocessable";
            $this->msg = $e->getMessage() . ' in line ' . $e->getLine();

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
            ], 200);
        }
    }

    public function VerifyAndGenerateTokenManual(Request $req)
    {
        try {
            $body = $req->all();
            if (empty($body['purchase'])) {
                $this->status = false;
                $this->code = 400;
                $this->error = "bad-request";
                $this->msg = "Please provide correct purchase history";
                $data = (object)[
                    'trx_id' => null,
                    'buyable_products' => null,
                    'stockout_products' => null,
                    'grand_total' => 0,
                ];
            } else {
                $total_buyable = collect();
                $total_stockout = collect();
                if (!empty($body['purchase']['with_variation'])) {
                    $validate_product = $this->validateProductStockWithVariation($body['purchase']['with_variation']);
                    $total_buyable->push([
                        'cart' => $validate_product['buyable_products'],
                        'grand_total' => $validate_product['grand_total'],
                    ]);
                    $total_stockout->push([
                        'cart' => $validate_product['stockout_products'],
                    ]);
                }
                if (!empty($body['purchase']['without_variation'])) {
                    $validate_product = $this->validateProductStockWithoutVariation($body['purchase']['without_variation']);
                    $total_buyable->push([
                        'buy_now' => $validate_product['buyable_products'],
                        'grand_total' => $validate_product['grand_total'],
                    ]);
                    $total_stockout->push([
                        'buy_now' => $validate_product['stockout_products'],
                    ]);
                }
                $appName = config('System.SystemConfig.system.app_primary_info.app_name');
                $words = explode(' ', $appName);
                $prefix = '';
                foreach ($words as $word) {
                    $prefix .= substr($word, 0, 2);
                }
                $prefix = strtoupper($prefix);
                $serial = IDGen::generate(['table' => 'trx_tokens', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                $ran_sl = Str::random(5);
                $dateWithMilliseconds = date('ymdhms') . sprintf("%03d", round(microtime(true) * 1000) % 1000);
                $trx_id = str_replace(' ', '', $prefix) . 'trx' . hexdec(uniqid()) . $ran_sl . $dateWithMilliseconds . $serial;

                $client_details = $body['client_details'] ? json_encode($body['client_details']) : json_encode((object)[]);

                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);

                $trx_history = json_encode(collect()->push([
                    "trx_id" => $trx_id,
                    "status" => "waiting for payment",
                    "time" => $time,
                ]));

                $save_trx = new TrxToken();
                $save_trx->trx_id  = $trx_id;
                $save_trx->serial = $serial;
                $save_trx->client_details = $client_details;
                $save_trx->buyable_products = json_encode($total_buyable);
                $save_trx->stockout_products = json_encode($total_stockout);
                $save_trx->grand_total = floatval($total_buyable->sum('grand_total'));
                $save_trx->trx_history = $trx_history;
                $save_trx->time = $time;
                $save_trx->save();

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = $validate_product;
                $data = (object)[
                    'trx_id' => $trx_id,
                    'buyable_products' => $total_buyable,
                    'stockout_products' => $total_stockout,
                    'grand_total' => floatval($total_buyable->sum('grand_total'))
                ];
            }
            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => "Trx id generated successfully",
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            $this->status = false;
            $this->code = 422;
            $this->error = "Unprocessable";
            $this->msg = $e->getMessage() . ' in line ' . $e->getLine();

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
            ], 200);
        }
    }

    private function validateProductStockWithVariation($purhcases)
    {
        try {
            $buyable_products = collect();
            $stockout_products = collect();
            $selected_qty_collection = collect();
            $total_price_array = [];

            $total_price = 0;
            $appName = config('System.SystemConfig.system.app_primary_info.app_name');
            $words = explode(' ', $appName);
            $prefix = '';
            foreach ($words as $word) {
                $prefix .= substr($word, 0, 2);
            }
            foreach ($purhcases as $key => $pd) {
                $product = Product::where('product_id', $pd['product_id'])->first();
                foreach ($selected_item = $pd['selected_item'] as $key => $sel_itm) {
                    $selected_qty_collection->push([
                        'newQty' => $sel_itm['newQty']
                    ]);
                }
                if ($product->total_stock >= $selected_qty_collection->sum('newQty') && $product->total_stock > 0) {
                    $prefix = strtoupper($prefix);
                    $serial = IDGen::generate(['table' => 'order_i_d_serial_indices', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                    $ran_sl = Str::random(5);
                    $dateWithMilliseconds = date('ymdhms') . sprintf("%03d", round(microtime(true) * 1000) % 1000);
                    $order_id = str_replace(' ', '', $prefix) . 'pur' . hexdec(uniqid()) . $ran_sl . $dateWithMilliseconds . $serial;
                    $save_order_id_index = new OrderIDSerialIndex();
                    $save_order_id_index->order_id = $order_id;
                    $save_order_id_index->serial = $serial;
                    $save_order_id_index->save();

                    $selected_item = $pd['selected_item'];
                    $buyable_products->push([
                        "order_id" => $order_id,
                        "product_id" => $pd['product_id'],
                        "product_details" => $pd['product_details'],
                        "selected_item" => $selected_item,
                    ]);
                    foreach ($selected_item as $key => $si) {
                        if ($si['identifier'] !== false || $si['identifier'] === null) {
                            foreach ($si['identifier']['identification'] as $key => $idn) {
                                $total_price_array[] = $idn['price'] * $idn['newQty'];
                            }
                        } else {
                            $total_price_array[] =  $product->price * $si['newQty'];
                        }
                    }

                    $total_price = array_sum($total_price_array);
                } else {
                    $stockout_products->push([
                        "product_id" => $pd['product_id'],
                        "product_details" => $pd['product_details'],
                        "selected_item" => $pd['selected_item'],
                    ]);
                }
            }
            return [
                'buyable_products' => $buyable_products,
                'stockout_products' => $stockout_products,
                'grand_total' => $total_price
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }



    private function validateProductStockWithoutVariation($purhcases)
    {
        try {
            $buyable_products = collect();
            $stockout_products = collect();
            $total_price = 0;
            $appName = config('System.SystemConfig.system.app_primary_info.app_name');
            $words = explode(' ', $appName);
            $prefix = '';
            foreach ($words as $word) {
                $prefix .= substr($word, 0, 2);
            }
            foreach ($purhcases as $key => $pd) {
                $product = Product::where('product_id', $pd['product_id'])->first();
                $selected_qty = $pd['selected_item'][0]['newQty'];
                if ($product->total_stock >= $selected_qty && $product->total_stock > 0) {
                    $prefix = strtoupper($prefix);
                    $serial = IDGen::generate(['table' => 'order_i_d_serial_indices', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                    $ran_sl = Str::random(5);
                    $dateWithMilliseconds = date('ymdhms') . sprintf("%03d", round(microtime(true) * 1000) % 1000);
                    $order_id = str_replace(' ', '', $prefix) . 'pur' . hexdec(uniqid()) . $ran_sl . $dateWithMilliseconds . $serial;
                    $save_order_id_index = new OrderIDSerialIndex();
                    $save_order_id_index->order_id = $order_id;
                    $save_order_id_index->serial = $serial;
                    $save_order_id_index->save();

                    $buyable_products->push([
                        "order_id" => $order_id,
                        "product_id" => $pd['product_id'],
                        "product_details" => $pd['product_details'],
                        "selected_item" => $pd['selected_item'],
                    ]);
                    $total_price = $product->price * $pd['selected_item'][0]['newQty'];
                } else {
                    $stockout_products->push([
                        "product_id" => $pd['product_id'],
                        "product_details" => $pd['product_details'],
                        "selected_item" => $pd['selected_item'],
                    ]);
                }
            }
            return [
                'buyable_products' => $buyable_products,
                'stockout_products' => $stockout_products,
                'grand_total' => $total_price
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }
}
