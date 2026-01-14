<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

/**
 * @OA\Info(
 *   version="1.0.0",
 *   title="Podrodze API",
 *   description="REST API documentation for the trip planning application.",
 *   @OA\Contact(email="support@podrodze.com")
 * )
 *
 * @OA\Server(
 *   url="http://localhost:8081/api/v1",
 *   description="API Server"
 * )
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
