<?php

namespace App\Features\Users\Models;

use App\Features\Admin\Concerns\HasTwoFactorAuthentication;
use App\Features\Admin\Support\AdminRole;
use App\Features\Blocking\Models\Block;
use App\Features\Devices\Models\Device;
use App\Features\Followers\Models\Follow;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Profiles\Models\Profile;
use App\Features\Reports\Models\Report;
use App\Features\Settings\Models\UserSetting;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\Wallet\Models\Wallet;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTwoFactorAuthentication, HasUuids, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BANNED = 'banned';

    public const STATUS_DELETED = 'deleted';

    protected $table = 'users';

    protected $fillable = [
        'email', 'username', 'firebase_uid', 'is_guest', 'is_admin', 'status',
        'password', 'email_verified_at', 'last_login_at',
        'banned_at', 'ban_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'firebase_uid',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'is_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if ($this->status === self::STATUS_BANNED || $this->is_guest) {
            return false;
        }

        return $this->is_admin || $this->hasAnyRole(AdminRole::all());
    }

    public function getFilamentName(): string
    {
        return (string) ($this->username ?: $this->email ?: 'Admin');
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED || $this->banned_at !== null;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    public function following(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function socialStatus(): HasOne
    {
        return $this->hasOne(UserStatus::class);
    }

    public function hostedLiveRooms(): HasMany
    {
        return $this->hasMany(LiveRoom::class, 'host_id');
    }

    public function liveViewerships(): HasMany
    {
        return $this->hasMany(LiveViewer::class);
    }

    public function liveMessages(): HasMany
    {
        return $this->hasMany(LiveMessage::class);
    }

    public function liveChatModeratorships(): HasMany
    {
        return $this->hasMany(LiveChatModerator::class);
    }

    public function sentGifts(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'sender_id');
    }

    public function receivedGifts(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'receiver_id');
    }
}
