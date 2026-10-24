<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Api = 'api';
    case Assistant = 'assistant';
}
