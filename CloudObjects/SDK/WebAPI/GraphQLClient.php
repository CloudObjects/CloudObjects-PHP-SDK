<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\SDK\WebAPI;

use GuzzleHttp\Client as GuzzleClient;
use GraphQL\Client as BaseGraphQLClient;

/**
 * This is an extension of the GraphQL Client from the gmostafa/php-graphql-client
 * package with the ability to provide a preconfigured Guzzle HTTP client.
 */
class GraphQLClient extends BaseGraphQLClient {

    public function __construct(GuzzleClient $client) {
        $this->endpointUrl = $client->getConfig('base_uri');
        $this->authorizationHeaders = [];
        $this->httpClient = $client;
    }

}