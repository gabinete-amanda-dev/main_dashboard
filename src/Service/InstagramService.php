<?php

namespace App\Service;

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Symfony\Component\HttpFoundation\JsonResponse;
// use Instagram\Api\Instagram; // Removed because the class does not exist

use InstagramScraper\Instagram;
use GuzzleHttp\Client;


class InstagramService
{
    private $instagram;

    public function __construct(string $accessToken)
    {
        $this->instagram = Instagram::withCredentials(
            new Client(),
            $accessToken,
            false,
            false // or true, depending on whether you want to enable debug mode
        );
    }

    public function getUserMedia(string $username, int $count = 12)
    {
        // Fetch user media using InstagramScraper\Instagram
        return $this->instagram->getMedias($username, $count);
    }

    // Add other methods for posting, insights, etc.
}