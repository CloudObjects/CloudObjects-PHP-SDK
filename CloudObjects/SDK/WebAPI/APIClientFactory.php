<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\WebAPI;

use ML\IRI\IRI;
use ML\JsonLD\Node;
use CloudObjects\SDK\NodeReader;
use GuzzleHttp\Client;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Exceptions\InvalidObjectConfigurationException,
    CloudObjects\SDK\Exceptions\CoreAPIException;

/**
 * The APIClientFactory can be used to create a preconfigured Guzzle HTTP API client
 * based on the configuration data available for an API on CloudObjects.
 */
class APIClientFactory {

    private $objectRetriever;
    private $namespace;
    private $reader;
    private $apiClients = [];

    private function configureBearerTokenAuthentication(Node $api, array $clientConfig) {
        // see also: https://cloudobjects.io/webapi.cloudobjects.io/HTTPBasicAuthentication

        $accessToken = $this->reader->getFirstValueString($api, 'oauth2:hasFixedBearerToken');

        if (!isset($accessToken)) {
            $tokenProperty = $this->reader->getFirstValueString($api, 'oauth2:usesFixedBearerTokenFrom');
            if (!isset($tokenProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed access token or a defined token property.");
            $accessToken = $this->reader->getFirstValueString($this->namespace, $tokenProperty);
            if (!isset($accessToken))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$tokenProperty.">.");
        }

        $clientConfig['headers']['Authorization'] = 'Bearer ' . $accessToken;

        return $clientConfig;
    }

    private function configureBasicAuthentication(Node $api, array $clientConfig) {
        // see also: https://cloudobjects.io/webapi.cloudobjects.io/HTTPBasicAuthentication

        $username = $this->reader->getFirstValueString($api, 'wa:hasFixedUsername');
        $password = $this->reader->getFirstValueString($api, 'wa:hasFixedPassword');

        if (!isset($username)) {
            $usernameProperty = $this->reader->getFirstValueString($api, 'wa:usesUsernameFrom');
            if (!isset($usernameProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed username or a defined username property.");
            $username = $this->reader->getFirstValueString($this->namespace, $usernameProperty);
            if (!isset($username))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$usernameProperty.">.");
        }

        if (!isset($password)) {
            $passwordProperty = $this->reader->getFirstValueString($api, 'wa:usesPasswordFrom');
            if (!isset($passwordProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed password or a defined password property.");
            $password = $this->reader->getFirstValueString($this->namespace, $passwordProperty);
            if (!isset($password))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$passwordProperty.">.");
        }
        
        $clientConfig['auth'] = [$username, $password];
        return $clientConfig;
    }

    private function configureSharedSecretBasicAuthentication(Node $api, array $clientConfig) {
        // see also: https://cloudobjects.io/webapi.cloudobjects.io/SharedSecretAuthenticationViaHTTPBasic

        $username = COIDParser::fromString($this->namespace->getId())->getHost();

        $apiCoid = COIDParser::fromString($api->getId());
        $providerNamespaceCoid = COIDParser::getNamespaceCOID($apiCoid);
        $providerNamespace = $this->objectRetriever->get($providerNamespaceCoid);
        $sharedSecret = $this->reader->getAllValuesNode($providerNamespace, 'co:hasSharedSecret');
        if (count($sharedSecret) != 1)
            throw new CoreAPIException("Could not retrieve the shared secret.");
        
        $password = $this->reader->getFirstValueString($sharedSecret[0], 'co:hasTokenValue');

        $clientConfig['auth'] = [$username, $password];
        var_dump($clientConfig); die;
        return $clientConfig;
    }

    private function createClient(Node $api) {        
        if (!$this->reader->hasType($api, 'wa:WebAPI'))
            throw new InvalidObjectConfigurationException("The API node must have the type <coid://webapi.cloudobjects.io/WebAPI>.");
        
        $baseUrl = $this->reader->getFirstValueString($api, 'wa:hasBaseURL');
        if (!isset($baseUrl))
            throw new InvalidObjectConfigurationException("The API must have a base URL.");
        
        $clientConfig = [ 'base_uri' => $baseUrl ];

        if ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'oauth2:FixedBearerTokenAuthentication'))
            $clientConfig = $this->configureBearerTokenAuthentication($api, $clientConfig);

        elseif ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'wa:HTTPBasicAuthentication'))
            $clientConfig = $this->configureBasicAuthentication($api, $clientConfig);

        elseif ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
        'wa:SharedSecretAuthenticationViaHTTPBasic'))
            $clientConfig = $this->configureSharedSecretBasicAuthentication($api, $clientConfig);
        
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
        
        $this->reader = new NodeReader([
            'prefixes' => [
                'co' => 'coid://cloudobjects.io/',
                'wa' => 'coid://webapi.cloudobjects.io/',
                'oauth2' => 'coid://oauth2.cloudobjects.io/'
            ]
        ]);
    }

    /**
     * Get an API client for the WebAPI with the specified COID.
     * 
     * @param $apiCoid WebAPI COID
     * @return Client
     */
    public function getClientWithCOID(IRI $apiCoid) {
        $apiCoidString = (string)$apiCoid;
        if (!isset($this->apiClients[$apiCoidString])) {
            $this->apiClients[$apiCoidString] = $this->createClient(
                $this->objectRetriever->getObject($apiCoid));
        }

        return $this->apiClients[$apiCoidString];
    }

}