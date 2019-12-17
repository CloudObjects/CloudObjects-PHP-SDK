<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\JSON;

use ML\IRI\IRI;
use ML\JsonLD\Node;
use Webmozart\Assert\Assert;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\NodeReader;

/**
 * The schema validator enables the validation of data against
 * JSON schemas in the CloudObjects RDF format.
 */
class SchemaValidator {

    private $objectRetriever;
    private $reader;

    /**
     * @param ObjectRetriever $objectRetriever An initialized and authenticated object retriever.
     */
    public function __construct(ObjectRetriever $objectRetriever) {
        $this->objectRetriever = $objectRetriever;
        $this->reader = new NodeReader([
            'prefixes' => [
                'json' => 'coid://json.cloudobjects.io/'
            ]
        ]);
    }

    /**
     * Validate data against an element specification in an RDF node.
     * 
     * @param mixed $data The data to validate.
     * @param Node $node The specification to validate against.
     */
    public function validateAgainstNode($data, Node $node) {
        if ($this->reader->hasType($node, 'json:String'))
            Assert::string($data);
        elseif ($this->reader->hasType($node, 'json:Boolean'))
            Assert::boolean($data);
        elseif ($this->reader->hasType($node, 'json:Number'))
            Assert::numeric($data);
        elseif ($this->reader->hasType($node, 'json:Integer'))
            Assert::integer($data);
        elseif ($this->reader->hasType($node, 'json:Array'))
            Assert::isArray($data);
        elseif ($this->reader->hasType($node, 'json:Object')) {
            Assert::isArrayAccessible($data);
            foreach ($this->reader->getAllValuesNode($node, 'json:requiresProperty') as $prop) {
                $key = $this->reader->getFirstValueString($prop, 'json:hasKey');
                Assert::keyExists($data, $key);
                $this->validateAgainstNode($data[$key], $prop);
            }
            foreach ($this->reader->getAllValuesNode($node, 'json:supportsOptionalProperty') as $prop) {
                $key = $this->reader->getFirstValueString($prop, 'json:hasKey');
                if (isset($data[$key]))
                    $this->validateAgainstNode($data[$key], $prop);
            }
        }
    }

    /**
     * Validate data against a specification stored in CloudObjects.
     * 
     * @param mixed $data The data to validate.
     * @param Node $node The COID of the specification.
     */
    public function validateAgainstCOID($data, IRI $coid) {
        $object = $this->objectRetriever->getObject($coid);
        Assert::true($this->reader->hasType($object, 'json:Element'),
            "You can only validate data against JSON elements!");
        $this->validateAgainstNode($data, $object);
    }
}