<?php

namespace App\Core\Data\DQL;

enum Stage: int
{
    case INITIAL = 1;
    case FIELD_INITIALIZATION = 2;
    case TABLE_DEFINITION = 3;
    case WHERE_CONDITION_PHASE = 4;
    case GROUP_BY_PHASE = 5;
    case HAVING_PHASE = 6;
    case ORDER_RECORDS_PHASE = 7;
    case LIMIT_RECORDS_PHASE = 8;
    case FINAL = 9;
}
