<?php

use App\Http\Controllers\SmsCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/sms/callback', SmsCallbackController::class)->name('api.sms.callback');
