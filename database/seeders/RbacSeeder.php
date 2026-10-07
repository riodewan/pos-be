<?php

namespace Database\Seeders;

use App\Core\Enums\RoleScope;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Tenant
            ['name' => 'tenant.view', 'module' => 'tenant', 'description' => 'View tenant details'],
            ['name' => 'tenant.update', 'module' => 'tenant', 'description' => 'Update tenant information'],
            ['name' => 'tenant.delete', 'module' => 'tenant', 'description' => 'Delete tenant account'],
            ['name' => 'tenant.settings', 'module' => 'tenant', 'description' => 'Manage tenant settings'],

            // Store
            ['name' => 'store.view', 'module' => 'store', 'description' => 'View store outlets'],
            ['name' => 'store.create', 'module' => 'store', 'description' => 'Create new store outlet'],
            ['name' => 'store.update', 'module' => 'store', 'description' => 'Update store details'],
            ['name' => 'store.delete', 'module' => 'store', 'description' => 'Deactivate or delete store'],

            // Users & Team
            ['name' => 'user.view', 'module' => 'user', 'description' => 'View tenant team members'],
            ['name' => 'user.invite', 'module' => 'user', 'description' => 'Invite new users to tenant'],
            ['name' => 'user.update', 'module' => 'user', 'description' => 'Update user status or profile'],
            ['name' => 'user.delete', 'module' => 'user', 'description' => 'Remove user from tenant'],
            ['name' => 'user.assign_role', 'module' => 'user', 'description' => 'Assign roles to team members'],

            // Roles
            ['name' => 'role.view', 'module' => 'role', 'description' => 'View roles and permissions'],
            ['name' => 'role.create', 'module' => 'role', 'description' => 'Create custom roles'],
            ['name' => 'role.update', 'module' => 'role', 'description' => 'Update custom role permissions'],
            ['name' => 'role.delete', 'module' => 'role', 'description' => 'Delete custom roles'],

            // Units
            ['name' => 'unit.view', 'module' => 'unit', 'description' => 'View measurement units'],
            ['name' => 'unit.create', 'module' => 'unit', 'description' => 'Create measurement unit'],
            ['name' => 'unit.update', 'module' => 'unit', 'description' => 'Update measurement unit'],
            ['name' => 'unit.delete', 'module' => 'unit', 'description' => 'Delete measurement unit'],

            // Catalog & Products
            ['name' => 'product.view', 'module' => 'catalog', 'description' => 'View products'],
            ['name' => 'product.create', 'module' => 'catalog', 'description' => 'Create new product'],
            ['name' => 'product.update', 'module' => 'catalog', 'description' => 'Update product details and price'],
            ['name' => 'product.delete', 'module' => 'catalog', 'description' => 'Delete product'],

            // Ingredients
            ['name' => 'ingredient.view', 'module' => 'catalog', 'description' => 'View raw ingredients'],
            ['name' => 'ingredient.create', 'module' => 'catalog', 'description' => 'Create raw ingredient'],
            ['name' => 'ingredient.update', 'module' => 'catalog', 'description' => 'Update ingredient info'],
            ['name' => 'ingredient.delete', 'module' => 'catalog', 'description' => 'Delete ingredient'],

            // Recipes
            ['name' => 'recipe.view', 'module' => 'recipe', 'description' => 'View product recipes and HPP'],
            ['name' => 'recipe.create', 'module' => 'recipe', 'description' => 'Create recipe for product'],
            ['name' => 'recipe.update', 'module' => 'recipe', 'description' => 'Update recipe items and portions'],
            ['name' => 'recipe.delete', 'module' => 'recipe', 'description' => 'Delete recipe'],

            // Menus
            ['name' => 'menu.view', 'module' => 'menu', 'description' => 'View menus'],
            ['name' => 'menu.create', 'module' => 'menu', 'description' => 'Create menu collection'],
            ['name' => 'menu.update', 'module' => 'menu', 'description' => 'Update menu details and items'],
            ['name' => 'menu.delete', 'module' => 'menu', 'description' => 'Delete menu'],

            // Suppliers
            ['name' => 'supplier.view', 'module' => 'supplier', 'description' => 'View suppliers'],
            ['name' => 'supplier.create', 'module' => 'supplier', 'description' => 'Add new supplier'],
            ['name' => 'supplier.update', 'module' => 'supplier', 'description' => 'Update supplier information'],
            ['name' => 'supplier.delete', 'module' => 'supplier', 'description' => 'Delete supplier'],

            // Purchasing
            ['name' => 'purchase.view', 'module' => 'purchase', 'description' => 'View purchase orders'],
            ['name' => 'purchase.create', 'module' => 'purchase', 'description' => 'Create purchase order'],
            ['name' => 'purchase.update', 'module' => 'purchase', 'description' => 'Update purchase order'],
            ['name' => 'purchase.receive', 'module' => 'purchase', 'description' => 'Receive purchase order into stock'],
            ['name' => 'purchase.cancel', 'module' => 'purchase', 'description' => 'Cancel purchase order'],

            // Inventory Stocks & Movements
            ['name' => 'inventory.view', 'module' => 'inventory', 'description' => 'View current inventory stock levels'],
            ['name' => 'inventory.adjust', 'module' => 'inventory', 'description' => 'Adjust stock quantities directly'],
            ['name' => 'inventory.movement', 'module' => 'inventory', 'description' => 'View inventory movement audit log'],

            // Wastage
            ['name' => 'wastage.view', 'module' => 'wastage', 'description' => 'View wastage reports'],
            ['name' => 'wastage.create', 'module' => 'wastage', 'description' => 'Record new wastage'],
            ['name' => 'wastage.approve', 'module' => 'wastage', 'description' => 'Approve recorded wastage'],

            // Tables & QRs
            ['name' => 'table.view', 'module' => 'table', 'description' => 'View tables layout and status'],
            ['name' => 'table.create', 'module' => 'table', 'description' => 'Add new table'],
            ['name' => 'table.update', 'module' => 'table', 'description' => 'Update table details/status'],
            ['name' => 'table.delete', 'module' => 'table', 'description' => 'Delete table'],
            ['name' => 'qr.view', 'module' => 'qr', 'description' => 'View table QR codes'],
            ['name' => 'qr.generate', 'module' => 'qr', 'description' => 'Generate or rotate QR tokens'],

            // POS Orders
            ['name' => 'order.view', 'module' => 'order', 'description' => 'View orders'],
            ['name' => 'order.create', 'module' => 'order', 'description' => 'Create POS / Dine-in / Takeaway order'],
            ['name' => 'order.update', 'module' => 'order', 'description' => 'Update order items or status'],
            ['name' => 'order.cancel', 'module' => 'order', 'description' => 'Cancel order'],

            // Payments
            ['name' => 'payment.view', 'module' => 'payment', 'description' => 'View payment transactions'],
            ['name' => 'payment.process', 'module' => 'payment', 'description' => 'Process customer payments'],
            ['name' => 'payment.refund', 'module' => 'payment', 'description' => 'Process payment refunds'],

            // Reports & Dashboard
            ['name' => 'report.sales', 'module' => 'report', 'description' => 'View sales and revenue reports'],
            ['name' => 'report.inventory', 'module' => 'report', 'description' => 'View inventory valuation and usage reports'],
            ['name' => 'report.financial', 'module' => 'report', 'description' => 'View financial and HPP profit reports'],

            // System & Audit
            ['name' => 'audit.view', 'module' => 'system', 'description' => 'View system audit logs'],
        ];

        $insertedPermissions = [];
        foreach ($permissions as $perm) {
            $permission = Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'id' => (string) Str::ulid(),
                    'module' => $perm['module'],
                    'description' => $perm['description'],
                ]
            );
            $insertedPermissions[] = $permission;
        }

        // Create platform Super Admin INTERNAL role if not exists
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'scope' => RoleScope::INTERNAL, 'tenant_id' => null],
            [
                'id' => (string) Str::ulid(),
                'is_default' => true,
                'description' => 'Platform Super Administrator with absolute access across the system.',
            ]
        );

        // Attach all permissions to Super Admin role
        foreach ($insertedPermissions as $p) {
            RolePermission::firstOrCreate(
                ['role_id' => $superAdminRole->id, 'permission_id' => $p->id],
                ['id' => (string) Str::ulid()]
            );
        }
    }
}
