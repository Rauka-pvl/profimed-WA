<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected function getAccessToken(): string
    {
        $client = new Client();
        $client->setAuthConfig(config('firebase.credentials'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        return $token['access_token'];
    }

    public function sendNotification(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): array {
        $accessToken = $this->getAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/"
            . config('firebase.project_id')
            . "/messages:send";

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                // 'data' => $data
            ]
        ];

        return Http::withToken($accessToken)
            ->post($url, $payload)
            ->json();
    }
}
