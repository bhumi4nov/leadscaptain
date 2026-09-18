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
        Schema::create('leads', function (Blueprint $table):void {
            $table->id();

            $table->string('leadscaptain_id')->unique();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('company_name')->nullable();
            $table->string('position_title')->nullable();

            $table->string('email_status')->nullable();
            $table->string('linkedin_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
