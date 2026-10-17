<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookCopyController extends Controller
{
    public function index(Book $book): JsonResponse
    {
        return response()->json(
            $book->copies()->orderBy('copy_code')->paginate(10)
        );
    }

    public function store(
        Request $request,
        Book $book
    ): JsonResponse {
        $validated = $request->validate([
            'copy_code' => [
                'required',
                'string',
                'max:100',
                'unique:book_copies,copy_code',
            ],
        ]);

        $copy = $book->copies()->create($validated);

        return response()->json($copy, Response::HTTP_CREATED);
    }

    public function availability(BookCopy $bookCopy): JsonResponse
    {
        return response()->json([
            'id' => $bookCopy->id,
            'copy_code' => $bookCopy->copy_code,
            'status' => $bookCopy->status,
            'available' => $bookCopy->isAvailable(),
        ]);
    }
}
