<?php


use Illuminate\Support\Facades\Route;

Route::get('/_roles/ping', function () {
    return response()->json(['ok' => true, 'pkg' => 'enadabuzaid/laravel-roles']);
});