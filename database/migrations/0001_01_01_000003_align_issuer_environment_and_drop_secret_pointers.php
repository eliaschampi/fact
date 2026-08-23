<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issuers')) {
            return;
        }

        if (Schema::hasColumn('issuers', 'certificate_path')) {
            Schema::table('issuers', function (Blueprint $table) {
                $table->dropColumn(['certificate_path', 'sol_user_ref', 'gre_client_id_ref']);
            });
        }

        DB::statement('ALTER TABLE issuers DROP CONSTRAINT IF EXISTS issuers_environment_check');
        DB::statement("UPDATE issuers SET environment = 'production' WHERE environment = 'prod'");
        DB::statement(<<<'SQL'
            ALTER TABLE issuers
            ADD CONSTRAINT issuers_environment_check
            CHECK (environment IN ('beta', 'production'))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE issuers DROP CONSTRAINT IF EXISTS issuers_environment_check');
        DB::statement("UPDATE issuers SET environment = 'prod' WHERE environment = 'production'");
        DB::statement(<<<'SQL'
            ALTER TABLE issuers
            ADD CONSTRAINT issuers_environment_check
            CHECK (environment IN ('beta', 'prod'))
            SQL);

        Schema::table('issuers', function (Blueprint $table) {
            $table->string('certificate_path')->nullable();
            $table->string('sol_user_ref', 40)->nullable();
            $table->string('gre_client_id_ref', 80)->nullable();
        });
    }
};
