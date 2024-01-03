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
     * @var array
     */
    protected static $allowedDomains = [];
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
     * Validation will pass when $value is either a known domain or in the list of allowed domains
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value): bool
    {
        // Extract domain from URL if provided
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value = parse_url($value, PHP_URL_HOST);
        }

        $domain = new Domain($value);

        return $domain->isKnown() || in_array($domain->get(), self::$allowedDomains);
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

    /**
     * Allow domains
     *
     * Add domains to the allowed domains array
     *
     * @param array $domains
     */
    public static function allow(array $domains): void
    {
        self::$allowedDomains = array_merge(self::$allowedDomains, $domains);
    }
}
