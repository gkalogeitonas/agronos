<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case DANGER = 'danger';
    case WARNING = 'warning';
    case INFO = 'info';
}
