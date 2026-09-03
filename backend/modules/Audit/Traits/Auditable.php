<?php

namespace Modules\Audit\Traits;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Services\AuditService;

trait Auditable
{
    /**
     * Boot the Auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $moduleName = static::resolveModuleName();
            AuditService::log(
                action: 'created',
                module: $moduleName,
                description: class_basename($model) . ' #' . $model->getKey() . ' was created.',
                entity: $model,
                oldValues: null,
                newValues: $model->getAttributes()
            );
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);

            // Ignore updated_at timestamp only changes
            if (count($changes) === 1 && isset($changes['updated_at'])) {
                return;
            }

            $moduleName = static::resolveModuleName();
            AuditService::log(
                action: 'updated',
                module: $moduleName,
                description: class_basename($model) . ' #' . $model->getKey() . ' was updated.',
                entity: $model,
                oldValues: $original,
                newValues: $changes
            );
        });

        static::deleted(function (Model $model) {
            $moduleName = static::resolveModuleName();
            AuditService::log(
                action: 'deleted',
                module: $moduleName,
                description: class_basename($model) . ' #' . $model->getKey() . ' was deleted.',
                entity: $model,
                oldValues: $model->getAttributes(),
                newValues: null
            );
        });
    }

    /**
     * Resolve module name from model namespace.
     */
    protected static function resolveModuleName(): string
    {
        $namespace = static::class;
        if (preg_match('/Modules\\\\([^\\\\]+)/', $namespace, $matches)) {
            return $matches[1];
        }
        return 'Core';
    }
}
