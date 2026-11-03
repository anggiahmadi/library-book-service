<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
