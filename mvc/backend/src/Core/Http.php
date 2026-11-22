<?php
declare(strict_types=1);
namespace Demo\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use InvalidArgumentException;

final class Http {
    public static function json(Response $response, array $data, int $status=200): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type','application/json; charset=utf-8')
            ->withHeader('Cache-Control','no-store')->withStatus($status);
    }
    public static function jsonBody(Request $request): array {
        try { $decoded = json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new InvalidArgumentException('JSON inválido'); }
        if (!is_array($decoded)) throw new InvalidArgumentException('JSON inválido');
        return $decoded;
    }
}
