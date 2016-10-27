<?php

namespace CloudObjects\SDK\AccountGateway;

use ML\IRI\IRI;

class AAUIDParserTest extends \PHPUnit_Framework_TestCase {

  public function testValidAccountAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd1234');
    $this->assertEquals(AAUIDParser::AAUID_ACCOUNT, AAUIDParser::getType($aauid));
    $this->assertEquals('abcd1234abcd1234', AAUIDParser::getAAUID($aauid));
  }

  public function testInvalidAccountAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd123');
    $this->assertEquals(AAUIDParser::AAUID_INVALID, AAUIDParser::getType($aauid));
    $this->assertNull(AAUIDParser::getAAUID($aauid));
  }

  public function testValidAccountConnectionAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd1234:connection:AA');
    $this->assertEquals(AAUIDParser::AAUID_CONNECTION, AAUIDParser::getType($aauid));
    $this->assertEquals('abcd1234abcd1234', AAUIDParser::getAAUID($aauid));
    $this->assertEquals('AA', AAUIDParser::getQualifier($aauid));
  }

  public function testInvalidAccountConnectionAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd1234:connection:AAA');
    $this->assertEquals(AAUIDParser::AAUID_INVALID, AAUIDParser::getType($aauid));
    $this->assertNull(AAUIDParser::getAAUID($aauid));
    $this->assertNull(AAUIDParser::getQualifier($aauid));
  }

  public function testValidConnectedAccountAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd1234:account:AA');
    $this->assertEquals(AAUIDParser::AAUID_CONNECTED_ACCOUNT, AAUIDParser::getType($aauid));
    $this->assertEquals('abcd1234abcd1234', AAUIDParser::getAAUID($aauid));
    $this->assertEquals('AA', AAUIDParser::getQualifier($aauid));
  }

  public function testInvalidConnectedAccountAAUID() {
    $aauid = new IRI('aauid:abcd1234abcd1234:account:X9');
    $this->assertEquals(AAUIDParser::AAUID_INVALID, AAUIDParser::getType($aauid));
    $this->assertNull(AAUIDParser::getAAUID($aauid));
    $this->assertNull(AAUIDParser::getQualifier($aauid));
  }

}
