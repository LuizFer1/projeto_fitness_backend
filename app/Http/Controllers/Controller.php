<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Fitness AI API Documentation',
    description: 'L5 Swagger OpenApi description for the Fitness Backend',
    contact: new OA\Contact(email: 'contact@example.com')
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
abstract class Controller
{
    //
}
