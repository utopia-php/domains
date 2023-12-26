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
        $this->assertEquals(true, $this->domain->isValid('example.com'));
        $this->assertEquals(true, $this->domain->isValid('google.com'));
        $this->assertEquals(true, $this->domain->isValid('bbc.co.uk'));
        $this->assertEquals(true, $this->domain->isValid('appwrite.io'));
        $this->assertEquals(true, $this->domain->isValid('usa.gov'));
        $this->assertEquals(true, $this->domain->isValid('stanford.edu'));
        $this->assertEquals(false, $this->domain->isValid('localhost'));
        $this->assertEquals(false, $this->domain->isValid('sub.demo.localhost'));
        $this->assertEquals(false, $this->domain->isValid('test.app.internal'));
        $this->assertEquals(false, $this->domain->isValid('home.local'));
        $this->assertEquals(false, $this->domain->isValid('qa.testing.internal'));
        $this->assertEquals(false, $this->domain->isValid('wiki.team.local'));
        $this->assertEquals(false, $this->domain->isValid('example.test'));
    }
}
