<?php

namespace App\Http\Controllers;

use App\Models\SmsSetting;
use App\Services\Sms\SmsCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsCallbackController extends Controller
{
    public function __invoke(Request $request, SmsCallbackHandler $handler): JsonResponse
    {
        $settings = SmsSetting::current();
        $expected = (string) ($settings->api_key ?? '');
        $provided = (string) $request->header('X-API-Key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $result = $handler->handle($payload);

        return response()->json(['success' => true] + (isset($result['duplicate']) ? ['duplicate' => true] : []));
    }
}
