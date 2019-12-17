<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\AccountGateway;

use ML\IRI\IRI;

class AccountContextTest extends \PHPUnit_Framework_TestCase {

    private $context;

    protected function setUp() {
        $this->context = new AccountContext(new IRI('aauid:aaaabbbbccccdddd'), 'DUMMY');
    }

    public function testDefaultGatewayBaseURL() {
        $this->assertEquals('https://aaaabbbbccccdddd.aauid.net', $this->context->getClient()->getConfig('base_uri'));
    }

    public function testSetAccountGatewayBaseURLTemplateWithPlaceholder() {
        $this->context->setAccountGatewayBaseURLTemplate('http://{aauid}.localhost');
        $this->assertEquals('http://aaaabbbbccccdddd.localhost', $this->context->getClient()->getConfig('base_uri'));
    }

    public function testSetAccountGatewayBaseURLTemplateWithoutPlaceholder() {
        $this->context->setAccountGatewayBaseURLTemplate('http://localhost');
        $this->assertEquals('http://localhost', $this->context->getClient()->getConfig('base_uri'));
    }

}
