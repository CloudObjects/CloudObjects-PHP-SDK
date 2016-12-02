<?php

namespace CloudObjects\SDK;

use ML\IRI\IRI;

class ObjectRetrieverTest extends \PHPUnit_Framework_TestCase {

   protected function setUp() {
    $this->retriever = new ObjectRetriever();
  }

  public function testGetRootResource() {
    $coid = new IRI('coid://cloudobjects.io');
    $object = $this->retriever->getObject($coid);
    $this->assertNotNull($object);
    $this->assertEquals((string)$coid, $object->getID());
    $this->assertNotNull($object->getProperty('http://www.w3.org/2000/01/rdf-schema#label'));
    $this->assertEquals('CloudObjects', $object->getProperty('http://www.w3.org/2000/01/rdf-schema#label')->getValue());
  }

}
