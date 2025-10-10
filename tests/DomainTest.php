<?php
/**
 * Utopia PHP Framework
 *
 *
 * @link https://github.com/utopia-php/framework
 *
 * @author Eldad Fux <eldad@appwrite.io>
 *
 * @version 1.0 RC4
 *
 * @license The MIT License (MIT) <http://www.opensource.org/licenses/mit-license.php>
 */

namespace Utopia\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Utopia\Domains\Domain;

class DomainTest extends TestCase
{
    public function testEdgecaseDomains(): void
    {
        $domain = new Domain('httpmydomain.com');
        $this->assertEquals('httpmydomain.com', $domain->getRegisterable());
    }

    public function testEdgecaseDomainsError(): void
    {
        try {
            $domain = new Domain('http://httpmydomain.com');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals(
                "'http://httpmydomain.com' must be a valid domain or hostname",
                $e->getMessage()
            );
        }
    }

    public function testEdgecaseDomainsError2(): void
    {
        try {
            $domain = new Domain('https://httpmydomain.com');
            $this->fail("Expected exception not thrown.");
        } catch (Exception $e) {
            $this->assertEquals("'https://httpmydomain.com' must be a valid domain or hostname", $e->getMessage());
        }
    }

    public function testExampleCoUk(): void
    {
        $domain = new Domain('demo.example.co.uk');

        $this->assertEquals('demo.example.co.uk', $domain->get());
        $this->assertEquals('uk', $domain->getTLD());
        $this->assertEquals('example.co.uk', $domain->getApex());
        $this->assertEquals('co.uk', $domain->getSuffix());
        $this->assertEquals('example.co.uk', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testSubSubExampleCoUk(): void
    {
        $domain = new Domain('subsub.demo.example.co.uk');

        $this->assertEquals('subsub.demo.example.co.uk', $domain->get());
        $this->assertEquals('uk', $domain->getTLD());
        $this->assertEquals('example.co.uk', $domain->getApex());
        $this->assertEquals('co.uk', $domain->getSuffix());
        $this->assertEquals('example.co.uk', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('subsub.demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testLocalhost(): void
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

    public function testDemoLocalhost(): void
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

    public function testSubSubDemoLocalhost(): void
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

    public function testSubDemoLocalhost(): void
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

    public function testUTF(): void
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

    public function testUTFSubdomain(): void
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

    public function testPrivateTLD(): void
    {
        $domain = new Domain('blog.potager.org');

        $this->assertEquals('blog.potager.org', $domain->get());
        $this->assertEquals('org', $domain->getTLD());
        $this->assertEquals('blog.potager.org', $domain->getApex());
        $this->assertEquals('potager.org', $domain->getSuffix());
        $this->assertEquals('blog.potager.org', $domain->getRegisterable());
        $this->assertEquals('blog', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(true, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testHTTPException1(): void
    {
        try {
            new Domain('http://www.facbook.com');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testHTTPException2(): void
    {
        try {
            new Domain('http://facbook.com');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testHTTPSException1(): void
    {
        try {
            new Domain('https://www.facbook.com');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testHTTPSException2(): void
    {
        try {
            new Domain('https://facbook.com');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testExampleExampleCk(): void
    {
        $domain = new Domain('example.example.ck');

        $this->assertEquals('example.example.ck', $domain->get());
        $this->assertEquals('ck', $domain->getTLD());
        $this->assertEquals('example.ck', $domain->getSuffix());
        $this->assertEquals('example.example.ck', $domain->getApex());
        $this->assertEquals('example.example.ck', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testSubSubExampleExampleCk(): void
    {
        $domain = new Domain('subsub.demo.example.example.ck');

        $this->assertEquals('subsub.demo.example.example.ck', $domain->get());
        $this->assertEquals('ck', $domain->getTLD());
        $this->assertEquals('example.example.ck', $domain->getApex());
        $this->assertEquals('example.ck', $domain->getSuffix());
        $this->assertEquals('example.example.ck', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('subsub.demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testWwwCk(): void
    {
        $domain = new Domain('www.ck');

        $this->assertEquals('www.ck', $domain->get());
        $this->assertEquals('ck', $domain->getTLD());
        $this->assertEquals('www.ck', $domain->getApex());
        $this->assertEquals('ck', $domain->getSuffix());
        $this->assertEquals('www.ck', $domain->getRegisterable());
        $this->assertEquals('www', $domain->getName());
        $this->assertEquals('', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testSubSubWwwCk(): void
    {
        $domain = new Domain('subsub.demo.www.ck');

        $this->assertEquals('subsub.demo.www.ck', $domain->get());
        $this->assertEquals('ck', $domain->getTLD());
        $this->assertEquals('www.ck', $domain->getApex());
        $this->assertEquals('ck', $domain->getSuffix());
        $this->assertEquals('www.ck', $domain->getRegisterable());
        $this->assertEquals('www', $domain->getName());
        $this->assertEquals('subsub.demo', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testWildcardNomBr(): void
    {
        $domain = new Domain('sub.example.com.nom.br');

        $this->assertEquals('sub.example.com.nom.br', $domain->get());
        $this->assertEquals('br', $domain->getTLD());
        $this->assertEquals('example.com.nom.br', $domain->getApex());
        $this->assertEquals('com.nom.br', $domain->getSuffix());
        $this->assertEquals('example.com.nom.br', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testWildcardKawasakiJp(): void
    {
        $domain = new Domain('sub.example.com.kawasaki.jp');

        $this->assertEquals('sub.example.com.kawasaki.jp', $domain->get());
        $this->assertEquals('jp', $domain->getTLD());
        $this->assertEquals('example.com.kawasaki.jp', $domain->getApex());
        $this->assertEquals('com.kawasaki.jp', $domain->getSuffix());
        $this->assertEquals('example.com.kawasaki.jp', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testExceptionKawasakiJp(): void
    {
        $domain = new Domain('sub.city.kawasaki.jp');

        $this->assertEquals('sub.city.kawasaki.jp', $domain->get());
        $this->assertEquals('jp', $domain->getTLD());
        $this->assertEquals('kawasaki.jp', $domain->getSuffix());
        $this->assertEquals('city.kawasaki.jp', $domain->getRegisterable());
        $this->assertEquals('city', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(true, $domain->isICANN());
        $this->assertEquals(false, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testWildcardPrivateDomain(): void
    {
        $domain = new Domain('sub.example.com.dev.adobeaemcloud.com');

        $this->assertEquals('sub.example.com.dev.adobeaemcloud.com', $domain->get());
        $this->assertEquals('com', $domain->getTLD());
        $this->assertEquals('com.dev.adobeaemcloud.com', $domain->getSuffix());
        $this->assertEquals('example.com.dev.adobeaemcloud.com', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(true, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }

    public function testPrivateDomain(): void
    {
        $domain = new Domain('sub.example.adobeaemcloud.net');

        $this->assertEquals('sub.example.adobeaemcloud.net', $domain->get());
        $this->assertEquals('net', $domain->getTLD());
        $this->assertEquals('adobeaemcloud.net', $domain->getSuffix());
        $this->assertEquals('example.adobeaemcloud.net', $domain->getRegisterable());
        $this->assertEquals('example', $domain->getName());
        $this->assertEquals('sub', $domain->getSub());
        $this->assertEquals(true, $domain->isKnown());
        $this->assertEquals(false, $domain->isICANN());
        $this->assertEquals(true, $domain->isPrivate());
        $this->assertEquals(false, $domain->isTest());
    }
}
