<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pterodactyl\Models\TelegramBotUser.
 *
 * A Telegram user that the panel owner (admin ID 1) has granted
 * access to the auto-panel-creation Telegram bot.
 *
 * @property int $id
 * @property int $telegram_user_id
 * @property string|null $telegram_username
 * @property string|null $display_name
 * @property bool $is_blocked
 * @property int $panels_created
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\TelegramPanelCreation[] $creations
 */
class TelegramBotUser extends Model
{
    protected $table = 'telegram_bot_users';

    protected bool $skipValidation = true;

    protected $fillable = [
        'telegram_user_id',
        'telegram_username',
        'display_name',
        'is_blocked',
        'panels_created',
        'last_used_at',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'panels_created' => 'integer',
        'telegram_user_id' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function creations(): HasMany
    {
        return $this->hasMany(TelegramPanelCreation::class, 'telegram_user_id', 'telegram_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
