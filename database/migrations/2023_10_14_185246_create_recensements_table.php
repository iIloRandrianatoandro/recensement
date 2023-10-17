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
            $table->id('idRecensement')->increments();
            $table->string('designation')->nullable();
            $table->year('annee')->default(Carbon::now()->year);
            $table->integer('deficitParArticle')->unsigned()->nullable();
            $table->integer('excedentParArticle')->unsigned()->nullable();
            $table->integer('existantApresEcriture')->unsigned()->nullable();
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
