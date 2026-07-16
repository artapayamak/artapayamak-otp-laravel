<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SendOtpService
{
    public function send($mobile, $otp)
    {
        $response = Http::withHeaders([
            'Authorization' => env('IPPANEL_API_TOKEN'),
        ])->post('https://edge.ippanel.com/v1/api/send', [
            'sending_type' => 'pattern',
            'from_number' => '+983000505',
            'code' => 'xxxxxxxxxxxxxxx',
            'recipients' => [$mobile],
            'params' => [
                'code' => $otp,
            ],
        ]);

        return $response->json();
    }
}
