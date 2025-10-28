<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DotsController;

Route::get('/', [DotsController::class, 'index']);
