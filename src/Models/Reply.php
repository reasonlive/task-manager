<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Data\DQL\Query;

class Reply extends \App\Core\Data\Model
{
    protected ?string $table = 'replies';
}