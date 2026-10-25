<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * An earlier version of the variant data migration stored the images as a JSON string
     * inside the JSON column ("[]" instead of []), so the array cast returned a string.
     */
    public function up(): void
    {
        DB::table('product_variants')->select('id', 'images')->orderBy('id')->each(function ($variant) {
            $decoded = json_decode((string) $variant->images, true);

            if (is_string($decoded)) {
                DB::table('product_variants')
                    ->where('id', $variant->id)
                    ->update(['images' => json_encode(json_decode($decoded, true) ?? [])]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible: the double-encoded form was a bug.
    }
};
