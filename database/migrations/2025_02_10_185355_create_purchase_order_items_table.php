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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->bigInteger('catalog_item_id')->comment('ConnectWise catalog item ID');
            $table->enum('received_status', ['FullyReceived', 'Cancelled', 'Waiting']);
            $table->string('cin7_adjustment_id')->nullable()->comment('Cin7 adjustment ID. (Used for voiding stock adjustment)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
