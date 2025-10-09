<?php

namespace App\Repositories;

use App\Core\Data\Repository;
use App\Models\Tag;

class TagRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Tag::class);
    }
}