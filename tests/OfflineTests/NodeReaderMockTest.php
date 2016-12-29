<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK;

use ML\IRI\IRI;
use GuzzleHttp\Client, GuzzleHttp\Handler\MockHandler,
  GuzzleHttp\HandlerStack, GuzzleHttp\Psr7\Response;

class NodeReaderMockTest extends \PHPUnit_Framework_TestCase {

  private $retriever;
  private $reader;

  private function setMockResponse(Response $response) {
    $mock = new MockHandler([$response]);
    $handler = HandlerStack::create($mock);
    $this->retriever->setClient(new Client(['handler' => $handler]));
  }

  private function useRootResourceMock() {
    $this->setMockResponse(new Response(200,
      ['Content-Type' => 'application/ld+json'],
      '{"@context":{"cloudobjects":"coid:\/\/cloudobjects.io\/","rdf":"http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#","rdfs":"http:\/\/www.w3.org\/2000\/01\/rdf-schema#"},"@id":"coid:\/\/cloudobjects.io","@type":"cloudobjects:Namespace","cloudobjects:hasPublicListing":"true","cloudobjects:revision":"1-325baa62b76105f56dc09386f5a2ec91","rdfs:comment":"The CloudObjects namespace defines the essential objects.","rdfs:label":"CloudObjects"}'));
  }

  protected function setUp() {
    $this->retriever = new ObjectRetriever;
    $this->reader = new NodeReader([
      'prefixes' => [
        'co' => 'coid://cloudobjects.io/',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'
      ]
    ]);
  }

  public function testHasType1() {    
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);
    
    $this->assertTrue($this->reader->hasType($object, 'coid://cloudobjects.io/Namespace'));
    $this->assertTrue($this->reader->hasType($object, 'co:Namespace'));
    $this->assertFalse($this->reader->hasType($object, 'coid://cloudobjects.io/MemberRole'));
    $this->assertFalse($this->reader->hasType($object, 'co:MemberRole'));
  }

  public function testHasPropertyValue1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);

    $this->assertTrue($this->reader->hasPropertyValue($object, 'http://www.w3.org/2000/01/rdf-schema#label', 'CloudObjects'));
    $this->assertTrue($this->reader->hasPropertyValue($object, 'rdfs:label', 'CloudObjects'));
  }

  public function testGetFirstValueString1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);
    
    $this->assertEquals('CloudObjects', $this->reader->getFirstValueString($object, 'http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertEquals('CloudObjects', $this->reader->getFirstValueString($object, 'rdfs:label'));

    $this->assertNull($this->reader->getFirstValueString($object, 'coid://cloudobjects.io/makesTriplesVisibleTo'));
    $this->assertNull($this->reader->getFirstValueString($object, 'co:makesTriplesVisibleTo'));

    $this->assertEquals('theDefaultValue', $this->reader->getFirstValueString($object, 'coid://cloudobjects.io/makesTriplesVisibleTo', 'theDefaultValue'));
    $this->assertEquals('theDefaultValue', $this->reader->getFirstValueString($object, 'co:makesTriplesVisibleTo', 'theDefaultValue'));
  }

}
