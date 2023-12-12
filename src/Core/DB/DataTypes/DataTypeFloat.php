<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core\DB\DataTypes;

/**
 * @package DataTypes
 */
class DataTypeFloat extends \ADIOS\Core\DB\DataType
{
  public function sqlCreateString($table_name, $col_name, $params = []) {
    $params['sql_definitions'] = '' != trim((string) $params['sql_definitions']) || $params['required'] ? $params['sql_definitions'] : ' default null ';
    $params['sql_definitions'] ??= '';
    $float_decimals = max((int) $params['decimals'], 1);
    $float_width = (int) $params['byte_size'];

    $sqlDataType = ($params['sql_data_type'] ?? "double");

    if (!in_array($sqlDataType, ["double", "float", "decimal", "numeric"])) {
      $sqlDataType = "double";
    }

    return "`{$col_name}` {$sqlDataType}($float_width, $float_decimals) {$params['sql_definitions']}";
  }

  public function sqlValueString($table_name, $col_name, $value, $params = [])
  {
    $params = _put_default_params_values($params, [
      'null_value' => false,
      'dumping_data' => false,
      'escape_string' => $this->adios->getConfig('m_datapub/escape_string', true),
    ]);

    if ($params['dumping_data'] && '' == $value) {
      $value = '-1';
    }

    $value = str_replace(',', '.', $value);

    if ($params['null_value'] or '' == $value) {
      return "$col_name=NULL";
    } else {
      if (is_numeric($value) && '' != $value) {
        return "$col_name='".($params['escape_string'] ? $this->adios->db->escape($value) : $value)."'";
      } else {
        return "$col_name=null";
      }
    }
  }

  public function toHtml($value, $params = [])
  {
    $html = '';

    if (isset($params['col_definition']['decimals'])) {
      $decimals = $params['col_definition']['decimals'];
    } else {
      $decimals = 2;
    }

    $value_number = number_format($value + 0, $decimals, ',', ' '); // str_replace(".", ",", strip_tags($value));

    if ('' == $params['col_definition']['format']) {
      $html = $value_number;
    } else {
      $html = str_replace('{%VALUE%}', $value_number, $params['col_definition']['format']);
    }

    if ($params['col_definition']['unit'] != "") $html .= " {$params['col_definition']['unit']}";

    return $html;
  }

  public function toCsv($value, $params = [])
  {
    return str_replace('.', ',', strip_tags($value + 0));
  }

  public function fromString(?string $value)
  {
    return (float) $value;
  }

  public function validate($value): bool
  {
    return empty($value) || is_numeric($value);
  }
}
