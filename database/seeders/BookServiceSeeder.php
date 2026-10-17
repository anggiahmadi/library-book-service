<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Seeder;

class BookServiceSeeder extends Seeder
{
    public function run(): void
    {
        $author = Author::create([
            'name' => 'Robert C. Martin',
            'biography' => 'Software engineer and author.',
        ]);

        $book = Book::create([
            'author_id' => $author->id,
            'isbn' => '9780132350884',
            'title' => 'Clean Code',
            'description' => 'A handbook of agile software craftsmanship.',
            'replacement_price' => 350000,
            'published_year' => 2008,
        ]);

        $book->copies()->createMany([
            ['copy_code' => 'CC-001'],
            ['copy_code' => 'CC-002'],
            ['copy_code' => 'CC-003'],
        ]);
    }
}
