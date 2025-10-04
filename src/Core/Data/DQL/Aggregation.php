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
}
