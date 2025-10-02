<?php
declare(strict_types=1);
namespace App\Enums\Task;

use App\Enums\ListsTraits;

enum Status: string
{
    use ListsTraits;
    case TODO = 'TODO';
    case IN_PROGRESS = 'IN_PROGRESS';
    case READY = 'READY';
    case FOR_REVIEW = 'FOR_REVIEW';
    case DONE = 'DONE';
}