<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tags = [];
        $normalizedNames = [];

        DB::table('tags')
            ->select(['id', 'user_id', 'name'])
            ->orderBy('id')
            ->each(function (object $tag) use (&$normalizedNames, &$tags): void {
                $name = self::normalizeName($tag->name);
                $normalizedName = self::normalizeNameKey($name);
                $collisionKey = $tag->user_id.'|'.$normalizedName;

                if (array_key_exists($collisionKey, $normalizedNames)) {
                    throw new RuntimeException("Tags {$normalizedNames[$collisionKey]} and {$tag->id} have the same normalized name.");
                }

                $normalizedNames[$collisionKey] = $tag->id;
                $tags[] = [
                    'id' => $tag->id,
                    'name' => $name,
                    'normalized_name' => $normalizedName,
                ];
            });

        Schema::table('tags', function (Blueprint $table): void {
            $table->string('color')->default('neutral');
            $table->boolean('is_active')->default(true);
            $table->string('normalized_name')->nullable();
        });

        foreach ($tags as $tag) {
            DB::table('tags')
                ->where('id', $tag['id'])
                ->update([
                    'name' => $tag['name'],
                    'normalized_name' => $tag['normalized_name'],
                ]);
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'name']);
            $table->string('normalized_name')->nullable(false)->change();
            $table->unique(['user_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'normalized_name']);
            $table->dropColumn(['color', 'is_active', 'normalized_name']);
            $table->unique(['user_id', 'name']);
        });
    }

    private static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    private static function normalizeNameKey(string $name): string
    {
        return mb_strtolower(self::normalizeName($name), 'UTF-8');
    }
};
