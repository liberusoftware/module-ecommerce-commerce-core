<?php

namespace Liberu\Ecommerce\CommerceCore\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\CommerceCore\Database\Factories\ChannelDomainFactory;

/**
 * One hostname a Channel answers on. Hostname only — no scheme, no port.
 *
 * The trusted-host cache this table feeds is the host application's, and so is
 * the listener that clears it: a module that reaches into `App\Http\Middleware`
 * is a module with one possible consumer. The invalidation is still registered
 * once, on the model, rather than at the call sites that add domains — see
 * `AppServiceProvider` — because a cache invalidated by whoever remembers is a
 * cache that goes stale, and stale here means a merchant adds a domain and
 * their storefront answers 400 until something else happens to clear it.
 *
 * @property int $id
 * @property int $channel_id
 * @property string $host
 * @property bool $is_primary
 */
class ChannelDomain extends Model
{
    use HasFactory;

    protected $fillable = ['channel_id', 'host', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Hosts are stored and compared in one shape, so that `EXAMPLE.com:8000`
     * and `example.com` are the same hostname rather than two.
     */
    public static function normalise(string $host): string
    {
        return strtolower(trim(explode(':', trim($host), 2)[0]));
    }

    public function setHostAttribute(string $value): void
    {
        $this->attributes['host'] = self::normalise($value);
    }

    protected static function newFactory(): Factory
    {
        return ChannelDomainFactory::new();
    }
}
