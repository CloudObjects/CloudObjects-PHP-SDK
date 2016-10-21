<?php

namespace CloudObjects\SDK;

use ML\IRI\IRI;

/**
 * The COIDParser can be used to validate COIDs and extract information.
 */
class COIDParser {

  const COID_INVALID = 0;

  const COID_ROOT = 1;
  const COID_UNVERSIONED = 2;
  const COID_VERSIONED = 3;
  const COID_VERSION_WILDCARD = 4;

  const REGEX_HOSTNAME = "/^([a-z0-9-]+\.)?[a-z0-9-]+\.[a-z]+$/";
  const REGEX_SEGMENT = "/^[A-Za-z-_0-9\.]+$/";
  const REGEX_VERSION_WILDCARD = "/^((\^|~)(\d+\.)?\d|(\d+\.){1,2}\*)$/";

  /**
   * Creates a new IRI object representing a COID from a string.
   * Adds the "coid://" prefix if necessary.
   *
   * @param string $coidString A COID string.
   * @return IRI
   */
  public static function fromString($coidString) {
    return new IRI(
      (substr($coidString, 0, 7)=='coid://') ? $coidString : 'coid://'.$coidString
    );
  }

  /**
   * Get the type of a COID.
   *
   * @param IRI $coid
   * @return int|null
   */
  public static function getType(IRI $coid) {
    if ($coid->getScheme()!='coid' || $coid->getHost()==''
        || preg_match(self::REGEX_HOSTNAME, $coid->getHost()) != 1)
      return self::COID_INVALID;

    if ($coid->getPath()=='' || $coid->getPath()=='/')
      return self::COID_ROOT;

    $segments = explode('/', $coid->getPath());
    switch (count($segments)) {
      case 2:
        return (preg_match(self::REGEX_SEGMENT, $segments[1]) == 1)
          ? self::COID_UNVERSIONED
          : self::COID_INVALID;
      case 3:
        if (preg_match(self::REGEX_SEGMENT, $segments[1]) != 1)
          return self::COID_INVALID;

        if (preg_match(self::REGEX_SEGMENT, $segments[2]) == 1)
          return self::COID_VERSIONED;
        else
        if (preg_match(self::REGEX_VERSION_WILDCARD, $segments[2]) == 1)
          return self::COID_VERSION_WILDCARD;
        else
          return self::COID_INVALID;
      default:
        return self::COID_INVALID;
    }
  }

  /**
   * Checks whether the given IRI object is a valid COID.
   *
   * @param IRI $coid
   * @return boolean
   */
  public static function isValidCOID(IRI $coid) {
    return (self::getType($coid)!=self::COID_INVALID);
  }

  /**
   * Get the name segment of a valid COID or null if not available.
   *
   * @param IRI $coid
   * @return string|null
   */
  public static function getName(IRI $coid) {
    if (self::getType($coid)!=self::COID_INVALID
        && self::getType($coid)!=self::COID_ROOT) {
      $segments = explode('/', $coid->getPath());
      return $segments[1];
    } else
      return null;
  }

  /**
   * Get the version segment of a valid, versioned COID or null if not available.
   *
   * @param IRI $coid
   * @return string|null
   */
  public static function getVersion(IRI $coid) {
    if (self::getType($coid)==self::COID_VERSIONED) {
      $segments = explode('/', $coid->getPath());
      return $segments[2];
    } else
      return null;
  }

  /**
   * Get the version segment of a versioned or version wildcard COID or
   * null if not available.
   *
   * @param IRI $coid
   * @return string|null
   */
  public static function getVersionWildcard(IRI $coid) {
    if (self::getType($coid)==self::COID_VERSION_WILDCARD) {
      $segments = explode('/', $coid->getPath());
      return $segments[2];
    } else
      return null;
  }

  /**
   * Returns the COID itself if it is a root COID or a new IRI object
   * representing the namespace underlying the given COID.
   *
   * @param IRI $coid
   * @return IRI|null
   */
  public static function getNamespaceCOID(IRI $coid) {
    switch (self::getType($coid)) {
      case self::COID_ROOT:
        return $coid;
      case self::COID_UNVERSIONED:
      case self::COID_VERSIONED:
      case self::COID_VERSION_WILDCARD:
        return new IRI('coid://'.$coid->getHost());
      default:
        return null;
    }
  }

}
