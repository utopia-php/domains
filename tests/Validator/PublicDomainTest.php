<?php

namespace Utopia\Domains\Validator;

use PHPUnit\Framework\TestCase;

class PublicDomainTest extends TestCase
{
    protected ?PublicDomain $domain;

    public function setUp(): void
    {
        $this->domain = new PublicDomain();
    }

    public function tearDown(): void
    {
        $this->domain = null;
    }

    public function testIsValid(): void
    {
        $this->assertSame('Value must be a public domain', $this->domain->getDescription());
        // Known public domains
        $this->assertSame(true, $this->domain->isValid('example.com'));
        $this->assertSame(true, $this->domain->isValid('google.com'));
        $this->assertSame(true, $this->domain->isValid('bbc.co.uk'));
        $this->assertSame(true, $this->domain->isValid('appwrite.io'));
        $this->assertSame(true, $this->domain->isValid('usa.gov'));
        $this->assertSame(true, $this->domain->isValid('stanford.edu'));

        // URLs
        $this->assertSame(true, $this->domain->isValid('http://google.com'));
        $this->assertSame(true, $this->domain->isValid('http://www.google.com'));
        $this->assertSame(true, $this->domain->isValid('https://example.com'));

        // Private domains
        $this->assertSame(false, $this->domain->isValid('localhost'));
        $this->assertSame(false, $this->domain->isValid('http://localhost'));
        $this->assertSame(false, $this->domain->isValid('sub.demo.localhost'));
        $this->assertSame(false, $this->domain->isValid('test.app.internal'));
        $this->assertSame(false, $this->domain->isValid('home.local'));
        $this->assertSame(false, $this->domain->isValid('qa.testing.internal'));
        $this->assertSame(false, $this->domain->isValid('wiki.team.local'));
        $this->assertSame(false, $this->domain->isValid('example.test'));
    }

    public function testAllowDomains(): void
    {
        // Adding localhost to allowed domains
        PublicDomain::allow(['localhost']);

        // Now localhost should be valid
        $this->assertSame(true, $this->domain->isValid('localhost'));
        $this->assertSame(true, $this->domain->isValid('http://localhost'));
        $this->assertSame(false, $this->domain->isValid('test.app.internal'));

        // Adding more domains to allowed domains
        PublicDomain::allow(['test.app.internal', 'home.local']);

        // Now these domains should be valid
        $this->assertSame(true, $this->domain->isValid('test.app.internal'));
        $this->assertSame(true, $this->domain->isValid('home.local'));
    }
}
