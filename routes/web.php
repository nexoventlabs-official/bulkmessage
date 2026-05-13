<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WhatsAppAccountController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\VoterController;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::get('/', [AuthController::class, 'showLogin']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('admin.auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // WhatsApp Accounts
    Route::resource('accounts', WhatsAppAccountController::class);
    Route::post('/accounts/{id}/check-connection', [WhatsAppAccountController::class, 'checkConnection'])
        ->name('accounts.check-connection');

    // Templates (nested under accounts)
    Route::get('/accounts/{accountId}/templates/create', [TemplateController::class, 'create'])
        ->name('templates.create');
    Route::post('/accounts/{accountId}/templates', [TemplateController::class, 'store'])
        ->name('templates.store');
    Route::get('/accounts/{accountId}/templates/{templateId}/preview', [TemplateController::class, 'preview'])
        ->name('templates.preview');
    Route::post('/accounts/{accountId}/templates/{templateId}/submit', [TemplateController::class, 'submitForApproval'])
        ->name('templates.submit');
    Route::get('/accounts/{accountId}/templates/{templateId}/status', [TemplateController::class, 'checkStatus'])
        ->name('templates.status');
    Route::delete('/accounts/{accountId}/templates/{templateId}', [TemplateController::class, 'destroy'])
        ->name('templates.destroy');

    // Voters
    Route::get('/voters', [VoterController::class, 'index'])->name('voters.index');
    Route::get('/voters/assembly-stats', [VoterController::class, 'assemblyStats'])->name('voters.assembly-stats');

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/accounts/{accountId}/templates/{templateId}/campaign', [CampaignController::class, 'create'])
        ->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{id}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{id}/start', [CampaignController::class, 'start'])->name('campaigns.start');
    Route::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{id}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::get('/campaigns/{id}/live-stats', [CampaignController::class, 'liveStats'])->name('campaigns.live-stats');
    Route::post('/campaigns/voter-count', [CampaignController::class, 'getVoterCount'])->name('campaigns.voter-count');

});
