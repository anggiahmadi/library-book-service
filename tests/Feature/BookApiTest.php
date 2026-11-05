<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_books_with_author_and_copy_counts(): void
    {
        $author = Author::create([
            'name' => 'Robert C. Martin',
        ]);

        $book = Book::create([
            'author_id' => $author->id,
            'isbn' => '9780132350884',
            'title' => 'Clean Code',
            'replacement_price' => 350000,
            'published_year' => 2008,
        ]);

        $book->copies()->createMany([
            ['copy_code' => 'CC-001'],
            ['copy_code' => 'CC-002'],
        ]);

        $response = $this->getJson('/api/books');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Clean Code')
            ->assertJsonPath('data.0.author.name', 'Robert C. Martin')
            ->assertJsonPath('data.0.copies_count', 2)
            ->assertJsonPath('data.0.available_copies_count', 2);
    }

    public function test_it_creates_a_book_with_valid_data(): void
    {
        $author = Author::create([
            'name' => 'Martin Fowler',
        ]);

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function (string $channel, string $payload): bool {
                $event = json_decode($payload, true);

                return $channel === 'new-book-published'
                    && is_array($event)
                    && Str::isUuid($event['event_id'] ?? '')
                    && $event['event_type'] === 'NewBookPublished'
                    && isset($event['occurred_at'])
                    && $event['data']['name'] === 'Refactoring'
                    && $event['data']['isbn'] === '9780201633610';
            });

        $response = $this->postJson('/api/books', [
            'author_id' => $author->id,
            'isbn' => '9780201633610',
            'title' => 'Refactoring',
            'replacement_price' => 300000,
            'published_year' => 1999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Refactoring');

        $this->assertDatabaseHas('books', [
            'title' => 'Refactoring',
            'author_id' => $author->id,
        ]);
    }

    public function test_it_rejects_a_book_without_a_title(): void
    {
        $author = Author::create([
            'name' => 'Martin Fowler',
        ]);

        $response = $this->postJson('/api/books', [
            'author_id' => $author->id,
            'replacement_price' => 300000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_it_updates_a_book_copy_code(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $copy = $book->copies()->create(['copy_code' => 'COPY-OLD']);

        $this->putJson("/api/book-copies/{$copy->id}", [
            'copy_code' => 'COPY-NEW',
        ])->assertOk()
            ->assertJsonPath('copy_code', 'COPY-NEW');

        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'copy_code' => 'COPY-NEW',
        ]);
    }

    public function test_it_deletes_available_and_lost_book_copies(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $availableCopy = $book->copies()->create(['copy_code' => 'COPY-AVAILABLE']);
        $lostCopy = $book->copies()->create(['copy_code' => 'COPY-LOST']);
        BookCopy::query()
            ->whereKey($lostCopy->id)
            ->update(['status' => BookCopy::LOST]);

        $this->deleteJson("/api/book-copies/{$availableCopy->id}")
            ->assertNoContent();
        $this->deleteJson("/api/book-copies/{$lostCopy->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('book_copies', ['id' => $availableCopy->id]);
        $this->assertDatabaseMissing('book_copies', ['id' => $lostCopy->id]);
    }

    public function test_it_cannot_delete_a_loaned_book_copy(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $copy = $book->copies()->create(['copy_code' => 'COPY-LOANED']);
        BookCopy::query()
            ->whereKey($copy->id)
            ->update(['status' => BookCopy::LOANED]);

        $this->deleteJson("/api/book-copies/{$copy->id}")
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'Eksemplar hanya dapat dihapus jika statusnya AVAILABLE atau LOST.'
            );

        $this->assertDatabaseHas('book_copies', ['id' => $copy->id]);
    }

    public function test_it_rejects_a_duplicate_copy_code(): void
    {
        $book = Book::create([
            'author_id' => Author::create([
                'name' => 'Test Author',
            ])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);

        BookCopy::create([
            'book_id' => $book->id,
            'copy_code' => 'CC-001',
        ]);

        $response = $this->postJson(
            "/api/books/{$book->id}/copies",
            ['copy_code' => 'CC-001']
        );

        $response->assertUnprocessable();
    }

    public function test_it_filters_books_by_title_and_author_and_paginates_results(): void
    {
        $author = Author::create(['name' => 'Author One']);
        $otherAuthor = Author::create(['name' => 'Author Two']);

        Book::create([
            'author_id' => $author->id,
            'title' => 'Clean Architecture',
            'replacement_price' => 100000,
        ]);
        Book::create([
            'author_id' => $author->id,
            'title' => 'Clean Code',
            'replacement_price' => 100000,
        ]);
        Book::create([
            'author_id' => $otherAuthor->id,
            'title' => 'Clean Testing',
            'replacement_price' => 100000,
        ]);

        $this->getJson("/api/books?search=Clean&author_id={$author->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Clean Architecture')
            ->assertJsonPath('data.1.title', 'Clean Code');
    }

    public function test_it_shows_a_book_with_its_author_and_copies(): void
    {
        $author = Author::create(['name' => 'Test Author']);
        $book = Book::create([
            'author_id' => $author->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $book->copies()->create(['copy_code' => 'COPY-001']);

        $this->getJson("/api/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Test Book')
            ->assertJsonPath('author.name', 'Test Author')
            ->assertJsonPath('copies.0.copy_code', 'COPY-001');
    }

    public function test_it_updates_a_book_and_persists_the_changes(): void
    {
        $author = Author::create(['name' => 'Test Author']);
        $book = Book::create([
            'author_id' => $author->id,
            'isbn' => '9780132350884',
            'title' => 'Old Title',
            'replacement_price' => 100000,
            'published_year' => 2000,
        ]);

        $this->putJson("/api/books/{$book->id}", [
            'title' => 'Updated Title',
            'replacement_price' => 125000,
        ])->assertOk()
            ->assertJsonPath('title', 'Updated Title')
            ->assertJsonPath('replacement_price', '125000.00')
            ->assertJsonPath('author.name', 'Test Author');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'replacement_price' => 125000,
        ]);
    }

    public function test_it_rejects_invalid_book_data_and_duplicate_isbns(): void
    {
        $author = Author::create(['name' => 'Test Author']);
        Book::create([
            'author_id' => $author->id,
            'isbn' => '9780132350884',
            'title' => 'Existing Book',
            'replacement_price' => 100000,
        ]);

        $this->postJson('/api/books', [
            'author_id' => 9999,
            'isbn' => '9780132350884',
            'title' => str_repeat('T', 256),
            'replacement_price' => -1,
            'published_year' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'author_id',
                'isbn',
                'title',
                'replacement_price',
                'published_year',
            ]);
    }

    public function test_it_deletes_a_book_without_copies(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Book to Delete',
            'replacement_price' => 100000,
        ]);

        $this->deleteJson("/api/books/{$book->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_it_cannot_delete_a_book_that_has_copies(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Book with Copy',
            'replacement_price' => 100000,
        ]);
        $book->copies()->create(['copy_code' => 'COPY-001']);

        $this->deleteJson("/api/books/{$book->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Buku masih memiliki eksemplar.');

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_it_lists_and_shows_authors_with_book_counts(): void
    {
        $author = Author::create(['name' => 'Zed Author']);
        $otherAuthor = Author::create(['name' => 'Alpha Author']);
        Book::create([
            'author_id' => $author->id,
            'title' => 'Author Book',
            'replacement_price' => 100000,
        ]);

        $this->getJson('/api/authors?total_per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha Author')
            ->assertJsonPath('per_page', 1);

        $this->getJson("/api/authors/{$author->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Zed Author')
            ->assertJsonPath('books.0.title', 'Author Book');
    }

    public function test_it_creates_an_author_and_publishes_the_author_event(): void
    {
        Redis::shouldReceive('publish')
            ->once()
            ->with('orders-channel', json_encode([
                'event' => 'order.created',
                'order_id' => 9981,
            ]));

        $this->postJson('/api/authors', [
            'name' => 'New Author',
            'biography' => 'Author biography.',
        ])->assertCreated()
            ->assertJsonPath('name', 'New Author')
            ->assertJsonPath('biography', 'Author biography.');

        $this->assertDatabaseHas('authors', [
            'name' => 'New Author',
            'biography' => 'Author biography.',
        ]);
    }

    public function test_it_rejects_an_author_without_a_name(): void
    {
        $this->postJson('/api/authors', [
            'biography' => 'Biography without a name.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_updates_and_deletes_an_author_without_books(): void
    {
        $author = Author::create([
            'name' => 'Old Author Name',
            'biography' => 'Old biography.',
        ]);

        $this->putJson("/api/authors/{$author->id}", [
            'name' => 'Updated Author Name',
            'biography' => 'Updated biography.',
        ])->assertOk()
            ->assertJsonPath('name', 'Updated Author Name')
            ->assertJsonPath('biography', 'Updated biography.');

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'Updated Author Name',
        ]);

        $this->deleteJson("/api/authors/{$author->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('authors', ['id' => $author->id]);
    }

    public function test_it_cannot_delete_an_author_that_has_books(): void
    {
        $author = Author::create(['name' => 'Author with Book']);
        Book::create([
            'author_id' => $author->id,
            'title' => 'Author Book',
            'replacement_price' => 100000,
        ]);

        $this->deleteJson("/api/authors/{$author->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Author masih memiliki buku.');

        $this->assertDatabaseHas('authors', ['id' => $author->id]);
    }

    public function test_it_lists_book_copies_in_code_order_and_validates_page_size(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $book->copies()->createMany([
            ['copy_code' => 'COPY-B'],
            ['copy_code' => 'COPY-A'],
        ]);

        $this->getJson("/api/books/{$book->id}/copies?per_page=1")
            ->assertOk()
            ->assertJsonPath('data.0.copy_code', 'COPY-A')
            ->assertJsonPath('per_page', 1);

        $this->getJson("/api/books/{$book->id}/copies?per_page=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_reports_copy_availability(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $copy = $book->copies()->create(['copy_code' => 'COPY-AVAILABLE']);

        $this->getJson("/api/book-copies/{$copy->id}/availability")
            ->assertOk()
            ->assertJsonPath('id', $copy->id)
            ->assertJsonPath('copy_code', 'COPY-AVAILABLE')
            ->assertJsonPath('status', BookCopy::AVAILABLE)
            ->assertJsonPath('available', true);

        BookCopy::query()
            ->whereKey($copy->id)
            ->update(['status' => BookCopy::LOST]);

        $this->getJson("/api/book-copies/{$copy->id}/availability")
            ->assertOk()
            ->assertJsonPath('status', BookCopy::LOST)
            ->assertJsonPath('available', false);
    }

    public function test_it_rejects_a_copy_code_already_used_by_another_copy(): void
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);
        $copy = $book->copies()->create(['copy_code' => 'COPY-001']);
        $otherCopy = $book->copies()->create(['copy_code' => 'COPY-002']);

        $this->putJson("/api/book-copies/{$otherCopy->id}", [
            'copy_code' => $copy->copy_code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['copy_code']);
    }

    public function test_it_reserves_a_copy_and_allows_idempotent_retry(): void
    {
        $copy = $this->createBookCopy('COPY-RESERVE');
        $reference = (string) Str::uuid();
        $payload = ['reservation_reference' => $reference];

        $this->postJson("/api/book-copies/{$copy->id}/reserve", $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Eksemplar berhasil direservasi.')
            ->assertJsonPath('reservation_reference', $reference)
            ->assertJsonPath('status', BookCopy::LOANED);

        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => BookCopy::LOANED,
            'reservation_reference' => $reference,
        ]);

        $this->postJson("/api/book-copies/{$copy->id}/reserve", $payload)
            ->assertOk()
            ->assertJsonPath('reservation_reference', $reference);
    }

    public function test_it_rejects_reservation_for_unavailable_copy_or_invalid_reference(): void
    {
        $copy = $this->createBookCopy('COPY-RESERVED');
        $reference = (string) Str::uuid();

        $this->postJson("/api/book-copies/{$copy->id}/reserve", [
            'reservation_reference' => 'not-a-uuid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reservation_reference']);

        $this->postJson("/api/book-copies/{$copy->id}/reserve", [
            'reservation_reference' => $reference,
        ])->assertOk();

        $this->postJson("/api/book-copies/{$copy->id}/reserve", [
            'reservation_reference' => (string) Str::uuid(),
        ])->assertConflict()
            ->assertJsonPath('message', 'Eksemplar tidak tersedia.');
    }

    public function test_it_releases_only_the_matching_reservation(): void
    {
        $copy = $this->createBookCopy('COPY-RELEASE');
        $reference = (string) Str::uuid();
        BookCopy::query()->whereKey($copy->id)->update([
            'status' => BookCopy::LOANED,
            'reservation_reference' => $reference,
        ]);

        $this->postJson("/api/book-copies/{$copy->id}/release", [
            'reservation_reference' => (string) Str::uuid(),
        ])->assertConflict()
            ->assertJsonPath('message', 'Reservasi tidak cocok atau sudah berubah.');

        $this->postJson("/api/book-copies/{$copy->id}/release", [
            'reservation_reference' => $reference,
        ])->assertOk()
            ->assertJsonPath('message', 'Eksemplar berhasil dilepas.')
            ->assertJsonPath('status', BookCopy::AVAILABLE);

        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => BookCopy::AVAILABLE,
            'reservation_reference' => null,
        ]);

        $this->postJson("/api/book-copies/{$copy->id}/release", [
            'reservation_reference' => $reference,
        ])->assertOk()
            ->assertJsonPath('message', 'Eksemplar sudah tersedia.');
    }

    public function test_it_marks_a_matching_reservation_lost_and_is_idempotent(): void
    {
        $copy = $this->createBookCopy('COPY-LOST-RESERVATION');
        $reference = (string) Str::uuid();
        BookCopy::query()->whereKey($copy->id)->update([
            'status' => BookCopy::LOANED,
            'reservation_reference' => $reference,
        ]);

        $this->postJson("/api/book-copies/{$copy->id}/mark-lost", [
            'reservation_reference' => $reference,
        ])->assertOk()
            ->assertJsonPath('message', 'Eksemplar ditandai hilang.')
            ->assertJsonPath('status', BookCopy::LOST);

        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => BookCopy::LOST,
            'reservation_reference' => $reference,
        ]);

        $this->postJson("/api/book-copies/{$copy->id}/mark-lost", [
            'reservation_reference' => $reference,
        ])->assertOk()
            ->assertJsonPath('message', 'Eksemplar sudah ditandai hilang.');

        $this->postJson("/api/book-copies/{$copy->id}/mark-lost", [
            'reservation_reference' => (string) Str::uuid(),
        ])->assertConflict()
            ->assertJsonPath('message', 'Reservasi tidak cocok atau status sudah berubah.');
    }

    private function createBookCopy(string $copyCode): BookCopy
    {
        $book = Book::create([
            'author_id' => Author::create(['name' => 'Test Author'])->id,
            'title' => 'Test Book',
            'replacement_price' => 100000,
        ]);

        return $book->copies()->create(['copy_code' => $copyCode]);
    }
}
