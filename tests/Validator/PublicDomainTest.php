<?php

namespace Utopia\Domains\Validator;

use PHPUnit\Framework\TestCase;

class PublicDomainTest extends TestCase
{
    protected ?PublicDomain $url;

    public function setUp(): void
    {
        $this->url = new PublicDomain();
    }

    public function tearDown(): void
    {
        $this->url = null;
    }

    public function testIsValid(): void
    {
        $this->assertEquals('Value must be a valid URL', $this->url->getDescription());
        $this->assertEquals(true, $this->url->isValid('http://example.com'));
        $this->assertEquals(true, $this->url->isValid('https://example.com'));
        $this->assertEquals(true, $this->url->isValid('htts://example.com')); // does not validate protocol
        $this->assertEquals(false, $this->url->isValid('example.com')); // though, requires some kind of protocol
        $this->assertEquals(false, $this->url->isValid('http:/example.com'));
        $this->assertEquals(true, $this->url->isValid('http://exa-mple.com'));
        $this->assertEquals(false, $this->url->isValid('htt@s://example.com'));
        $this->assertEquals(true, $this->url->isValid('http://www.example.com/foo%2\u00c2\u00a9zbar'));
        $this->assertEquals(true, $this->url->isValid('http://www.example.com/?q=%3Casdf%3E'));
        $this->assertEquals(false, $this->url->isValid('http://sub.demo.localhost'));
        $this->assertEquals(true, $this->url->isValid('https://sub.example.com.nom.br'));
        $this->assertEquals(false, $this->url->isValid('http://localhost'));
    }
}
