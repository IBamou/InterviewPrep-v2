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
            $table->unsignedInteger('xp')->default(0)->after('practice_streak');
            $table->json('unlocked_tiers')->default('["junior"]')->after('xp');
            $table->decimal('mastery_score', 5, 2)->nullable()->after('unlocked_tiers');
            $table->json('tier_xp')->nullable()->after('mastery_score');
            $table->json('tier_ratings')->nullable()->after('tier_xp');
        });
    }

    public function down(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->dropColumn(['xp', 'unlocked_tiers', 'mastery_score', 'tier_xp', 'tier_ratings']);
        });
    }
};
