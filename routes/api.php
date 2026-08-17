<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\AuthController;


// ─────────────────────────────
// Public routes
// ─────────────────────────────
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/menu-items', [MenuItemController::class, 'index']);
Route::get('/menu-items/{menuItem}', [MenuItemController::class, 'show']);
Route::get('/tags', [TagController::class, 'index']);
Route::get('/site-settings', [SiteSettingController::class, 'index']);
 
Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:10,1'); // max 10 submissions per minute per IP
 
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // max 5 attempts per minute per IP
 
// ─────────────────────────────
// Admin routes (protected)
// ─────────────────────────────
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
 
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
 
    Route::apiResource('menu-items', MenuItemController::class)->except(['index', 'show']);
    Route::post('/menu-items/reorder', [MenuItemController::class, 'reorder']);
 
    Route::apiResource('tags', TagController::class)->except(['index']);
    Route::put('/site-settings', [SiteSettingController::class, 'update']);
 
    Route::get('/contact-messages', [ContactMessageController::class, 'index']);
    Route::get('/contact-messages/{contactMessage}', [ContactMessageController::class, 'show']);
    Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy']);
});