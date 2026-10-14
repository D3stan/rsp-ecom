<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings registry storage (docs/admin-settings.md): `type` becomes a plain string (the enum could only hold
 * string|boolean|json), rows record who changed them, and every change is audited in `setting_changes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL keeps the old enum as a CHECK constraint even after the column becomes a string:
        // it would reject the new types (int, float, bool, list...).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE settings DROP CONSTRAINT IF EXISTS settings_type_check');
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->string('type')->default('string')->change();
        });

        if (! Schema::hasColumn('settings', 'updated_by')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('setting_changes')) {
            Schema::create('setting_changes', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['key', 'created_at']);
            });
        }
    }

    /**
     * Restores the original columns. Types the old enum cannot hold (the registry writes int, float, bool, list...)
     * are folded into 'string' first; the audit trail is dropped.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_changes');

        if (Schema::hasColumn('settings', 'updated_by')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
            });
        }

        DB::table('settings')->whereNotIn('type', ['string', 'boolean', 'json'])->update(['type' => 'string']);

        Schema::table('settings', function (Blueprint $table) {
            $table->enum('type', ['string', 'boolean', 'json'])->default('string')->change();
        });
    }
};
