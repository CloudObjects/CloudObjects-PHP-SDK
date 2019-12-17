<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\Common;

use Exception;
use ML\IRI\IRI;
use CloudObjects\SDK\NodeReader;
use Defuse\Crypto\Key, Defuse\Crypto\Crypto;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Exceptions\InvalidObjectConfigurationException;

/**
 * The crypto helper can be used to encrypt or decrypt data with
 * the defuse PHP encryption library.
 */
class CryptoHelper {

    private $objectRetriever;
    private $namespace;
    private $reader;
    
    /**
     * Gets a key based on the coid://common.cloudobjects.io/usesSharedEncryptionKey value
     * for the default namespace.
     */
    public function getSharedEncryptionKey() {
        $keyValue = $this->reader->getFirstValueString($this->namespace, 'common:usesSharedEncryptionKey');
        if (!isset($keyValue))
            throw new InvalidObjectConfigurationException("The namespace doesn't have an encryption key.");

        return Key::loadFromAsciiSafeString($keyValue);
    }

    /**
     * Encrypt data with the default namespace's shared encryption key.
     */
    public function encryptWithSharedEncryptionKey($data) {
        return Crypto::encrypt($data, $this->getSharedEncryptionKey());
    }

    /**
     * Decrypt data with the default namespace's shared encryption key.
     */
    public function decryptWithSharedEncryptionKey($data) {
        return Crypto::decrypt($data, $this->getSharedEncryptionKey());
    }

    /**
     * @param ObjectRetriever $objectRetriever An initialized and authenticated object retriever.
     * @param IRI|null $namespaceCoid The namespace used to retrieve keys. If this parameter is not provided, the namespace provided with the "auth_ns" configuration option from the object retriever is used.
     */
    public function __construct(ObjectRetriever $objectRetriever, IRI $namespaceCoid = null) {
        if (!class_exists('Defuse\Crypto\Crypto'))
            throw new Exception("Run composer require defuse/php-encryption before using CryptoHelper.");

        $this->objectRetriever = $objectRetriever;
        $this->namespace = isset($namespaceCoid)
            ? $objectRetriever->getObject($namespaceCoid)
            : $objectRetriever->getAuthenticatingNamespaceObject();
        
        $this->reader = new NodeReader([
            'prefixes' => [
                'common' => 'coid://common.cloudobjects.io/'
            ]
        ]);
    }

}