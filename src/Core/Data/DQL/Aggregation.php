<?php

namespace App\Core\Data\DQL;

enum Aggregation: string
{
    case COUNT = 'COUNT';
    case GROUP_CONCAT = 'GROUP_CONCAT';
    case SUM = 'SUM';
    case AVG = 'AVG';
    case MIN = 'MIN';
    case MAX = 'MAX';
    case JSON_AGG = 'JSON_ARRAYAGG';

    /**
     * @param array $fields table fields
     * @param string $alias table alias
     * @return string
     */
    public static function transformToJsonClause(array $fields, string $alias): string
    {
        $result = '';
        foreach ($fields as $i => $item) {
            $result .= "\"$item\", $alias.$item";

            if ($i < count($fields) - 1) {
                $result .= ', ';
            }
        }

        return "JSON_OBJECT($result)";
    }
}
