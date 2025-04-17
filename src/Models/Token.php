<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Models;

use ADIOS\Core\Db\Column\DateTime;
use ADIOS\Core\Db\Column\Integer;
use ADIOS\Core\Db\Column\Varchar;

/**
 * Model for storing various validation tokens. Stored in 'tokens' SQL table.
 *
 * @package DefaultModels
 */
class Token extends \ADIOS\Core\Model {

  public string $table = "tokens";
  public ?string $lookupSqlValue = "{%TABLE%}.token";
  public $tokenTypes = [];
  public string $recordManagerClass = RecordManagers\Token::class;

  public function describeColumns(): array
  {
    return array_merge(parent::describeColumns(), [
      "type" => new Varchar($this, 'Token type'),
      "valid_to" => new DateTime($this, 'Expiration date'),
      "token" => new Varchar($this, 'Token')
    ]);
  }

  public function indexes(array $indexes = []): array
  {
    return parent::indexes([
      "uid" => [
        "type" => "index",
        "columns" => [
          "token" => [
            "order" => "asc",
          ],
        ],
      ],
    ]);
  }

  public function isTokenTypeRegistered($type) {
    return in_array($type, $this->tokenTypes);
  }

  public function registerTokenType($type) {
    if (!in_array($type, $this->tokenTypes)) {
      $this->tokenTypes[] = $type;
    } else {
      throw new \ADIOS\Core\Exceptions\GeneralException("Duplicate token type: {$type}");
    }
  }

  public function generateToken($tokenSalt, $tokenType, $validTo = NULL) {
    $token = uniqid()."-".md5($tokenSalt);

    if (!in_array($tokenType, $this->tokenTypes)) {
      throw new \ADIOS\Core\Exceptions\GeneralException("Unknown token type: {$tokenType}");
    }

    if ($validTo === NULL) {
      $validTo = date("Y-m-d H:i:s", strtotime("+ 3 day", time()));
    }

    if (strtotime($validTo) < time()) {
      throw new \ADIOS\Core\Exceptions\GeneralException("Token validity can not be in the past.");
    }

    $tokenId = $this->insertRow([
      "type" => $tokenType,
      "valid_to" => $validTo,
      "token" => $token,
    ]);

    return ["id" => $tokenId, "token" => $token];
  }

  public function validateToken($token) {
    $tokenQuery = $this->getQuery('*');
    $tokenQuery
      ->where("token", "=", $token)
      ->whereDate("valid_to", ">=", date("Y-m-d H:i:s"))
    ;

    $tokenData = reset($this->fetchRows($tokenQuery));

    if (!is_array($tokenData)) {
      throw new \ADIOS\Core\Exceptions\InvalidToken($token);
    }

    return $tokenData;
  }

  public function deleteToken($tokenId) {
    $this->where('id', $tokenId)->delete();
  }
}
