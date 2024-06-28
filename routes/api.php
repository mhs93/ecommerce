<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Backend\AuxController;
use App\Http\Controllers\Backend\DashboardFunctionController;
use App\Http\Controllers\Backend\LoginController;
use App\Http\Controllers\Backend\ProductController;
use App\Http\Controllers\CS\CSController;
use App\Http\Controllers\CS\ManualOrderProcessController;
use App\Http\Controllers\Frontend\AddtoCartController;
use App\Http\Controllers\Frontend\Client\ClientController;
use App\Http\Controllers\Frontend\Client\SignupLoginController;
use App\Http\Controllers\Frontend\ProductListDetailsController;
use App\Http\Controllers\Frontend\PurchaseCycle\GenerateTokenPurhcaseCompleteController;
use App\Http\Controllers\Frontend\PurchaseCycle\ProductPurchaseByUser as PurchaseCycleProductPurchaseByUser;
use App\Http\Controllers\Frontend\SiteController;
use App\Http\Controllers\Frontend\WishListController;
use App\Http\Middleware\Admin\AdminAccessVerify;
use App\Http\Middleware\Backend\BackendAccessVerify;
use App\Http\Middleware\Frontend\FrontendAccessVeify;
use App\Http\Middleware\Frontend\LoginAccessVerify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


/**
 * --------------------------------------------------------------------------
 * Frontend Controller
 * --------------------------------------------------------------------------
 */
Route::middleware([FrontendAccessVeify::class])->group(function () {
    Route::prefix('product-list-details')->group(function () {
        Route::get('/search', [ProductListDetailsController::class, 'ProductListDetails']);
        Route::get('/search-filter', [ProductListDetailsController::class, 'ProductListDetailsFilter']);
        Route::get('search-suggestion', [ProductListDetailsController::class, 'SearchSuggestion']);
        Route::get('details', [ProductListDetailsController::class, 'ProductDetails']);
    });
    Route::prefix('home-page')->group(function () {
        Route::get('categories-sidebar', [SiteController::class, 'CategoriesSidebar']);
        Route::get('get-city', [
            SiteController::class,
            'GetCity'
        ]);
        Route::get('get-area', [SiteController::class, 'Getarea']);
        Route::get('homepage-product-list', [SiteController::class, 'HomePageProductList']);
    });
    Route::prefix('purchase-cycle-action')->group(function () {
        Route::post('trx-token-generate', [GenerateTokenPurhcaseCompleteController::class, 'VerifyAndGenerateToken']);
        Route::post('save-shipping-address', [PurchaseCycleProductPurchaseByUser::class, 'SetShippingAddressOnOrder']);
        Route::patch('update-trx-inverntory', [PurchaseCycleProductPurchaseByUser::class, 'UpdateTrxInventory']);
    });
});


/**
 * --------------------------------------------------------------------------
 * Client Controller
 * --------------------------------------------------------------------------
 */
Route::prefix('client')->group(function () {
    Route::post('add', [SignupLoginController::class, 'SignUp']);
    Route::post('login', [SignupLoginController::class, 'ClientLogin']);
    Route::middleware([LoginAccessVerify::class])->group(function () {
        Route::post('add-wishlist', [WishListController::class, 'AddtoWishList']);
        Route::post('wishlist', [WishListController::class, 'Wishlist']);
        Route::patch('cart-update', [AddtoCartController::class, 'CartAdd']);
        Route::get('order-list', [ClientController::class, 'TotalProductPurchaseByClient']);
    });
});



/**
 * --------------------------------------------------------------------------
 * Backend Controller
 * --------------------------------------------------------------------------
 */
Route::middleware([AdminAccessVerify::class])->group(function () {
    Route::get('/', [AdminController::class, 'Index'])->name('index');
    Route::put('/set-system-config', [AdminController::class, 'UpdateSystemConfig']);
    Route::prefix('backend-controller')->group(function () {
        Route::post('login-auth-save', [LoginController::class, 'LoginAuthSave']);
        Route::post('logout-auth-remove', [LoginController::class, 'LogoutAuthRemove']);
    });
});

Route::middleware([BackendAccessVerify::class])->group(function () {
    Route::prefix('dash-func')->group(function () {
        Route::get('product-list', [DashboardFunctionController::class, 'ProductListPage']);
    });

    Route::prefix('internal-func')->group(function () {
        Route::prefix('product-store')->group(function () {
            Route::post('step-one', [ProductController::class, 'ProductStore']);
            Route::prefix('step-two')->group(function () {
                Route::post('no-group-variation', [ProductController::class, 'ProductStoreWithNoGroupVariation']);
                Route::post('with-group-variation', [ProductController::class, 'ProductStoreWithGroupVariation']);
            });
        });
        Route::patch('img-insert', [ProductController::class, 'ImageStore']);
        Route::delete('product-delete', [ProductController::class, 'ProductDelete']);
        Route::get('product-details', [ProductController::class, 'ProductDetails']);
        Route::post('product-update', [ProductController::class, 'ProductUpdate']);
        Route::get('product-with-details', [ProductController::class, 'ProductWithDetails']);
        Route::post('product-offline-purchse', [ProductController::class, 'ProductOfflinePurchase']);
        Route::get('aux-data-form-ctrl', [AuxController::class, 'GetAuxiliaryDataFormCtrl']);
        Route::prefix('cs')->group(function () {
            Route::prefix('order')->group(function () {
                Route::get('list', [CSController::class, 'OrderListTable']);
                Route::get('list-excel', [CSController::class, 'OrderListTableExcel']);
                Route::patch('response', [CSController::class, 'CSResponse']);
                Route::get('search', [CSController::class, 'OrderIdSearch']);
            });
            Route::prefix('manual-order')->group(function () {
                Route::get('/get-product-list', [ManualOrderProcessController::class, 'GetProductList']);
            });
        });
    });

    Route::prefix('purchase-cycle-action-manual')->group(function () {
        Route::post('trx-token-generate', [GenerateTokenPurhcaseCompleteController::class, 'VerifyAndGenerateTokenManual']);
        Route::post('save-shipping-address', [PurchaseCycleProductPurchaseByUser::class, 'SetShippingAddressOnOrderManual']);
        Route::patch('update-trx-inverntory', [PurchaseCycleProductPurchaseByUser::class, 'UpdateTrxInventoryManual']);
    });
});


Route::prefix('sys-info')->group(function () {
    Route::get('system-ram', [ProductController::class, 'Info']);
});
/* Route::post('product-update', [ProductController::class, 'ProductUpdate']);
Route::get('product-with-details', [ProductController::class, 'ProductWithDetails']);
Route::post('product-offline-purchse', [ProductController::class, 'ProductOfflinePurchase']); */
