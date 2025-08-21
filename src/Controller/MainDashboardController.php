<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;


final class MainDashboardController extends AbstractController
{
    #[Route('/main/dashboard', name: 'app_main_dashboard')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
            'datasets' => [
                [
                    'label' => 'My First dataset',
                    'backgroundColor' => 'rgb(255, 99, 132)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => [0, 10, 5, 2, 20, 30, 45],
                ],
            ],
        ]);

        $chart->setOptions([
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 100,
                ],
            ],
        ]);

        return $this->render('main_dashboard/index.html.twig', [
            'controller_name' => 'MainDashboardController',
            'chart' => $chart,
        ]);
    }

    private function getFacebookInsights(): Response
    {
        $fb = new Facebook([
            'app_id' => 'YOUR_APP_ID',
            'app_secret' => 'YOUR_APP_SECRET',
            'default_graph_version' => 'v19.0', // Use the latest stable version
        ]);

        $accessToken = 'YOUR_PAGE_ACCESS_TOKEN'; // Or user access token with page permissions

        try {
            $response = $fb->get('/YOUR_PAGE_ID/insights/page_impressions?period=week', $accessToken);
            $graphEdge = $response->getGraphEdge();

            $impressions = [];
            foreach ($graphEdge as $graphNode) {
                $impressions[] = $graphNode->asArray();
            }

            // Process and display the impressions data
        } catch (FacebookResponseException $e) {
            // When Graph returns an error
            return new Response('Graph returned an error: ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            // When validation fails or other local issues
            return new Response('Facebook SDK returned an error: ' . $e->getMessage());
        }
            return new Response('Facebook SDK returned an error: ');
    }
}
    
