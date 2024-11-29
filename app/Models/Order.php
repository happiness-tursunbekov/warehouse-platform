<?php

namespace App\Models;

use App\Services\ConnectWiseService;
use App\Traits\ModelCamelCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property OrderItem[]|Collection $items
*/
class Order extends Model
{
    use ModelCamelCase;

    protected $fillable = [
        'project_id',
        'team_id',
        'prepared_by_id',
        'accepted_by_member_id',
        'signature_id',
        'status',
        'total_cost',
        'customer_type',
        'customer_id'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getProjectAttribute()
    {
        $connectWiseService = new ConnectWiseService();

        return $connectWiseService->getProject($this->projectId, 'id,name');
    }

    public function getTeamAttribute()
    {
        $connectWiseService = new ConnectWiseService();

        return $connectWiseService->getSystemDepartment($this->teamId, 'id,name');
    }

    public function customer()
    {
        return $this->morphTo('customer');
    }
}
