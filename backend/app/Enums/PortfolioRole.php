<?php

namespace App\Enums;

enum PortfolioRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canManagePortfolio(): bool
    {
        return $this === self::Owner;
    }

    public function canModifyPortfolioData(): bool
    {
        return $this === self::Owner || $this === self::Editor;
    }

    public function canManageMembers(): bool
    {
        return $this->canManagePortfolio();
    }
}
