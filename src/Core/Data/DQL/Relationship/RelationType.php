<?php

namespace App\Core\Data\DQL\Relationship;

use App\Core\Data\DQL\Query;

enum RelationType: string
{
    case ONE_TO_ONE = 'oneToOne';
    case ONE_TO_MANY = 'oneToMany';
    case MANY_TO_ONE = 'manyToOne';
    case MANY_TO_MANY = 'manyToMany';
}
