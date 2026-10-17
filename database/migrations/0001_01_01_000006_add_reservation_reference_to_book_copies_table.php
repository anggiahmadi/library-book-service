<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->uuid('reservation_reference')
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropUnique([
                'reservation_reference',
            ]);

            $table->dropColumn('reservation_reference');
        });
    }
};
