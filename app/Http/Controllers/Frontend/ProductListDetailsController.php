<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ProductListDetailsController extends Controller
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
        $database_save_timer = Config::get('AppConfig.AppConfig.system.database_save_timer');
        set_time_limit($database_save_timer);
    }
    public function ProductListDetails(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $data_collection = collect();
            $search = $req->input('search');
            $page = $req->input('page') ?? 1;
            $modSearch = str_replace(' ', '_', $search);
            $key = $modSearch . "_P" . $page;
            if (!empty($search)) {
                if ((Cache::has($key))) {
                    $get_user_data  = Cache::get($key);
                    $this->status   = true;
                    $this->code     = 200;
                    $this->error    = "Verified";
                    $this->msg      = "Data served (Cached)";
                    $total_products = $get_user_data["total"];
                    $result         = $get_user_data["table"];
                } else {
                    $searchTerm = "%$search%";
                    $total_products = Product::where([
                        ['tag', 'LIKE', $searchTerm],
                        ['total_stock', '>', 0],
                        ['status', '=', "active"],
                    ])->count();
                    $product_details = Product::where([
                        ['tag', 'LIKE', $searchTerm],
                        ['total_stock', '>', 0],
                        ['status', '=', "active"],
                    ])
                        ->orderBy("created_at", "DESC")
                        ->paginate($paginate_data_length);

                    foreach ($product_details as $pd) {
                        $price     = $pd->price;
                        $old_price = $pd->old_price;
                        $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                        $data_collection->push([
                            'product_id'          => $pd->product_id,
                            'serial'              => $pd->serial,
                            'name'                => $pd->name,
                            'main_image'          => $pd->main_image ? json_decode($pd->main_image, true) : null,
                            'sub_image'           => $pd->sub_image ? json_decode($pd->sub_image, true) : [],
                            'quantity'            => $pd->quantity,
                            'price'               => $price,
                            'old_price'           => $old_price,
                            'offer_percentage'    => $offer_percentage,
                            'category'            => $pd->category,
                            'has_group_variation' => filter_var($pd->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                            'group'               => $pd->group ? json_decode($pd->group, true) : null,
                            'variation'           => $pd->variation ? json_decode($pd->variation, true) : null,
                            'total_stock'         => $pd->total_stock,
                            'seo'                 => $pd->seo ? json_decode($pd->seo, true) : null,
                            'tag'                 => $pd->tag ? json_decode($pd->tag, true) : null,
                            'description'         => $pd->description ? json_decode($pd->description, true) : null,
                            'links'               => $pd->links ? json_decode($pd->links, true) : null,
                            'rating'              => $pd->rating,
                            'status'              => $pd->status,
                            'created_at'          => $pd->created_at,
                            'updated_at'          => $pd->updated_at,
                        ]);
                    }
                    if (config('url.pagination.is_live') === 'yes') {
                        $baseURL = config('url.pagination.live');
                    } else {
                        $baseURL = config('url.pagination.local');
                    }
                    $pageURL    = $baseURL . 'api/v1/web/user/dashboard/list?page=';
                    $data       = $data_collection;
                    $pagination = $product_details->toArray();

                    $links = [];

                    // "Previous" link
                    $prevLink = [
                        'url'    => null,
                        'label'  => '&laquo; Previous',
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
                        $url    = $pageURL . $i;
                        $label  = (string) $i;
                        $active = ($i == $pagination['current_page']);

                        $link = [
                            'url'    => $url,
                            'label'  => $label,
                            'active' => $active
                        ];

                        $links[] = $link;
                    }

                    // "Next" link
                    $nextLink = [
                        'url'    => null,
                        'label'  => 'Next &raquo;',
                        'active' => false
                    ];
                    if ($pagination['current_page'] < $pagination['last_page']) {
                        $nextLink['url'] = $pageURL . ($pagination['current_page'] + 1);
                    }
                    $links[] = $nextLink;

                    $pagination['data']  = $data;
                    $pagination['links'] = $links;

                    unset($pagination['first_page_url']);
                    unset($pagination['last_page_url']);
                    unset($pagination['next_page_url']);
                    unset($pagination['prev_page_url']);
                    unset($pagination['path']);

                    $result = [
                        'current_page'  => $product_details->currentPage(),
                        'data'          => $data,
                        'first_page_url' => $product_details->url(1),
                        'from'          => $product_details->firstItem(),
                        'last_page'     => $product_details->lastPage(),
                        'last_page_url' => $product_details->url($product_details->lastPage()),
                        'links'         => $pagination['links'],
                        'next_page_url' => $product_details->nextPageUrl(),
                        'path'          => $product_details->url($product_details->currentPage()),
                        'per_page'      => $product_details->perPage(),
                        'prev_page_url' => $product_details->previousPageUrl(),
                        'to'            => $product_details->lastItem(),
                        'total'         => $product_details->total(),
                    ];
                    $body = [
                        'total' => $total_products,
                        'table' => $result,
                    ];

                    if ($data_collection->isNotEmpty()) {
                        $timezone        = config('app.timezone');
                        $currentDateTime = Carbon::now($timezone);
                        $newDateTime     = $currentDateTime->addMinutes(1);
                        Cache::put($key, $body, $newDateTime);
                    }


                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Data served";
                }
            } else {
                $this->status   = false;
                $this->code     = 400;
                $this->error    = "bad-request";
                $this->msg      = "Please provide a search key to retrieve data";
                $total_products = 0;
                $result = [];
            }
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
        } catch (Exception $e) {
            $this->status = false;
            $this->code   = 422;
            $this->error  = "Unprocessable";
            $this->msg    = $e->getMessage() . ' in line ' . $e->getLine();

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
            ], 200);
        }
    }

    public function ProductListDetailsFilter(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $paginate_data_length = config('AppConfig.AppConfig.system.paginate_data_length');
            $data_collection = collect();
            $search = $req->input('search');
            $lowest_price = $req->input('lowest_price');
            $height_price = $req->input('height_price');
            $page = $req->input('page') ?? 1;
            $key = $search . "_P" . $page;
            if (!empty($search)) {
                $searchTerm = "%$search%";
                $total_products = Product::where([
                    ['tag', 'LIKE', $searchTerm],
                    ['total_stock', '>', 0],
                    ['status', '=', "active"],
                ])->whereBetween('price', [$lowest_price, $height_price])->count();
                $product_details = Product::where([
                    ['tag', 'LIKE', $searchTerm],
                    ['total_stock', '>', 0],
                    ['status', '=', "active"],
                ])
                    ->whereBetween('price', [$lowest_price, $height_price])
                    ->orderBy("created_at", "DESC")
                    ->paginate($paginate_data_length);

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
                        'group' => $pd->group ? json_decode($pd->group, true) : null,
                        'variation' => $pd->variation ? json_decode($pd->variation, true) : null,
                        'total_stock' => $pd->total_stock,
                        'seo' => $pd->seo ? json_decode($pd->seo, true) : null,
                        'tag' => $pd->tag ? json_decode($pd->tag, true) : null,
                        'description' => $pd->description ? json_decode($pd->description, true) : '<!DOCTYPE html><html><head><title>Page Title</title></head><body><div style="background-color: red; text-align:center;color:white;font-size: 40px;padding:10px 0px;font-family: Arial, sans-serif;">Seller Did not provide any description</div></body></html>',
                        'links' => $pd->links ? json_decode($pd->links, true) : null,
                        'rating' => $pd->rating,
                        'status' => $pd->status,
                        'created_at' => $pd->created_at,
                        'updated_at' => $pd->updated_at,
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
                $body = [
                    'total' => $total_products,
                    'table' => $result,
                ];
                if ($data_collection->isNotEmpty()) {
                    $timezone = config('app.timezone');
                    $currentDateTime = Carbon::now($timezone);
                    $newDateTime = $currentDateTime->addHours(2);
                    Cache::put($key, $body, $newDateTime);
                }


                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Data served db";
            } else {
                $this->status = false;
                $this->code = 400;
                $this->error = "bad-request";
                $this->msg = "Please provide a search key to retrieve data";
                $total_products = 0;
                $result = [];
            }

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


    public function SearchSuggestion(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);

            $search = $req->input('search');

            if (empty($search)) {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'error' => 'bad-request',
                    'msg' => 'Please provide a search key to retrieve data',
                    'data' => [
                        'total' => 0,
                        'table' => [],
                    ]
                ], 400);
            }

            $modSearch = str_replace(' ', '_', $search);
            $key = $modSearch . "_seven";

            if (Cache::has($key)) {
                $cachedData = Cache::get($key);
                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'error' => 'Verified',
                    'msg' => 'Data served',
                    'data' => $cachedData,
                ], 200);
            }

            $searchTerm = "%$search%";
            $product_details = Product::where([
                ['tag', 'LIKE', $searchTerm],
                ['total_stock', '>', 0],
                ['status', '=', "active"],
            ])->orderBy("rating", "DESC")->take(7)->get();

            $data = $product_details->map(function ($product) {
                $price = $product->price;
                $old_price = $product->old_price;
                $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                return [
                    'product_id' => $product->product_id,
                    'serial' => $product->serial,
                    'name' => $product->name,
                    'main_image' => json_decode($product->main_image),
                    'sub_image' => json_decode($product->sub_image),
                    'quantity' => $product->quantity,
                    'price' => $price,
                    'old_price' => $old_price,
                    'offer_percentage' => $offer_percentage,
                    'category' => $product->category,
                    'has_group_variation' => filter_var($product->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                    'group' => $product->group ? json_decode($product->group) : null,
                    'variation' => $product->variation ? json_decode($product->variation) : null,
                    'total_stock' => $product->total_stock,
                    'seo' => json_decode($product->seo),
                    'tag' => json_decode($product->tag),
                    'description' => json_decode($product->description),
                    'links' => json_decode($product->links),
                    'rating' => $product->rating,
                    'status' => $product->status,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            $result = [
                'total' => $product_details->count(),
                'data' => $data->toArray(),
            ];

            $body = [
                'total' => $product_details->count(),
                'table' => $result,
            ];

            $cacheExpiry = Carbon::now()->addMinutes(1);
            Cache::put($key, $body, $cacheExpiry);

            return response()->json([
                'status' => true,
                'code' => 200,
                'error' => 'Verified',
                'msg' => 'Data served',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $e->getMessage() . ' in line ' . $e->getLine(),
            ], 422);
        }
    }

    public function ProductDetails(Request $req)
    {
        try {
            $product_id = $req->product_id;
            $key = "cached_details" . $product_id;
            if (empty($product_id)) {
                $this->status = false;
                $this->code = 400;
                $this->error = "bad-resquest";
                $this->msg = "Product Id required";
                $data = [];
            } else {
                if (Cache::has($key)) {
                    $cachedData = Cache::get($key);
                    return response()->json([
                        'status' => true,
                        'code' => 200,
                        'error' => 'Verified',
                        'msg' => 'Product found (cashed)',
                        'data' => $cachedData,
                    ], 200);
                } else {
                    $product_details = Product::where('product_id', $product_id)->first();

                    if ($product_details) {
                        $this->status = true;
                        $this->code = 200;
                        $this->error = "Verified";
                        $this->msg = "Product found";

                        $price = $product_details->price;
                        $old_price = $product_details->old_price;
                        $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                        $data = [
                            'product_id' => $product_details->product_id,
                            'serial' => $product_details->serial,
                            'name' => $product_details->name,
                            'main_image' => json_decode($product_details->main_image),
                            'sub_image' => json_decode($product_details->sub_image),
                            'quantity' => $product_details->quantity,
                            'price' => $price,
                            'old_price' => $old_price,
                            'offer_percentage' => $offer_percentage,
                            'old_price' => $product_details->old_price,
                            'category' => $product_details->category,
                            'has_group_variation' => filter_var($product_details->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                            'group' => $product_details->group ? json_decode($product_details->group) : null,
                            'variation' => $product_details->variation ? json_decode($product_details->variation) : null,
                            'total_stock' => $product_details->total_stock,
                            'seo' => json_decode($product_details->seo),
                            'tag' => json_decode($product_details->tag),
                            'description' => json_decode($product_details->description),
                            'links' => json_decode($product_details->links),
                            'rating' => $product_details->rating,
                            'status' => $product_details->status,
                            'created_at' => $product_details->created_at,
                            'updated_at' => $product_details->updated_at,
                        ];
                        $cacheExpiry = Carbon::now()->addMinutes(1);
                        Cache::put($key, $data, $cacheExpiry);
                    } else {
                        $this->status = true;
                        $this->code = 200;
                        $this->error = "Verified";
                        $this->msg = "Product not found";
                        $data = [];
                    }
                }
            }

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => $data
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
