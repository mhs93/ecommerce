<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AddtoCart;
use Exception;
use Illuminate\Http\Request;

class AddtoCartController extends Controller
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

    public function CartAdd(Request $req)
    {
        try {
            $data = $req->all();

            $client_id = $data['client_details']['client_id'];

            if (!isset($data['purchase'])) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'Bad Request',
                    'msg'    => 'The purchase key is missing in the request data.',
                ], 400);
            }

            $client_cart_data = $data['purchase'];
            $total_cart = count($data['purchase']['with_variation'] ?? []) + count($data['purchase']['without_variation'] ?? []);
            $client_details = AddtoCart::where('client_id', $client_id)->first();

            if ($client_details !== null) {
                $cart_data = json_decode($client_details->cart, true);
                $server_cart_count = $total_cart + count($cart_data['purchase']['without_variation'] ?? []);

                if ($total_cart <= 25) {
                    $sub_total = $total_cart + $server_cart_count;
                    if ($sub_total <= 25) {
                        $client_details->cart = json_encode($client_cart_data);
                        $client_details->save();

                        return response()->json([
                            'status' => true,
                            'code'   => 200,
                            'error'  => 'verified',
                            'msg'    => 'cart updated successfully',
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => false,
                            'code'   => 200,
                            'error'  => 'bad request',
                            'msg'    => 'total cart must be less than 25. Please buy the previous product first',
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'code'   => 200,
                        'error'  => 'bad request',
                        'msg'    => 'already 25 products added to cart',
                    ], 200);
                }
            } else {
                $addToCart = new AddtoCart();
                $addToCart->client_id = $client_id;
                $addToCart->cart =  json_encode($client_cart_data);
                $addToCart->save();

                return response()->json([
                    'status' => true,
                    'code'   => 200,
                    'error'  => 'verified',
                    'msg'    => 'cart added successfully',
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'code'   => 500,
                'error'  => 'Internal Server Error',
                'msg'    => $e->getMessage() . ' in line ' . $e->getLine(),
            ], 500);
        }
    }
}
