<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final readonly class HealthController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'), true);

        return new JsonResponse(
            [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
            ],
            $healthy ? 200 : 503,
        );
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            $this->database->query('SELECT 1');

            return ['ok' => true, 'message' => 'Connected'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
