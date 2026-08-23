<?php

namespace Database\Seeders;

use App\Models\AuthAccount;
use App\Models\Branch;
use App\Models\Issuer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $login = Str::lower(trim((string) config('necofact.seed_admin.login')));
        $password = (string) config('necofact.seed_admin.password');

        if ($login === '' && $password === '') {
            $this->call(ProductSeeder::class);
            $this->call(CustomerSeeder::class);

            return;
        }

        if ($login === '' || $password === '') {
            throw new RuntimeException(
                'Define juntos NECOFACT_SEED_ADMIN_LOGIN y NECOFACT_SEED_ADMIN_PASSWORD.',
            );
        }

        DB::transaction(function () use ($login, $password): void {
            $existingAccount = AuthAccount::query()
                ->with('user')
                ->where('login', $login)
                ->first();

            if ($existingAccount) {
                if (! $existingAccount->user?->is_super_admin) {
                    throw new RuntimeException(
                        "El login de bootstrap «{$login}» ya pertenece a otro usuario.",
                    );
                }

                $existingAccount->user->role->permissionScopes()->syncWithoutDetaching(
                    Permission::query()->pluck('code'),
                );

                return;
            }

            $issuer = Issuer::query()->firstOrCreate(
                ['ruc' => '20000000001'],
                [
                    'legal_name' => 'EMISOR DE DEMOSTRACION SAC',
                    'trade_name' => 'NecoFact Demo',
                    'tax_regime' => Issuer::REGIME_GENERAL,
                    'address_line' => 'Av. Demo 123',
                    'ubigeo' => '150101',
                    'department' => 'LIMA',
                    'province' => 'LIMA',
                    'district' => 'LIMA',
                    'capabilities' => [
                        'invoice' => true,
                        'receipt' => true,
                        'credit_note' => true,
                        'gre_sender' => true,
                    ],
                    'environment' => Issuer::ENVIRONMENT_BETA,
                ],
            );

            $branch = Branch::query()->firstOrCreate(
                [
                    'issuer_code' => $issuer->code,
                    'name' => 'Sede principal',
                ],
                [
                    'sunat_establishment_code' => '0000',
                    'is_active' => true,
                ],
            );

            $role = Role::query()->firstOrCreate(
                ['name' => 'Administración'],
                [
                    'description' => 'Categoría administrativa. El alcance define permisos asignables, no grants automáticos.',
                    'is_active' => true,
                ],
            );

            $role->permissionScopes()->syncWithoutDetaching(
                Permission::query()->pluck('code'),
            );

            $user = User::query()->create([
                'first_name' => 'Administrador',
                'last_name' => 'NecoFact',
                'role_code' => $role->code,
                'is_active' => true,
                'is_super_admin' => true,
            ]);

            $user->branches()->attach($branch);

            AuthAccount::query()->create([
                'login' => $login,
                'password' => $password,
                'user_code' => $user->code,
                'is_active' => true,
            ]);
        });

        $this->call(ProductSeeder::class);
        $this->call(CustomerSeeder::class);
    }
}
