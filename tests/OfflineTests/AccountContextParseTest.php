<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\AccountGateway;

use GuzzleHttp\Psr7\Request as GuzzlePsrRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class AccountContextParseTest extends \PHPUnit_Framework_TestCase {

    public function testParsePsrRequest() {
        $request = new GuzzlePsrRequest('GET', '/', [
            'C-AAUID' => '1234123412341234', 'C-Access-Token' => 'test'
        ]);
        
        $context = AccountContext::fromPsrRequest($request);
        $this->assertNotNull($context);
        $this->assertEquals('1234123412341234', AAUIDParser::getAAUID($context->getAAUID()));
    }

    public function testParseSymfonyRequest() {
        $request = SymfonyRequest::create('/', 'GET', [], [], [], [
            'HTTP_C_AAUID' => '1234123412341234', 'HTTP_C_ACCESS_TOKEN' => 'test'
        ]);

        $context = AccountContext::fromSymfonyRequest($request);
        $this->assertNotNull($context);
        $this->assertEquals('1234123412341234', AAUIDParser::getAAUID($context->getAAUID()));
    }

}
