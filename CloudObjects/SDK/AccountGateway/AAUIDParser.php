<?php

namespace CloudObjects\SDK\AccountGateway;

use ML\IRI\IRI;

class AAUIDParser {

  const AAUID_INVALID = 0;

  const AAUID_ACCOUNT = 1;
  const AAUID_CONNECTION = 2;
  const AAUID_CONNECTED_ACCOUNT = 3;

  const REGEX_AAUID = "/^[a-z0-9]{16}$/";
  const REGEX_QUALIFIER = "/^[A-Z]{2}$/";

  /**
   * Creates a new IRI object representing a AAUID from a string.
   * Adds the "aauid:" prefix if necessary.
   *
   * @param string $aauidString An AAUID string.
   * @return IRI
   */
  public static function fromString($aauidString) {
    return new IRI(
      (substr($aauidString, 0, 6)=='aauid:') ? $aauidString : 'aauid:'.$aauidString
    );
  }

  public static function getType(IRI $iri) {
    if ($iri->getScheme()!='aauid' || $iri->getPath()=='')
      return self::AAUID_INVALID;

    $segments = explode(':', $iri->getPath());
    switch (count($segments)) {
      case 1:
        return (preg_match(self::REGEX_AAUID, $segments[0]) == 1)
          ? self::AAUID_ACCOUNT
          : self::AAUID_INVALID;
      case 3;
        if (preg_match(self::REGEX_AAUID, $segments[0]) != 1
            || preg_match(self::REGEX_QUALIFIER, $segments[2]) != 1)
          return self::AAUID_INVALID;
        switch ($segments[1]) {
            case "connection":
              return self::AAUID_CONNECTION;
            case "account":
              return self::AAUID_CONNECTED_ACCOUNT;
            default:
              return self::AAUID_INVALID;
        }
      default:
        return self::AAUID_INVALID;
    }
  }

  public static function getAAUID(IRI $iri) {
    if (self::getType($iri)!=self::AAUID_INVALID) {
      $segments = explode(':', $iri->getPath());
      return $segments[0];
    } else
      return null;
  }

  public static function getQualifier(IRI $iri) {
    if (self::getType($iri)==self::AAUID_CONNECTION
        || self::getType($iri)==self::AAUID_CONNECTED_ACCOUNT) {
      $segments = explode(':', $iri->getPath());
      return $segments[2];
    } else
      return null;
  }

}
