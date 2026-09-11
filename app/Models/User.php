<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_KONTROLA = 'kontrola';
    public const ROLE_BRAVARIJA = 'bravarija';

    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the user's role
     */
    public function getRole()
    {
        return $this->role ?? 'user';
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role)
    {
        return $this->getRole() === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Check if user is manager
     */
    public function isManager()
    {
        return $this->hasRole('manager');
    }

    /**
     * Check if user is employee
     */
    public function isEmployee()
    {
        return $this->hasRole('employee');
    }

    /**
     * Kontrola and Bravarija deliberately share the regular Korisnik access
     * profile; their only extra behaviour is the scanner priority transition.
     */
    public function hasRegularUserJurisdiction(): bool
    {
        return in_array($this->getRole(), [
            self::ROLE_USER,
            self::ROLE_KONTROLA,
            self::ROLE_BRAVARIJA,
        ], true);
    }

    /**
     * Roles which assign a Pantheon delivery priority when an existing work
     * order is opened from the QR scanner.
     */
    public function scanWorkOrderPriorityRole(): ?string
    {
        return match ($this->getRole()) {
            self::ROLE_KONTROLA, self::ROLE_BRAVARIJA => $this->getRole(),
            default => null,
        };
    }

    /**
     * Check if user can access AI order module.
     */
    public function canAccessAiOrderModule(): bool
    {
        return $this->isAdmin()
            || $this->isManager()
            || $this->isEmployee();
    }

    public function isQlaDevUser(): bool
    {
        $username = strtolower(trim((string) ($this->username ?? '')));
        $email = strtolower(trim((string) ($this->email ?? '')));

        return $username === 'qla.dev'
            || $email === 'colakovic.vedad@qla.dev';
    }
}
