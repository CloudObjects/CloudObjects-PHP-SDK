<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK;

use ML\IRI\IRI;

class ObjectRetrieverTest extends \PHPUnit_Framework_TestCase {

    private $retriever;

    protected function setUp() {
        $this->retriever = new ObjectRetriever;
    }

    private function stringifyItems(array $input) {
        $output = [];
        foreach ($input as $i)
            $output[] = (string)$i;
        
        return $output;
    }

    public function testGetRootObject() {
        $coid = new IRI('coid://cloudobjects.io');
        $object = $this->retriever->getObject($coid);
        $this->assertNotNull($object);
        $this->assertEquals((string)$coid, $object->getID());
        $this->assertNotNull($object->getProperty('http://www.w3.org/2000/01/rdf-schema#label'));
        $this->assertEquals('CloudObjects', $object->getProperty('http://www.w3.org/2000/01/rdf-schema#label')->getValue());
    }

    public function testGetCOIDList() {
        $coid = new IRI('coid://cloudobjects.io');
        $list = $this->stringifyItems(
            $this->retriever->getCOIDListForNamespace($coid)
        );
        $this->assertNotEmpty($list);

        $this->assertContains('coid://cloudobjects.io/isVisibleTo', $list);
        $this->assertContains('coid://cloudobjects.io/Public', $list);
        $this->assertNotContains('coid://json.co-n.net/Element', $list);
    }

    public function testGetFilteredCOIDList() {
        $coid = new IRI('coid://cloudobjects.io');
        $list = $this->stringifyItems(
            $this->retriever->getCOIDListForNamespaceWithType($coid, 'coid://cloudobjects.io/Audience')
        );
        $this->assertNotEmpty($list);

        $this->assertNotContains('coid://cloudobjects.io/isVisibleTo', $list);
        $this->assertContains('coid://cloudobjects.io/Public', $list);
        $this->assertContains('coid://cloudobjects.io/Private', $list);
    }

}
