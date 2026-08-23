<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array CATALOG = [
        'Inicio' => [
            'dashboard.view' => 'Acceder a la página de inicio.',
        ],
        'Sedes' => [
            'branches.view' => 'Ver las sedes del emisor.',
            'branches.manage' => 'Crear, editar y activar sedes.',
        ],
        'Usuarios' => [
            'employees.view' => 'Ver los usuarios del personal.',
            'employees.manage' => 'Crear, editar y activar usuarios.',
        ],
        'Roles' => [
            'roles.view' => 'Ver los roles y el alcance de permisos.',
            'roles.manage' => 'Crear y editar roles y su alcance de permisos.',
        ],
        'Productos' => [
            'products.view' => 'Ver el catálogo de productos y variantes.',
            'products.manage' => 'Registrar y editar productos, precios y afectación.',
        ],
        'Clientes' => [
            'customers.view' => 'Buscar identidades tributarias.',
            'customers.manage' => 'Registrar y editar clientes fiscales.',
        ],
        'Inventario' => [
            'inventory.view' => 'Ver saldos por sede.',
            'inventory.manage' => 'Transferir stock entre sedes.',
        ],
        'Compras' => [
            'purchases.view' => 'Ver compras.',
            'purchases.manage' => 'Registrar, recibir y cancelar compras.',
        ],
        'Ventas' => [
            'sales.view' => 'Ver ventas y el POS.',
            'sales.manage' => 'Registrar y anular ventas.',
        ],
        'Caja' => [
            'cashbox.view' => 'Ver la caja propia.',
            'cashbox.manage' => 'Registrar ingresos, egresos y transferencias de efectivo.',
        ],
        'Fiscal' => [
            'fiscal.view' => 'Ver la cola de CPE y artefactos.',
            'fiscal.manage' => 'Reintentar, consultar y emitir correcciones fiscales.',
        ],
        'Guías' => [
            'gre.view' => 'Ver guías de remisión.',
            'gre.manage' => 'Emitir guías de remisión remitente.',
        ],
        'Emisor' => [
            'settings.view' => 'Ver la configuración del emisor y las series.',
            'settings.manage' => 'Editar RUC, series, ambiente y certificado.',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::CATALOG as $groupLabel => $permissions) {
                foreach ($permissions as $name => $description) {
                    Permission::query()->updateOrCreate(
                        ['name' => $name],
                        [
                            'group_label' => $groupLabel,
                            'description' => $description,
                        ],
                    );
                }
            }
        });
    }
}
