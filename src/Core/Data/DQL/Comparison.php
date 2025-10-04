<?php

namespace App\Core\Data\DQL;

enum Comparison: string
{
    case LESS = '<';
    case LESS_EQUAL = '<=';
    case MORE = '>';
    case MORE_EQUAL = '>=';
    case EQUAL = '=';
    case NULL = 'IS NULL';
    case NOT_NULL = 'IS NOT NULL';
    case LIKE = 'LIKE';

    case LIKE_START_MARK = '^';
    case LIKE_END_MARK = '$';
    case LIKE_EXACTLY_MARK = '^$';
}