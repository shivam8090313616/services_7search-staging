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
        Schema::create('admin_removed_payment_resctriction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20);
            $table->string('remark', 150);
            $table->dateTime('payment_lock_at')->default(null);
            $table->tinyInteger('removed_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_removed_payment_resctriction_logs');
    }
};
