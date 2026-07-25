<?php

namespace App\Features\Payments\Http\Controllers;

use App\Features\Payments\Actions\HandlePaymentWebhook;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Exceptions\WebhookVerificationException;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaymentWebhookController extends Controller
{
    public function __invoke(string $gateway, Request $request, HandlePaymentWebhook $action): JsonResponse
    {
        if (! PaymentGatewayKey::isValid($gateway)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        try {
            $event = $action->handle($gateway, $request);
        } catch (WebhookVerificationException $exception) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        } catch (PaymentGatewayException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::error('payments.webhook_error', ['gateway' => $gateway, 'error' => $exception->getMessage()]);

            // Ask the provider to retry later.
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['status' => $event->status]);
    }
}
