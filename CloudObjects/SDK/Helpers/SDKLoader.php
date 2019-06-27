<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\Helpers;

use Exception;
use CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;

/**
 * The SDKLoader helper allows developers to quickly load common PHP SDKs
 * from API providers and apply configuration stored in CloudObjects.
 */
class SDKLoader {

    private $objectRetriever;
    private $reader;
    private $classes = [];

    /**
     * @param ObjectRetriever $objectRetriever An initialized and authenticated object retriever.
     */
    public function __construct(ObjectRetriever $objectRetriever) {
        $this->objectRetriever = $objectRetriever;
        $this->reader = new NodeReader;
    }

    /**
     * Initialize and return the SDK with the given classname.
     * Throws Exception if the SDK is not supported.
     * 
     * @param $classname Classname for the SDK's main class
     * @param array $options Additional options for the SDK (if necessary)
     */
    public function get($classname, array $options) {
        if (!class_exists($classname))
            throw new Exception("<".$classname."> is not a valid classname.");

        $hashkey = md5($classname.serialize($options));
        if (!isset($this->classes[$hashkey])) {
            $nsNode = $this->objectRetriever->getAuthenticatingNamespaceObject();

            // --- Amazon Web Services (https://aws.amazon.com/) ---
            // has multiple classnames, so check for common superclass
            if (is_a($classname, 'Aws\AwsClient', true)) {
                $class = new $classname(array_merge($options, [
                    'credentials' => [
                        'key' => $this->reader->getFirstValueString($nsNode, 'coid://amazonws.cloudobjects.io/accessKeyId'),
                        'secret' => $this->reader->getFirstValueString($nsNode, 'coid://amazonws.cloudobjects.io/secretAccessKey')
                    ]
                ]));
            } else {
                switch ($classname) {

                    // --- stream (https://getstream.io/) ---
                    case "GetStream\Stream\Client":
                        $class = new $classname(
                            $this->reader->getFirstValueString($nsNode, 'coid://getstreamio.cloudobjects.io/key'),
                            $this->reader->getFirstValueString($nsNode, 'coid://getstreamio.cloudobjects.io/secret')
                        );
                        break;

                    // --- Pusher (https://pusher.com/) ---
                    case "Pusher":
                        $class = new $classname(
                            $this->reader->getFirstValueString($nsNode, 'coid://pusher.cloudobjects.io/key'),
                            $this->reader->getFirstValueString($nsNode, 'coid://pusher.cloudobjects.io/secret'),
                            $this->reader->getFirstValueString($nsNode, 'coid://pusher.cloudobjects.io/appId'),
                            $options
                    );
                    break;
                }
            }
        }

        if (!isset($class))
            throw new Exception("No rules defined to initialize <".$classname.">.");

        $this->classes[$hashkey] = $class;
        return $this->classes[$hashkey];
    }

}