<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\Helpers;

use ML\IRI\IRI;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;

/**
 * The SharedSecretAuthentication helper allows developers to quickly
 * implement authentication based on CloudObjects shared secrets.
 */
class SharedSecretAuthentication {

    const RESULT_OK = 0;
    const RESULT_INVALID_USERNAME = 1;
    const RESULT_INVALID_PASSWORD = 2;
    const RESULT_NAMESPACE_NOT_FOUND = 3;
    const RESULT_SHARED_SECRET_NOT_RETRIEVABLE = 4;
    const RESULT_SHARED_SECRET_INCORRECT = 5;

    private $objectRetriever;

    /**
     * @param ObjectRetriever $objectRetriever An initialized and authenticated object retriever.
     */
    public function __construct(ObjectRetriever $objectRetriever) {
        $this->objectRetriever = $objectRetriever;
    }

    /**
     * Verifies credentials.
     * @deprecated
     * 
     * @param ObjectRetriever $retriever Provides access to CloudObjects.
     * @param string $username Username; a domain.
     * @param string $password Password; a shared secret.
     * 
     * @return integer A result constant, RESULT_OK if successful.
     */
    public static function verifyCredentials(ObjectRetriever $retriever, $username, $password) {
        // Validate input
        $namespaceCoid = new IRI('coid://'.$username);
        if (COIDParser::getType($namespaceCoid) != COIDParser::COID_ROOT)
            return self::RESULT_INVALID_USERNAME;
        if (strlen($password) != 40)
            return self::RESULT_INVALID_PASSWORD;

        // Retrieve namespace
        $namespace = $retriever->getObject($namespaceCoid);
        if (!isset($namespace))
            return self::RESULT_NAMESPACE_NOT_FOUND;

        // Read and validate shared secret
        $reader = new NodeReader([
            'prefixes' => [
                'co' => 'coid://cloudobjects.io/'
            ]
        ]);
        $sharedSecret = $reader->getAllValuesNode($namespace, 'co:hasSharedSecret');
        if (count($sharedSecret) != 1)
            return self::RESULT_SHARED_SECRET_NOT_RETRIEVABLE;
        
        if ($reader->getFirstValueString($sharedSecret[0], 'co:hasTokenValue') == $password)
            return self::RESULT_OK;
        else
            return self::RESULT_SHARED_SECRET_INCORRECT;        
    }

    /**
     * Verifies credentials.
     *
     * @param string $username Username; a domain.
     * @param string $password Password; a shared secret.
     * 
     * @return integer A result constant, RESULT_OK if successful.
     */
    public function verify($username, $password) {
        // Validate input
        $namespaceCoid = new IRI('coid://'.$username);
        if (COIDParser::getType($namespaceCoid) != COIDParser::COID_ROOT)
            return self::RESULT_INVALID_USERNAME;
        if (strlen($password) != 40)
            return self::RESULT_INVALID_PASSWORD;

        // Retrieve namespace
        $namespace = $this->objectRetriever->getObject($namespaceCoid);
        if (!isset($namespace))
            return self::RESULT_NAMESPACE_NOT_FOUND;

        // Read and validate shared secret
        $reader = new NodeReader([
            'prefixes' => [
                'co' => 'coid://cloudobjects.io/'
            ]
        ]);
        $sharedSecret = $reader->getAllValuesNode($namespace, 'co:hasSharedSecret');
        if (count($sharedSecret) != 1)
            return self::RESULT_SHARED_SECRET_NOT_RETRIEVABLE;
        
        if ($reader->getFirstValueString($sharedSecret[0], 'co:hasTokenValue') == $password)
            return self::RESULT_OK;
        else
            return self::RESULT_SHARED_SECRET_INCORRECT;        
    }

}