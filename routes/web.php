<?php

use App\Http\Controllers\PDFController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invoice/{id}/pdf', [PDFController::class, 'generateInvoicePdf'])->name('invoice.pdf');
