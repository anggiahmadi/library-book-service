<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookCopyController;
use App\Http\Controllers\Api\BookCopyReservationController;
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

Route::post(
    '/book-copies/{bookCopy}/reserve',
    [BookCopyReservationController::class, 'reserve']
);

Route::post(
    '/book-copies/{bookCopy}/release',
    [BookCopyReservationController::class, 'release']
);

Route::post(
    '/book-copies/{bookCopy}/mark-lost',
    [BookCopyReservationController::class, 'markLost']
);
