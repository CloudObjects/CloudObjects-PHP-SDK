<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
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
    $coid = new IRI('COID://example.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('Coid://example.com');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://EXAMPLE.COM');
    $this->assertEquals(COIDParser::COID_INVALID, COIDParser::getType($coid));
    $coid = new IRI('coid://exAMPle.CoM');
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

  public function testIRICaseSensitivity() {
    $coid1 = new IRI('coid://example.com/example/1.0');
    $coid2 = new IRI('coid://example.com/Example/1.0');
    $this->assertFalse($coid1->equals($coid2));
  }

  public function testRootFromString() {
    $coid1 = new IRI('coid://example.com');
    $coid2 = COIDParser::fromString('coid://example.com');
    $coid3 = COIDParser::fromString('example.com');
    $this->assertTrue($coid1->equals($coid2));
    $this->assertTrue($coid1->equals($coid3));
  }

  public function testUnversionedFromString() {
    $coid1 = new IRI('coid://example.com/Example');
    $coid2 = COIDParser::fromString('coid://example.com/Example');
    $coid3 = COIDParser::fromString('example.com/Example');
    $this->assertTrue($coid1->equals($coid2));
    $this->assertTrue($coid1->equals($coid3));
  }

  public function testVersionedFromString() {
    $coid1 = new IRI('coid://example.com/Example/1.0');
    $coid2 = COIDParser::fromString('coid://example.com/Example/1.0');
    $coid3 = COIDParser::fromString('example.com/Example/1.0');
    $this->assertTrue($coid1->equals($coid2));
    $this->assertTrue($coid1->equals($coid3));
  }

  public function testNormalizeRootFromString() {
    $coid1 = new IRI('coid://example.com');
    $coid2 = COIDParser::fromString('COID://example.com');
    $coid3 = COIDParser::fromString('ExAmple.COM');
    $this->assertTrue($coid1->equals($coid2));
    $this->assertTrue($coid1->equals($coid3));
  }

  public function testNormalizeNonRootFromString() {
    $coid1 = new IRI('coid://example.com/Example');
    $coid2 = COIDParser::fromString('COID://example.com/Example');
    $coid3 = COIDParser::fromString('ExAmple.COM/Example');
    $coid4 = COIDParser::fromString('ExAmple.COM/EXample');
    $this->assertTrue($coid1->equals($coid2));
    $this->assertTrue($coid1->equals($coid3));
    $this->assertFalse($coid1->equals($coid4));
  }

}
