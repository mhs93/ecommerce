<?php

namespace App\Http\Controllers\CS;

use App\Http\Controllers\Controller;
use App\Models\ProductPurchaseByUser;
use Exception;
use Illuminate\Http\Request;

class CSController extends Controller
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

    public function OrderListTable(Request $req)
    {
        try {
            $payment_status = $req->payment_status;
            $search = $req->input('search');
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $page = $req->input('page') ?? 1;
            $data = [];

            $processingCount = ProductPurchaseByUser::where('payment_status', 'processing')->count();
            $interestedCount = ProductPurchaseByUser::where('payment_status', 'interested')->count();
            $notinterestedCount = ProductPurchaseByUser::where('payment_status', 'not interested')->count();
            $waitingCount = ProductPurchaseByUser::whereIn('payment_status', ['waiting for payment', null])->count();
            $cancelledCount = ProductPurchaseByUser::where('payment_status', 'cancelled')->count();
            $failedCount = ProductPurchaseByUser::where('payment_status', 'failed')->count();

            $count_data = (object)[
                "paid" => $processingCount,
                "interested" => $interestedCount,
                "notinterested" => $notinterestedCount,
                "waiting_for_payment" => $waitingCount,
                "cancelled" => $cancelledCount,
                "failed" => $failedCount,
            ];

            if (!empty($search)) {
                $order_data = ProductPurchaseByUser::orWhere('trx_id', 'like', '%' . $search . '%')
                    ->orWhere('order_id', 'like', '%' . $search . '%')
                    ->orWhere('master_order_id', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('client_id', 'like', '%' . $search . '%')
                    ->orWhere('client_name', 'like', '%' . $search . '%')
                    ->orderBy('time', 'DESC')
                    ->paginate($paginate_data_length);
            } else {
                if (!empty($payment_status)) {
                    $order_data = ProductPurchaseByUser::where('payment_status', $payment_status)->orderBy('time', 'DESC')->paginate($paginate_data_length);
                } else {
                    $order_data = ProductPurchaseByUser::orderBy('time', 'DESC')->paginate($paginate_data_length);
                }
            }

            $formatted_data = collect();
            foreach ($order_data as $order) {
                $formatted_data->push([
                    'trx_id' => $order->trx_id,
                    'order_id' => $order->order_id,
                    'master_order_id' => $order->master_order_id,
                    'phone' => $order->phone,
                    'email' => $order->email,
                    'client_id' => $order->client_id,
                    'client_name' => $order->client_name,
                    'shipping_info' => $order->shipping_info ? json_decode($order->shipping_info, true) : null,
                    'product_primary_details' => $order->product_primary_details ? json_decode($order->product_primary_details, true) : null,
                    'product_variations' => $order->product_variations ? json_decode($order->product_variations, true) : null,
                    'product_total_price' => $order->product_total_price,
                    'delivery_charge' => $order->delivery_charge,
                    'grand_price' => $order->grand_price,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'cs_response' => $order->cs_response,
                    'cs_note' => $order->cs_note ? json_decode($order->cs_note, true) : null,
                    'tracking' => $order->tracking,
                    'note_by_delivery' => $order->note_by_delivery,
                    'time' => $order->time,
                    'order_type' => $order->order_type,
                ]);
            }
            $pagination = $order_data->toArray();

            $baseURL = config('url.pagination.is_live') === 'yes' ? config('url.pagination.live') : config('url.pagination.local');
            $pageURL = $baseURL . 'api/internal-func/cs/order/list?payment_status=' . $payment_status . '&search&page';

            $prevLink = [
                'url' => $pagination['prev_page_url'],
                'label' => '&laquo; Previous',
                'active' => false
            ];
            $links[] = $prevLink;

            for ($i = 1; $i <= $pagination['last_page']; $i++) {
                $url = $pageURL . $i;
                $label = (string) $i;
                $active = ($i == $pagination['current_page']);

                $link = [
                    'url' => $url,
                    'label' => $label,
                    'active' => $active
                ];

                $links[] = $link;
            }

            $nextLink = [
                'url' => $pagination['next_page_url'],
                'label' => 'Next &raquo;',
                'active' => false
            ];
            $links[] = $nextLink;

            $pagination['links'] = $links;
            $pagination['data'] = $formatted_data;

            unset($pagination['first_page_url']);
            unset($pagination['last_page_url']);
            unset($pagination['next_page_url']);
            unset($pagination['prev_page_url']);
            unset($pagination['path']);

            $this->status = true;
            $this->code = 200;
            $this->error = "Verified";
            $this->msg = "Data fetched successfully";
            $data[] = $pagination;



            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => (object)[
                    "table" => $data,
                    "count_data" => $count_data
                ]
            ], 200);
        } catch (Exception $e) {
            $error_message = $e->getMessage() . ' in line ' . $e->getLine();
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $error_message,
            ], 200);
        }
    }
    public function OrderListTableExcel(Request $req)
    {
        try {
            $payment_status = $req->payment_status;
            $data = [];

            if (!empty($payment_status)) {
                $order_data = ProductPurchaseByUser::where('payment_status', $payment_status)->orderBy('time', 'DESC')->get();
            } else {
                $order_data = ProductPurchaseByUser::orderBy('time', 'DESC')->get();
            }

            $formatted_data = collect();
            foreach ($order_data as $order) {
                $formatted_data->push([
                    'trx_id' => $order->trx_id,
                    'order_id' => $order->order_id,
                    'master_order_id' => $order->master_order_id,
                    'phone' => $order->phone,
                    'email' => $order->email,
                    'client_id' => $order->client_id,
                    'client_name' => $order->client_name,
                    'shipping_info' => $order->shipping_info ? json_decode($order->shipping_info, true) : null,
                    'product_primary_details' => $order->product_primary_details ? json_decode($order->product_primary_details, true) : null,
                    'product_variations' => $order->product_variations ? json_decode($order->product_variations, true) : null,
                    'product_total_price' => $order->product_total_price,
                    'delivery_charge' => $order->delivery_charge,
                    'grand_price' => $order->grand_price,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'cs_response' => $order->cs_response,
                    'cs_note' => $order->cs_note ? json_decode($order->cs_note, true) : null,
                    'tracking' => $order->tracking,
                    'note_by_delivery' => $order->note_by_delivery,
                    'time' => $order->time,
                    'order_type' => $order->order_type,
                ]);
            }

            $this->status = true;
            $this->code = 200;
            $this->error = "Verified";
            $this->msg = "Data fetched successfully";
            $data[] = $formatted_data;



            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => $data
            ], 200);
        } catch (Exception $e) {
            $error_message = $e->getMessage() . ' in line ' . $e->getLine();
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $error_message,
            ], 200);
        }
    }


    public function CSResponse(Request $req)
    {
        try {
            $order_id = $req->order_id;
            $cs_note = $req->note;
            $cs_response = $req->response;
            $inserted_by = $req->attributes->get('employee_email');

            $timezone = config('app.timezone');
            $currentDateTime = now()->setTimezone($timezone);

            $cs_note_data = collect([
                [
                    'note' => $cs_note,
                    'uploaded_by' => $inserted_by,
                    'time' => $currentDateTime,
                ]
            ]);

            $tracking = null;

            if ($cs_response === 'order confirm') {
                $tracking = "processing delivery";
            } elseif ($cs_response === 'request for refund') {
                $tracking = "refund in processing";
            }
            $csUpdate = ProductPurchaseByUser::where('order_id', $order_id)->first();

            if ($csUpdate) {
                $csUpdate->cs_response = $cs_response;
                $csUpdate->cs_note = $cs_note_data;
                $csUpdate->tracking = $tracking;
                $csUpdate->save();
                return response()->json([
                    'status' => true,
                    'code'   => 200,
                    'error'  => "verified",
                    'msg'    => "cs response saved successfully",
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => "bad request",
                    'msg'    => "order id not found",
                ], 200);
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage() . ' in line ' . $e->getLine();
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $error_message,
            ], 200);
        }
    }

    public function OrderIdSearch(Request $req)
    {
        try {
            $order_id = $req->order_id;
            $length = strlen($order_id);
            if ($length === 8) {
                $order = ProductPurchaseByUser::whereRaw("RIGHT(order_id, 8) = ?", [$order_id])->first();
            } else {
                $order = ProductPurchaseByUser::where('order_id', $order_id)->first();
            }
            if (!empty($order)) {
                $order_data = [
                    'trx_id' => $order->trx_id,
                    'order_id' => $order->order_id,
                    'master_order_id' => $order->master_order_id,
                    'phone' => $order->phone,
                    'email' => $order->email,
                    'client_id' => $order->client_id,
                    'client_name' => $order->client_name,
                    'shipping_info' => $order->shipping_info ? json_decode($order->shipping_info, true) : null,
                    'product_primary_details' => $order->product_primary_details ? json_decode($order->product_primary_details, true) : null,
                    'product_variations' => $order->product_variations ? json_decode($order->product_variations, true) : null,
                    'product_total_price' => $order->product_total_price,
                    'delivery_charge' => $order->delivery_charge,
                    'grand_price' => $order->grand_price,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'cs_response' => $order->cs_response,
                    'cs_note' => $order->cs_note ? json_decode($order->cs_note, true) : null,
                    'tracking' => $order->tracking,
                    'note_by_delivery' => $order->note_by_delivery,
                    'time' => $order->time,
                ];
            } else {
                $order_data = [];
            }


            return response()->json([
                'status' => true,
                'code'   => 200,
                'error'  => "verified",
                'msg'    => "order data fatched successfully",
                'data'    => (object)$order_data,
            ], 200);
        } catch (Exception $e) {
            $error_message = $e->getMessage() . ' in line ' . $e->getLine();
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $error_message,
            ], 200);
        }
    }
}
