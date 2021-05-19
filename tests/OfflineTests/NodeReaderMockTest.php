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
      '{"@context":{"co":"coid:\/\/cloudobjects.io\/","rdf":"http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#","agws":"coid:\/\/aauid.net\/","rdfs":"http:\/\/www.w3.org\/2000\/01\/rdf-schema#"},"@id":"coid:\/\/cloudobjects.io","@type":["agws:Service","co:Namespace"],"co:isAtRevision":"6-fbea0c90b2c5e5300e4039ed99be9b2d","co:isVisibleTo":{"@id":"co:Public"},"co:recommendsPrefix":"co","co:wasUpdatedAt":{"@type":"http:\/\/www.w3.org\/2001\/XMLSchema#dateTime","@value":"2017-01-16T17:29:22+00:00"},"rdfs:comment":"The CloudObjects namespace defines the essential objects.","rdfs:label":"CloudObjects"}'));
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

  public function testGetFirstValueIRI1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);

    $this->assertInstanceOf('ML\IRI\IRI', $this->reader->getFirstValueIRI($object, 'coid://cloudobjects.io/isVisibleTo'));
    $this->assertInstanceOf('ML\IRI\IRI', $this->reader->getFirstValueIRI($object, 'co:isVisibleTo'));
    
    $this->assertEquals(new IRI('coid://cloudobjects.io/Public'), $this->reader->getFirstValueIRI($object, 'coid://cloudobjects.io/isVisibleTo'));
    $this->assertEquals(new IRI('coid://cloudobjects.io/Public'), $this->reader->getFirstValueIRI($object, 'co:isVisibleTo'));
  }

  public function testGetFirstValueNode1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);

    $this->assertInstanceOf('ML\JsonLD\Node', $this->reader->getFirstValueNode($object, 'coid://cloudobjects.io/isVisibleTo'));
    $this->assertInstanceOf('ML\JsonLD\Node', $this->reader->getFirstValueNode($object, 'co:isVisibleTo'));
    
    $this->assertEquals('coid://cloudobjects.io/Public', $this->reader->getFirstValueNode($object, 'coid://cloudobjects.io/isVisibleTo')->getId());
    $this->assertEquals('coid://cloudobjects.io/Public', $this->reader->getFirstValueNode($object, 'co:isVisibleTo')->getId());
  }

  public function testGetAllValuesString1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);
    
    $this->assertCount(1, $this->reader->getAllValuesString($object, 'http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertCount(1, $this->reader->getAllValuesString($object, 'rdfs:label'));

    $this->assertEquals('CloudObjects', $this->reader->getAllValuesString($object, 'http://www.w3.org/2000/01/rdf-schema#label')[0]);
    $this->assertEquals('CloudObjects', $this->reader->getAllValuesString($object, 'rdfs:label')[0]);

    $this->assertCount(0, $this->reader->getAllValuesString($object, 'coid://cloudobjects.io/makesTriplesVisibleTo'));
    $this->assertCount(0, $this->reader->getAllValuesString($object, 'co:makesTriplesVisibleTo'));

    $this->assertCount(2, $this->reader->getAllValuesString($object, '@type'));
  }

  public function testGetAllValuesIRI1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);

    $this->assertCount(0, $this->reader->getAllValuesIRI($object, 'http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertCount(0, $this->reader->getAllValuesIRI($object, 'rdfs:label'));

    $this->assertCount(1, $this->reader->getAllValuesIRI($object, 'coid://cloudobjects.io/isVisibleTo'));
    $this->assertCount(1, $this->reader->getAllValuesIRI($object, 'co:isVisibleTo'));

    $this->assertCount(2, $this->reader->getAllValuesIRI($object, '@type'));

    $this->assertInstanceOf('ML\IRI\IRI', $this->reader->getAllValuesIRI($object, 'coid://cloudobjects.io/isVisibleTo')[0]);
    $this->assertInstanceOf('ML\IRI\IRI', $this->reader->getAllValuesIRI($object, 'co:isVisibleTo')[0]);

    $this->assertEquals(new IRI('coid://cloudobjects.io/Public'), $this->reader->getAllValuesIRI($object, 'coid://cloudobjects.io/isVisibleTo')[0]);
    $this->assertEquals(new IRI('coid://cloudobjects.io/Public'), $this->reader->getAllValuesIRI($object, 'co:isVisibleTo')[0]);
  }

  public function testGetAllValuesNode1() {
    $coid = new IRI('coid://cloudobjects.io');
    $this->useRootResourceMock();
    $object = $this->retriever->getObject($coid);

    $this->assertCount(0, $this->reader->getAllValuesNode($object, 'http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertCount(0, $this->reader->getAllValuesNode($object, 'rdfs:label'));

    $this->assertCount(1, $this->reader->getAllValuesNode($object, 'coid://cloudobjects.io/isVisibleTo'));
    $this->assertCount(1, $this->reader->getAllValuesNode($object, 'co:isVisibleTo'));

    $this->assertCount(2, $this->reader->getAllValuesNode($object, '@type'));

    $this->assertInstanceOf('ML\JsonLD\Node', $this->reader->getAllValuesNode($object, 'coid://cloudobjects.io/isVisibleTo')[0]);
    $this->assertInstanceOf('ML\JsonLD\Node', $this->reader->getAllValuesNode($object, 'co:isVisibleTo')[0]);

    $this->assertEquals('coid://cloudobjects.io/Public', $this->reader->getAllValuesNode($object, 'coid://cloudobjects.io/isVisibleTo')[0]->getId());
    $this->assertEquals('coid://cloudobjects.io/Public', $this->reader->getAllValuesNode($object, 'co:isVisibleTo')[0]->getId());
  }

}
