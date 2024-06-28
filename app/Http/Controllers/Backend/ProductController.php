<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPurchaseByUser;
use App\Models\Purchase_Cycle\TrxToken;
use App\Models\Subcategory;
use Carbon\Carbon;
use Exception;
use Haruncpi\LaravelIdGenerator\IdGenerator as IDGen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
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

    public function ProductStore(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $validator = Validator::make($req->all(), [
                'name'          => 'required|string',
                'price'         => 'required|numeric',
                'category'      => 'required|string',
                'sub_category'  => 'required',
                'tag'           => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $name = $req->name;
                $price = $req->price;
                $prefix =  str_replace(' ', '_', config('System.SystemConfig.system.app_primary_info.app_name'));
                $serial = IDGen::generate(['table' => 'products', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                $product_id = str_replace(' ', '_', $prefix) . '-' . hexdec(uniqid()) . $serial;

                $category_name = Str::lower($req->category);
                $string_mod = preg_replace('/\s+/', ' ', $category_name);
                $category_slug =  Str::replace(' ', '-', $string_mod);

                $validate_category_existance = Category::where('slug', '=', $category_slug)->exists();
                if ($validate_category_existance === false) {
                    $new_category = new Category();
                    $new_category->name = $string_mod;
                    $new_category->slug = $category_slug;
                    $new_category->save();
                }

                $sub_category_name = Str::lower($req->sub_category);
                $string_mod_sub = preg_replace('/\s+/', ' ', $sub_category_name);
                $sub_category_slug =  Str::replace(' ', '-', $string_mod_sub);

                $validate_sub_category_existance = Subcategory::where([
                    ['slug', '=', $sub_category_slug],
                    ['category_slug', '=', $category_slug]
                ])->exists();

                if ($validate_sub_category_existance === false) {
                    $new_sub_category = new Subcategory();
                    $new_sub_category->name = $string_mod_sub;
                    $new_sub_category->slug = $sub_category_slug;
                    $new_sub_category->category_slug = $category_slug;
                    $new_sub_category->save();
                }

                $stingify_seo = $req->has('seo') ? json_encode($req->input('seo')) : null;
                $stingify_description = $req->has('description') ? json_encode($req->input('description')) : '<!DOCTYPE html><html><head><title>Page Title</title></head><body><div style="background-color: red; text-align:center;color:white;font-size: 40px;padding:10px 0px;font-family: Arial, sans-serif;">Seller Did not provide any description</div></body></html>';
                $stingify_links = $req->has('links') ? json_encode($req->input('links')) : null;


                $tag_name = Str::lower($name);
                $string_mod_name = preg_replace('/\s+/', ' ', $tag_name);

                $tagArray = [];
                array_push($tagArray, $string_mod_name, $string_mod, $string_mod_sub);
                if ($req->has('tag')) {
                    $tagData = $req->input('tag');
                    $tagArray = array_merge($tagArray, $tagData);
                }
                $stingify_tag = json_encode($tagArray);
                $inserted_by =  $req->attributes->get('employee_email');

                $product = new Product();
                $product->product_id = $product_id;
                $product->serial = $serial;
                $product->name = $name;
                $product->price = $price;
                $product->category = $string_mod;
                $product->sub_category = $string_mod_sub;
                $product->seo = $stingify_seo;
                $product->tag = $stingify_tag;
                $product->description = $stingify_description;
                $product->links = $stingify_links;
                $product->inserted_by = $inserted_by;
                $product->save();

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Product uploaded successfully";

                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                    'data' => [
                        'product_id' => $product->product_id,
                    ]
                ], 200);
            }
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

    public function ImageStore(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $validator = Validator::make($req->all(), [
                'product_id'     => 'required',
                'path'           => 'required',
                'url'            => 'required',
                'store_location' => ['required', 'string'],
            ], [
                'product_id.required'       => 'Product id required.',
                'path.required'             => 'The path field is required.',
                'url.required'              => 'The url field is required.',
                'store_location.required'   => 'The store location field is required.',
                'store_location.string'     => 'This store location should be string.',

            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $product_id = $req->input('product_id');
                $path = $req->input('path');
                $url = json_encode($req->input('url'));
                $store_location = $req->input('store_location');
                if ($store_location === "product") {
                    if ($path === 'main_img') {
                        Product::where(
                            'product_id',
                            '=',
                            $product_id
                        )->update([
                            'main_image' => $url,
                            'updated_at' => Carbon::now(),
                        ]);
                    } elseif ($path === 'sub_img') {
                        Product::where(
                            'product_id',
                            '=',
                            $product_id
                        )->update([
                            'sub_image' => $url,
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = "Images saved on assigned product";
                } else {
                    $this->status = true;
                    $this->code = 501;
                    $this->error = "Not-implemented";
                    $this->msg = "Could not save the image link";
                }


                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                    'data' => [
                        'product_id' => $product_id,
                    ]
                ], 200);
            }
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

    public function ProductDelete(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $validator = Validator::make($req->all(), [
                'product_id'     => 'required'
            ], [
                'product_id.required'       => 'Product id required.'

            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $product_id = $req->input('product_id');
                $product_array = [];
                $product = Product::where('product_id', '=', $product_id)->first();
                if ($product) {
                    if (!empty($product->main_image) && !empty($product->sub_image)) {
                        $main_image = json_decode($product->main_image, true);
                        $sub_image = json_decode($product->sub_image, true);
                        $group = json_decode($product->group, true);
                        if (!in_array($main_image, $product_array)) {
                            $product_array[] = $main_image;
                        }
                        for ($i = 0; $i < count($sub_image); $i++) {
                            if (!in_array($sub_image[$i], $product_array)) {
                                $product_array[] = $sub_image[$i];
                            }
                        }
                        if (filter_var($product->has_group_variation, FILTER_VALIDATE_BOOLEAN)) {
                            foreach ($group as $key => $gp) {
                                if ($gp['has_img'] === true  && !in_array($gp['img'], $product_array)) {
                                    $product_array[] = $gp['img'];
                                }
                            }
                        }

                        $img = $product_array;
                    } else {
                        $img = [];
                    }

                    $del_rec = $product->delete();
                    if ($del_rec) {

                        $msg = "Product deleted successfully. Now deleting cdn images";
                    } else {

                        $msg = "Could not delete the product";
                    }

                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = $msg;
                } else {
                    $img = [];
                    $msg = "Product not found";
                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = $msg;
                }
                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                    'img' => $img
                ], 200);
            }
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

    public function ProductDetails(Request $req)
    {
        try {
            $database_save_timer = Config::get('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $product_id = $req->product_id;
            if (empty($product_id)) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = "bad-resquest";
                $this->msg    = "Product Id required";
                $data = [];
            } else {
                $product_details = Product::where('product_id', $product_id)->first();

                if ($product_details) {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Product found";
                    $data = [
                        'serial'      => $product_details->serial,
                        'name'        => $product_details->name,
                        'main_image'  => $product_details->main_image ? json_decode($product_details->main_image, true) : null,
                        'sub_image'   => $product_details->sub_image ? json_decode($product_details->sub_image, true) : [],
                        'quantity'    => $product_details->quantity,
                        'price'       => $product_details->price,
                        'category'    => $product_details->category,
                        'has_group_variation' => filter_var($product_details->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                        'group'       => $product_details->group ? json_decode($product_details->group, true) : null,
                        'variation'   => $product_details->variation ? json_decode($product_details->variation, true) : null,
                        'total_stock' => $product_details->total_stock,
                        'seo'         => $product_details->seo ? json_decode($product_details->seo, true) : null,
                        'tag'         => $product_details->tag ? json_decode($product_details->tag, true) : null,
                        'description' => $product_details->description ? json_decode($product_details->description, true) : '<!DOCTYPE html><html><head><title>Page Title</title></head><body><div style="background-color: red; text-align:center;color:white;font-size: 40px;padding:10px 0px;font-family: Arial, sans-serif;">Seller Did not provide any description</div></body></html>',
                        'links'       => $product_details->links ? json_decode($product_details->links, true) : null,
                        'inserted_by' => $product_details->inserted_by,
                        'status'      => $product_details->status,
                    ];
                } else {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Product not found";
                    $data = [];
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

    public function ProductStoreWithNoGroupVariation(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $validator = Validator::make($req->all(), [
                'product_id' => "required",
                'quantity'   => "required",
            ], [
                'product_id.required' => 'Product id required.',
                'quantity.required'   => 'The quantity field is required.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $product_id = $req->input('product_id');
                $quantity  = intval($req->input('quantity'));
                $updateQty = Product::where(
                    'product_id',
                    '=',
                    $product_id
                )->update([
                    'quantity'    => $quantity,
                    'total_stock' => $quantity,
                    'status'      => 'active',
                    'updated_at'  => Carbon::now(),
                ]);
                if ($updateQty) {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Quantity updated";
                } else {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Could not update product quantity";
                }

                if (Cache::has('category_data')) {
                    Cache::forget('category_data');
                }

                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                ], 200);
            }
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

    public function ProductStoreWithGroupVariation(Request $req)
    {
        try {
            $database_save_timer = config('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $validator = Validator::make($req->all(), [
                'product_id' => "required",
                'quantity'   => "required",
                'group'      => "required",
                'variation'  => "required",
            ], [
                'product_id.required' => 'Product id is required.',
                'quantity.required'   => 'quantity field is required.',
                'group.required'      => 'group field is required.',
                'variation.required'  => 'Variation field is required.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $product_id = $req->input('product_id');
                $quantity   = intval($req->input('quantity'));
                $group = $req->input('group');
                // $group = $req->has('group') ? json_decode($req->input('group'), true) : null;
                $variation  = $req->input('variation');

                if (isset($variation['old_price'])) {
                    $old_price = $variation['old_price'];
                } else {

                    $existingProduct = Product::where('product_id', '=', $product_id)->first();
                    $old_price = $existingProduct->old_price;
                }

                $updateGroupVariation = Product::where('product_id', '=', $product_id)->update([
                    'quantity'   => $quantity,
                    'has_group_variation' => "true",
                    'group'      => isset($group) ? json_encode($group) : null,
                    'variation'  => isset($variation) ? json_encode($variation) : null,
                    'total_stock' => $quantity,
                    'status'     => 'active',
                    'updated_at' => Carbon::now(),
                    'old_price'  => $old_price,
                ]);

                if ($updateGroupVariation) {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Product details updated";
                } else {
                    $this->status = false;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Could not update product quantity";
                }

                if (Cache::has('category_data')) {
                    Cache::forget('category_data');
                }

                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                ], 200);
            }
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

    public function ProductWithDetails(Request $req)
    {
        try {
            $database_save_timer = Config::get('AppConfig.AppConfig.system.database_save_timer');
            set_time_limit($database_save_timer);
            $product_id = $req->product_id;
            if (empty($product_id)) {
                $this->status = false;
                $this->code   = 400;
                $this->error  = "bad-resquest";
                $this->msg    = "Product Id required";
                $data = [];
            } else {
                $product_details = Product::where('product_id', $product_id)->first();

                if ($product_details) {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Product found";

                    $price     = $product_details->price;
                    $old_price = $product_details->old_price;
                    $offer_percentage = $old_price > 0 ? floor((($old_price - $price) * 100) / $old_price) : 0;
                    $data = [
                        'product_id'  => $product_id,
                        'serial'      => $product_details->serial,
                        'name'        => $product_details->name,
                        'main_image'  => $product_details->main_image ? json_decode($product_details->main_image, true) : null,
                        'sub_image'   => $product_details->sub_image ? json_decode($product_details->sub_image, true) : [],
                        'quantity'    => $product_details->quantity,
                        'price'       => $price,
                        'old_price'   => $old_price,
                        'offer_percentage' => $offer_percentage,
                        'category'    => $product_details->category,
                        'subcategory' => $product_details->sub_category,
                        'has_group_variation' => filter_var($product_details->has_group_variation, FILTER_VALIDATE_BOOLEAN),
                        'group'       => $product_details->group ? json_decode($product_details->group, true) : null,
                        'variation'   => $product_details->variation ? json_decode($product_details->variation, true) : null,
                        'total_stock' => $product_details->total_stock,
                        'seo'         => $product_details->seo ? json_decode($product_details->seo, true) : null,
                        'tag'         => $product_details->tag ? json_decode($product_details->tag, true) : null,
                        'description' => $product_details->description ? json_decode($product_details->description, true) : '<!DOCTYPE html><html><head><title>Page Title</title></head><body><div style="background-color: red; text-align:center;color:white;font-size: 40px;padding:10px 0px;font-family: Arial, sans-serif;">Seller Did not provide any description</div></body></html>',
                        'links'       => $product_details->links ? json_decode($product_details->links, true) : null,
                        'inserted_by' => $product_details->inserted_by,
                        'status'      => $product_details->status,
                    ];
                } else {
                    $this->status = true;
                    $this->code   = 200;
                    $this->error  = "Verified";
                    $this->msg    = "Product not found";
                    $data = [];
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

    public function ProductUpdate(Request $req)
    {
        try {
            $body = $req->all();
            $product_id = $body['product_id'];
            $name = $body['name'];
            $total_stock = $body['total_stock'];
            $quantity = intval($body['quantity']);
            $group = isset($body['group']) ? json_encode($body['group']) : null;
            $price = json_encode($body['price']);
            $category = $body['category'];
            $sub_category = $body['subcategory'];
            $variation = isset($body['variation']) ? json_encode($body['variation']) : NULL;
            $has_group_variation = json_encode($body['has_group_variation']);
            $seo = $req->has('seo') ? json_encode($req->input('seo')) : null;
            $tag = $req->has('tag') ? json_encode($req->input('tag')) : null;
            $description = isset($body['description']) ? json_encode($body['description']) : null;
            $old_price = isset($body['old_price']) ? $body['old_price'] : 0;
            $status = $body['status'];
            $inserted_by = $body['inserted_by'];

            if (empty($product_id)) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'Bad Request',
                    'msg'    => 'Product Id required'
                ], 200);
            } else {

                $updateProduct = Product::where('product_id', $product_id)->update([
                    'name'        => $name,
                    'quantity'    => $quantity,
                    'price'       => $price,
                    'has_group_variation' => $has_group_variation,
                    'seo'         => $seo,
                    'tag'         => $tag,
                    'description' => $description,
                    'group'       => $group,
                    'variation'   => $variation,
                    'old_price'   => $old_price,
                    'total_stock' => $total_stock,
                    'status'      => $status,
                    'updated_at'  => now(),
                    'inserted_by' => $inserted_by,
                ]);

                if ($updateProduct) {

                    Cache::forget($name);
                    Cache::forget($category);
                    Cache::forget($sub_category);
                    $modSearch = str_replace(' ', '_', $name);
                    $key = $modSearch . "_P" . 1;
                    Cache::forget($key);

                    $decoded_tag = json_decode($tag);
                    for ($i = 0; $i < count($decoded_tag); $i++) {
                        $modSearch = str_replace(' ', '_', $decoded_tag[$i]);
                        $key = $modSearch . "_P" . 1;
                        Cache::forget($key);
                    }

                    return response()->json([
                        'status' => true,
                        'code'   => 200,
                        'error'  => 'Verified',
                        'msg'    => 'Product updated successfully',

                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'code'   => 400,
                        'error'  => 'Bad Request',
                        'msg'    => 'Failed to update product',
                    ], 400);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'code'   => 422,
                'error'  => 'Unprocessable',
                'msg'    => $e->getMessage() . ' in line ' . $e->getLine(),
            ], 422);
        }
    }

    public function ProductOfflinePurchase(Request $req)
    {
        try {
            $body = $req->all();
            $product_id = $body['product_id'];
            $trx_id = $body['trx_id'];
            $product_quantity = $body['quantity'];
            $total_stock = $body['total_stock'];
            $variation = isset($body['variation']) ? json_encode($body['variation']) : null;
            $inserted_by = $body['inserted_by'];

            $product = DB::table('products')->where('product_id', $product_id)->first();
            $product_name = $product->name;
            $main_image = $product->main_image;
            $price = $product->price;
            $product_details = [
                'name' => $product_name,
                'main_image' => $main_image,
                'price' => $price
            ];
            $product_primary_details = json_encode($product_details);


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


            $updateProduct = Product::where('product_id', $product_id)->update([
                'quantity' => $product_quantity,
                'total_stock' => $total_stock,
                'updated_at' => now(),
                'inserted_by' => $inserted_by,
            ]);
            if ($updateProduct) {
                $purchase = new ProductPurchaseByUser();
                $purchase->trx_id = $trx_id;
                $purchase->product_primary_details = $product_primary_details;
                $purchase->product_variations = $variation;
                $purchase->save();
                if ($purchase->id) {
                    return response()->json([
                        'status' => true,
                        'code' => 200,
                        'error' => 'Verified',
                        'msg' => 'Offline purchase successfully generated',

                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'code' => 200,
                        'error' => 'Verified',
                        'msg' => 'Offline purchase failed',
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'error' => 'Bad Request',
                    'msg' => 'Failed to update product',
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'error' => 'Unprocessable',
                'msg' => $e->getMessage() . ' in line ' . $e->getLine(),
            ], 422);
        }
    }



    public function Info()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows system
            $ramInfo = shell_exec('systeminfo | findstr /C:"Total Physical Memory"');
            $cpuInfo = shell_exec('wmic cpu get Name,NumberOfCores,NumberOfLogicalProcessors');
            $storageInfo = shell_exec('wmic logicaldisk get Size,FreeSpace,FileSystem,DeviceID,VolumeName');

            // Windows does not provide built-in command to get RAM usage, CPU usage, and storage usage directly.
            // You may need to use third-party tools or PowerShell scripts to get this information.
            $ramUsage = $cpuUsage = $storageUsage = "Not available on Windows";
        } else {
            // Linux system
            $ramInfo = shell_exec('free -m');
            $cpuInfo = shell_exec('cat /proc/cpuinfo');
            $storageInfo = shell_exec('df -h');

            // Get RAM usage
            $ramUsage = shell_exec('free | grep Mem | awk \'{print $3/$2 * 100.0}\'');

            // Get CPU usage
            $cpuUsage = shell_exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\([0-9.]*\)%* id.*/\1/' | awk '{print 100 - $1}'");

            // Get storage usage for root directory
            $storageUsage = shell_exec("df -h / | grep -v Filesystem | awk '{print $5}'");
        }
        $exec_loads = sys_getloadavg();
        $exec_cores = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
        $cpu = round($exec_loads[1] / ($exec_cores + 1) * 100, 0) . '%';

        return [
            'ramInfo' => $ramInfo,
            'cpuInfo' => $cpuInfo,
            'storageInfo' => $storageInfo,
            'ramUsage' => $ramUsage,
            'cpuUsage' => $cpuUsage,
            'storageUsage' => $storageUsage,
            'cpu' => $cpu,
        ];
    }
}
