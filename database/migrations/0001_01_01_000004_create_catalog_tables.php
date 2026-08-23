<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('issuer_code');
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE products
            ADD CONSTRAINT products_name_not_blank_check
            CHECK (btrim(name) <> '')
            SQL);

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('product_code');
            $table->uuid('issuer_code');
            $table->string('sku', 40);
            $table->string('name', 80)->nullable();
            $table->string('unit_code', 4)->default('NIU');
            $table->string('tax_affectation', 2)->default('10');
            $table->decimal('price', 12, 2);
            $table->timestampsTz();

            $table->unique(['issuer_code', 'sku'], 'product_variants_sku_uq');
            $table->index('product_code');
            // Restrict, not cascade: a variant is never deleted, so a
            // product row cannot disappear while variants reference it.
            $table->foreign('product_code')
                ->references('code')
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE product_variants
            ADD CONSTRAINT product_variants_sku_format_check
            CHECK (sku = lower(sku) AND sku ~ '^[a-z0-9][a-z0-9-]*$'),
            ADD CONSTRAINT product_variants_unit_check
            CHECK (unit_code IN ('NIU', 'ZZ')),
            ADD CONSTRAINT product_variants_affectation_check
            CHECK (tax_affectation IN ('10', '20', '30')),
            ADD CONSTRAINT product_variants_price_nonneg_check
            CHECK (price >= 0)
            SQL);

        DB::statement(
            'CREATE INDEX products_name_trgm_ix ON products USING gin (name gin_trgm_ops)',
        );
        DB::statement(
            'CREATE INDEX product_variants_sku_trgm_ix ON product_variants USING gin (sku gin_trgm_ops)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
