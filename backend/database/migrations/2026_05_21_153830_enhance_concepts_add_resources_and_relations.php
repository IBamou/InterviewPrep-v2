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
        Schema::table('concepts', function (Blueprint $table) {
            $table->unsignedInteger('total_practice_sessions')->default(0)->after('status');
            $table->decimal('average_rating', 5, 2)->nullable()->after('total_practice_sessions');
            $table->timestamp('last_practiced_at')->nullable()->after('average_rating');
            $table->json('practice_streak')->nullable()->after('last_practiced_at');
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['concept_id', 'type']);
        });

        Schema::create('concept_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['concept_id', 'related_concept_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_relations');
        Schema::dropIfExists('resources');

        Schema::table('concepts', function (Blueprint $table) {
            $table->dropColumn([
                'total_practice_sessions',
                'average_rating',
                'last_practiced_at',
                'practice_streak',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
