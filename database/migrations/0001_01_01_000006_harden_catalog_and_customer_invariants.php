<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['code', 'issuer_code'], 'products_code_issuer_uq');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['product_code']);
            $table->foreign(['product_code', 'issuer_code'], 'product_variants_product_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->unique(
                ['issuer_code', 'sunat_establishment_code'],
                'branches_issuer_establishment_uq',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_identity_shape_check
            CHECK (
                (doc_type = '0' AND doc_number ~ '^[0-9A-Z]{1,15}$')
                OR (doc_type = '1' AND doc_number ~ '^[0-9]{8}$' AND doc_number <> '00000000')
                OR (doc_type = '6' AND doc_number ~ '^[0-9]{11}$')
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_identity_shape_check');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_issuer_establishment_uq');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign('product_variants_product_issuer_fk');
            $table->foreign('product_code')
                ->references('code')
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_code_issuer_uq');
        });
    }
};
