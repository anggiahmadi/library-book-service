<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BookCopyController extends Controller
{
    public function index(Book $book): JsonResponse
    {
        $validated = request()->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $book->copies()
                ->orderBy('copy_code')
                ->paginate($validated['per_page'] ?? 10)
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

    public function update(
        Request $request,
        BookCopy $bookCopy
    ): JsonResponse {
        $validated = $request->validate([
            'copy_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('book_copies', 'copy_code')
                    ->ignore($bookCopy->id),
            ],
        ]);

        $bookCopy->update($validated);

        return response()->json($bookCopy);
    }

    public function destroy(BookCopy $bookCopy): JsonResponse
    {
        $deleted = BookCopy::query()
            ->whereKey($bookCopy->id)
            ->whereIn('status', [
                BookCopy::AVAILABLE,
                BookCopy::LOST,
            ])
            ->delete();

        if ($deleted !== 1) {
            return response()->json([
                'message' => 'Eksemplar hanya dapat dihapus jika statusnya AVAILABLE atau LOST.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
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
