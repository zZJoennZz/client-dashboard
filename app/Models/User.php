<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    public const ACCOUNT_TYPE_REGULAR = 'regular';

    public const ACCOUNT_TYPE_ADMINISTRATOR = 'administrator';

    public const ACCOUNT_TYPE_SUPER_ADMIN = 'super_admin';

    public const SYSTEM_ADMIN_MODULES = [
        'overview',
        'businesses',
        'accounts',
        'memberships',
        'content',
        'reports',
        'events',
        'marketing',
        'orders',
        'billing',
        'analytics',
        'engagement',
        'communications',
        'audit',
        'settings',
    ];

    public const DASHBOARD_MODULES = [
        'overview',
        'business',
        'membership',
        'reports',
        'events',
        'resources',
        'guidelines',
        'marketing',
        'settings',
        'users',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_type === self::ACCOUNT_TYPE_SUPER_ADMIN;
    }

    public function isAdministrator(): bool
    {
        return $this->account_type === self::ACCOUNT_TYPE_ADMINISTRATOR;
    }

    public function isSystemAdministrator(): bool
    {
        return $this->isAdministrator() || $this->isSuperAdmin();
    }

    public function isBusinessAdministrator(): bool
    {
        if ($this->account_type !== self::ACCOUNT_TYPE_REGULAR) {
            return false;
        }

        return $this->businessAssociations()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereIn('role', ['owner', 'admin'])
                    ->orWhereIn('access_level', ['owner', 'admin']);
            })
            ->exists();
    }

    public function canAccessDashboardModule(string $module, string $ability = 'read'): bool
    {
        if (! in_array($module, self::DASHBOARD_MODULES, true) || ! in_array($ability, ['read', 'write'], true)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($this->isSystemAdministrator()) {
            return false;
        }

        $isAdministrator = $this->isBusinessAdministrator();
        if ($module === 'users' && ! $isAdministrator) {
            return false;
        }
        if (! $isAdministrator) {
            return true;
        }

        $permission = $this->dashboardModulePermissions()
            ->where('module', $module)
            ->first();

        if ($permission === null) {
            return $module !== 'users';
        }

        return (bool) $permission->{'can_'.$ability}
            && ($ability !== 'write' || $permission->can_read);
    }

    /**
     * @return array<string, array{read: bool, write: bool}>
     */
    public function dashboardModuleAccess(): array
    {
        $access = array_fill_keys(self::DASHBOARD_MODULES, ['read' => true, 'write' => true]);
        $access['users'] = ['read' => false, 'write' => false];
        if ($this->isSuperAdmin()) {
            $access['users'] = ['read' => true, 'write' => true];

            return $access;
        }
        if ($this->isSystemAdministrator()) {
            return array_fill_keys(self::DASHBOARD_MODULES, ['read' => false, 'write' => false]);
        }
        if (! $this->isBusinessAdministrator()) {
            return $access;
        }

        foreach ($this->dashboardModulePermissions as $permission) {
            $access[$permission->module] = [
                'read' => $permission->can_read,
                'write' => $permission->can_write,
            ];
        }

        return $access;
    }

    public function canAccessSystemAdminModule(string $module, string $ability = 'read'): bool
    {
        if (! in_array($module, self::SYSTEM_ADMIN_MODULES, true)
            || ! in_array($ability, ['read', 'write'], true)
            || ! $this->isSystemAdministrator()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $permission = $this->dashboardModulePermissions()
            ->where('module', 'system_'.$module)
            ->first();

        if ($permission === null) {
            return true;
        }

        return (bool) $permission->{'can_'.$ability}
            && ($ability !== 'write' || $permission->can_read);
    }

    /**
     * @return array<string, array{read: bool, write: bool}>
     */
    public function systemAdminModuleAccess(): array
    {
        $access = array_fill_keys(self::SYSTEM_ADMIN_MODULES, ['read' => true, 'write' => true]);
        if ($this->isSuperAdmin()) {
            return $access;
        }
        if (! $this->isAdministrator()) {
            return array_fill_keys(self::SYSTEM_ADMIN_MODULES, ['read' => false, 'write' => false]);
        }

        foreach ($this->dashboardModulePermissions as $permission) {
            $module = str_starts_with($permission->module, 'system_')
                ? substr($permission->module, strlen('system_'))
                : null;
            if ($module !== null && in_array($module, self::SYSTEM_ADMIN_MODULES, true)) {
                $access[$module] = [
                    'read' => $permission->can_read,
                    'write' => $permission->can_write && $permission->can_read,
                ];
            }
        }

        return $access;
    }

    public function dashboardModulePermissions(): HasMany
    {
        return $this->hasMany(DashboardModulePermission::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->withPivot('role', 'access_level', 'status', 'is_primary', 'invited_at', 'accepted_at')
            ->withTimestamps();
    }

    public function businessAssociations(): HasMany
    {
        return $this->hasMany(BusinessUser::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function recordedOrderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'recorded_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
