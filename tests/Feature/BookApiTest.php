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
