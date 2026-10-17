<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('author_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('isbn', 20)->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('replacement_price', 12, 2);
            $table->unsignedSmallInteger('published_year')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
