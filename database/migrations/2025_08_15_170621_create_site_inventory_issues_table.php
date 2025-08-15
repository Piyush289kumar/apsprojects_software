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
            $table->foreignId('store_id')->constrained()->cascadeOnDelete(); // From which store
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();   // To which site
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Product issued
            $table->foreignId('issued_by')->constrained('users'); // Manager who issued

            $table->integer('quantity')->default(0); // Quantity issued            

            $table->enum('status', ['issued', 'returned', 'damaged'])->default('issued');
            $table->text('notes')->nullable(); // Optional description or reason
            $table->json('meta')->nullable();  // Flexible JSON for future tracking
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
