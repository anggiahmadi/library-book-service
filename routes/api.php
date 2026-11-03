<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookCopyController;
use App\Http\Controllers\Api\BookCopyReservationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('authors', AuthorController::class);
Route::apiResource('books', BookController::class);

Route::post('trigger-publish', [AuthorController::class, 'testingRedis']);

Route::get(
    'books/{book}/copies',
    [BookCopyController::class, 'index']
);

Route::post(
    'books/{book}/copies',
    [BookCopyController::class, 'store']
);

Route::put(
    'book-copies/{bookCopy}',
    [BookCopyController::class, 'update']
);

Route::delete(
    'book-copies/{bookCopy}',
    [BookCopyController::class, 'destroy']
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
