<?php

namespace App\Enums;

enum PortalRoleIdentifier: string
{
    case SiteManager = 'site_manager';
    case AssistantSiteManager = 'assistant_site_manager';
    case FinishingForeman = 'finishing_foreman';
    case FensterOfficeStaff = 'fenster_office_staff';

    public function label(): string
    {
        return match ($this) {
            self::SiteManager => 'Site Manager',
            self::AssistantSiteManager => 'Assistant Site Manager',
            self::FinishingForeman => 'Finishing Foreman',
            self::FensterOfficeStaff => 'Fenster Office Staff',
        };
    }

    public function isSiteRole(): bool
    {
        return in_array($this, self::siteRoles(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function siteRoles(): array
    {
        return [
            self::SiteManager,
            self::AssistantSiteManager,
            self::FinishingForeman,
        ];
    }
}
