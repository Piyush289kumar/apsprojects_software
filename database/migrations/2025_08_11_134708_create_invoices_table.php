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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Invoice number (unique, auto-increment or formatted)
            $table->string('invoice_number')->unique();

            // Polymorphic billing party: customer or vendor
            $table->morphs('billable');

            $table->enum('type', ['sale', 'purchase'])->default('sale'); // sale = customer, purchase = vendor

            // Invoice date and due date
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // GST / Tax related
            $table->string('place_of_supply')->nullable(); // state code
            $table->decimal('taxable_value', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);

            // Discount and totals
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Payment status: pending, paid, partial, cancelled
            $table->enum('status', ['pending', 'paid', 'partial', 'cancelled'])->default('pending');

            $table->text('notes')->nullable();

            // User/admin who created the invoice
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
