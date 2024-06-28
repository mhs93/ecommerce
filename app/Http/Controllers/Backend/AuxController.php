<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subcategory;
use Exception;
use Illuminate\Http\Request;

class AuxController extends Controller
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
    public function GetAuxiliaryDataFormCtrl()
    {
        try {
            $category_collection = collect();
            $sub_category_on_sub_category_collection = collect();
            $categories = Category::select('name', 'slug')->get();
            foreach ($categories as $cd) {
                $category_collection->push([
                    'name' => $cd->name,
                    'slug' => $cd->slug,
                ]);
                $sub_category_on_sub_category = Subcategory::where('category_slug', '=', $cd->slug)->get();
                foreach ($sub_category_on_sub_category as $scd) {
                    $sub_category_on_sub_category_collection->push([
                        'category' => $cd->name,
                        'slug' => $cd->slug,
                        'sub-category' => [
                            'name' => $scd->name,
                            'slug' => $scd->slug,
                        ]
                    ]);
                }
            }

            $this->status = true;
            $this->code = 200;
            $this->error = "Verified";
            $this->msg = "Category and Subcategory returned";

            return response()->json([
                'status' => $this->status,
                'code'   => $this->code,
                'error'  => $this->error,
                'msg'    => $this->msg,
                'data' => [
                    'categories' => $category_collection,
                    'sub_categories' => $sub_category_on_sub_category_collection
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
