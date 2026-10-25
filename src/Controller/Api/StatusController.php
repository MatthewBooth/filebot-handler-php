<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController extends AbstractController
{
    #[Route(
        '/api/v1/status',
        name: 'app_api_status',
        methods: ['GET'],
        format: 'json'
    )]
    public function index(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'version' => '1.0',
        ]);
    }
}
