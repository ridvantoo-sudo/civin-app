<?php

namespace App\Features\Growth\Contracts;

interface PushGateway
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{ok:bool, reference:?string, error:?string}
     */
    public function send(string $deviceToken, string $title, string $body, ?string $actionUrl = null, array $data = []): array;

    public function isEnabled(): bool;
}
