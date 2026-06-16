<?php

use App\Models\Filiere;
use App\Services\ColorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the nullable foreign key.
        Schema::table('etudiants', function (Blueprint $table) {
            $table->foreignId('filiere_id')
                ->nullable()
                ->after('prenom')
                ->constrained('filieres')
                ->nullOnDelete();
        });

        // 2. Migrate existing string data into the new filieres table.
        if (Schema::hasColumn('etudiants', 'filiere')) {
            $distinct = DB::table('etudiants')
                ->select('filiere')
                ->distinct()
                ->pluck('filiere');

            $usedColors = [];
            foreach ($distinct as $nom) {
                $nom = trim((string) $nom);
                if ($nom === '') {
                    $nom = 'Inconnue';
                }

                $filiere = Filiere::query()
                    ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
                    ->first();

                if (! $filiere) {
                    $color = ColorService::nextFiliereColor($usedColors);
                    $usedColors[] = $color;
                    $filiere = Filiere::create([
                        'nom'     => $nom,
                        'couleur' => $color,
                    ]);
                }

                DB::table('etudiants')
                    ->where('filiere', $nom === 'Inconnue' ? '' : $nom)
                    ->update(['filiere_id' => $filiere->id]);

                if ($nom === 'Inconnue') {
                    DB::table('etudiants')
                        ->whereNull('filiere_id')
                        ->update(['filiere_id' => $filiere->id]);
                }
            }
        }

        // 3. Drop the old string column.
        if (Schema::hasColumn('etudiants', 'filiere')) {
            Schema::table('etudiants', function (Blueprint $table) {
                $table->dropColumn('filiere');
            });
        }
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('filiere')->default('Inconnue');
        });

        if (Schema::hasColumn('etudiants', 'filiere_id')) {
            // Restore the string value from the filiere relation.
            $rows = DB::table('etudiants')
                ->join('filieres', 'etudiants.filiere_id', '=', 'filieres.id')
                ->select('etudiants.id', 'filieres.nom')
                ->get();
            foreach ($rows as $row) {
                DB::table('etudiants')->where('id', $row->id)->update(['filiere' => $row->nom]);
            }

            Schema::table('etudiants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('filiere_id');
            });
        }
    }
};
