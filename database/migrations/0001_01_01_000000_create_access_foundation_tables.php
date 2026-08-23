<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('issuers', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->char('ruc', 11);
            $table->string('legal_name', 250);
            $table->string('trade_name', 250)->nullable();
            $table->string('tax_regime', 16);
            $table->string('address_line', 200)->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('department', 80)->nullable();
            $table->string('province', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->jsonb('capabilities');
            $table->string('certificate_path')->nullable();
            $table->string('sol_user_ref', 40)->nullable();
            $table->string('gre_client_id_ref', 80)->nullable();
            $table->string('environment', 16);
            $table->timestampsTz();

            $table->unique('ruc', 'issuers_ruc_uq');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE issuers
            ADD CONSTRAINT issuers_ruc_format_check
            CHECK (ruc ~ '^[0-9]{11}$'),
            ADD CONSTRAINT issuers_tax_regime_check
            CHECK (tax_regime IN ('NRUS', 'RER', 'RMT', 'GENERAL')),
            ADD CONSTRAINT issuers_environment_check
            CHECK (environment IN ('beta', 'prod')),
            ADD CONSTRAINT issuers_legal_name_not_blank_check
            CHECK (btrim(legal_name) <> '')
            SQL);

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->uuid('issuer_code');
            $table->string('name', 120);
            $table->string('address_line', 200)->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->char('sunat_establishment_code', 4)->default('0000');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('issuer_code');
            $table->foreign('issuer_code')
                ->references('code')
                ->on('issuers')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE branches
            ADD CONSTRAINT branches_name_not_blank_check
            CHECK (btrim(name) <> ''),
            ADD CONSTRAINT branches_sunat_establishment_code_check
            CHECK (sunat_establishment_code ~ '^[0-9]{4}$')
            SQL);

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->string('name', 100)->unique();
            $table->string('group_label', 100);
            $table->string('description');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE permissions
            ADD CONSTRAINT permissions_name_format_check
            CHECK (name ~ '^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$'),
            ADD CONSTRAINT permissions_group_label_not_blank_check
            CHECK (btrim(group_label) <> ''),
            ADD CONSTRAINT permissions_description_not_blank_check
            CHECK (btrim(description) <> '')
            SQL);

        Schema::create('role_permission_scopes', function (Blueprint $table) {
            $table->uuid('role_code');
            $table->uuid('permission_code');

            $table->primary(['role_code', 'permission_code']);
            $table->index('permission_code');
            $table->foreign('role_code')
                ->references('code')
                ->on('roles')
                ->cascadeOnDelete();
            $table->foreign('permission_code')
                ->references('code')
                ->on('permissions')
                ->cascadeOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->uuid('preferred_branch_code')->nullable();
            $table->uuid('role_code');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);
            $table->timestampsTz();

            $table->index('role_code');
            $table->foreign('preferred_branch_code')
                ->references('code')
                ->on('branches')
                ->nullOnDelete();
            $table->foreign('role_code')
                ->references('code')
                ->on('roles')
                ->restrictOnDelete();
        });

        Schema::create('user_branches', function (Blueprint $table) {
            $table->uuid('user_code');
            $table->uuid('branch_code');

            $table->primary(['user_code', 'branch_code']);
            $table->index('branch_code');
            $table->foreign('user_code')
                ->references('code')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('branch_code')
                ->references('code')
                ->on('branches')
                ->cascadeOnDelete();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->uuid('user_code');
            $table->uuid('permission_code');

            $table->primary(['user_code', 'permission_code']);
            $table->index('permission_code');
            $table->foreign('user_code')
                ->references('code')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('permission_code')
                ->references('code')
                ->on('permissions')
                ->cascadeOnDelete();
        });

        Schema::create('auth_accounts', function (Blueprint $table) {
            $table->uuid('code')->primary();
            $table->string('login', 100)->unique();
            $table->string('password');
            $table->uuid('user_code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_code')
                ->references('code')
                ->on('users')
                ->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE auth_accounts
            ADD CONSTRAINT auth_accounts_login_normalized_check
            CHECK (login = lower(btrim(login)) AND login <> '')
            SQL);

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->foreign('user_id')
                ->references('code')
                ->on('auth_accounts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('auth_accounts');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permission_scopes');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('issuers');
    }
};
