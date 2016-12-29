<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK;

use ML\IRI\IRI;
use GuzzleHttp\Client, GuzzleHttp\Handler\MockHandler,
  GuzzleHttp\HandlerStack, GuzzleHttp\Psr7\Response;

class ObjectRetrieverMockTest extends \PHPUnit_Framework_TestCase {

  private $retriever;

  private function setMockResponse(Response $response) {
    $mock = new MockHandler([$response]);
    $handler = HandlerStack::create($mock);
    $this->retriever->setClient(new Client(['handler' => $handler]));
  }

  protected function setUp() {
    $this->retriever = new ObjectRetriever;
  }

  public function testGetRootResource() {
    $this->setMockResponse(new Response(200,
      ['Content-Type' => 'application/ld+json'],
      '{"@context":{"cloudobjects":"coid:\/\/cloudobjects.io\/","rdf":"http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#","rdfs":"http:\/\/www.w3.org\/2000\/01\/rdf-schema#"},"@id":"coid:\/\/cloudobjects.io","@type":"cloudobjects:Namespace","cloudobjects:hasPublicListing":"true","cloudobjects:revision":"1-325baa62b76105f56dc09386f5a2ec91","rdfs:comment":"The CloudObjects namespace defines the essential objects.","rdfs:label":"CloudObjects"}'));

    $coid = new IRI('coid://cloudobjects.io');
    $object = $this->retriever->getObject($coid);
    $this->assertNotNull($object);
    $this->assertEquals((string)$coid, $object->getID());
    $this->assertNotNull($object->getProperty('http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertEquals('CloudObjects', $object->getProperty('http://www.w3.org/2000/01/rdf-schema#label')->getValue());
  }

}
