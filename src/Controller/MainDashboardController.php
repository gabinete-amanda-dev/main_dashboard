<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;



final class MainDashboardController extends AbstractController
{
    #[Route('/main/dashboard', name: 'app_main_dashboard')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $user_data = [
            'name' => 'Amanda Vetorazzo',
            'email' => 'amanda@vetorazzo.com',
            'redes' => [
                'twitter' => '@amandavetorazzo',
                'tiktok' => 'in/amandavetorazzo',
                'youtube' => 'amandavetorazzo',
                'instagram' => 'amandavetorazzo'
            ],
        ];

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);

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
            'responsive' => false,
            'scales' => [
                'y' => [
                    'suggestedMin' => 50,
                    'suggestedMax' => 50,
                ],
            ],
        ]);

        return $this->render('main_dashboard/index.html.twig', [
            'controller_name' => 'MainDashboardController',
            'chart' => $chart,
            'user_data' => $user_data,
        ]);
    }

    
}
    
