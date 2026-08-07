<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PortalRoleIdentifier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['customer_organisation_id', 'portal_role_id', 'name', 'email', 'password', 'is_active', 'is_preview_user'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_preview_user' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomerOrganisation, $this>
     */
    public function customerOrganisation(): BelongsTo
    {
        return $this->belongsTo(CustomerOrganisation::class);
    }

    /**
     * @return BelongsTo<PortalRole, $this>
     */
    public function portalRole(): BelongsTo
    {
        return $this->belongsTo(PortalRole::class);
    }

    /**
     * @return BelongsToMany<Site, $this>
     */
    public function assignedSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user_assignments')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CallOffBatch, $this>
     */
    public function submittedCallOffBatches(): HasMany
    {
        return $this->hasMany(CallOffBatch::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<CallOffBatchOperation, $this>
     */
    public function performedCallOffOperations(): HasMany
    {
        return $this->hasMany(CallOffBatchOperation::class, 'performed_by_user_id');
    }

    /**
     * @return HasMany<CallOffStatusHistory, $this>
     */
    public function performedCallOffStatusHistories(): HasMany
    {
        return $this->hasMany(CallOffStatusHistory::class, 'performed_by_user_id');
    }

    /** @return HasMany<PortalNotification, $this> */
    public function portalNotifications(): HasMany
    {
        return $this->hasMany(PortalNotification::class, 'notifiable_user_id');
    }

    public function hasPortalRole(PortalRoleIdentifier $role): bool
    {
        return $this->portalRole?->identifier === $role->value;
    }

    public function isSiteRole(): bool
    {
        $role = PortalRoleIdentifier::tryFrom((string) $this->portalRole?->identifier);

        return $role?->isSiteRole() ?? false;
    }

    public function isFensterOfficeStaff(): bool
    {
        return $this->hasPortalRole(PortalRoleIdentifier::FensterOfficeStaff);
    }

    public function hasCompletePortalProfile(): bool
    {
        return $this->is_active
            && $this->customer_organisation_id !== null
            && $this->portal_role_id !== null;
    }

    public function canAccessSite(Site $site): bool
    {
        if ($this->customer_organisation_id === null || $this->customer_organisation_id !== $site->customer_organisation_id) {
            return false;
        }

        return $this->assignedSites()
            ->whereKey($site->getKey())
            ->exists();
    }
}
