<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Data\DQL\Aggregation;
use App\Core\Data\DQL\Query;

class Task extends \App\Core\Data\Model
{
    protected static string $table = 'tasks';
}