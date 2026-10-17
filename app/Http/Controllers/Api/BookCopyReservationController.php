<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookCopyReservationController extends Controller
{
    public function reserve(Request $request, BookCopy $bookCopy): JsonResponse
    {
        $validated = $request->validate([
            'reservation_reference' => [
                'required',
                'uuid',
            ],
        ]);

        $reference = $validated['reservation_reference'];

        $result = DB::transaction(function () use ($bookCopy, $reference) {
            $copy = BookCopy::query()
                ->whereKey($bookCopy->id)
                ->firstOrFail();

            // Retry dari transaksi yang sama.
            if ($copy->status === BookCopy::LOANED && $copy->reservation_reference === $reference) {
                return 'RESERVED';
            }

            if ($copy->status !== BookCopy::AVAILABLE) {
                return 'UNAVAILABLE';
            }

            // Conditional UPDATE mencegah dua request sukses
            // untuk eksemplar yang sama.
            $updated = BookCopy::query()
                ->whereKey($copy->id)
                ->where('status', BookCopy::AVAILABLE)
                ->update([
                    'status' => BookCopy::LOANED,
                    'reservation_reference' => $reference,
                    'updated_at' => now(),
                ]);

            return $updated === 1 ? 'RESERVED' : 'UNAVAILABLE';
        });

        if ($result === 'UNAVAILABLE') {
            return response()->json([
                'message' => 'Eksemplar tidak tersedia.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Eksemplar berhasil direservasi.',
            'reservation_reference' => $reference,
            'status' => BookCopy::LOANED,
        ]);
    }

    public function release(Request $request, BookCopy $bookCopy): JsonResponse
    {
        $validated = $request->validate([
            'reservation_reference' => [
                'required',
                'uuid',
            ],
        ]);

        $current = BookCopy::findOrFail($bookCopy->id);

        if ($current->status === BookCopy::AVAILABLE && $current->reservation_reference === null) {
            return response()->json([
                'message' => 'Eksemplar sudah tersedia.',
                'status' => BookCopy::AVAILABLE,
            ]);
        }

        $updated = BookCopy::query()
            ->whereKey($bookCopy->id)
            ->where('status', BookCopy::LOANED)
            ->where(
                'reservation_reference',
                $validated['reservation_reference']
            )
            ->update([
                'status' => BookCopy::AVAILABLE,
                'reservation_reference' => null,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return response()->json([
                'message' => 'Reservasi tidak cocok atau sudah berubah.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Eksemplar berhasil dilepas.',
            'status' => BookCopy::AVAILABLE,
        ]);
    }

    public function markLost(Request $request, BookCopy $bookCopy): JsonResponse
    {
        $validated = $request->validate([
            'reservation_reference' => [
                'required',
                'uuid',
            ],
        ]);

        $current = BookCopy::findOrFail($bookCopy->id);

        if ($current->status === BookCopy::LOST && $current->reservation_reference === $request->input('reservation_reference')) {
            return response()->json([
                'message' => 'Eksemplar sudah ditandai hilang.',
                'status' => BookCopy::LOST,
            ]);
        }

        $updated = BookCopy::query()
            ->whereKey($bookCopy->id)
            ->where('status', BookCopy::LOANED)
            ->where(
                'reservation_reference',
                $validated['reservation_reference']
            )
            ->update([
                'status' => BookCopy::LOST,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return response()->json([
                'message' => 'Reservasi tidak cocok atau status sudah berubah.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Eksemplar ditandai hilang.',
            'status' => BookCopy::LOST,
        ]);
    }
}
