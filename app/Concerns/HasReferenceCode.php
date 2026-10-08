<?php

namespace App\Concerns;

trait HasReferenceCode
{
    /**
     * The prefix of this model's human-readable code, e.g. "CAN" for CAN-00042.
     */
    abstract protected static function referenceCodePrefix(): string;

    /**
     * Give every new record a stable code built from its id.
     */
    protected static function bootHasReferenceCode(): void
    {
        static::created(function (self $model): void {
            $model->forceFill(['code' => static::referenceCodeFor((int) $model->getKey())])->saveQuietly();
        });
    }

    public static function referenceCodeFor(int $id): string
    {
        return static::referenceCodePrefix().'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
