<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteController extends Controller
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
    public function CategoriesSidebar()
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $key = 'category_data';
            if (Cache::has($key)) {
                $data = Cache::get($key);
                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Category and Subcategory returned from cache";
            } else {
                $category_collection = collect();
                $sub_category_collection = collect();
                $categories = Category::select('name', 'slug')->get();
                foreach ($categories as $cat) {
                    $img = Product::where('category', $cat->name)->take(10)->pluck('main_image')->first();
                    $categoryData = [
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'img' => $img ? json_decode($img) : null,
                        'sub_category' => [],
                    ];

                    $sub_category_on_sub_category = Subcategory::where('category_slug', '=', $cat->slug)->get();
                    foreach ($sub_category_on_sub_category as $subCat) {
                        $subCatImg = Product::where('sub_category', $subCat->name)->inRandomOrder()->take(10)->pluck('main_image')->first();
                        $categoryData['sub_category'][] = [
                            'name' => $subCat->name,
                            'img' => $subCatImg ? json_decode($subCatImg) : null,
                            'slug' => $subCat->slug,
                        ];
                        $sub_category_collection->push([
                            'category' => $cat->name,
                            'slug' => $cat->slug,
                            'img' => $img ? json_decode($img) : null,
                            'sub-category' => [
                                'name' => $subCat->name,
                                'img' => $img ? json_decode($img) : null,
                                'slug' => $subCat->slug,
                            ]
                        ]);
                    }
                    $category_collection->push($categoryData);
                }
                $data = [
                    'category' => $category_collection,
                    'sub_category' => $sub_category_collection,
                ];
                $timezone = config('app.timezone');
                $currentDateTime = Carbon::now($timezone);
                $newDateTime = $currentDateTime->addHours(4);
                Cache::put($key, $data, $newDateTime);

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Category and Subcategory returned from DB";
            }
            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
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

    public function HomePageProductList(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $search = $req->input('search');
            $searchTerm = "%$search%";
            $paginate_data_length = config('AppConfig.AppConfig.system.homepage_product_list');
            $result_collection = collect();
            $modSearch = str_replace(' ', '_', $search);

            if (!empty($search)) {
                if (Cache::has($modSearch)) {
                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = "Data served";
                    $result = Cache::get($modSearch);
                } else {
                    $product_details = Product::where([
                        ['tag', 'LIKE', $searchTerm],
                        ['total_stock', '>', 0],
                        ['status', '=', "active"],
                    ])
                        ->orderBy("created_at", "DESC")
                        ->take($paginate_data_length)
                        ->get();

                    foreach ($product_details as $pd) {
                        $price     = $pd->price;
                        $old_price = $pd->old_price;
                        $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                        $result_collection->push([
                            'product_id' => $pd->product_id,
                            'serial' => $pd->serial,
                            'name' => $pd->name,
                            'main_image' => $pd->main_image ? json_decode($pd->main_image, true) : null,
                            'sub_image' => $pd->sub_image ? json_decode($pd->sub_image, true) : [],
                            'quantity' => $pd->quantity,
                            'price'       => $price,
                            'old_price'   => $old_price,
                            'offer_percentage' => $offer_percentage,
                            'category' => $pd->category,
                            'has_group_variation' => filter_var($pd->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                            'group' => $pd->group ? json_decode($pd->group, true) : null,
                            'variation' => $pd->variation ? json_decode($pd->variation, true) : null,
                            'total_stock' => $pd->total_stock,
                            'seo' => $pd->seo ? json_decode($pd->seo, true) : null,
                            'tag' => $pd->tag ? json_decode($pd->tag, true) : null,
                            'description' => $pd->description ? json_decode($pd->description, true) : null,
                            'links' => $pd->links ? json_decode($pd->links, true) : null,
                            'rating' => $pd->rating,
                            'status' => $pd->status,
                        ]);
                    }

                    if ($product_details->isNotEmpty()) {
                        $timezone = config('app.timezone');
                        $currentDateTime = Carbon::now($timezone);
                        $newDateTime = $currentDateTime->addHours(2);
                        Cache::put($modSearch, $result_collection, $newDateTime);
                    }

                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = "Data served";
                    $result = $result_collection;
                }
            } else {
                $this->status = false;
                $this->code = 400;
                $this->error = "bad-request";
                $this->msg = "Please provide a search key to retrieve data";
                $result = [];
            }

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => [
                    'data' => $result,
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

    public function GetCity(Request $req)
    {
        try {
            $division = $req->input('division');
            $key = 'getCity' . $division;

            if (Cache::has($key)) {
                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Data served from cache";
                $data = Cache::get($key);
            } else {
                $header = [
                    'authority' => 'www.pickaboo.com',
                    'Accept' => 'application/json, text/plain, */*',
                    'Referer' => 'https://www.pickaboo.com/address/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                ];
                $url = "https://www.pickaboo.com/rest/default/V1/dcastalia-address/getcity?param=" . $division;
                $getRes = Http::withHeaders($header)->get($url);
                $data =  json_decode($getRes->getBody(), true);

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Data served";

                $cacheExpiry = Carbon::now()->addHours(12);
                Cache::put($key, $data, $cacheExpiry);
            }

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => [
                    'data' => $data,
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

    public function GetArea(Request $req)
    {
        try {
            $city = $req->input('city');
            $key = 'getArea' . $city;

            if (Cache::has($key)) {
                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Data served from cache";
                $data = Cache::get($key);
            } else {
                $header = [
                    'authority' => 'www.pickaboo.com',
                    'Accept' => 'application/json, text/plain, */*',
                    'Referer' => 'https://www.pickaboo.com/address/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                ];
                $url = "https://www.pickaboo.com/rest/default/V1/dcastalia-address/getarea?param=" . $city;
                $getRes = Http::withHeaders($header)->get($url);
                $data =  json_decode($getRes->getBody(), true);
                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Data served";

                $cacheExpiry = Carbon::now()->addHours(12);
                Cache::put($key, $data, $cacheExpiry);
            }

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data'   => [
                    'data' => $data,
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
