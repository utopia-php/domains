<?php

namespace Utopia\Domains\Validator;

use PHPUnit\Framework\TestCase;

class ApexDomainTest extends TestCase
{
    protected ?ApexDomain $domain;

    public function setUp(): void
    {
        $this->domain = new ApexDomain();
    }

    public function tearDown(): void
    {
        $this->domain = null;
    }

    public function testIsValid(): void
    {
        // Description
        $this->assertEquals('Value must be a public apex domain', $this->domain->getDescription());

        // Valid apex domains
        $this->assertTrue($this->domain->isValid('example.com'));
        $this->assertTrue($this->domain->isValid('google.com'));
        $this->assertTrue($this->domain->isValid('bbc.co.uk'));
        $this->assertTrue($this->domain->isValid('appwrite.io'));
        $this->assertTrue($this->domain->isValid('usa.gov'));
        $this->assertTrue($this->domain->isValid('stanford.edu'));

        // Invalid apex domains
        $this->assertFalse($this->domain->isValid('blog.bbc.co.uk'));
        $this->assertFalse($this->domain->isValid('www.google.com'));
        $this->assertFalse($this->domain->isValid('test.usa.gov'));
        $this->assertFalse($this->domain->isValid('test.com.test'));
    }
}
