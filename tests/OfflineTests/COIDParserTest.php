<?php

namespace CloudObjects\SDK;

use ML\IRI\IRI;

class COIDParserTest extends \PHPUnit_Framework_TestCase {

  public function testRootCOID() {
    $coid = new IRI('coid://example.com');
    $this->assertEquals(COIDParser::COID_ROOT, COIDParser::getType($coid));
  }

  public function testInvalidRootCOID() {
    $coid = new IRI('coid://example');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://exämple.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://ex&mple.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
  }

  public function testInvalidCOID() {
    $coid = new IRI('http://example.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('example.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
  }

  public function testUnversionedCOID() {
    $coid = new IRI('coid://example.com/Example');
    $this->assertEquals(COIDParser::COID_UNVERSIONED, COIDParser::getType($coid));
  }

  public function testInvalidUnversionedCOID() {
    $coid = new IRI('coid://example.com/Exümple');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://example.com/Examp%e');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
  }

  public function testVersionedCOID() {
    $coid = new IRI('coid://example.com/Example/1.0');
    $this->assertEquals(COIDParser::COID_VERSIONED, COIDParser::getType($coid));
    $coid = new IRI('coid://example.com/Example/alpha');
    $this->assertEquals(COIDParser::COID_VERSIONED, COIDParser::getType($coid));
  }

  public function testInvalidVersionedCOID() {
    $coid = new IRI('coid://example.com/Example/1.$');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
  }

  public function testVersionWildcardCOID() {
    $coid = new IRI('coid://example.com/Example/^1.0');
    $this->assertEquals(COIDParser::COID_VERSION_WILDCARD, COIDParser::getType($coid));
    $coid = new IRI('coid://example.com/Example/~1.0');
    $this->assertEquals(COIDParser::COID_VERSION_WILDCARD, COIDParser::getType($coid));
    $coid = new IRI('coid://example.com/Example/1.*');
    $this->assertEquals(COIDParser::COID_VERSION_WILDCARD, COIDParser::getType($coid));
  }

  public function testInvalidVersionWildcardCOID() {
    $coid = new IRI('coid://example.com/Example/^1.*');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://example.com/Example/1.a.*');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
  }

}
