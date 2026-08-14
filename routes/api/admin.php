<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BlogPostController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DistrictController;
use App\Http\Controllers\Api\Admin\GovernorateController;
use App\Http\Controllers\Api\Admin\LegalPageController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\SliderController;
use App\Http\Controllers\Api\Admin\SpecializationController;
use App\Http\Controllers\Api\Admin\TechnicianApplicationController;
use App\Http\Controllers\Api\Admin\TechnicianController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Route;

$crud = function (string $uri, string $controller, string $param, array $except = []) {
    $can = fn (string $action) => "permission:{$uri}.{$action},admin";
    $has = fn (string $feature) => ! in_array($feature, $except, true);

    Route::get($uri, [$controller, 'index'])->middleware($can('view'))->name("{$uri}.index");
    Route::post($uri, [$controller, 'store'])->middleware($can('create'))->name("{$uri}.store");

    if ($has('reorder')) {
        Route::post("{$uri}/reorder", [$controller, 'reorder'])->middleware($can('update'))->name("{$uri}.reorder");
    }

    Route::get("{$uri}/{{$param}}", [$controller, 'show'])->middleware($can('view'))->name("{$uri}.show");
    Route::match(['put', 'patch'], "{$uri}/{{$param}}", [$controller, 'update'])->middleware($can('update'))->name("{$uri}.update");
    Route::delete("{$uri}/{{$param}}", [$controller, 'destroy'])->middleware($can('delete'))->name("{$uri}.destroy");

    if ($has('toggle')) {
        Route::post("{$uri}/{{$param}}/toggle", [$controller, 'toggle'])->middleware($can('update'))->name("{$uri}.toggle");
    }
};

