<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Osiset\ShopifyApp\Contracts\PlanModel as IPlanModel;
use Osiset\ShopifyApp\Traits\PlanModel;

class Plan extends Model implements IPlanModel
{
    use HasFactory;
    use PlanModel;

    // Package'ın beklediği sütunlar zaten migration'da var.
}
