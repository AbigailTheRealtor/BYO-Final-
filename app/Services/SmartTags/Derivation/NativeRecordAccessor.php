<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\NativeMetaValueReader;

/**
 * Reads a native Offer Listing's meta through NativeMetaValueReader, which
 * absorbs the storage-shape differences between Create, Edit and MLS import.
 */
final class NativeRecordAccessor implements StructuredValueAccessor
{
    public function __construct(private readonly NativeMetaValueReader $meta)
    {
    }

    public function reader(): NativeMetaValueReader
    {
        return $this->meta;
    }

    public function boolean(array $rule): ?bool
    {
        return $this->meta->yesNo((string) $rule['field']);
    }

    public function scalar(array $rule): ?string
    {
        return $this->meta->scalar((string) $rule['field']);
    }

    public function values(array $rule): array
    {
        return $this->meta->list((string) $rule['field']);
    }

    public function number(array $rule): ?float
    {
        return $this->meta->number((string) $rule['field']);
    }

    public function flag(array $rule): ?bool
    {
        return $this->meta->flag((string) $rule['field'], (string) ($rule['sub'] ?? ''));
    }

    public function inputsFor(array $rules): array
    {
        $inputs = [];
        foreach ($rules as $rule) {
            $field = (string) ($rule['field'] ?? '');
            if ($field !== '') {
                $inputs[$field] = $this->meta->raw($field);
            }
        }
        ksort($inputs);

        return $inputs;
    }
}
