<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\WebAPI;

use Exception;
use ML\IRI\IRI;
use ML\JsonLD\Node;
use CloudObjects\SDK\NodeReader;
use GuzzleHttp\Client, GuzzleHttp\HandlerStack, GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Exceptions\InvalidObjectConfigurationException,
    CloudObjects\SDK\Exceptions\CoreAPIException;

/**
 * The APIClientFactory can be used to create a preconfigured Guzzle HTTP API client
 * based on the configuration data available for an API on CloudObjects.
 */
class APIClientFactory {

    const DEFAULT_CONNECT_TIMEOUT = 5;
    const DEFAULT_TIMEOUT = 20;

    private $objectRetriever;
    private $namespace;
    private $reader;
    private $apiClients = [];

    private function configureAPIKeyAuthentication(Node $api, array $clientConfig) {
        // see also: https://coid.link/webapis.co-n.net/APIKeyAuthentication

        $apiKey = $this->reader->getFirstValueString($api, 'wa:hasFixedAPIKey');

        if (!isset($apiKey)) {
            $apiKeyProperty = $this->reader->getFirstValueString($api, 'wa:usesAPIKeyFrom');
            if (!isset($apiKeyProperty))
                throw new InvalidObjectConfigurationException("An API must have either a fixed API key or a defined API key property.");
            $apiKey = $this->reader->getFirstValueString($this->namespace, $apiKeyProperty);
            if (!isset($apiKey))
                throw new InvalidObjectConfigurationException("The namespace does not have a value for <".$apiKeyProperty.">.");
        }
        
        $parameter = $this->reader->getFirstValueNode($api, 'wa:usesAuthenticationParameter');

        if (!isset($parameter) || !$this->reader->hasProperty($parameter, 'wa:hasKey'))
            throw new InvalidObjectConfigurationException("The API does not declare a parameter for inserting the API key.");

        $parameterName = $this->reader->getFirstValueString($parameter, 'wa:hasKey');

        if ($this->reader->hasType($parameter, 'wa:HeaderParameter'))
            $clientConfig['headers'][$parameterName] = $apiKey;

        elseif ($this->reader->hasType($parameter, 'wa:QueryParameter')) {
            // Guzzle currently doesn't merge query strings from default options and the request itself,
            // therefore we're implementing this behavior with a custom middleware
            $handler = HandlerStack::create();
            $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($parameterName, $apiKey) {
                $uri = $request->getUri();
                $uri = $uri->withQuery(
                    (!empty($uri->getQuery()) ? $uri->getQuery().'&' : '')
                    . urlencode($parameterName).'='.urlencode($apiKey)
                );
                return $request->withUri($uri);                
            }));
            $clientConfig['handler'] = $handler;
        }            

        else
            throw new InvalidObjectConfigurationException("The authentication parameter must be either <wa:HeaderParameter> or <wa:QueryParameter>.");

        return $clientConfig;
    }

    private function configureBearerTokenAuthentication(Node $api, array $clientConfig) {
        // see also: https://coid.link/webapis.co-n.net/HTTPBasicAuthentication

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
        // see also: https://coid.link/webapis.co-n.net/HTTPBasicAuthentication

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
        // see also: https://coid.link/webapis.co-n.net/SharedSecretAuthenticationViaHTTPBasic

        $username = COIDParser::fromString($this->namespace->getId())->getHost();

        $apiCoid = COIDParser::fromString($api->getId());
        $providerNamespaceCoid = COIDParser::getNamespaceCOID($apiCoid);
        $providerNamespace = $this->objectRetriever->get($providerNamespaceCoid);
        $sharedSecret = $this->reader->getAllValuesNode($providerNamespace, 'co:hasSharedSecret');
        if (count($sharedSecret) != 1)
            throw new CoreAPIException("Could not retrieve the shared secret.");
        
        $password = $this->reader->getFirstValueString($sharedSecret[0], 'co:hasTokenValue');

        $clientConfig['auth'] = [$username, $password];
        return $clientConfig;
    }

    private function createClient(Node $api, bool $specificClient = false) {        
        if (!$this->reader->hasType($api, 'wa:HTTPEndpoint'))
            throw new InvalidObjectConfigurationException("The API node must have the type <coid://webapis.co-n.net/HTTPEndpoint>.");
        
        $baseUrl = $this->reader->getFirstValueString($api, 'wa:hasBaseURL');
        if (!isset($baseUrl))
            throw new InvalidObjectConfigurationException("The API must have a base URL.");
        
        $clientConfig = [
            'base_uri' => $baseUrl,
            'connect_timeout' => self::DEFAULT_CONNECT_TIMEOUT,
            'timeout' => self::DEFAULT_TIMEOUT
        ];

        if ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'wa:APIKeyAuthentication'))
            $clientConfig = $this->configureAPIKeyAuthentication($api, $clientConfig);

        elseif ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'oauth2:FixedBearerTokenAuthentication'))
            $clientConfig = $this->configureBearerTokenAuthentication($api, $clientConfig);

        elseif ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'wa:HTTPBasicAuthentication'))
            $clientConfig = $this->configureBasicAuthentication($api, $clientConfig);

        elseif ($this->reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
        'wa:SharedSecretAuthenticationViaHTTPBasic'))
            $clientConfig = $this->configureSharedSecretBasicAuthentication($api, $clientConfig);

        if ($specificClient == false)
            return new Client($clientConfig);

        if ($this->reader->hasType($api, 'wa:GraphQLEndpoint')) {
            if (!class_exists('GraphQL\Client'))
                throw new Exception("Install the gmostafa/php-graphql-client package to retrieve a specific client for wa:GraphQLEndpoint objects.");
            
            return new \GraphQL\Client($clientConfig['base_uri'],
                isset($clientConfig['headers']) ? $clientConfig['headers'] : []);
        } else
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
                'wa' => 'coid://webapis.co-n.net/',
                'oauth2' => 'coid://oauth2.co-n.net/'
            ]
        ]);
    }

    /**
     * Get an API client for the WebAPI with the specified COID.
     * 
     * @param IRI $apiCoid WebAPI COID
     * @param boolean $specificClient If TRUE, returns a specific client class based on the API type. If FALSE, always returns a Guzzle client. Defaults to FALSE.
     * @return Client
     */
    public function getClientWithCOID(IRI $apiCoid, bool $specificClient = false) {
        $idString = (string)$apiCoid.(string)$specificClient;
        if (!isset($this->apiClients[$idString])) {
            $object = $this->objectRetriever->getObject($apiCoid);
            if (!isset($object))
                throw new CoreAPIException("Could not retrieve API <".(string)$apiCoid.">.");
            $this->apiClients[$idString] = $this->createClient($object, $specificClient);
        }

        return $this->apiClients[$idString];
    }

}