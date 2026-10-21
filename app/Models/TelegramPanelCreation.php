<?php

namespace Pterodactyl\Models;

/**
 * Pterodactyl\Models\TelegramPanelCreation.
 *
 * Audit log of every panel user + server created via the Telegram
 * bot. Stored so the admin can see who created what, and so the
 * bot can list/delete servers it created.
 *
 * @property int $id
 * @property int $telegram_user_id
 * @property string|null $telegram_username
 * @property int|null $panel_user_id
 * @property string|null $panel_username
 * @property int|null $server_id
 * @property string|null $server_name
 * @property int|null $egg_id
 * @property string|null $egg_name
 * @property int $memory_mb
 * @property int $disk_mb
 * @property string $status  // created|deleted|failed
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TelegramPanelCreation extends Model
{
    protected $table = 'telegram_panel_creations';

    protected bool $skipValidation = true;

    protected $fillable = [
        'telegram_user_id',
        'telegram_username',
        'panel_user_id',
        'panel_username',
        'server_id',
        'server_name',
        'egg_id',
        'egg_name',
        'memory_mb',
        'disk_mb',
        'status',
        'error_message',
    ];

    protected $casts = [
        'telegram_user_id' => 'integer',
        'panel_user_id' => 'integer',
        'server_id' => 'integer',
        'egg_id' => 'integer',
        'memory_mb' => 'integer',
        'disk_mb' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
