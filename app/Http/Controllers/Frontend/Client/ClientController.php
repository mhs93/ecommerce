<?php

namespace App\Http\Controllers\Frontend\Client;

use App\Http\Controllers\Controller;
use App\Models\ProductPurchaseByUser;
use Exception;
use Illuminate\Http\Request;

class ClientController extends Controller
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

    public function TotalProductPurchaseByClient(Request $req)
    {
        try {
            $search = $req->input('search');
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $page = $req->input('page') ?? 1;
            $data = [];


            if (!empty($search)) {
                $order_data = ProductPurchaseByUser::where(function ($query) use ($req, $search) {
                    $query->where([
                        ['client_id', $req->client_id],
                        ['payment_status', 'processing']
                    ])
                        ->Where('trx_id', 'like', '%' . $search . '%');
                })
                    ->orderBy('time', 'DESC')
                    ->paginate($paginate_data_length);
            } else {
                $order_data = ProductPurchaseByUser::where([
                    ['client_id', $req->client_id],
                    ['payment_status', 'processing']
                ])->orderBy('time', 'DESC')->paginate($paginate_data_length);
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
                ]);
            }
            $pagination = $order_data->toArray();

            $baseURL = config('url.pagination.is_live') === 'yes' ? config('url.pagination.live') : config('url.pagination.local');
            $pageURL = $baseURL . 'api/internal-func/cs/order/list?search=&page=' . $page;

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
                    "table" => $data
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
}
