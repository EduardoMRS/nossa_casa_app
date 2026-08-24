<?php

namespace App\Http\Middleware;

use App\Models\AppInstance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidatePushGatewaySignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('services.native_push.gateway.enabled') === true, 404);

        $instanceId = $request->header('X-Push-Instance-ID');
        $instance = is_string($instanceId)
            ? AppInstance::query()->find($instanceId)
            : null;
        $timestamp = $request->header('X-Push-Timestamp');
        $nonce = $request->header('X-Push-Nonce');
        $signature = $request->header('X-Push-Signature');
        $fresh = is_string($timestamp) && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300;
        $nonceIsValid = is_string($nonce) && preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) === 1;
        $signaturePayload = $instanceId.'.'.$timestamp.'.'.$nonce.'.'.hash('sha256', $request->getContent());
        $publicKey = is_string($instance?->push_gateway_public_key)
            ? base64_decode($instance->push_gateway_public_key, true)
            : false;
        $decodedSignature = is_string($signature)
            ? base64_decode(strtr($signature, '-_', '+/'), true)
            : false;
        $signatureIsValid = $instance !== null
            && $instance->push_gateway_enabled
            && $fresh
            && $nonceIsValid
            && is_string($publicKey)
            && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && is_string($decodedSignature)
            && strlen($decodedSignature) === SODIUM_CRYPTO_SIGN_BYTES
            && sodium_crypto_sign_verify_detached($decodedSignature, $signaturePayload, $publicKey);

        if (! $signatureIsValid) {
            return response()->json([
                'message' => __('api.push.gateway_unauthorized'),
                'code' => 'PUSH_GATEWAY_UNAUTHORIZED',
            ], 401);
        }

        if (! Cache::add('push-gateway-nonce:'.$instanceId.':'.hash('sha256', $nonce), true, now()->addMinutes(5))) {
            return response()->json([
                'message' => __('api.push.gateway_replayed'),
                'code' => 'PUSH_GATEWAY_REPLAYED',
            ], 409);
        }

        $request->attributes->set('push_gateway_instance', $instance);

        return $next($request);
    }
}
