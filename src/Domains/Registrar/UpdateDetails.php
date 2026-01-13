<?php

namespace Utopia\Domains\Registrar;

abstract class UpdateDetails
{
    /**
     * Convert details to array format
     *
     * @return array
     */
    abstract public function toArray(): array;
}
