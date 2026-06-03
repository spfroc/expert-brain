<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $permissions = [
        'system.setting.view',
        'system.setting.update',
        'user.view',
        'user.create',
        'user.update',
        'user.disable',
        'role.view',
        'role.create',
        'role.update',
        'dashboard.view',
        'knowledge_base.view',
        'knowledge_base.create',
        'knowledge_base.update',
        'knowledge_base.delete',
        'knowledge_document.view',
        'knowledge_document.create',
        'knowledge_document.update',
        'knowledge_document.delete',
        'knowledge_document.review',
        'knowledge_document.publish',
        'knowledge_document.expire',
        'knowledge_document.reindex',
        'rag.ask',
        'rag.view_conversation',
        'rag.delete_conversation',
        'customer.view',
        'customer.create',
        'customer.update',
        'customer.delete',
        'customer.analyze',
        'business_rule.view',
        'business_rule.create',
        'business_rule.update',
        'business_rule.delete',
        'business_rule.test',
        'plan.generate',
        'plan.view',
        'plan.update_own',
        'plan.update_all',
        'plan.delete',
        'case.view',
        'case.create',
        'case.update',
        'case.delete',
        'case.publish',
        'report.view',
        'audit.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $knowledgeAdmin = Role::findOrCreate('knowledge-admin', 'web');
        $businessUser = Role::findOrCreate('business-user', 'web');
        $manager = Role::findOrCreate('manager', 'web');
        $readonly = Role::findOrCreate('readonly', 'web');

        $superAdmin->syncPermissions($this->permissions);

        $knowledgeAdmin->syncPermissions([
            'dashboard.view',
            'knowledge_base.view',
            'knowledge_base.create',
            'knowledge_base.update',
            'knowledge_document.view',
            'knowledge_document.create',
            'knowledge_document.update',
            'knowledge_document.review',
            'knowledge_document.publish',
            'knowledge_document.expire',
            'knowledge_document.reindex',
            'case.view',
            'case.create',
            'case.update',
            'case.publish',
        ]);

        $businessUser->syncPermissions([
            'dashboard.view',
            'knowledge_document.view',
            'rag.ask',
            'rag.view_conversation',
            'customer.view',
            'customer.create',
            'customer.update',
            'customer.analyze',
            'plan.generate',
            'plan.view',
            'plan.update_own',
            'case.view',
        ]);

        $manager->syncPermissions([
            'dashboard.view',
            'knowledge_document.view',
            'customer.view',
            'plan.view',
            'case.view',
            'report.view',
            'audit.view',
        ]);

        $readonly->syncPermissions([
            'dashboard.view',
            'knowledge_document.view',
            'customer.view',
            'case.view',
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $admin->assignRole($superAdmin);
    }
}
