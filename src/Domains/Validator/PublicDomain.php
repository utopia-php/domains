<?php

namespace Utopia\Domains\Validator;

use Utopia\Domains\Domain;
use Utopia\Validator\URL;

/**
 * PublicDomain
 *
 * Validate that a URL has a public domain
 */
class PublicDomain extends URL
{
    /**
     * Is valid
     *
     * Validation will pass when $value is valid URL and has a public domain
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value): bool
    {
        if (\filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        if (!empty($this->allowedSchemes) && !\in_array(\parse_url($value, PHP_URL_SCHEME), $this->allowedSchemes)) {
            return false;
        }

        $domain = new Domain(\parse_url($value, PHP_URL_HOST));
        if (!$domain->isKnown()) {
            return false;
        }

        return true;
    }
}