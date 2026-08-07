<?php

namespace App\Enums;

enum CallOffOperationType: string
{
    case Withdrawal = 'withdrawal';
    case Trash = 'trash';
    case Restore = 'restore';
    case QuickUndo = 'quick_undo';
}
