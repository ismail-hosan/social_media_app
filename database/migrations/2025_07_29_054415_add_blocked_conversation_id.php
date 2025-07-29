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
        Schema::table('block_users', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->constrained('wire_conversations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('block_users', function (Blueprint $table) {
            $table->dropColumn('conversation_id');
        });
    }
};
