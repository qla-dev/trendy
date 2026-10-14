<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\CustomRoles;
use Tests\TestCase;

class CustomRolesTest extends TestCase
{
    public function test_only_the_reserved_admin_and_qla_usernames_can_manage_roles(): void
    {
        $leila = new User(['name' => 'Leila Karančić', 'username' => 'Admin']);
        $currentAdmin = new User(['name' => 'Lejla Krnjić', 'username' => 'admin']);
        $qlaDev = new User(['username' => 'qla.dev']);
        $otherAdmin = new User(['name' => 'Drugi administrator', 'username' => 'drugi.admin', 'role' => 'admin']);
        $sameEmail = new User(['username' => 'other', 'email' => 'colakovic.vedad@qla.dev']);

        $this->assertTrue($leila->canManageRoles());
        $this->assertTrue($currentAdmin->canManageRoles());
        $this->assertTrue($qlaDev->canManageRoles());
        $this->assertFalse($otherAdmin->canManageRoles());
        $this->assertFalse($sameEmail->canManageRoles());
        $this->assertTrue(User::isRoleManagerUsername('qla.dev'));
    }

    public function test_custom_role_starts_with_only_its_selected_sidebar_items(): void
    {
        $originalPath = app()->storagePath();
        $testPath = sys_get_temp_dir() . '/trendy-roles-' . bin2hex(random_bytes(8));
        mkdir($testPath . '/app', 0777, true);
        app()->useStoragePath($testPath);

        try {
            $roles = new CustomRoles();
            $sections = $roles->sections();
            $this->assertArrayHasKey('Narudžbe', $sections);
            $this->assertContains('app-order-ai-inbox', array_column($sections['Narudžbe'], 'slug'));
            $this->assertSame(count($roles->allowedSlugs()), count(array_unique($roles->allowedSlugs())));

            $roles->create('Uloga A', []);
            $stored = array_values($roles->all());
            $this->assertSame('Uloga A', $stored[0]['name']);
            $this->assertSame([], $stored[0]['slugs']);

            $roles->create('Uloga B', ['app-order-ai-inbox']);
            $stored = array_values($roles->all());
            $this->assertSame(['app-order-ai-inbox'], $stored[1]['slugs']);

            $keys = array_keys($roles->all());
            $roles->update($keys[1], 'Uloga B nova', ['app-invoice-preview']);
            $this->assertSame('Uloga B nova', $roles->find($keys[1])['name']);
            $this->assertSame(['app-invoice-preview'], $roles->find($keys[1])['slugs']);

            $roles->update(User::ROLE_USER, 'Korisnik', ['app-invoice-preview', 'app-production-plan']);
            $this->assertSame('Korisnik', $roles->find(User::ROLE_USER)['name']);
            $this->assertSame(['app-invoice-preview', 'app-production-plan'], $roles->find(User::ROLE_USER)['slugs']);

            $roles->delete($keys[1]);
            $this->assertNull($roles->find($keys[1]));
            $this->expectException(\RuntimeException::class);
            $roles->delete(User::ROLE_USER);
        } finally {
            app()->useStoragePath($originalPath);
            @unlink($testPath . '/app/custom-roles.json');
            @rmdir($testPath . '/app');
            @rmdir($testPath);
        }
    }
}
