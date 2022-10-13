<?php

namespace Utopia\Tests;

use Exception;
use Utopia\Domains\Domain;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    /**
     * @var Test
     */
    protected $test = null;

    public function testEdgecaseDomains()
    {
        $domain = new Domain('httpmydomain.com');
        $this->assertEquals('httpmydomain.com', $domain->getRegisterable());
    }

    public function testEdgecaseDomainsError()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage("'http://httpmydomain.com' must be a valid domain or hostname");
        $domain = new Domain('http://httpmydomain.com');
    }

    public function testEdgecaseDomainsError2()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage("'https://httpmydomain.com' must be a valid domain or hostname");
        $domain = new Domain('https://httpmydomain.com');
    }

    public function testExampleCoUk()
    {
        $domain = new Domain('demo.example.co.uk');
       
        $this->assertEquals('demo.example.co.uk', $domain->get());
        $this->assertEquals('uk', $domain->getTLD());
        $this->assertEquals('co.uk', $domain->getSuffix());
        $this->assertEquals('example.co.uk', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testSubSubExampleCoUk()
    {
        $domain = new Domain('subsub.demo.example.co.uk');
       
        $this->assertEquals('subsub.demo.example.co.uk', $domain->get());
        $this->assertEquals('uk', $domain->getTLD());
        $this->assertEquals('co.uk', $domain->getSuffix());
        $this->assertEquals('example.co.uk', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('subsub.demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testLocalhost()
    {
        $domain = new Domain('localhost');
       
        $this->assertEquals('localhost', $domain->get());
        $this->assertEquals('localhost', $domain->getTLD());
        $this->assertEquals('', $domain->getSuffix());
        $this->assertEquals('', $domain->getRegisterable());
        $this->assertEquals('', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(false, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(true, $domain->isTest());
    }

    public function testDemoLocalhost()
    {
        $domain = new Domain('demo.localhost');
       
        $this->assertEquals('demo.localhost', $domain->get());
        $this->assertEquals('localhost', $domain->getTLD());
        $this->assertEquals('', $domain->getSuffix());
        $this->assertEquals('', $domain->getRegisterable());
        $this->assertEquals('demo', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(false, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(true, $domain->isTest());
    }

    public function testSubSubDemoLocalhost()
    {
        $domain = new Domain('sub.sub.demo.localhost');
       
        $this->assertEquals('sub.sub.demo.localhost', $domain->get());
        $this->assertEquals('localhost', $domain->getTLD());
        $this->assertEquals('', $domain->getSuffix());
        $this->assertEquals('', $domain->getRegisterable());
        $this->assertEquals('demo', $domain->getName());
        $this->assertEquals('sub.sub', $domain->getSub());
        $this->assertEquals(false, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(true, $domain->isTest());
    }

    public function testSubDemoLocalhost()
    {
        $domain = new Domain('sub.demo.localhost');
       
        $this->assertEquals('sub.demo.localhost', $domain->get());
        $this->assertEquals('localhost', $domain->getTLD());
        $this->assertEquals('', $domain->getSuffix());
        $this->assertEquals('', $domain->getRegisterable());
        $this->assertEquals('demo', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(false, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(true, $domain->isTest());
    }

    public function testUTF()
    {
        $domain = new Domain('אשקלון.קום');
       
        $this->assertEquals('אשקלון.קום', $domain->get());
        $this->assertEquals('קום', $domain->getTLD());
        $this->assertEquals('קום', $domain->getSuffix());
        $this->assertEquals('אשקלון.קום', $domain->getRegisterable());
        $this->assertEquals('אשקלון', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testUTFSubdomain()
    {
        $domain = new Domain('חדשות.אשקלון.קום');
       
        $this->assertEquals('חדשות.אשקלון.קום', $domain->get());
        $this->assertEquals('קום', $domain->getTLD());
        $this->assertEquals('קום', $domain->getSuffix());
        $this->assertEquals('אשקלון.קום', $domain->getRegisterable());
        $this->assertEquals('אשקלון', $domain->getName());
        $this->assertEquals('חדשות', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testPrivateTLD()
    {
        $domain = new Domain('blog.potager.org');
       
        $this->assertEquals('blog.potager.org', $domain->get());
        $this->assertEquals('org', $domain->getTLD());
        $this->assertEquals('potager.org', $domain->getSuffix());
        $this->assertEquals('blog.potager.org', $domain->getRegisterable());
        $this->assertEquals('blog', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(true, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testHTTPException1()
    {
        $this->expectException(Exception::class);

        new Domain('http://www.facbook.com');
    }

    public function testHTTPException2()
    {
        $this->expectException(Exception::class);

        new Domain('http://facbook.com');
    }

    public function testHTTPSException1()
    {
        $this->expectException(Exception::class);

        new Domain('https://www.facbook.com');
    }

    public function testHTTPSException2()
    {
        $this->expectException(Exception::class);

        new Domain('https://facbook.com');
    }
}
