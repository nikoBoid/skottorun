<?php

namespace App\Enums;

enum UserRole: string
{
    case DungeonMaster = 'dm';
    case Player = 'player';
}
