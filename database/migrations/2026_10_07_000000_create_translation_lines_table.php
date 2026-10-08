<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('translation-manager.table', 'translation_lines'), function (Blueprint $table) {
            $table->id();
            $table->string('namespace', 100)->default('*');
            $table->string('group', 100);
            $table->string('key', 500);
            $table->json('source')->nullable();
            $table->json('text')->nullable();
            $table->timestamps();

            $table->unique(['namespace', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('translation-manager.table', 'translation_lines'));
    }
};
