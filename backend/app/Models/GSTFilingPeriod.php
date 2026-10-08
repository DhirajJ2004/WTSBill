<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class GSTFilingPeriod extends Model
{
    use BelongsToTenant;

    protected $table = 'gst_filing_periods';

    protected $fillable = [
        'company_id',
        'financial_year',
        'period_name', // M01..M12, Q1..Q4
        'return_type', // GSTR1, GSTR3B
        'status', // DRAFT, VALIDATED, READY_TO_FILE, FILED
        'summary_data_json',
        'filing_date',
        'arn_number',
        'filed_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    public function getSummaryDataAttribute(): array
    {
        if (empty($this->summary_data_json)) {
            return [];
        }
        return is_array($this->summary_data_json) ? $this->summary_data_json : (json_decode($this->summary_data_json, true) ?: []);
    }
}
