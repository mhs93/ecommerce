<?php

namespace App\Http\Controllers\Frontend\PurchaseCycle;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Purchase_Cycle\TrxToken;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductPurchaseByUser extends Controller
{
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
    }

    public function SetShippingAddressOnOrder(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'phone'     => 'required|digits:11',
                'trx_id'           => 'required',
                'master_order_id'           => 'required',
            ]);
            if ($validator->fails()) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = 'bad-request';
                $this->msg    = $validator->errors()->first();
                $data = (object)[];
            } else {
                $first_name = $req->input('first_name');
                $last_name = $req->input('last_name');
                $phone_number = $req->input('phone');
                $division = $req->input('division');
                $district = $req->input('district');
                $upazila = $req->input('upazila');
                $full_address = $req->input('full_address');
                $email = $req->input('email') ?? null;
                $trx_id = $req->input('trx_id');
                $master_order_id = $req->input('master_order_id');
                $purchased_item = $req->input('purchased_item');
                $delivery_charge = floatval($req->input('delivery_charge'));
                $grand_total = floatval($req->input('grand_total'));
                $payment_method = $req->input('payment_method');
                $note = $req->input('note') ? json_encode($req->input('note')) : null;
                $order_ids = [];

                $name = $first_name . ' ' . $last_name;

                $shipping_info = json_encode([
                    'name' => $name,
                    'phone' => $phone_number,
                    'email' => $email,
                    'division' => $division,
                    'district' => $district,
                    'upazila' => $upazila,
                    'full_address' => $full_address,
                ]);

                // Client details update
                if (Client::where('phone', $phone_number)->exists()) {
                    $client_info = Client::where('phone', $phone_number)->first();
                    $client_info->name = $name;
                    $client_info->email = $email;
                    $client_info->address = $full_address;
                    $client_info->city = $upazila;
                    $client_info->district = $district;
                    $client_info->division = $division;
                    $client_info->save();
                    $client_id = $client_info->client_id;
                } elseif (Client::where('email', $email)->exists()) {
                    $client_info = Client::where('email', $email)->first();
                    $client_info->name = $name;
                    $client_info->phone = $phone_number;
                    $client_info->address = $full_address;
                    $client_info->city = $upazila;
                    $client_info->district = $district;
                    $client_info->division = $division;
                    $client_info->save();
                    $client_id = $client_info->client_id;
                } else {
                    $client_id = null;
                }
                /* Client details on trx token */

                $find_trx_data = TrxToken::where('trx_id', $trx_id)->first();
                if ($find_trx_data) {
                    $find_trx_data->client_details = $shipping_info;
                    $find_trx_data->save();
                }

                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);


                $validate_Trx_processing = DB::table('product_purchase_by_users')
                    ->where([
                        ['trx_id', '=', $trx_id],
                        ['master_order_id', '=', $master_order_id],
                        ['payment_status', '=', 'processing'],
                    ])
                    ->exists();

                if (!$validate_Trx_processing) {
                    foreach ($purchased_item as $pi) {
                        $encoded_primary_details = json_encode($pi['product_details']);
                        $encoded_variations = json_encode($pi['selected_item']);

                        $payment_status = ["waiting for payment", "canceled", "failed", "processing"];

                        $validate_existence = DB::table('product_purchase_by_users')
                            ->where('order_id', '=', $pi['order_id'])
                            ->whereIn('payment_status', $payment_status)
                            ->exists();

                        if (!$validate_existence) {
                            DB::table('product_purchase_by_users')->insert([
                                'trx_id' => $trx_id,
                                'order_id' => $pi['order_id'],
                                'master_order_id' => $master_order_id,
                                'phone' => $phone_number,
                                'email' => $email,
                                'client_id' => $client_id,
                                'client_name' => $name,
                                'shipping_info' => $shipping_info,
                                'product_primary_details' => $encoded_primary_details,
                                'product_variations' => $encoded_variations,
                                'product_total_price' => $pi['total_price'],
                                'delivery_charge' => $delivery_charge,
                                'grand_price' => $grand_total,
                                'payment_method' => $payment_method,
                                'note' => $note,
                                'order_type' => 'online',
                                'time' => $time,
                                'created_at' => $time,
                            ]);
                        }

                        $order_ids[] = $pi["order_id"];
                    }

                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Shipping address saved and product purchase cycle completed";
                    $data = (object)[
                        "trx_id" => $trx_id,
                        "master_order_id" => $master_order_id,
                        "order_ids" => $order_ids,
                        "shipping_info" => $shipping_info
                    ];
                } else {
                    $this->status = false;
                    $this->code   = 400;
                    $this->error  = "bad-request";
                    $this->msg    = "You have already paid for this item";
                    $data = (object)[
                        "trx_id" => $trx_id,
                        "master_order_id" => $master_order_id,
                        "order_ids" => $order_ids,
                        "shipping_info" => $shipping_info
                    ];
                }
            }

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
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
    public function SetShippingAddressOnOrderManual(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'phone'     => 'required|digits:11',
                'trx_id'           => 'required',
                'master_order_id'           => 'required',
            ]);
            if ($validator->fails()) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = 'bad-request';
                $this->msg    = $validator->errors()->first();
                $data = (object)[];
            } else {
                $first_name = $req->input('first_name');
                $last_name = $req->input('last_name');
                $phone_number = $req->input('phone');
                $division = $req->input('division');
                $district = $req->input('district');
                $upazila = $req->input('upazila');
                $full_address = $req->input('full_address');
                $email = $req->input('email') ?? null;
                $trx_id = $req->input('trx_id');
                $master_order_id = $req->input('master_order_id');
                $purchased_item = $req->input('purchased_item');
                $delivery_charge = floatval($req->input('delivery_charge'));
                $grand_total = floatval($req->input('grand_total'));
                $payment_method = $req->input('payment_method');
                $note = $req->input('note') ? json_encode($req->input('note')) : null;
                $order_ids = [];

                $name = $first_name . ' ' . $last_name;

                $shipping_info = json_encode([
                    'name' => $name,
                    'phone' => $phone_number,
                    'email' => $email,
                    'division' => $division,
                    'district' => $district,
                    'upazila' => $upazila,
                    'full_address' => $full_address,
                ]);

                // Client details update
                if (Client::where('phone', $phone_number)->exists()) {
                    $client_info = Client::where('phone', $phone_number)->first();
                    $client_info->name = $name;
                    $client_info->email = $email;
                    $client_info->address = $full_address;
                    $client_info->city = $upazila;
                    $client_info->district = $district;
                    $client_info->division = $division;
                    $client_info->save();
                    $client_id = $client_info->client_id;
                } elseif (Client::where('email', $email)->exists()) {
                    $client_info = Client::where('email', $email)->first();
                    $client_info->name = $name;
                    $client_info->phone = $phone_number;
                    $client_info->address = $full_address;
                    $client_info->city = $upazila;
                    $client_info->district = $district;
                    $client_info->division = $division;
                    $client_info->save();
                    $client_id = $client_info->client_id;
                } else {
                    $client_id = null;
                }
                /* Client details on trx token */

                $find_trx_data = TrxToken::where('trx_id', $trx_id)->first();
                if ($find_trx_data) {
                    $find_trx_data->client_details = $shipping_info;
                    $find_trx_data->save();
                }

                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);


                $validate_Trx_processing = DB::table('product_purchase_by_users')
                    ->where([
                        ['trx_id', '=', $trx_id],
                        ['master_order_id', '=', $master_order_id],
                        ['payment_status', '=', 'processing'],
                    ])
                    ->exists();

                if (!$validate_Trx_processing) {
                    foreach ($purchased_item as $pi) {
                        $encoded_primary_details = json_encode($pi['product_details']);
                        $encoded_variations = json_encode($pi['selected_item']);

                        $payment_status = ["waiting for payment", "canceled", "failed", "processing"];

                        $validate_existence = DB::table('product_purchase_by_users')
                            ->where('order_id', '=', $pi['order_id'])
                            ->whereIn('payment_status', $payment_status)
                            ->exists();

                        if (!$validate_existence) {
                            DB::table('product_purchase_by_users')->insert([
                                'trx_id' => $trx_id,
                                'order_id' => $pi['order_id'],
                                'master_order_id' => $master_order_id,
                                'phone' => $phone_number,
                                'email' => $email,
                                'client_id' => $client_id,
                                'client_name' => $name,
                                'shipping_info' => $shipping_info,
                                'product_primary_details' => $encoded_primary_details,
                                'product_variations' => $encoded_variations,
                                'product_total_price' => $pi['total_price'],
                                'delivery_charge' => $delivery_charge,
                                'grand_price' => $grand_total,
                                'payment_method' => $payment_method,
                                'note' => $note,
                                'order_type' => 'manual',
                                'time' => $time,
                                'created_at' => $time,
                            ]);
                        }

                        $order_ids[] = $pi["order_id"];
                    }

                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Shipping address saved and product purchase cycle completed";
                    $data = (object)[
                        "trx_id" => $trx_id,
                        "master_order_id" => $master_order_id,
                        "order_ids" => $order_ids,
                        "shipping_info" => $shipping_info
                    ];
                } else {
                    $this->status = false;
                    $this->code   = 400;
                    $this->error  = "bad-request";
                    $this->msg    = "You have already paid for this item";
                    $data = (object)[
                        "trx_id" => $trx_id,
                        "master_order_id" => $master_order_id,
                        "order_ids" => $order_ids,
                        "shipping_info" => $shipping_info
                    ];
                }
            }

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
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

    public function UpdateTrxInventory(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'trx_id' => 'required',
                'master_order_id'  => 'required',
                'payment_status' => 'required',
                'order_ids' => ['required', 'array', 'min:1']
            ]);
            if ($validator->fails()) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = 'bad-request';
                $this->msg    = $validator->errors()->first();
            } else {
                $body = $req->all();
                $payment_status =  Str::replace(['  ', '   ', '    '], ' ', Str::lower($body['payment_status']));
                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);
                $order_inventory = collect();

                $find_trx_data = TrxToken::where('trx_id', $body['trx_id'])->first();
                if ($find_trx_data) {
                    $old_history = json_decode($find_trx_data->trx_history, true);
                    $new_history = collect();
                    foreach ($old_history as $key => $old) {
                        $new_history->push([
                            "trx_id" => $old['trx_id'],
                            "status" => $old['status'],
                            "time" => $old['time'],
                        ]);
                    }
                    $new_history->push([
                        "trx_id" => $body['trx_id'],
                        "status" => $payment_status,
                        "time" => $time,
                    ]);
                    $find_trx_data->status = $payment_status;
                    $find_trx_data->trx_history = $new_history;
                    $find_trx_data->time = $time;
                    $find_trx_data->save();
                }

                foreach ($body["order_ids"] as $order_id) {
                    $order_exists = DB::table("product_purchase_by_users")
                        ->where("trx_id", $body["trx_id"])
                        ->where("order_id", $order_id)
                        ->where("master_order_id", $body["master_order_id"])
                        ->exists();

                    if ($order_exists) {
                        DB::table("product_purchase_by_users")
                            ->where("trx_id", $body["trx_id"])
                            ->where("order_id", $order_id)
                            ->where("master_order_id", $body["master_order_id"])
                            ->update([
                                "payment_status" => $payment_status,
                                "tracking" => $payment_status === "processing" ? "Purchase Completed" : null,
                                "time" => $time,
                                "updated_at" => Carbon::now(),
                            ]);
                        $purchased_item = collect($body["purchased_item"])->where("order_id", $order_id)->first();
                        if ($purchased_item) {
                            $total_qty = [];
                            foreach ($purchased_item["selected_item"] as $selected_item) {
                                $total_qty[] = $selected_item["newQty"];
                            }

                            $product = DB::table('products')->where('product_id', $purchased_item["product_id"])->first();
                            $order_inventory->push([
                                "order_id" => $order_id,
                                "product_id" => $purchased_item["product_id"],
                                "product_details" => $purchased_item["product_details"],
                                "product" => $product,
                                "total_price" => $purchased_item["total_price"],
                                "total_qty" => array_sum($total_qty),
                                "selected_item" => $purchased_item["selected_item"],
                            ]);
                        }
                    }
                }


                foreach ($order_inventory as $key => $or_in) {
                    $product = $or_in["product"];
                    $product_id = $product->product_id;
                    $selected_item = $or_in["selected_item"];
                    $total_qty = $or_in["total_qty"];
                    /*  if (isset($product->has_group_variation) && filter_var($product->has_group_variation, FILTER_VALIDATE_BOOLEAN)) {
                        $getInverntoryWithVariation = $this->vairationUproduct_detailsate($product_id, $product, $selected_item, $total_qty);
                        if($getInverntoryWithVariation){
                            $key = "cached_details" . $product_id;
                            Cache::forget($key);
                        }
                    } else {
                        $getInverntoryWithoutValidation = $this->noVairationUproduct_detailsate($product_id, $product, $total_qty);
                        if($getInverntoryWithoutValidation){
                            $key = "cached_details" . $product_id;
                            Cache::forget($key);
                        }
                    } */
                }

                $this->status = true;
                $this->code   = 200;
                $this->error  = 'Verified';
                $this->msg    = "Purchase " . $payment_status;
            }
            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
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

    public function UpdateTrxInventoryManual(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'trx_id' => 'required',
                'master_order_id'  => 'required',
                'payment_status' => 'required',
                'order_ids' => ['required', 'array', 'min:1']
            ]);
            if ($validator->fails()) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = 'bad-request';
                $this->msg    = $validator->errors()->first();
            } else {
                $body = $req->all();
                $payment_status =  Str::replace(['  ', '   ', '    '], ' ', Str::lower($body['payment_status']));
                $timezone = config('app.timezone');
                $time = Carbon::now($timezone);
                $order_inventory = collect();

                $find_trx_data = TrxToken::where('trx_id', $body['trx_id'])->first();
                if ($find_trx_data) {
                    $old_history = json_decode($find_trx_data->trx_history, true);
                    $new_history = collect();
                    foreach ($old_history as $key => $old) {
                        $new_history->push([
                            "trx_id" => $old['trx_id'],
                            "status" => $old['status'],
                            "time" => $old['time'],
                        ]);
                    }
                    $new_history->push([
                        "trx_id" => $body['trx_id'],
                        "status" => $payment_status,
                        "time" => $time,
                    ]);
                    $find_trx_data->status = $payment_status;
                    $find_trx_data->trx_history = $new_history;
                    $find_trx_data->time = $time;
                    $find_trx_data->save();
                }

                foreach ($body["order_ids"] as $order_id) {
                    $order_exists = DB::table("product_purchase_by_users")
                        ->where("trx_id", $body["trx_id"])
                        ->where("order_id", $order_id)
                        ->where("master_order_id", $body["master_order_id"])
                        ->exists();

                    if ($order_exists) {
                        DB::table("product_purchase_by_users")
                            ->where("trx_id", $body["trx_id"])
                            ->where("order_id", $order_id)
                            ->where("master_order_id", $body["master_order_id"])
                            ->update([
                                "payment_status" => $payment_status,
                                "tracking" => $payment_status === "processing" ? "Purchase Completed" : null,
                                "time" => $time,
                                "updated_at" => Carbon::now(),
                            ]);
                        $purchased_item = collect($body["purchased_item"])->where("order_id", $order_id)->first();
                        if ($purchased_item) {
                            $total_qty = [];
                            foreach ($purchased_item["selected_item"] as $selected_item) {
                                $total_qty[] = $selected_item["newQty"];
                            }

                            $product = DB::table('products')->where('product_id', $purchased_item["product_id"])->first();
                            $order_inventory->push([
                                "order_id" => $order_id,
                                "product_id" => $purchased_item["product_id"],
                                "product_details" => $purchased_item["product_details"],
                                "product" => $product,
                                "total_price" => $purchased_item["total_price"],
                                "total_qty" => array_sum($total_qty),
                                "selected_item" => $purchased_item["selected_item"],
                            ]);
                        }
                    }
                }

                $product_details = collect();
                foreach ($order_inventory as $key => $or_in) {
                    $product = $or_in["product"];
                    $product_id = $product->product_id;
                    $selected_item = $or_in["selected_item"];
                    $total_qty = $or_in["total_qty"];

                    if (isset($product->has_group_variation) && filter_var($product->has_group_variation, FILTER_VALIDATE_BOOLEAN)) {
                        $getInverntoryWithVariation = $this->vairationUproduct_detailsate($product_id, $product, $selected_item, $total_qty);
                        if ($getInverntoryWithVariation) {
                            $product_details->push([
                                "product_id" => $product_id,
                                "product" => $product,
                                "selected_item" => $selected_item,
                                "total_qty" => $total_qty,
                            ]);
                            $key = "cached_details" . $product_id;
                            Cache::forget($key);
                        }
                    } else {
                        $getInverntoryWithoutValidation = $this->noVairationUproduct_detailsate($product_id, $product, $total_qty);
                        if ($getInverntoryWithoutValidation) {
                            $product_details->push([
                                "product_id" => $product_id,
                                "product" => $product,
                                "selected_item" => $selected_item,
                                "total_qty" => $total_qty,
                            ]);
                            $key = "cached_details" . $product_id;
                            Cache::forget($key);
                        }
                    }
                }

                $this->status = true;
                $this->code   = 200;
                $this->error  = 'Verified';
                $this->msg    = "Purchase " . $payment_status;
            }
            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
                'data' => $product_details,
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

    private function vairationUproduct_detailsate($product_id, $product, $selected_item, $total_qty)
    {
        $variation = json_decode($product->variation, true);
        $updated_variation = [];

        foreach ($selected_item as $item) {
            foreach ($variation as &$var_item) {
                if ($var_item['id'] === $item['id']) {
                    $remaining_stock = (int)$var_item['qty'] - (int)$item['newQty'];

                    if (isset($item['identifier']) && $item['identifier'] !== false) {
                        $existing_identification = $var_item["identifier"]["identification"];
                        $item_identification = $item['identifier']['identification'];

                        if ($remaining_stock > 0) {
                            $merged_identification = [];

                            foreach ($existing_identification as $ex_iden) {
                                foreach ($item_identification as $idenInput) {
                                    if ($ex_iden["value"] === $idenInput['value']) {
                                        $remaining_stock_iden = (int)$ex_iden['qty'] - (int)$idenInput['newQty'];

                                        if ($remaining_stock_iden > 0) {
                                            $merged_identification[] = [
                                                "value" => $ex_iden['value'],
                                                "price" => $ex_iden['price'],
                                                "qty" => $remaining_stock_iden,
                                            ];
                                        } else {
                                            $merged_identification[] = [
                                                "value" => $ex_iden['value'],
                                                "price" => $ex_iden['price'],
                                                "qty" => $ex_iden['qty'],
                                            ];
                                        }
                                    }
                                }
                            }

                            $var_item["identifier"]["identification"] = $merged_identification;
                        }
                    }

                    if ($remaining_stock > 0) {
                        $var_item['qty'] = $remaining_stock;
                        $updated_variation[] = $var_item;
                    }
                }
            }
        }

        $final_total_stock =  collect($updated_variation)->sum("qty");
        DB::table('products')->where('product_id', '=', $product_id)->update([
            "variation" => $updated_variation,
            "total_stock" => $final_total_stock
        ]);
        return "Inventory updated";
    }

    private function noVairationUproduct_detailsate($product_id, $product, $total_qty)
    {
        $remaining_stock = $product->total_stock - $total_qty;
        if ($product->total_stock >= $total_qty) {
            if ($remaining_stock > 0) {
                DB::table('products')->where('product_id', '=', $product_id)->decrement('total_stock', $total_qty);
            } else {
                $product->status = 'inactive';
                $remaining_stock = 0;
            }

            DB::table('products')->where('product_id', '=', $product_id)->update([
                'total_stock' => $remaining_stock,
                'status' => $product->status
            ]);
        }

        return "Inventory updated";
    }
}
