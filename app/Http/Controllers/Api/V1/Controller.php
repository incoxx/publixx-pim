<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Traits\ApiResponse;
use App\Http\Traits\Filterable;
use App\Http\Traits\LocalizesResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ApiResponse;
    use Filterable;
    use LocalizesResponse;
}
