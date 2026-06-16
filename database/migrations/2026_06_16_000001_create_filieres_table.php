<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();           // e.g. "GI", "TDIA", "Médecine"
            $table->string('nom_complet')->nullable();  // e.g. "Génie Informatique"
            $table->string('couleur')->default('#E0E0E0'); // hex color for this filière
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filieres');
    }
};
