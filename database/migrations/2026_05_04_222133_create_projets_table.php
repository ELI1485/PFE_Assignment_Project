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
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->string('cne')->nullable();
            $table->foreignId('etudiant_id')->constrained('etudiants')->onDelete('cascade');
            $table->foreignId('etudiant2_id')->nullable()->constrained('etudiants')->onDelete('set null');
            $table->string('sujet')->nullable();
            $table->string('titre')->nullable();
            $table->string('langue_soutenance')->nullable()->default('Français');
            $table->foreignId('encadrant_id')->nullable()->constrained('enseignants')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
