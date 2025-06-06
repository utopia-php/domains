<?php

namespace Utopia\Domains;

use Exception;

class Domain
{
    /**
     * @var array<string, array{suffix: string, type: string, comments: string[]}>
     */
    protected static $list = [];

    /**
     * Domain
     *
     * @var string
     */
    protected $domain = '';

    /**
     * TLD
     *
     * @var string
     */
    protected $TLD = '';

    /**
     * Suffix
     *
     * @var string
     */
    protected $suffix = '';

    /**
     * Name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Sub Domain
     *
     * @var string
     */
    protected $sub = '';

    /**
     * PSL rule matching suffix
     *
     * @var string
     */
    protected $rule = '';

    /**
     * Domain Parts
     *
     * @var string[]
     */
    protected $parts = [];

    /**
     * Domain constructor.
     */
    public function __construct(string $domain)
    {
        if ((strpos($domain, 'http://') === 0) || (strpos($domain, 'https://') === 0)) {
            throw new Exception("'{$domain}' must be a valid domain or hostname");
        }

        $this->domain = \mb_strtolower($domain);
        $this->parts = \explode('.', $this->domain);

        if (empty(self::$list)) {
            self::$list = include __DIR__.'/../../data/data.php';
        }
    }

    /**
     * Return domain
     */
    public function get(): string
    {
        return $this->domain;
    }

    /**
     * Return apex domain
     */
    public function getApex(): string
    {
        return $this->getName() . '.' . $this->getSuffix();
    }

    /**
     * Return top level domain
     */
    public function getTLD(): string
    {
        if ($this->TLD) {
            return $this->TLD;
        }

        if (empty($this->parts)) {
            return '';
        }

        $this->TLD = \end($this->parts);

        return $this->TLD;
    }

    /**
     * Returns domain public suffix
     */
    public function getSuffix(): string
    {
        if ($this->suffix) {
            return $this->suffix;
        }

        for ($i = 0; $i < count($this->parts); $i++) {
            $joined = \implode('.', \array_slice($this->parts, $i));
            $next = \implode('.', \array_slice($this->parts, $i + 1));
            $exception = '!'.$joined;
            $wildcard = '*.'.$next;

            if (\array_key_exists($exception, self::$list)) {
                $this->suffix = $next;
                $this->rule = $exception;

                return $next;
            }

            if (\array_key_exists($joined, self::$list)) {
                $this->suffix = $joined;
                $this->rule = $joined;

                return $joined;
            }

            if (\array_key_exists($wildcard, self::$list)) {
                $this->suffix = $joined;
                $this->rule = $wildcard;

                return $joined;
            }
        }

        return '';
    }

    public function getRule(): string
    {
        if (! $this->rule) {
            $this->getSuffix();
        }
        return $this->rule;
    }

    /**
     * Returns registerable domain name
     */
    public function getRegisterable(): string
    {
        if (! $this->isKnown()) {
            return '';
        }

        $registerable = $this->getName().'.'.$this->getSuffix();

        return $registerable;
    }

    /**
     * Returns domain name
     */
    public function getName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $suffix = $this->getSuffix();
        $suffix = (! empty($suffix)) ? '.'.$suffix : '.'.$this->getTLD();

        $name = \explode('.', \mb_substr($this->domain, 0, \mb_strlen($suffix) * -1));

        $this->name = \end($name);

        return $this->name;
    }

    /**
     * Returns sub-domain name
     */
    public function getSub(): string
    {
        $name = $this->getName();
        $name = (! empty($name)) ? '.'.$name : '';

        $suffix = $this->getSuffix();
        $suffix = (! empty($suffix)) ? '.'.$suffix : '.'.$this->getTLD();

        $domain = $name.$suffix;

        $sub = \explode('.', \mb_substr($this->domain, 0, \mb_strlen($domain) * -1));

        $this->sub = \implode('.', $sub);

        return $this->sub;
    }

    /**
     * Returns true if the public suffix is found;
     */
    public function isKnown(): bool
    {
        if (\array_key_exists($this->getRule(), self::$list)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the public suffix is found using ICANN domains section
     */
    public function isICANN(): bool
    {
        if (isset(self::$list[$this->getRule()]) && self::$list[$this->getRule()]['type'] === 'ICANN') {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the public suffix is found using PRIVATE domains section
     */
    public function isPrivate(): bool
    {
        if (isset(self::$list[$this->getRule()]) && self::$list[$this->getRule()]['type'] === 'PRIVATE') {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the public suffix is reserved for testing purpose
     */
    public function isTest(): bool
    {
        if (\in_array($this->getTLD(), ['test', 'localhost'])) {
            return true;
        }

        return false;
    }
}
