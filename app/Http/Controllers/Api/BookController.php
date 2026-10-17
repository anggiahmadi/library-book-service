<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()
            ->with('author')
            ->withCount([
                'copies',
                'copies as available_copies_count' =>
                    fn ($q) => $q->where('status', 'AVAILABLE'),
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('author_id')) {
            $query->where('author_id', $request->integer('author_id'));
        }

        return response()->json(
            $query->orderBy('title')->paginate(10)
        );
    }

    public function show(Book $book): JsonResponse
    {
        return response()->json(
            $book->load([
                'author',
                'copies',
            ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author_id' => [
                'required',
                'integer',
                'exists:authors,id',
            ],
            'isbn' => [
                'nullable',
                'string',
                'max:20',
                'unique:books,isbn',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'replacement_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'published_year' => [
                'nullable',
                'integer',
                'min:1000',
                'max:2100',
            ],
        ]);

        $book = Book::create($validated);

        return response()->json(
            $book->load('author'),
            Response::HTTP_CREATED
        );
    }

    public function update(
        Request $request,
        Book $book
    ): JsonResponse {
        $validated = $request->validate([
            'author_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:authors,id',
            ],
            'isbn' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('books', 'isbn')->ignore($book->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'replacement_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'published_year' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1000',
                'max:2100',
            ],
        ]);

        $book->update($validated);

        return response()->json(
            $book->load('author')
        );
    }

    public function destroy(Book $book): JsonResponse
    {
        if ($book->copies()->exists()) {
            return response()->json([
                'message' => 'Buku masih memiliki eksemplar.',
            ], 409);
        }

        $book->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
