<?php

/**
 * @file
 * SQL helper for Date API.
 *
 * @TODO
 * Add experimental support for sqlite: http://www.sqlite.org/lang_datefunc.html
 * and Oracle (http://psoug.org/reference/date_func.html and
 * http://psoug.org/reference/datatypes.html) date/time functions.
 */

/**
 * A helper function to do cross-database concatation of date parts.
 *
 * @param array $array
 *   An array of values to be concatonated in sql.
 *
 * @return string
 *   Correct sql string for database type.
 */
function date_sql_concat($array) {
  switch (Database::getConnection()->databaseType()) {
    case 'mysql':
      return "CONCAT(" . implode(",", $array) . ")";
    case 'pgsql':
      return implode(" || ", $array);
  }
}

/**
 * Helper function to do cross-database NULL replacements
 *
 * @param array $array
 *   An array of values to test for NULL values.
 *
 * @return string
 *   SQL statement to return the first non-NULL value in the list.
 */
function date_sql_coalesce($array) {
  switch (Database::getConnection()->databaseType()) {
    case 'mysql':
    case 'pgsql':
      return "COALESCE(" . implode(',', $array) . ")";
  }
}

/**
 * A helper function to do cross-database padding of date parts.
 *
 * @param string $str
 *   A string to apply padding to
 * @param int $size
 *   The size the final string should be
 * @param string $pad
 *   The value to pad the string with
 * @param string $side
 *   The side of the string to pad
 */
function date_sql_pad($str, $size = 2, $pad = '0', $side = 'l') {
  switch ($side) {
    case 'r':
      return "RPAD($str, $size, '$pad')";
    default:
      return "LPAD($str, $size, '$pad')";
  }
}

