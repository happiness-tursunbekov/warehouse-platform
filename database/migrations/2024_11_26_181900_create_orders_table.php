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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('team_id');
            $table->morphs('customer');
            $table->unsignedBigInteger('prepared_by_id')->unsigned()->nullable();
            $table->foreign('prepared_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('accepted_by_member_id')->nullable();
            $table->unsignedBigInteger('signature_id')->nullable();
            $table->foreign('signature_id')->references('id')->on('files')->onDelete('cascade');
            $table->enum('status', ['NEW', 'READY', 'SENT'])->default('NEW')->comment('Order status');
            $table->float('total_cost');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
