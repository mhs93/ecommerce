<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WishList;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;

class WishListController extends Controller
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
        $this->systemConfig =  app('system_config');
    }

    public function AddtoWishList(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'client_id'    => 'required',
                'product_id'    => 'required'
            ], [
                'client_id.required'    => 'client id is required.',
                'product_id.required'    => 'product id is required.'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            }

            $product_id = $req->product_id;
            $client_id = $req->client_id;

            $product_exists = WishList::where(['product_id' => $product_id, 'client_id' => $client_id])->exists();
            if ($product_exists == true) {
                return response()->json([
                    'status' => true,
                    'code'   => 200,
                    'error'  => 'unprocessable',
                    'msg'    => 'product already added to wish list',
                ], 200);
            }

            $wishList = new WishList();
            $wishList->client_id = $client_id;
            $wishList->product_id = $product_id;
            $wishList->save();
            return response()->json([
                'status' => true,
                'code'   => 200,
                'error'  => "Verified",
                'msg'    => "product added to the wishlist successfully",
                'data' => [
                    '[product_id]' => $product_id,
                    '[client_id]' => $client_id,
                ]
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

    public function Wishlist(Request $req)
    {
        try {
            $client_id = $req->client_id;
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $products = WishList::where('client_id', $client_id)
                ->join('products', 'wish_lists.product_id', '=', 'products.product_id')
                ->where('products.status', 'active')
                ->where('products.quantity', '>', 0)
                ->select('wish_lists.product_id')
                ->distinct()
                ->paginate($paginate_data_length);
            $data_collection = collect();
            foreach ($products as $product) {
                $product_details = Product::where('product_id', $product->product_id)->first();
                $data_collection->push([
                    'product_id' => $product_details->product_id,
                    'serial' => $product_details->serial,
                    'name' => $product_details->name,
                    'main_image' => $product_details->main_image ? json_decode($product_details->main_image, true) : null,
                    'sub_image' => $product_details->sub_image ? json_decode($product_details->sub_image, true) : [],
                    'quantity' => $product_details->quantity,
                    'price' => $product_details->price,
                    'category' => $product_details->category,
                    'sub_category' => $product_details->sub_category,
                    'has_group_variation' => $product_details->has_group_variation,
                    'group' => $product_details->group ? json_decode($product_details->group, true) : null,
                    'variation' => $product_details->variation ? json_decode($product_details->variation, true) : null,
                    'total_stock' => $product_details->total_stock,
                    'seo' => $product_details->seo ? json_decode($product_details->seo, true) : null,
                    'tag' => $product_details->tag ? json_decode($product_details->tag, true) : null,
                    'description' => $product_details->description ? json_decode($product_details->description, true) : null,
                    'links' => $product_details->links ? json_decode($product_details->links, true) : null,
                    'rating' => $product_details->rating,
                    'inserted_by' => $product_details->inserted_by,
                    'status' => $product_details->status,
                ]);
            }
            if (config('url.pagination.is_live') === 'yes') {
                $baseURL = config('url.pagination.live');
            } else {
                $baseURL = config('url.pagination.local');
            }
            $pageURL = $baseURL . 'api/v1/web/user/dashboard/list?page=';

            $data = $data_collection;
            $pagination = $products->toArray();

            $links = [];

            $prevLink = [
                'url' => null,
                'label' => '&laquo; Previous',
                'active' => false
            ];
            if ($pagination['current_page'] > 1) {
                $prevLink['url'] = $pageURL . ($pagination['current_page'] - 1);
            }
            $links[] = $prevLink;

            for (
                $i = 1;
                $i <= $pagination['last_page'];
                $i++
            ) {
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
                'url' => null,
                'label' => 'Next &raquo;',
                'active' => false
            ];
            if ($pagination['current_page'] < $pagination['last_page']) {
                $nextLink['url'] = $pageURL . ($pagination['current_page'] + 1);
            }
            $links[] = $nextLink;

            $pagination['data'] = $data;
            $pagination['links'] = $links;

            unset($pagination['first_page_url']);
            unset($pagination['last_page_url']);
            unset($pagination['next_page_url']);
            unset($pagination['prev_page_url']);
            unset($pagination['path']);

            $result = [
                'current_page' => $products->currentPage(),
                'data' => $data,
                'first_page_url' => $products->url(1),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'last_page_url' => $products->url($products->lastPage()),
                'links' => $pagination['links'],
                'next_page_url' => $products->nextPageUrl(),
                'path' => $products->url($products->currentPage()),
                'per_page' => $products->perPage(),
                'prev_page_url' => $products->previousPageUrl(),
                'to' => $products->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'code'   => 200,
                'error'  => "verified",
                'msg'    => "data served",
                'data' => [
                    'table' => $result,
                ]
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
}
