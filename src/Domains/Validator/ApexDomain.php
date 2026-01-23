<?php

namespace Utopia\Domains\Validator;

use Utopia\Domains\Domain;
use Utopia\Domains\Validator\PublicDomain;

/**
 *
 * Validate that a domain is a public apex domain
 */
class ApexDomain extends PublicDomain
{
    /**
     * Returns error message when validation fails
     */
    public function getDescription(): string
    {
        return 'Value must be a public apex domain';
    }

    /**
     * Validate that the domain is a public apex domain
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value): bool
    {
        $valid = parent::isValid($value);

        if (!$valid) {
            return $valid;
        }

        $domain = new Domain($value);
        return $domain->getApex() === $value;
    }
}