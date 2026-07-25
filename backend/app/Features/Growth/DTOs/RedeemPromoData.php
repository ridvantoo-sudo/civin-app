<?php

namespace App\Features\Growth\DTOs;

final readonly class RedeemPromoData
{
    public function __construct(public string $code) {}
}
