<?php

namespace App\Core\Data\DQL;

enum Operation: string
{
    case SELECT = 'SELECT';
    case INSERT = 'INSERT INTO';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE FROM';
}