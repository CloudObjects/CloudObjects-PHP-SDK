<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\WebAPI;

use ML\IRI\IRI;
use ML\JsonLD\Node;
use CloudObjects\SDK\NodeReader;
use GuzzleHttp\Client;
use CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Exceptions\InvalidObjectConfigurationException;

/**
 * The APIClientFactory can be used to create a preconfigured Guzzle HTTP API client
 * based on the configuration data available for an API on CloudObjects.
 */
class APIClientFactory {

    private $objectRetriever;
    private $namespace;
    private $apiClients;

    private static function configureBearerTokenAuthentication(Node $api, Node $namespace, NodeReader $reader, array $clientConfig) {
        $accessToken = $reader->getFirstValueString($api, 'oauth2:hasFixedBearerToken');

        if (!isset($accessToken)) {
            $tokenProperty = $reader->getFirstValueString($api, 'oauth2:usesFixedBearerTokenFrom');
            if (!isset($tokenProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed access token or a defined token property.");
            $accessToken = $reader->getFirstValueString($namespace, $tokenProperty);
            if (!isset($accessToken))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$tokenProperty.">.");
        }

        $clientConfig['headers']['Authorization'] = 'Bearer ' . $accessToken;

        return $clientConfig;
    }

    private static function configureBasicAuthentication(Node $api, Node $namespace, NodeReader $reader, array $clientConfig) {
        $username = $reader->getFirstValueString($api, 'wa:hasFixedUsername');
        $password = $reader->getFirstValueString($api, 'wa:hasFixedPassword');

        if (!isset($username)) {
            $usernameProperty = $reader->getFirstValueString($api, 'wa:usesUsernameFrom');
            if (!isset($usernameProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed username or a defined username property.");
            $username = $reader->getFirstValueString($namespace, $usernameProperty);
            if (!isset($username))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$usernameProperty.">.");
        }

        if (!isset($password)) {
            $passwordProperty = $reader->getFirstValueString($api, 'wa:usesPasswordFrom');
            if (!isset($passwordProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed password or a defined password property.");
            $password = $reader->getFirstValueString($namespace, $passwordProperty);
            if (!isset($password))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$passwordProperty.">.");
        }
        
        $clientConfig['auth'] = [$username, $password];
        return $clientConfig;
    }

    /**
     * Create a client statically.
     * @deprecated
     * 
     * @param Node $api The Web API for which the client should be created.
     * @param Node $namespace The namespace that is accessing the API.
     */
    public static function createClient(Node $api, Node $namespace) {
        $reader = new NodeReader([
            'prefixes' => [
                'wa' => 'coid://webapi.cloudobjects.io/',
                'oauth2' => 'coid://oauth2.cloudobjects.io/'
            ]
        ]);

        if (!$reader->hasType($api, 'wa:WebAPI'))
            throw new InvalidObjectConfigurationException("The API node must have the type <coid://webapi.cloudobjects.io/WebAPI>.");
        
        $baseUrl = $reader->getFirstValueString($api, 'wa:hasBaseURL');
        if (!isset($baseUrl))
            throw new InvalidObjectConfigurationException("The API must have a base URL.");
        
        $clientConfig = [ 'base_uri' => $baseUrl ];

        if ($reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'oauth2:FixedBearerTokenAuthentication'))
            $clientConfig = self::configureBearerTokenAuthentication($api, $namespace, $reader, $clientConfig);
        else
        if ($reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'wa:HTTPBasicAuthentication'))
            $clientConfig = self::configureBasicAuthentication($api, $namespace, $reader, $clientConfig);
        
        return new Client($clientConfig);
    }

    /**
     * @param ObjectRetriever $objectRetriever An initialized and authenticated object retriever.
     * @param IRI|null $namespaceCoid The namespace of the API client. Used to retrieve credentials. If this parameter is not provided, the namespace provided with the "auth_ns" configuration option from the object retriever is used.
     */
    public function __construct(ObjectRetriever $objectRetriever, IRI $namespaceCoid = null) {
        $this->objectRetriever = $objectRetriever;
        $this->namespace = isset($namespaceCoid)
            ? $objectRetriever->getObject($namespaceCoid)
            : $objectRetriever->getAuthenticatingNamespaceObject();
    }

    /**
     * Get an API client for the WebAPI with the specified COID.
     * 
     * @param $apiCoid WebAPI COID
     */
    public function getClientWithCOID(IRI $apiCoid) {
        $apiCoidString = (string)$apiCoid;
        if (!isset($this->apiClients[$apiCoidString])) {
            $this->apiClients[$apiCoidString] = self::createClient(
                $this->objectRetriever->getObject($apiCoid),
                $this->namespace);
        }

        return $this->apiClients[$apiCoidString];
    }

}