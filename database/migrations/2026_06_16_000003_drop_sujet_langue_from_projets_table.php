<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            foreach (['sujet', 'titre', 'langue_soutenance'] as $column) {
                if (Schema::hasColumn('projets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->string('sujet')->nullable();
            $table->string('titre')->nullable();
            $table->string('langue_soutenance')->nullable()->default('Français');
        });
    }
};
