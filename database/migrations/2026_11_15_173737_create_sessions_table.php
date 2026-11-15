<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('therapy_type');
            $table->string('audio_path')->nullable();
            $table->string('audio_mime')->nullable();
            $table->longText('transcript')->nullable();
            $table->longText('summary')->nullable();
            $table->json('topics')->nullable();
            $table->longText('interventions')->nullable();
            $table->longText('warnings')->nullable();
            $table->longText('next_ideas')->nullable();
            $table->string('status')->default('draft');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapy_sessions');
    }
};
