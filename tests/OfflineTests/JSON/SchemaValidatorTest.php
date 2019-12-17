<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\JSON;

use InvalidArgumentException;
use ML\JsonLD\JsonLD;
use CloudObjects\SDK\ObjectRetriever;

class SchemaValidatorTest extends \PHPUnit_Framework_TestCase {

    private $schemaValidator;
    private $graph;

    public function setUp() {
        $this->schemaValidator = new SchemaValidator(new ObjectRetriever);
        $this->graph = JsonLD::getDocument('{}')->getGraph();
    }

    public function testString() {
        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));
        $this->schemaValidator->validateAgainstNode("Test", $node);
    }

    public function testNotString() {
        $this->setExpectedException(InvalidArgumentException::class);

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));
        $this->schemaValidator->validateAgainstNode(9, $node);
    }

    public function testNumber() {
        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Number'));
        $this->schemaValidator->validateAgainstNode(3.5, $node);
    }

    public function testNotNumber() {
        $this->setExpectedException(InvalidArgumentException::class);

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Number'));
        $this->schemaValidator->validateAgainstNode("ABC", $node);
    }

    public function testInteger() {
        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Integer'));
        $this->schemaValidator->validateAgainstNode(12, $node);
    }

    public function testNotInteger() {
        $this->setExpectedException(InvalidArgumentException::class);

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Integer'));
        $this->schemaValidator->validateAgainstNode(1.4, $node);
    }

    public function testArray() {
        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Array'));
        $this->schemaValidator->validateAgainstNode([ 1, 2, "foo" ], $node);
    }

    public function testNotArray() {
        $this->setExpectedException(InvalidArgumentException::class);

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Array'));
        $this->schemaValidator->validateAgainstNode("NANANA", $node);
    }

    public function testObject() {
        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $this->schemaValidator->validateAgainstNode([
            'a' => 'A',
            'b' => 'B'
        ], $node);
    }

    public function testNotObject() {
        $this->setExpectedException(InvalidArgumentException::class);

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $this->schemaValidator->validateAgainstNode(5, $node);
    }

    public function testObjectWithProperty() {
        $stringNode = $this->graph->createNode();
        $stringNode->setProperty('coid://json.cloudobjects.io/hasKey', 'a');
        $stringNode->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $node->setProperty('coid://json.cloudobjects.io/supportsOptionalProperty', $stringNode);
        $this->schemaValidator->validateAgainstNode([
            'a' => 'A',
            'b' => 'B'
        ], $node);
    }

    public function testObjectWithPropertyTypeError() {
        $this->setExpectedException(InvalidArgumentException::class);

        $stringNode = $this->graph->createNode();
        $stringNode->setProperty('coid://json.cloudobjects.io/hasKey', 'a');
        $stringNode->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $node->setProperty('coid://json.cloudobjects.io/supportsOptionalProperty', $stringNode);
        $this->schemaValidator->validateAgainstNode([
            'a' => 0,
            'b' => 'B'
        ], $node);
    }

    public function testObjectWithRequiredProperty() {
        $stringNode = $this->graph->createNode();
        $stringNode->setProperty('coid://json.cloudobjects.io/hasKey', 'a');
        $stringNode->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $node->setProperty('coid://json.cloudobjects.io/requiresProperty', $stringNode);
        $this->schemaValidator->validateAgainstNode([
            'a' => 'A',
            'b' => 'B'
        ], $node);
    }

    public function testObjectWithRequiredPropertyTypeError() {
        $this->setExpectedException(InvalidArgumentException::class);

        $stringNode = $this->graph->createNode();
        $stringNode->setProperty('coid://json.cloudobjects.io/hasKey', 'a');
        $stringNode->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $node->setProperty('coid://json.cloudobjects.io/requiresProperty', $stringNode);
        $this->schemaValidator->validateAgainstNode([
            'a' => 0,
            'b' => 'B'
        ], $node);
    }

    public function testObjectWithRequiredPropertyMissing() {
        $this->setExpectedException(InvalidArgumentException::class);

        $stringNode = $this->graph->createNode();
        $stringNode->setProperty('coid://json.cloudobjects.io/hasKey', 'a');
        $stringNode->setType($this->graph->createNode('coid://json.cloudobjects.io/String'));

        $node = $this->graph->createNode();
        $node->setType($this->graph->createNode('coid://json.cloudobjects.io/Object'));
        $node->setProperty('coid://json.cloudobjects.io/requiresProperty', $stringNode);
        $this->schemaValidator->validateAgainstNode([
            'b' => 'B',
            'c' => 'C'
        ], $node);
    }

}
