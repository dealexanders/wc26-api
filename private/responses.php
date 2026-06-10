<?php

declare(strict_types=1);

function sendJson(
    mixed $data,
    int $statusCode = 200
): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

    exit;
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        sendJson([
            'error' => [
                'code' => 'EMPTY_BODY',
                'message' => 'Request body is required.',
            ],
        ], 400);
    }

    try {
        $body = json_decode(
            $rawBody,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        sendJson([
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Request body must contain valid JSON.',
            ],
        ], 400);
    }

    if (!is_array($body)) {
        sendJson([
            'error' => [
                'code' => 'INVALID_BODY',
                'message' => 'JSON object is required.',
            ],
        ], 400);
    }

    return $body;
}

function applyCors(array $allowedOrigins): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header(
        'Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data'
    );
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}