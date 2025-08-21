<?php

namespace App\Service;

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Symfony\Component\HttpFoundation\JsonResponse;

class FacebookService
{
    private $accessToken;

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function getInsights(): JsonResponse
    {
        $fb = new Facebook([
            'app_id' => 'YOUR_APP_ID',
            'app_secret' => 'YOUR_APP_SECRET',
            'default_graph_version' => 'v19.0', // Use the latest stable version
        ]);

        try {
            $response = $fb->get('/me/insights?metric=impressions,reach&period=day', $this->accessToken);
            $data = $response->getGraphEdge();

            // Process and display the insights data
        } catch (FacebookResponseException $e) {
            // When Graph returns an error
            return new JsonResponse('Graph returned an error: ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            // When validation fails or other local issues
            return new JsonResponse('Facebook SDK returned an error: ' . $e->getMessage());
        }
        
        return new JsonResponse($data);
    }
}