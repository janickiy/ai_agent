<?php

declare(strict_types=1);

namespace App\DTO;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
abstract readonly class DataTransferObject implements Arrayable
{
    /** @return array<string, mixed> */
    abstract public function toArray(): array;
}
