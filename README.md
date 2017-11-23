# CloudObjects PHP SDK

[![Latest Stable Version](https://poser.pugx.org/cloudobjects/sdk/v/stable)](https://packagist.org/packages/cloudobjects/sdk) [![Total Downloads](https://poser.pugx.org/cloudobjects/sdk/downloads)](https://packagist.org/packages/cloudobjects/sdk) [![Build Status](https://travis-ci.org/CloudObjects/CloudObjects-PHP-SDK.svg?branch=master)](https://travis-ci.org/CloudObjects/CloudObjects-PHP-SDK) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/50356ef0-2266-4890-a5a0-2c4e3e86c761/mini.png)](https://insight.sensiolabs.com/projects/50356ef0-2266-4890-a5a0-2c4e3e86c761)

The CloudObjects PHP SDK provides simple access to [CloudObjects](https://cloudobjects.io/) from PHP-based applications. It wraps the [Object API](https://cloudobjects.io/cloudobjects.io/ObjectAPI/1.0) to fetch objects from the CloudObjects Core database and provides object-based access to their RDF description. A two-tiered caching mechanism (in-memory and Doctrine cache drivers) is included. The SDK also contains a helper class to validate COIDs.

## Installation

The SDK is [distributed through packagist](https://packagist.org/packages/cloudobjects/sdk). Add `cloudobjects/sdk` to the `require` section of your `composer.json`, like this:

````json
{
  "require": {
    "cloudobjects/sdk" : ">=0.7"
  }
}
````

## Retrieving Objects

In order to retrieve objects from the CloudObjects Core database you need to create an instance of `CloudObjects\SDK\ObjectRetriever`. Then you can call `getObject()`. This method returns an `ML\JsonLD\Node` instance or `null` if the object is not found. You can use the object interface of the [JsonLD library](https://github.com/lanthaler/JsonLD/) to read the information from the object.

Here's a simple example:

````php
use ML\IRI\IRI;
use CloudObjects\SDK\ObjectRetriever;

/* ... */

$retriever = new ObjectRetriever();
$object = $this->retriever->getObject(new IRI('coid://cloudobjects.io'));
if (isset($object))
    echo $object->getProperty('http://www.w3.org/2000/01/rdf-schema#label')->getValue();
else
    echo "Object not found.";
````

### Configuration

You can pass an array of configuration options to the ObjectRetriever's constructor:

| Option | Description | Default |
|---|---|---|
| `cache_provider` | The type of cache used. Currently supports `redis`, `file` and `none`. | `none` |
| `cache_prefix` | A prefix used for cache IDs. Normally this should not be set but might be necessary on shared caches. | `clobj:` |
| `cache_ttl` | Determines how long objects can remain cached. | `60` |
| `auth_ns` | The namespace of the service that this retriever acts for. If not set the API is accessed anonymously. | `null` |
| `auth_secret` | The shared secret between the namespace in `auth_ns` and `cloudobjects.io` for authenticated. If not set the API is accessed anonymously. | `null` |

#### For `redis` cache:

| Option | Description | Default |
|---|---|---|
| `cache_provider.redis.host` | The hostname or IP of the Redis instance. | `127.0.0.1` |
| `cache_provider.redis.port` | The port number of the Redis instance. | `6379` |

#### For `file` cache:

| Option | Description | Default |
|---|---|---|
| `cache_provider.file.directory` | The directory to store cache data in. | The system's temporary directory. |

## API Documentation

A full API documentation of all SDK classes is generated automatically and published at [php-sdk-docs.cloudobjects.io](https://php-sdk-docs.cloudobjects.io/).

If you're curious, we've [documented the documentation build process on our blog](https://blog.cloudobjects.io/coding/opensource/2017/05/19/php-sdk-documentation/).

## License

The PHP SDK is licensed under Mozilla Public License (see LICENSE file).