<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Registra nel registro attività le modifiche fatte da venditore e collaboratori.
 * Le operazioni dei clienti (es. giacenza scalata dal checkout) non vengono tracciate.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->logActivity('created'));
        static::updated(fn ($model) => $model->logActivity('updated', array_keys(array_diff_key($model->getChanges(), ['updated_at' => true]))));
        static::deleted(fn ($model) => $model->logActivity('deleted'));
    }

    /**
     * @param  array<int, string>  $changedFields
     */
    protected function logActivity(string $event, array $changedFields = []): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! ($user->isSeller() || $user->isCollaborator())) {
            return;
        }

        if ($event === 'updated' && $changedFields === []) {
            return;
        }

        app(ActivityLogger::class)->log(static::activityPrefix().'.'.$event, $this, array_filter([
            'name' => $this->getAttribute('name'),
            'fields' => $changedFields,
        ]));
    }

    protected static function activityPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }
}
