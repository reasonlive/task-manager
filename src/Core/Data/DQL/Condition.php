<?php

namespace App\Core\Data\DQL;

enum Condition: string
{
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';
}