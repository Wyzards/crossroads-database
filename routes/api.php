<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapInstanceController;
use App\Http\Controllers\CrossroadsUserController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStaffController;
use App\Http\Controllers\ProjectLinkController;
use App\Http\Controllers\ProjectAttachmentController;

Route::middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('maps')->group(function () {
        Route::get('/by-image-name/{image_name}', [MapController::class, 'getByImageName']);
        Route::delete('/by-image-name/{image_name}', [MapController::class, 'deleteByImageName']);
        Route::get('/', [MapController::class, 'index']);
        Route::post('/', [MapController::class, 'store']);
        Route::get('/{map}', [MapController::class, 'show']);
    });

    Route::prefix('map-instances')->group(function () {
        Route::get('/user/{user}', [MapInstanceController::class, 'index']);
        Route::post('/user/{user}', [MapInstanceController::class, 'store']);
        Route::get('/{instance}/secondary-users', [MapInstanceController::class, 'getSecondaryUsers']);
        Route::post('/{instance}/secondary-users', [MapInstanceController::class, 'addSecondaryUser']);
        Route::delete('/{instance}/secondary-users/{user}', [MapInstanceController::class, 'removeSecondaryUser']);
        Route::delete('/{instance}', [MapInstanceController::class, 'destroy']);
    });

    Route::prefix('crossroads-users')->group(function () {
        Route::post('/', [CrossroadsUserController::class, 'store']);
        Route::get('/', [CrossroadsUserController::class, 'index']);
        Route::get('/{user}', [CrossroadsUserController::class, 'show']);
        Route::delete('/{user}', [CrossroadsUserController::class, 'destroy']);
        Route::get('/minecraft/{uuid}', [CrossroadsUserController::class, 'findByMinecraftUuid']);
        Route::get('/discord/{id}', [CrossroadsUserController::class, 'findByDiscordId']);
    });

    Route::prefix('projects')->group(callback: function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);

        // Project Staff
        Route::get('/{project}/staff', [ProjectStaffController::class, 'index']);
        Route::post('/{project}/staff', [ProjectStaffController::class, 'store']);
        Route::delete('/{project}/staff/{user}', [ProjectStaffController::class, 'destroy']);

        // Attachments
        Route::get('/{project}/attachments', [ProjectAttachmentController::class, 'index']);
        Route::post('/{project}/attachments', [ProjectAttachmentController::class, 'store']);
        Route::delete('/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'destroy']);

        // Links
        Route::get('/{project}/links', [ProjectLinkController::class, 'index']);
        Route::post('/{project}/links', [ProjectLinkController::class, 'store']);
        Route::delete('/{project}/links/{link}', [ProjectLinkController::class, 'destroy']);
    });
});