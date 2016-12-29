<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\WebAPI;

use ML\JsonLD\Node;
use CloudObjects\SDK\NodeReader;
use GuzzleHttp\Client;

/**
 * The APIClientFactory can be used to create a preconfigured Guzzle HTTP API client
 * based on the configuration data available for an API on CloudObjects.
 */
class APIClientFactory {

    private static function configureBasicAuthentication(Node $api, Node $namespace, NodeReader $reader, array $clientConfig) {
        $username = $reader->getFirstValueString($api, 'wa:hasFixedUsername');
        $password = $reader->getFirstValueString($api, 'wa:hasFixedPassword');

        if (!isset($username)) {
            $usernameProperty = $reader->getFirstValueString($api, 'wa:usesUsernameFrom');
            if (!isset($usernameProperty))
                throw new \Exception("An API must have either a fixed username or a defined username property.");
            $username = $reader->getFirstValueString($namespace, $usernameProperty);
            if (!isset($username))
                throw new \Exception("The namespace does not have a value for <".$usernameProperty.">.");
        }

        if (!isset($password)) {
            $passwordProperty = $reader->getFirstValueString($api, 'wa:usesPasswordFrom');
            if (!isset($passwordProperty))
                throw new \Exception("An API must have either a fixed password or a defined password property.");
            $password = $reader->getFirstValueString($namespace, $passwordProperty);
            if (!isset($password))
                throw new \Exception("The namespace does not have a value for <".$passwordProperty.">.");
        }
        
        $clientConfig['auth'] = [$username, $password];
        return $clientConfig;
    }

    /**
     * Create a client.
     * Node $api The Web API for which the client should be created.
     * Node $namepsace The namespace that is accessing the API.
     */
    public static function createClient(Node $api, Node $namespace) {
        $reader = new NodeReader([
            'prefixes' => [
                'wa' => 'coid://webapi.cloudobjects.io/'
            ]
        ]);

        if (!$reader->hasType($api, 'wa:WebAPI'))
            throw new \Exception("The API node must have the type <coid://webapi.cloudobjects.io/WebAPI>.");
        
        $baseUrl = $reader->getFirstValueString($api, 'wa:hasBaseURL');
        if (!isset($baseUrl))
            throw new \Exception("The API must have a base URL.");
        
        $clientConfig = [ 'base_uri' => $baseUrl ];

        if ($reader->hasPropertyValue($api, 'wa:supportsAuthenticationMechanism',
                'wa:HTTPBasicAuthentication'))
            $clientConfig = self::configureBasicAuthentication($api, $namespace, $reader, $clientConfig);
        
        return new Client($clientConfig);
    }

}