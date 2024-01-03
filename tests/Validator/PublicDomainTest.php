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
        $this->assertEquals('Value must be a public domain', $this->domain->getDescription());
        // Known public domains
        $this->assertEquals(true, $this->domain->isValid('example.com'));
        $this->assertEquals(true, $this->domain->isValid('google.com'));
        $this->assertEquals(true, $this->domain->isValid('bbc.co.uk'));
        $this->assertEquals(true, $this->domain->isValid('appwrite.io'));
        $this->assertEquals(true, $this->domain->isValid('usa.gov'));
        $this->assertEquals(true, $this->domain->isValid('stanford.edu'));

        // URLs
        $this->assertEquals(true, $this->domain->isValid('http://google.com'));
        $this->assertEquals(true, $this->domain->isValid('http://www.google.com'));
        $this->assertEquals(true, $this->domain->isValid('https://example.com'));

        // Private domains
        $this->assertEquals(false, $this->domain->isValid('localhost'));
        $this->assertEquals(false, $this->domain->isValid('http://localhost'));
        $this->assertEquals(false, $this->domain->isValid('sub.demo.localhost'));
        $this->assertEquals(false, $this->domain->isValid('test.app.internal'));
        $this->assertEquals(false, $this->domain->isValid('home.local'));
        $this->assertEquals(false, $this->domain->isValid('qa.testing.internal'));
        $this->assertEquals(false, $this->domain->isValid('wiki.team.local'));
        $this->assertEquals(false, $this->domain->isValid('example.test'));
    }

    public function testAllowDomains(): void
    {
        // Adding localhost to allowed domains
        PublicDomain::allow(['localhost']);

        // Now localhost should be valid
        $this->assertEquals(true, $this->domain->isValid('localhost'));
        $this->assertEquals(true, $this->domain->isValid('http://localhost'));
        $this->assertEquals(false, $this->domain->isValid('test.app.internal'));

        // Adding more domains to allowed domains
        PublicDomain::allow(['test.app.internal', 'home.local']);

        // Now these domains should be valid
        $this->assertEquals(true, $this->domain->isValid('test.app.internal'));
        $this->assertEquals(true, $this->domain->isValid('home.local'));
    }
}
