<?php

namespace App\Features\LiveStreaming\Models;

use App\Features\Users\Models\User;
use Database\Factories\LiveViewerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveViewer extends Model
{
    /** @use HasFactory<LiveViewerFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = ['room_id', 'user_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
