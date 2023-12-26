<?php

namespace Utopia\Domains\Validator;

use Utopia\Domains\Domain;
use Utopia\Validator;

/**
 * PublicDomain
 *
 * Validate that a domain is a public domain
 */
class PublicDomain extends Validator
{
    /**
     * Get Description
     *
     * Returns validator description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Value must be a public domain';
    }

    /**
     * Is valid
     *
     * Validation will pass when $value is a public domain
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value): bool
    {
        $domain = new Domain($value);
        if (!$domain->isKnown()) {
            return false;
        }

        return true;
    }

    /**
     * Is array
     *
     * Function will return true if object is array.
     *
     * @return bool
     */
    public function isArray(): bool
    {
        return false;
    }

    /**
     * Get Type
     *
     * Returns validator type.
     *
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_STRING;
    }
}
