<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;

class AuthorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Author::query()
                ->withCount('books')
                ->orderBy('name')
                ->paginate(($request->total_per_page) ? $request->total_per_page : 10)
        );
    }

    public function show(Author $author): JsonResponse
    {
        return response()->json(
            $author->load('books')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string'],
        ]);

        $author = Author::create($validated);

        Redis::publish('orders-channel', json_encode([
            'event' => 'order.created',
            'order_id' => 9981,
        ]));

        Log::info('insert author');

        return response()->json($author, Response::HTTP_CREATED);
    }

    public function update(
        Request $request,
        Author $author
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'biography' => ['sometimes', 'nullable', 'string'],
        ]);

        $author->update($validated);

        return response()->json($author);
    }

    public function destroy(Author $author): JsonResponse
    {
        if ($author->books()->exists()) {
            return response()->json([
                'message' => 'Author masih memiliki buku.',
            ], Response::HTTP_CONFLICT);
        }

        $author->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
