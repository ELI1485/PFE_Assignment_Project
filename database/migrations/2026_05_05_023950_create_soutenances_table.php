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
        Schema::create('soutenances', function (Blueprint $table) {
            $table->id();
            $table->string('cne')->nullable();
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('encadrant_id')->nullable()->constrained('enseignants')->onDelete('set null');
            $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
            $table->foreignId('jury_id')->nullable()->constrained('juries')->onDelete('set null');
            $table->foreignId('salle_id')->nullable()->constrained('salles')->onDelete('set null');
            $table->string('langue')->nullable()->default('Français');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soutenances');
    }
};
