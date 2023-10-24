<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\materiel;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recensements', function (Blueprint $table) {
            $table->id('idRecensement');
            $table->year('annee')->default(Carbon::now()->year);
            $table->integer('deficitParArticle')->unsigned()->default(0);
            $table->integer('excedentParArticle')->unsigned()->default(0);
            $table->integer('existantApresEcriture')->unsigned()->default(0);
            $table->decimal('prixUnite',20,5);
            $table->string('observation')->nullable();
            $table->boolean('recense')->default(false);
            $table->foreignIdFor(materiel::class)->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recensements');
    }
};
