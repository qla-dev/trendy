<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class CustomRoles
{
    public const BUILTIN = [
        User::ROLE_ADMIN => 'Admin',
        User::ROLE_USER => 'Korisnik',
        User::ROLE_KONTROLA => 'Kontrola',
        User::ROLE_BRAVARIJA => 'Bravarija',
        User::ROLE_PROIZVODNJA => 'Proizvodnja',
    ];

    private function path(): string
    {
        return storage_path('app/custom-roles.json');
    }

    public function all(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return [];
        }

        $file = fopen($path, 'r');
        if ($file === false) {
            return [];
        }
        try {
            if (!flock($file, LOCK_SH)) {
                return [];
            }
            $roles = json_decode((string) stream_get_contents($file), true);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
        if (!is_array($roles)) {
            return [];
        }
        foreach ($roles as &$role) {
            $role['slugs'] = $this->normalizeLegacySlugs($role['slugs'] ?? []);
        }
        unset($role);
        return $roles;
    }

    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function defaults(string $key): array
    {
        $all = $this->allowedSlugs();
        return match ($key) {
            User::ROLE_ADMIN => $all,
            User::ROLE_PROIZVODNJA => ['app-production-plan'],
            User::ROLE_USER, User::ROLE_KONTROLA, User::ROLE_BRAVARIJA => ['app-invoice-preview'],
            default => [],
        };
    }

    public static function menuItemKey($item): string
    {
        $slug = is_array($item) ? ($item['slug'] ?? '') : ($item->slug ?? '');
        if ($slug !== '') {
            return $slug;
        }
        $url = is_array($item) ? ($item['url'] ?? '') : ($item->url ?? '');
        $icon = is_array($item) ? ($item['icon'] ?? '') : ($item->icon ?? '');
        return 'item:' . sha1($url . "\0" . $icon);
    }

    private function normalizeLegacySlugs(array $slugs): array
    {
        if (!array_filter($slugs, fn ($slug) => is_string($slug) && str_starts_with($slug, 'menu:'))) {
            return $slugs;
        }
        $menu = json_decode((string) file_get_contents(base_path('resources/data/menu-data/mainMenu.json')), true)['menu'] ?? [];
        $legacyKeys = [];
        $oldIndex = 0;
        foreach ($menu as $item) {
            if (($item['slug'] ?? '') === 'app-role-list') {
                continue;
            }
            if (!empty($item['url']) && empty($item['slug'])) {
                $legacyKeys['menu:' . $oldIndex] = self::menuItemKey($item);
            }
            $oldIndex++;
        }
        return array_values(array_unique(array_map(fn ($slug) => $legacyKeys[$slug] ?? $slug, $slugs)));
    }

    public function create(string $name, array $slugs): void
    {
        $path = $this->path();
        $file = fopen($path, 'c+');
        if ($file === false) {
            throw new RuntimeException('Uloge nije moguće sačuvati.');
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Uloge nije moguće zaključati.');
            }
            $existing = stream_get_contents($file);
            $roles = $existing === '' ? [] : json_decode($existing, true);
            if (!is_array($roles)) {
                throw new RuntimeException('Podaci o ulogama nisu ispravni.');
            }
            foreach ($roles as $role) {
                if (Str::lower($role['name'] ?? '') === Str::lower($name)) {
                    throw new RuntimeException('Uloga s tim nazivom već postoji.');
                }
            }
            $key = 'role_' . Str::lower(Str::random(20));
            $roles[$key] = ['name' => $name, 'slugs' => array_values(array_unique($slugs))];
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function update(string $key, string $name, array $slugs): void
    {
        $this->mutate(function (array &$roles) use ($key, $name, $slugs): void {
            if (!isset($roles[$key]) && !isset(self::BUILTIN[$key]) && !User::where('role', $key)->exists()) {
                throw new RuntimeException('Uloga nije pronađena.');
            }
            foreach ($roles as $otherKey => $role) {
                if ($otherKey !== $key && Str::lower($role['name'] ?? '') === Str::lower($name)) {
                    throw new RuntimeException('Uloga s tim nazivom već postoji.');
                }
            }
            $roles[$key] = [
                'name' => self::BUILTIN[$key] ?? $name,
                'slugs' => array_values(array_unique($slugs)),
            ];
        });
    }

    public function delete(string $key): void
    {
        if (isset(self::BUILTIN[$key])) {
            throw new RuntimeException('Ugrađenu ulogu nije moguće obrisati.');
        }
        $this->mutate(function (array &$roles) use ($key): void {
            if (!isset($roles[$key])) {
                throw new RuntimeException('Uloga nije pronađena.');
            }
            unset($roles[$key]);
        });
    }

    private function mutate(callable $change): void
    {
        $file = fopen($this->path(), 'c+');
        if ($file === false) {
            throw new RuntimeException('Uloge nije moguće sačuvati.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Uloge nije moguće zaključati.');
            }
            $content = stream_get_contents($file);
            $roles = $content === '' ? [] : json_decode($content, true);
            if (!is_array($roles)) {
                throw new RuntimeException('Podaci o ulogama nisu ispravni.');
            }
            $change($roles);
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function sections(): array
    {
        $menu = json_decode((string) file_get_contents(base_path('resources/data/menu-data/mainMenu.json')), true);
        $sections = [];
        $heading = 'Općenito';
        foreach ($menu['menu'] ?? [] as $item) {
            if (isset($item['navheader'])) {
                $translated = __('locale.' . $item['navheader']);
                $heading = $translated === 'locale.' . $item['navheader'] ? $item['navheader'] : $translated;
                $sections[$heading] ??= [];
            } elseif (!empty($item['url']) && ($item['slug'] ?? '') !== 'app-role-list') {
                $translated = __('locale.' . $item['name']);
                $sections[$heading][] = [
                    'slug' => self::menuItemKey($item),
                    'name' => $translated === 'locale.' . $item['name'] ? $item['name'] : $translated,
                ];
            }
        }
        return array_filter($sections);
    }

    public function allowedSlugs(): array
    {
        return array_values(array_merge(...array_map(
            fn (array $section) => array_column($section, 'slug'),
            array_values($this->sections())
        )));
    }
}
