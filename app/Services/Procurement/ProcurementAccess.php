<?php

namespace App\Services\Procurement;

use App\Models\User;

class ProcurementAccess
{
    public function can(?User $user, string $action = 'view'): bool
    {
        if (!$user || $user->getRole() === User::ROLE_SUPPLIER) return false;
        if ($user->isAdmin()) return true;
        $slugs = match ($action) {
            'manage' => ['app-procurement-manage'],
            'edit' => ['app-procurement-materials','app-procurement-manage'],
            'receive' => ['app-procurement-receive','app-procurement-manage'],
            default => ['app-procurement','app-procurement-materials','app-procurement-receive','app-procurement-manage'],
        };
        return $user->customRoleCanSeeAny($slugs);
    }

    public function registry(): ProcurementStore
    {
        return new ProcurementStore(app(ProcurementStore::class)->directory() . '/registry');
    }

    public function supplier(User $user): ?string
    {
        if ($user->getRole() !== User::ROLE_SUPPLIER) return null;
        foreach ($this->registry()->all() as $row) if (($row['kind'] ?? '') === 'account' && (int) $row['user_id'] === (int) $user->id) return $row['supplier'];
        return null;
    }

    public function bind(int $userId, string $supplier, string $contact, int $actor): void
    {
        $this->registry()->update(function (&$rows) use ($userId, $supplier, $contact, $actor) {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['kind'] ?? '') !== 'account' || (int) $r['user_id'] !== $userId));
            $rows[] = ['kind'=>'account','user_id'=>$userId,'supplier'=>$supplier,'contact'=>$contact,'by'=>$actor,'at'=>now()->toIso8601String()];
        });
    }

    public function owns(User $user, array $purchase): bool
    {
        $supplier = $this->supplier($user);
        return $supplier !== null && hash_equals($supplier, $purchase['supplier']);
    }
}
