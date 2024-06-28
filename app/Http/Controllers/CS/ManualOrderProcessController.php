<?php

namespace App\Http\Controllers\CS;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class ManualOrderProcessController extends Controller
{
    protected $status;
    protected $code;
    protected $error;
    protected $msg;
    protected $systemConfig;

    public function __construct(){
        $this->status = true;
        $this->code   = 200;
        $this->error  = null;
        $this->msg    = null;
    }
    public function GetProductList(Request $req){
        try{
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $data_collection = collect();
            $search = $req->input('search');
            $page = $req->input('page') ?? 1;
            if (!empty($search)) {
                $searchTerm = "%$search%";
                $total_products = Product::where('tag', 'LIKE', $searchTerm)->count();
                $product_details = Product::where('tag', 'LIKE', $searchTerm)
                    ->orderBy("created_at", "DESC")
                    ->paginate($paginate_data_length);
            } else {
                $total_products = Product::count();
                $product_details = Product::orderBy("created_at", "DESC")->paginate($paginate_data_length);
            }
            foreach ($product_details as $pd) {
                $price = $pd->price;
                $old_price = $pd->old_price;
                $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                $data_collection->push([
                    'product_id' => $pd->product_id,
                    'serial' => $pd->serial,
                    'name' => $pd->name,
                    'main_image' => $pd->main_image ? json_decode($pd->main_image, true) : null,
                    'sub_image' => $pd->sub_image ? json_decode($pd->sub_image, true) : [],
                    'quantity' => $pd->quantity,
                    'price' => $price,
                    'old_price' => $old_price,
                    'offer_percentage' => $offer_percentage,
                    'category' => $pd->category,
                    'has_group_variation' => filter_var($pd->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                    'group' => $pd->group?json_decode($pd->group,true):null,
                    'variation' => $pd->variation?json_decode($pd->variation,true):null,
                    'total_stock' => $pd->total_stock,
                    'seo' => $pd->seo ? json_decode($pd->seo, true) : null,
                    'tag' => $pd->tag ? json_decode($pd->tag, true) : null,
                    'description' => $pd->description ? json_decode($pd->description, true) : null,
                    'links' => $pd->links ? json_decode($pd->links, true) : null,
                    'inserted_by' => $pd->inserted_by,
                    'status' => $pd->status,
                    'created_at' => $pd->created_at?Carbon::parse($pd->created_at):null,
                    'updated_at' => $pd->updated_at?Carbon::parse($pd->updated_at):null,
                ]);
            }
            if (config('url.pagination.is_live') === 'yes') {
                $baseURL = config('url.pagination.live');
            } else {
                $baseURL = config('url.pagination.local');
            }
            $pageURL = $baseURL . 'api/v1/web/user/dashboard/list?page=';

            $data = $data_collection;
            $pagination = $product_details->toArray();

            $links = [];

            // "Previous" link
            $prevLink = [
                'url' => null,
                'label' => '&laquo; Previous',
                'active' => false
            ];
            if ($pagination['current_page'] > 1) {
                $prevLink['url'] = $pageURL . ($pagination['current_page'] - 1);
            }
            $links[] = $prevLink;

            //  numbered page links
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

            // "Next" link
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
                'current_page' => $product_details->currentPage(),
                'data' => $data,
                'first_page_url' => $product_details->url(1),
                'from' => $product_details->firstItem(),
                'last_page' => $product_details->lastPage(),
                'last_page_url' => $product_details->url($product_details->lastPage()),
                'links' => $pagination['links'],
                'next_page_url' => $product_details->nextPageUrl(),
                'path' => $product_details->url($product_details->currentPage()),
                'per_page' => $product_details->perPage(),
                'prev_page_url' => $product_details->previousPageUrl(),
                'to' => $product_details->lastItem(),
                'total' => $product_details->total(),
            ];

            $this->status = true;
            $this->code = 200;
            $this->error = "Verified";
            $this->msg = "Data served";
            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => [
                    'total' => $total_products,
                    'table' => $result,
                ]
            ], 200);
        }catch (Exception $e) {
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
