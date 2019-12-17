<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\JSON;

use InvalidArgumentException;
use ML\IRI\IRI;
use CloudObjects\SDK\ObjectRetriever;

class SchemaValidatorPublicTest extends \PHPUnit_Framework_TestCase {

    private $schemaValidator;

    public function setUp() {
        $this->schemaValidator = new SchemaValidator(new ObjectRetriever);
    }

    public function testAddress() {        
        $this->schemaValidator->validateAgainstCOID([
            'locality' => 'Frankfurt',
            'region' => 'Hessen',
            'country-name' => 'Germany'
        ], new IRI('coid://json.cloudobjects.io/Address'));
    }

    public function testNotAddress() {        
        $this->setExpectedException(InvalidArgumentException::class);

        $this->schemaValidator->validateAgainstCOID([
            'region' => 'Hessen',
            'country-name' => 'Germany'
        ], new IRI('coid://json.cloudobjects.io/Address'));
    }

}
