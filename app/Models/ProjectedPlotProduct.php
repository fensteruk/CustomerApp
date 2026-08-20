<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectedPlotProduct extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'projected_plot_id',
        'product_code',
        'quantity',
        'source_updated_at',
        'last_source_import_run_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'source_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectedPlot, $this> */
    public function projectedPlot(): BelongsTo
    {
        return $this->belongsTo(ProjectedPlot::class);
    }

    /** @return BelongsTo<SourceImportRun, $this> */
    public function lastSourceImportRun(): BelongsTo
    {
        return $this->belongsTo(SourceImportRun::class, 'last_source_import_run_id');
    }

    public function hasPositiveQuantity(): bool
    {
        return (float) $this->quantity > 0;
    }

    public function isBifold(): bool
    {
        return $this->hasPositiveQuantity()
            && str_contains(mb_strtoupper($this->product_code), 'BF');
    }
}
