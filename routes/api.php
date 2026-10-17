<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookCopyController;
use Illuminate\Support\Facades\Route;

Route::apiResource('authors', AuthorController::class);
Route::apiResource('books', BookController::class);

Route::get(
    'books/{book}/copies',
    [BookCopyController::class, 'index']
);

Route::post(
    'books/{book}/copies',
    [BookCopyController::class, 'store']
);

Route::get(
    'book-copies/{bookCopy}/availability',
    [BookCopyController::class, 'availability']
);
