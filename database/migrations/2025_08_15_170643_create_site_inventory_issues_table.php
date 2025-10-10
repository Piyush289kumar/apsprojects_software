<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_inventory_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            // site_id can be NULL for transfer records
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();

            $table->enum('status', ['issued', 'returned', 'damaged'])->default('issued')->index();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->enum('issue_type', ['site', 'transfer'])->default('site');
            $table->foreignId('transfer_to_store_id')->nullable()->constrained('stores')->nullOnDelete();

            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_inventory_issues');
    }
};
