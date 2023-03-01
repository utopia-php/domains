<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Adapter as DomainsAdapter;

abstract class Adapter extends DomainsAdapter
{
    abstract public function available(string $domain): bool;

    abstract public function purchase(string $domain, array $details): array;

    abstract public function suggest(array $query, array $tlds = [], $minLength = 1, $maxLength = 100): array;

    abstract public function tlds(): array;

    abstract public function domain(string $domain): array;

    abstract public function renew(string $domain, int $years): array;

    abstract public function transfer(string $domain, array $details): array;
}
