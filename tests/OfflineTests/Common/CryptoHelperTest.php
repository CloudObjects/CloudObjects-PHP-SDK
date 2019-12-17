<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\Common;

use InvalidArgumentException;
use GuzzleHttp\Client, GuzzleHttp\Handler\MockHandler,
    GuzzleHttp\HandlerStack, GuzzleHttp\Psr7\Response;
use CloudObjects\SDK\ObjectRetriever;

class CryptoHelperTest extends \PHPUnit_Framework_TestCase {

    private $retriever;
    private $graph;

    private function setMockResponse(Response $response) {
        $mock = new MockHandler([$response]);
        $handler = HandlerStack::create($mock);
        $this->retriever->setClient(new Client(['handler' => $handler]));
      }

    public function setUp() {
        $this->retriever = new ObjectRetriever([
            'auth_ns' => 'test.cloudobjects.io',
            'auth_secret' => 'TEST'
        ]);        
    }

    public function testEncryptDecrypt() {
        $this->setMockResponse(new Response(200,
            [ 'Content-Type' => 'application/ld+json' ],
            '{"@context":{"common":"coid:\/\/common.cloudobjects.io\/"},"@id":"coid:\/\/test.cloudobjects.io","common:usesSharedEncryptionKey": "def0000092c63296feb07f6b44f323351ab2e570fb04c2dff73c3119fd1103234ea03f5af094d33e8fb5122c5cf73f745957a5f8f47b4fc3c43bc86fb631969f4c591831"}'));

        $cryptoHelper = new CryptoHelper($this->retriever);

        $cleartext = "CLEARTEXT";
        $ciphertext = $cryptoHelper->encryptWithSharedEncryptionKey($cleartext);
        $encryptedDecryptedText = $cryptoHelper->decryptWithSharedEncryptionKey($ciphertext);
        
        $this->assertEquals($cleartext, $encryptedDecryptedText);
    }

}
