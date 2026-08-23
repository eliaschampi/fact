<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->char('doc_type', 1);
            $table->string('doc_number', 15);
            $table->string('name', 150);
            $table->timestampsTz();

            $table->index('issuer_code');
            $table->unique(['issuer_code', 'doc_type', 'doc_number'], 'customers_identity_uq');
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_doc_type_check
            CHECK (doc_type IN ('0', '1', '6')),
            ADD CONSTRAINT customers_doc_number_format_check
            CHECK (doc_number = upper(doc_number) AND doc_number ~ '^[0-9A-Z]{1,15}$'),
            ADD CONSTRAINT customers_name_not_blank_check
            CHECK (btrim(name) <> '')
            SQL);

        DB::statement(
            'CREATE INDEX customers_name_trgm_ix ON customers USING gin (name gin_trgm_ops)',
        );
        DB::statement('CREATE INDEX customers_doc_number_ix ON customers (doc_number)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