Route::prefix('admin')->name('admin.')->group(function () use ($crud) {

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('auth.login');

    Route::middleware('auth:admin')->group(function () use ($crud) {

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });

        $crud('governorates', GovernorateController::class, 'governorate');
        $crud('districts', DistrictController::class, 'district');
        $crud('categories', CategoryController::class, 'category');
        $crud('services', ServiceController::class, 'service');
        $crud('sliders', SliderController::class, 'slider');
        $crud('specializations', SpecializationController::class, 'specialization', ['reorder']);
        $crud('blog', BlogPostController::class, 'blog', ['reorder']);
        $crud('technicians', TechnicianController::class, 'technician', ['reorder', 'toggle']);

        Route::get('technicians/{technician}/orders', [TechnicianController::class, 'orders'])
            ->middleware('permission:technicians.view,admin')->name('technicians.orders');
        Route::patch('technicians/{technician}/status', [TechnicianController::class, 'changeStatus'])
            ->middleware('permission:technicians.update,admin')->name('technicians.status');
        Route::delete('technicians/{technician}/media/{media}', [TechnicianController::class, 'destroyMedia'])
            ->middleware('permission:technicians.update,admin')->name('technicians.media.destroy');

        Route::prefix('technician-applications')->name('technician-applications.')->group(function () {
            Route::get('pending-count', [TechnicianApplicationController::class, 'pendingCount'])
                ->middleware('permission:technician-applications.view,admin')->name('pending-count');
            Route::get('/', [TechnicianApplicationController::class, 'index'])
                ->middleware('permission:technician-applications.view,admin')->name('index');
            Route::get('{application}', [TechnicianApplicationController::class, 'show'])
                ->middleware('permission:technician-applications.view,admin')->name('show');
            Route::patch('{application}/status', [TechnicianApplicationController::class, 'changeStatus'])
                ->middleware('permission:technician-applications.update,admin')->name('status');
            Route::post('{application}/accept', [TechnicianApplicationController::class, 'accept'])
                ->middleware('permission:technician-applications.update,admin')->name('accept');
            Route::delete('{application}', [TechnicianApplicationController::class, 'destroy'])
                ->middleware('permission:technician-applications.delete,admin')->name('destroy');
        });

        Route::prefix('orders')->name('orders.')->group(function () {
            $view = 'permission:orders.view,admin';
            $manage = 'permission:orders.update,admin';

            Route::get('/', [OrderController::class, 'index'])->middleware($view)->name('index');
            Route::get('{order}', [OrderController::class, 'show'])->middleware($view)->name('show');
            Route::get('{order}/available-technicians', [OrderController::class, 'availableTechnicians'])
                ->middleware($view)->name('available-technicians');

            Route::post('{order}/confirm', [OrderController::class, 'confirm'])->middleware($manage)->name('confirm');
            Route::post('{order}/assign-technician', [OrderController::class, 'assignTechnician'])->middleware($manage)->name('assign');
            Route::post('{order}/reassign-technician', [OrderController::class, 'reassignTechnician'])->middleware($manage)->name('reassign');
            Route::post('{order}/inspect', [OrderController::class, 'inspect'])->middleware($manage)->name('inspect');
            Route::post('{order}/complete', [OrderController::class, 'complete'])->middleware($manage)->name('complete');
            Route::post('{order}/cancel', [OrderController::class, 'cancel'])->middleware($manage)->name('cancel');
        });

        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view,admin')->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view,admin')->name('users.show');
        Route::get('users/{user}/orders', [UserController::class, 'orders'])
            ->middleware('permission:users.view,admin')->name('users.orders');
        Route::patch('users/{user}/status', [UserController::class, 'changeStatus'])
            ->middleware('permission:users.update,admin')->name('users.status');
        Route::delete('users/{user}/deletion-request', [UserController::class, 'dismissDeletionRequest'])
            ->middleware('permission:users.update,admin')->name('users.deletion-request.dismiss');

        Route::get('dashboard', DashboardController::class)
            ->middleware('permission:orders.view,admin')->name('dashboard');

        Route::prefix('admins')->name('admins.')->group(function () {
            Route::get('/', [AdminController::class, 'index'])
                ->middleware('permission:admins.view,admin')->name('index');
            Route::post('/', [AdminController::class, 'store'])
                ->middleware('permission:admins.create,admin')->name('store');
            Route::get('{admin}', [AdminController::class, 'show'])
                ->middleware('permission:admins.view,admin')->name('show');
            Route::put('{admin}', [AdminController::class, 'update'])
                ->middleware('permission:admins.update,admin')->name('update');
            Route::patch('{admin}/status', [AdminController::class, 'changeStatus'])
                ->middleware('permission:admins.update,admin')->name('status');
            Route::delete('{admin}', [AdminController::class, 'destroy'])
                ->middleware('permission:admins.delete,admin')->name('destroy');
        });

        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::get('filters', [PermissionController::class, 'filters'])
                ->middleware('permission:admins.view,admin')->name('filters');
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('permission:admins.view,admin')->name('index');
        });

        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('permission:admins.view,admin')->name('index');
            Route::get('permissions', [RoleController::class, 'permissions'])
                ->middleware('permission:admins.view,admin')->name('permissions');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('permission:admins.create,admin')->name('store');
            Route::put('{role}', [RoleController::class, 'update'])
                ->middleware('permission:admins.update,admin')->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])
                ->middleware('permission:admins.delete,admin')->name('destroy');
        });

        Route::get('settings', [SettingController::class, 'index'])
            ->middleware('permission:settings.view,admin')->name('settings.index');
        Route::match(['put', 'patch'], 'settings', [SettingController::class, 'update'])
            ->middleware('permission:settings.update,admin')->name('settings.update');

        Route::get('legal-pages', [LegalPageController::class, 'index'])
            ->middleware('permission:legal-pages.view,admin')->name('legal-pages.index');
        Route::get('legal-pages/{key}', [LegalPageController::class, 'show'])
            ->middleware('permission:legal-pages.view,admin')->name('legal-pages.show');
        Route::match(['put', 'patch'], 'legal-pages/{key}', [LegalPageController::class, 'update'])
            ->middleware('permission:legal-pages.update,admin')->name('legal-pages.update');
    });
});
