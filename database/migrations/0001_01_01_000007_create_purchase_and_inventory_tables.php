<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE product_variants
            ADD CONSTRAINT product_variants_cost_nonneg_check
            CHECK (cost_price >= 0)
            SQL);

        Schema::table('branches', function (Blueprint $table) {
            $table->unique(['code', 'issuer_code'], 'branches_code_issuer_uq');
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('variant_code');
            $table->uuid('branch_code');
            $table->integer('on_hand')->default(0);
            $table->timestampsTz();

            $table->unique(['variant_code', 'branch_code'], 'inventory_balances_variant_branch_uq');
            $table->index('branch_code');
            $table->foreign('variant_code')
                ->references('code')
                ->on('product_variants')
                ->restrictOnDelete();
            $table->foreign('branch_code')
                ->references('code')
                ->on('branches')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_balances
            ADD CONSTRAINT inventory_balances_on_hand_nonneg_check
            CHECK (on_hand >= 0)
            SQL);

        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->uuid('branch_code');
            $table->uuid('user_code');
            $table->string('purchase_number', 20);
            $table->string('status', 16);
            $table->date('ordered_at');
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['issuer_code', 'purchase_number'], 'purchases_number_uq');
            $table->index(['branch_code', 'status']);
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
            $table->foreign('user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(['branch_code', 'issuer_code'], 'purchases_branch_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('branches')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE purchases
            ADD CONSTRAINT purchases_status_check
            CHECK (status IN ('in_transit', 'received', 'cancelled')),
            ADD CONSTRAINT purchases_number_format_check
            CHECK (purchase_number ~ '^PUR-[0-9]{6}$'),
            ADD CONSTRAINT purchases_status_timestamps_check
            CHECK (
                (status = 'in_transit' AND received_at IS NULL AND cancelled_at IS NULL)
                OR (status = 'received' AND received_at IS NOT NULL AND cancelled_at IS NULL)
                OR (status = 'cancelled' AND cancelled_at IS NOT NULL)
            )
            SQL);

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('purchase_code');
            $table->uuid('variant_code');
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['purchase_code', 'variant_code'], 'purchase_items_purchase_variant_uq');
            $table->index('variant_code');
            $table->foreign('purchase_code')
                ->references('code')
                ->on('purchases')
                ->restrictOnDelete();
            $table->foreign('variant_code')
                ->references('code')
                ->on('product_variants')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE purchase_items
            ADD CONSTRAINT purchase_items_quantity_positive_check
            CHECK (quantity > 0),
            ADD CONSTRAINT purchase_items_unit_cost_nonneg_check
            CHECK (unit_cost >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('inventory_balances');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_code_issuer_uq');
        });

        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT IF EXISTS product_variants_cost_nonneg_check');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
