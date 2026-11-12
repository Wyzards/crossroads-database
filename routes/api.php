<?php

use App\Http\Controllers\CrossroadsUserController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapInstanceController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLinkController;
use App\Http\Controllers\ProjectListEntriesController;
use App\Http\Controllers\ProjectListTagsController;
use App\Http\Controllers\ProjectListsController;
use App\Http\Controllers\ProjectStaffController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BadgeProgressionController;
use App\Http\Controllers\EraController;
use App\Http\Controllers\UserBadgeController;
use App\Http\Controllers\XpController;
use App\Http\Controllers\GameNightController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\XpEventDefinitionController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\TagRequestController;
use App\Http\Controllers\AnnouncementConnectionController;
use App\Http\Controllers\InviteCheckController;
use App\Http\Controllers\ProjectContentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('eras')->group(function () {
        Route::get('/', [EraController::class, 'index']);
        Route::post('/', [EraController::class, 'store']);
        Route::get('/{era}', [EraController::class, 'show']);
        Route::put('/{era}', [EraController::class, 'update']);
        Route::post('/{era}/activate', [EraController::class, 'activate']);
    });

    Route::prefix('game-nights')->group(function () {
        Route::get('/', [GameNightController::class, 'index']);
        Route::post('/', [GameNightController::class, 'store']);
        Route::get('/active', [GameNightController::class, 'active']);
        Route::get('/{gameNight}', [GameNightController::class, 'show']);
        Route::put('/{gameNight}', [GameNightController::class, 'update']);
        Route::post('/{gameNight}/attendees', [GameNightController::class, 'addAttendees']);
        Route::post('/{gameNight}/rsvps', [GameNightController::class, 'markRsvps']);
    });

    Route::prefix('xp-event-definitions')->group(function () {
        Route::get('/', [XpEventDefinitionController::class, 'index']);
        Route::post('/', [XpEventDefinitionController::class, 'store']);
        Route::get('/{definition}', [XpEventDefinitionController::class, 'show']);
        Route::put('/{definition}', [XpEventDefinitionController::class, 'update']);
        Route::delete('/{definition}', [XpEventDefinitionController::class, 'destroy']);
    });

    Route::prefix('badges')->group(function () {
        Route::get('/', [BadgeController::class, 'index']);
        Route::post('/', [BadgeController::class, 'store']);
        Route::get('/{badge}', [BadgeController::class, 'show']);
        Route::put('/{badge}', [BadgeController::class, 'update']);

        Route::get('/{badge}/progression', [BadgeProgressionController::class, 'show']);
        Route::put('/{badge}/progression', [BadgeProgressionController::class, 'update']);
    });

    Route::prefix('system')->group(function () {
        Route::get('/channels', [SystemController::class, 'getChannels']);
        Route::post('/channels', [SystemController::class, 'setChannels']);
    });

    Route::prefix('maps')->group(function () {
        Route::get('/by-image-name/{image_name}', action: [MapController::class, 'getByImageName']);
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

        // Badge assignment
        Route::post('/{user}/badges/{badge}', [UserBadgeController::class, 'assignBadge']);
        Route::delete('/{user}/badges/{badge}', [UserBadgeController::class, 'removeBadge']);

        // XP Events
        Route::post('/{user}/xp-events/{definition}/trigger', [XpController::class, 'triggerEvent']);

        // Profile
        Route::get('/{user}/profile', [ProfileController::class, 'show']);

        // Leaderboards
        Route::get('/{viewer}/leaderboard/{type}', [LeaderboardController::class, 'show']);

        // Subscription
        Route::post('/{user}/subscription', [SubscriptionController::class, 'updateSubscription']);

    });

    Route::prefix('projects')->group(callback: function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::post('/search', [ProjectController::class, 'search']);
        Route::get('/exists/by-guild/{guild_id}', [ProjectController::class, 'existsByGuild']);
        Route::get('/exists/by-name/{name}', [ProjectController::class, 'existsByName']);
        Route::get('/by-name/{name}', [ProjectController::class, 'showByName']);
        Route::get('/by-guild/{guild_id}', [ProjectController::class, 'showByGuild']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);

        // Project Staff
        Route::get('/{project}/staff', [ProjectStaffController::class, 'index']);
        Route::put('/{project}/staff/{user}', [ProjectStaffController::class, 'upsert']);
        Route::delete('/{project}/staff/{user}', [ProjectStaffController::class, 'destroy']);

        // Attachments
        Route::get('/{project}/attachments', [ProjectAttachmentController::class, 'index']);
        Route::delete('/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'destroy']);

        // Content: tags, sections (text + files), banner
        Route::put('/{project}/tags', [ProjectContentController::class, 'setTags']);
        Route::post('/{project}/sections', [ProjectContentController::class, 'addSection']);
        Route::put('/{project}/sections/{section}', [ProjectContentController::class, 'updateSection']);
        Route::delete('/{project}/sections/{section}', [ProjectContentController::class, 'deleteSection']);
        Route::post('/{project}/sections/{section}/files', [ProjectContentController::class, 'setSectionFiles']);
        Route::post('/{project}/banner', [ProjectContentController::class, 'setBanner']);
        Route::delete('/{project}/banner', [ProjectContentController::class, 'deleteBanner']);
        Route::get('/{project}/files', [ProjectContentController::class, 'files']);

        // Links
        Route::get('/{project}/links', [ProjectLinkController::class, 'index']);
        Route::post('/{project}/links', [ProjectLinkController::class, 'store']);
        Route::delete('/{project}/links/{link}', [ProjectLinkController::class, 'destroy']);
    });

    Route::prefix('applications')->group(function () {
        Route::get('/', [ApplicationController::class, 'index']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/{application}', [ApplicationController::class, 'show']);
        Route::put('/{application}', [ApplicationController::class, 'update']);
        Route::put('/{application}/working', [ApplicationController::class, 'setWorking']);
        Route::post('/{application}/files', [ApplicationController::class, 'addFile']);
        Route::get('/{application}/files', [ApplicationController::class, 'files']);
        Route::post('/{application}/submit', [ApplicationController::class, 'submit']);
        Route::post('/{application}/withdraw', [ApplicationController::class, 'withdraw']);
        Route::post('/{application}/accept', [ApplicationController::class, 'accept']);
        Route::post('/{application}/reject', [ApplicationController::class, 'reject']);
    });

    Route::prefix('invite-checks')->group(function () {
        Route::get('/', [InviteCheckController::class, 'index']);
        Route::post('/', [InviteCheckController::class, 'store']);
        Route::delete('/{id}', [InviteCheckController::class, 'destroy']);
    });

    Route::prefix('announcement-connections')->group(function () {
        Route::get('/', [AnnouncementConnectionController::class, 'index']);
        Route::post('/', [AnnouncementConnectionController::class, 'store']);
        Route::put('/{connection}', [AnnouncementConnectionController::class, 'update']);
    });

    Route::prefix('tag-requests')->group(function () {
        Route::post('/', [TagRequestController::class, 'store']);
        Route::get('/{tagRequest}', [TagRequestController::class, 'show']);
        Route::put('/{tagRequest}', [TagRequestController::class, 'update']);
        Route::post('/{tagRequest}/decide', [TagRequestController::class, 'decide']);
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index']);
        Route::post('/', [TagController::class, 'store']);
        Route::put('/{tag}', [TagController::class, 'update']);
        Route::delete('/{tag}', [TagController::class, 'destroy']);
    });

    Route::prefix('project-lists')->group(function () {
        Route::get('/', [ProjectListsController::class, 'index']);
        Route::get('/{list}', [ProjectListsController::class, 'show']);

        Route::post('/', [ProjectListsController::class, 'store']);
        Route::put('/{list}', [ProjectListsController::class, 'update']);
        Route::delete('/{list}', [ProjectListsController::class, 'destroy']);

        Route::get('/{list}/tags', [ProjectListTagsController::class, 'index']);
        Route::post('/{list}/tags', [ProjectListTagsController::class, 'store']);
        Route::put('/{list}/tags/{tag}', [ProjectListTagsController::class, 'update']);
        Route::delete('/{list}/tags/{tag}', [ProjectListTagsController::class, 'destroy']);

        Route::get('/{list}/evaluate-tags/{project}', [ProjectListTagsController::class, 'evaluate']);
        Route::post('/{list}/evaluate-tags-bulk', [ProjectListTagsController::class, 'evaluateBulk']);
    });

    Route::prefix('project-list-entries')->group(function () {
        Route::get('/list/{list}', [ProjectListEntriesController::class, 'entriesForList']);
        Route::post('/sync', [ProjectListEntriesController::class, 'sync']);
    });
});
