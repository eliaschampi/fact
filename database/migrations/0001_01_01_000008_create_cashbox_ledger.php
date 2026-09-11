<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE cash_outflow_number_seq START WITH 1 INCREMENT BY 1');
        DB::statement('CREATE SEQUENCE cashbox_transfer_number_seq START WITH 1 INCREMENT BY 1');

        Schema::create('cash_outflows', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->uuid('branch_code');
            $table->uuid('cashier_user_code');
            $table->uuid('registered_by_user_code');
            $table->string('outflow_number', 20);
            $table->date('business_date');
            $table->string('concept', 160);
            $table->string('note', 500)->nullable();
            $table->decimal('amount', 12, 2);
            $table->uuid('request_key')->unique('cash_outflows_request_key_uq');
            $table->char('request_fingerprint', 64);
            $table->string('status', 16)->default('active');
            $table->timestampTz('reversed_at')->nullable();
            $table->uuid('reversed_by_user_code')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestampsTz();

            $table->unique('outflow_number', 'cash_outflows_number_uq');
            $table->unique(['code', 'issuer_code'], 'cash_outflows_code_issuer_uq');
            $table->index(['cashier_user_code', 'business_date'], 'cash_outflows_cashier_date_ix');
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
            $table->foreign(['branch_code', 'issuer_code'], 'cash_outflows_branch_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign('cashier_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('registered_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reversed_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cash_outflows
            ALTER COLUMN outflow_number SET DEFAULT
                ('OUT-' || lpad(nextval('cash_outflow_number_seq')::text, 6, '0')),
            ADD CONSTRAINT cash_outflows_number_format_check
            CHECK (outflow_number ~ '^OUT-[0-9]{6,}$'),
            ADD CONSTRAINT cash_outflows_concept_not_blank_check
            CHECK (btrim(concept) <> ''),
            ADD CONSTRAINT cash_outflows_amount_positive_check
            CHECK (amount > 0),
            ADD CONSTRAINT cash_outflows_fingerprint_check
            CHECK (request_fingerprint ~ '^[0-9a-f]{64}$'),
            ADD CONSTRAINT cash_outflows_status_check
            CHECK (status IN ('active', 'reversed')),
            ADD CONSTRAINT cash_outflows_reversal_state_check
            CHECK (
                (status = 'active'
                    AND reversed_at IS NULL
                    AND reversed_by_user_code IS NULL
                    AND reversal_reason IS NULL)
                OR (status = 'reversed'
                    AND reversed_at IS NOT NULL
                    AND reversed_by_user_code IS NOT NULL
                    AND reversal_reason IS NOT NULL
                    AND btrim(reversal_reason) <> '')
            )
            SQL);

        Schema::create('cash_outflow_returns', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->uuid('branch_code');
            $table->uuid('cash_outflow_code')->unique('cash_outflow_returns_outflow_uq');
            $table->uuid('created_by_user_code');
            $table->date('business_date');
            $table->decimal('amount', 12, 2);
            $table->string('note', 500)->nullable();
            $table->uuid('request_key')->unique('cash_outflow_returns_request_key_uq');
            $table->char('request_fingerprint', 64);
            $table->timestampsTz();

            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
            $table->foreign(['branch_code', 'issuer_code'], 'cash_outflow_returns_branch_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['cash_outflow_code', 'issuer_code'], 'cash_outflow_returns_outflow_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('cash_outflows')
                ->restrictOnDelete();
            $table->foreign('created_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cash_outflow_returns
            ADD CONSTRAINT cash_outflow_returns_amount_positive_check
            CHECK (amount > 0),
            ADD CONSTRAINT cash_outflow_returns_fingerprint_check
            CHECK (request_fingerprint ~ '^[0-9a-f]{64}$')
            SQL);

        Schema::create('cashbox_transfers', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->uuid('branch_code');
            $table->uuid('source_cashier_user_code');
            $table->uuid('destination_cashier_user_code');
            $table->uuid('registered_by_user_code');
            $table->string('transfer_number', 20);
            $table->date('business_date');
            $table->decimal('amount', 12, 2);
            $table->string('note', 500)->nullable();
            $table->uuid('request_key')->unique('cashbox_transfers_request_key_uq');
            $table->char('request_fingerprint', 64);
            $table->string('status', 16)->default('active');
            $table->timestampTz('reversed_at')->nullable();
            $table->uuid('reversed_by_user_code')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestampsTz();

            $table->unique('transfer_number', 'cashbox_transfers_number_uq');
            $table->unique(['code', 'issuer_code'], 'cashbox_transfers_code_issuer_uq');
            $table->index(['source_cashier_user_code', 'business_date'], 'cashbox_transfers_source_date_ix');
            $table->index(['destination_cashier_user_code', 'business_date'], 'cashbox_transfers_destination_date_ix');
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
            $table->foreign(['branch_code', 'issuer_code'], 'cashbox_transfers_branch_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign('source_cashier_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('destination_cashier_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('registered_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reversed_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cashbox_transfers
            ALTER COLUMN transfer_number SET DEFAULT
                ('CTX-' || lpad(nextval('cashbox_transfer_number_seq')::text, 6, '0')),
            ADD CONSTRAINT cashbox_transfers_number_format_check
            CHECK (transfer_number ~ '^CTX-[0-9]{6,}$'),
            ADD CONSTRAINT cashbox_transfers_distinct_cashiers_check
            CHECK (source_cashier_user_code <> destination_cashier_user_code),
            ADD CONSTRAINT cashbox_transfers_amount_positive_check
            CHECK (amount > 0),
            ADD CONSTRAINT cashbox_transfers_fingerprint_check
            CHECK (request_fingerprint ~ '^[0-9a-f]{64}$'),
            ADD CONSTRAINT cashbox_transfers_status_check
            CHECK (status IN ('active', 'reversed')),
            ADD CONSTRAINT cashbox_transfers_reversal_state_check
            CHECK (
                (status = 'active'
                    AND reversed_at IS NULL
                    AND reversed_by_user_code IS NULL
                    AND reversal_reason IS NULL)
                OR (status = 'reversed'
                    AND reversed_at IS NOT NULL
                    AND reversed_by_user_code IS NOT NULL
                    AND reversal_reason IS NOT NULL
                    AND btrim(reversal_reason) <> '')
            )
            SQL);

        Schema::create('cashbox_movements', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->uuid('branch_code');
            $table->uuid('cashier_user_code');
            $table->uuid('registered_by_user_code');
            $table->date('business_date');
            $table->string('movement_type', 24);
            $table->string('direction', 3);
            $table->decimal('amount', 12, 2);
            $table->string('concept', 160)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('cash_outflow_code')->nullable();
            $table->uuid('cashbox_transfer_code')->nullable();
            $table->uuid('request_key')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestampTz('reversed_at')->nullable();
            $table->uuid('reversed_by_user_code')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestampsTz();

            $table->index(
                ['cashier_user_code', 'business_date', 'created_at'],
                'cashbox_movements_cashier_date_created_ix',
            );
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
            $table->foreign(['branch_code', 'issuer_code'], 'cashbox_movements_branch_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign('cashier_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('registered_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reversed_by_user_code')
                ->references('code')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(['cash_outflow_code', 'issuer_code'], 'cashbox_movements_outflow_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('cash_outflows')
                ->restrictOnDelete();
            $table->foreign(['cashbox_transfer_code', 'issuer_code'], 'cashbox_movements_transfer_issuer_fk')
                ->references(['code', 'issuer_code'])
                ->on('cashbox_transfers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cashbox_movements
            ADD CONSTRAINT cashbox_movements_type_check
            CHECK (movement_type IN ('manual_income', 'expense', 'outflow_return', 'transfer')),
            ADD CONSTRAINT cashbox_movements_direction_check
            CHECK (direction IN ('in', 'out')),
            ADD CONSTRAINT cashbox_movements_amount_positive_check
            CHECK (amount > 0),
            ADD CONSTRAINT cashbox_movements_status_check
            CHECK (status IN ('active', 'reversed')),
            ADD CONSTRAINT cashbox_movements_fingerprint_check
            CHECK (request_fingerprint IS NULL OR request_fingerprint ~ '^[0-9a-f]{64}$'),
            ADD CONSTRAINT cashbox_movements_owner_alignment_check
            CHECK (
                (movement_type = 'manual_income'
                    AND direction = 'in'
                    AND cash_outflow_code IS NULL
                    AND cashbox_transfer_code IS NULL
                    AND concept IS NOT NULL
                    AND btrim(concept) <> ''
                    AND request_key IS NOT NULL
                    AND request_fingerprint IS NOT NULL)
                OR (movement_type = 'expense'
                    AND direction = 'out'
                    AND cash_outflow_code IS NOT NULL
                    AND cashbox_transfer_code IS NULL
                    AND concept IS NULL
                    AND note IS NULL
                    AND request_key IS NULL
                    AND request_fingerprint IS NULL)
                OR (movement_type = 'outflow_return'
                    AND direction = 'in'
                    AND cash_outflow_code IS NOT NULL
                    AND cashbox_transfer_code IS NULL
                    AND concept IS NULL
                    AND note IS NULL
                    AND request_key IS NULL
                    AND request_fingerprint IS NULL)
                OR (movement_type = 'transfer'
                    AND direction IN ('in', 'out')
                    AND cash_outflow_code IS NULL
                    AND cashbox_transfer_code IS NOT NULL
                    AND concept IS NULL
                    AND note IS NULL
                    AND request_key IS NULL
                    AND request_fingerprint IS NULL)
            ),
            ADD CONSTRAINT cashbox_movements_reversal_state_check
            CHECK (
                (status = 'active'
                    AND reversed_at IS NULL
                    AND reversed_by_user_code IS NULL
                    AND reversal_reason IS NULL)
                OR (status = 'reversed'
                    AND reversed_at IS NOT NULL
                    AND reversed_by_user_code IS NOT NULL
                    AND reversal_reason IS NOT NULL
                    AND btrim(reversal_reason) <> '')
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX cashbox_movements_manual_request_uq
                ON cashbox_movements (request_key)
                WHERE request_key IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX cashbox_movements_expense_owner_uq
                ON cashbox_movements (cash_outflow_code)
                WHERE movement_type = 'expense'
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX cashbox_movements_return_owner_uq
                ON cashbox_movements (cash_outflow_code)
                WHERE movement_type = 'outflow_return'
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX cashbox_movements_transfer_cashier_uq
                ON cashbox_movements (cashbox_transfer_code, cashier_user_code)
                WHERE movement_type = 'transfer'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX cashbox_movements_active_balance_ix
                ON cashbox_movements (cashier_user_code, business_date)
                INCLUDE (direction, amount)
                WHERE status = 'active'
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW cashbox_daily_summary AS
            WITH daily_movements AS (
                SELECT
                    movement.cashier_user_code,
                    movement.business_date,
                    COALESCE(SUM(movement.amount) FILTER (
                        WHERE movement.direction = 'in'
                            AND movement.movement_type <> 'transfer'
                    ), 0)::numeric(12,2) AS income_amount,
                    COALESCE(SUM(movement.amount) FILTER (
                        WHERE movement.direction = 'out'
                            AND movement.movement_type <> 'transfer'
                    ), 0)::numeric(12,2) AS expense_amount,
                    COALESCE(SUM(movement.amount) FILTER (
                        WHERE movement.direction = 'in'
                            AND movement.movement_type = 'transfer'
                    ), 0)::numeric(12,2) AS transfer_in_amount,
                    COALESCE(SUM(movement.amount) FILTER (
                        WHERE movement.direction = 'out'
                            AND movement.movement_type = 'transfer'
                    ), 0)::numeric(12,2) AS transfer_out_amount,
                    SUM(CASE movement.direction
                        WHEN 'in' THEN movement.amount
                        ELSE -movement.amount
                    END)::numeric(12,2) AS net_amount
                FROM cashbox_movements movement
                WHERE movement.status = 'active'
                GROUP BY movement.cashier_user_code, movement.business_date
            ), running_totals AS (
                SELECT
                    daily.*,
                    COALESCE(SUM(daily.net_amount) OVER (
                        PARTITION BY daily.cashier_user_code
                        ORDER BY daily.business_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0)::numeric(12,2) AS opening_amount
                FROM daily_movements daily
            )
            SELECT
                running.cashier_user_code,
                btrim(cashier.first_name || ' ' || cashier.last_name) AS cashier_name,
                running.business_date,
                running.opening_amount,
                running.income_amount,
                running.expense_amount,
                running.transfer_in_amount,
                running.transfer_out_amount,
                running.net_amount,
                (running.opening_amount + running.net_amount)::numeric(12,2) AS current_amount
            FROM running_totals running
            INNER JOIN users cashier ON cashier.code = running.cashier_user_code
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS cashbox_daily_summary');
        Schema::dropIfExists('cashbox_movements');
        Schema::dropIfExists('cash_outflow_returns');
        Schema::dropIfExists('cashbox_transfers');
        Schema::dropIfExists('cash_outflows');
        DB::statement('DROP SEQUENCE IF EXISTS cashbox_transfer_number_seq');
        DB::statement('DROP SEQUENCE IF EXISTS cash_outflow_number_seq');
    }
};
