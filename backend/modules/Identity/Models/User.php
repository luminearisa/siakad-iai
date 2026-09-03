<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Traits\HasRolesAndPermissions;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Get the student profile associated with the user.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get the lecturer profile associated with the user.
     */
    public function lecturer(): HasOne
    {
        return $this->hasOne(Lecturer::class);
    }
}
