<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class About extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_name',
        'shop_location',
        'business_type',
        'tenant_user_id',
        'photo'
    ];

    public function tenantUser()
    {
        return $this->belongsTo(TenantUser::class);
    }
}
