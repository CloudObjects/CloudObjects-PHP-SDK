<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK;

use ML\JsonLD\Node;
use ML\IRI\IRI;

/**
 * The NodeReader provides some convenience methods for reading information
 * from an object graph node.
 */
class NodeReader {

    private $prefixes = [];

    public function __construct(array $options = []) {
        if (isset($options['prefixes']))
            $this->prefixes = $options['prefixes'];
    }

    private function expand($uri) {
        if (!is_string($uri)) $uri = (string)$uri;
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (isset($scheme) && isset($this->prefixes[$scheme]))
            return str_replace($scheme.':', $this->prefixes[$scheme], $uri);
        else
            return $uri;
    }

    /**
     * Checks whether a node has a certain type.
     *
     * @param Node $node The node to work on.
     * @param string|object $type The type to check for.
     * @return boolean
     */
    public function hasType(Node $node = null, $type) {
        if (!isset($node))
            return false;
        $type = $this->expand($type);
        $typesFromNode = $node->getType();
        if (!isset($typesFromNode))
            return false;
        if (is_array($typesFromNode)) {
            foreach ($typesFromNode as $t)
                if (is_a($t, 'ML\JsonLD\Node')
                    && $t->getId() == $type)
                return true;
        } else
        if (is_a($typesFromNode, 'ML\JsonLD\Node')
                && $typesFromNode->getId() == $type)
            return true;
        else
            return false;
        
        return false;
    }

    private function getFirstValue(Node $node = null, $property, $default = null) {
        if (!isset($node))
            return $default;
        $valueFromNode = $node->getProperty($this->expand($property));
        if (!isset($valueFromNode))
            return $default;
        if (is_array($valueFromNode))
            return $valueFromNode[0];
        
        return $valueFromNode;
    }

    /**
     * Reads a property from a node and converts it into a string.
     * If the property has multiple values only the first is returned.
     * If no value is found or the node is null, the default is returned.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @param $default The default that is returned if no value for the property exists on the node.
     * @return string|null
     */
    public function getFirstValueString(Node $node = null, $property, $default = null) {
        $valueFromNode = $this->getFirstValue($node, $property, $default);
        if ($valueFromNode == $default)
            return $default;
        
        if (is_a($valueFromNode, 'ML\JsonLD\Node'))
            return $valueFromNode->getId();
        else
            return $valueFromNode->getValue();
    }

    /**
     * Reads a property from a node and converts it into a IRI.
     * If the property has multiple values only the first is returned.
     * If no value is found, value is a literal or the node is null, the default is returned.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @param $default The default that is returned if no value for the property exists on the node.
     * @return string|null
     */
    public function getFirstValueIRI(Node $node = null, $property, IRI $default = null) {
        $valueFromNode = $this->getFirstValue($node, $property, $default);
        if ($valueFromNode == $default)
            return $default;
        
        if (is_a($valueFromNode, 'ML\JsonLD\Node'))
            return new IRI($valueFromNode->getId());
        else
            return $default;
    }

    /**
     * Reads a property from a node and returns it as a Node.
     * If the property has multiple values only the first is returned.
     * If no value is found, value is a literal or the node is null, the default is returned.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @param $default The default that is returned if no value for the property exists on the node.
     * @return string|null
     */
    public function getFirstValueNode(Node $node = null, $property, Node $default = null) {
        $valueFromNode = $this->getFirstValue($node, $property, $default);
        if ($valueFromNode == $default)
            return $default;
        
        if (is_a($valueFromNode, 'ML\JsonLD\Node'))
            return $valueFromNode;
        else
            return $default;
    }

    /**
     * Checks whether a node has a specific value for a property.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @param string|object $value The expected value.
     * @return boolean
     */
    public function hasPropertyValue(Node $node = null, $property, $value) {
        if (!isset($node))
            return false;
        $valuesFromNode = $node->getProperty($this->expand($property));
        if (!isset($valuesFromNode))
            return false;
        if (!is_array($valuesFromNode))
            $valuesFromNode = array($valuesFromNode);
        
        foreach ($valuesFromNode as $v) {
            if (is_a($v, 'ML\JsonLD\Node')) {
                if ($v->getId() == $this->expand($value))
                    return true;
            } else {
                if ($v->getValue() == $value)
                    return true;
            }                
        }
        
        return false;
    }

    /**
     * Checks whether the node has at least one value for a property.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @return boolean
     */
    public function hasProperty(Node $node = null, $property) {
        if (!isset($node))
            return false;
        
        return ($node->getProperty($this->expand($property)) != null);
    }

    private function getAllValues(Node $node = null, $property) {
        if (!isset($node))
            return [];

        $valueFromNode = $node->getProperty($this->expand($property));
        if (!isset($valueFromNode))
            return [];
        if (!is_array($valueFromNode))
            $valueFromNode = [$valueFromNode];
        return $valueFromNode;
    }

    /**
     * Reads all values from a node and returns them as a string array.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @return array<string>
     */
    public function getAllValuesString(Node $node = null, $property) {
        $allValues = $this->getAllValues($node, $property);
        $output = [];
        foreach ($allValues as $a)
            if (is_a($a, 'ML\JsonLD\Node'))
                $output[] = $a->getId();
            else
                $output[] = $a->getValue();

        return $output;
    }

    /**
     * Reads all values from a node and returns them as a IRI array.
     * Only converts the Node IDs of nodes into IRI, literal values are skipped.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @return array<IRI>
     */
    public function getAllValuesIRI(Node $node = null, $property) {
        $allValues = $this->getAllValues($node, $property);
        $output = [];
        foreach ($allValues as $a)
            if (is_a($a, 'ML\JsonLD\Node'))
                $output[] = new IRI($a->getId());

        return $output;
    }

    /**
     * Reads all values from a node and returns them as a Node array.
     * Returns only nodes, literal values are skipped.
     *
     * @param Node $node The node to work on.
     * @param string|object $property The property to read.
     * @return array<Node>
     */
    public function getAllValuesNode(Node $node = null, $property) {
        $allValues = $this->getAllValues($node, $property);
        $output = [];
        foreach ($allValues as $a)
            if (is_a($a, 'ML\JsonLD\Node'))
                $output[] = $a;

        return $output;
    }

}