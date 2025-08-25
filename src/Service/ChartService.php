<?php

namespace App\Service;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class ChartService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder
    ) {}

    public function createDonutChart(
        string $label,
        array $labels,
        array $data,
        array $colors,
        array $options = []
    ): Chart {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        
        // Configurações padrão
        $defaultOptions = [
            'showTitle' => false,
            'cutout' => '65%',
            'borderWidth' => 2,
            'hoverOffset' => 12,
            'borderRadius' => 8,
            'titleColor' => '#374151',
            'legendPosition' => 'bottom',
        ];
        
        $config = array_merge($defaultOptions, $options);
        
        $chart->setData([
            'labels'   => $labels,
            'datasets' => [[
                'label'           => $label,
                'data'            => $data,
                'backgroundColor' => $colors,
                'borderColor'     => '#fff',
                'borderWidth'     => $config['borderWidth'],
                'hoverOffset'     => $config['hoverOffset'],
                'borderRadius'    => $config['borderRadius'],
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'title' => [
                    'display' => $config['showTitle'],
                    'text'    => $label,
                    'font'    => ['size' => 18, 'weight' => 'bold'],
                    'color'   => $config['titleColor']
                ],
                'legend' => [
                    'position' => $config['legendPosition'],
                    'labels' => [
                        'color' => '#334155',
                        'font' => ['size' => 13, 'weight' => 500],
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 18
                    ]
                ],
                'tooltip' => [
                    'backgroundColor' => '#0f172a',
                    'titleColor' => '#f1f5f9',
                    'bodyColor' => '#e2e8f0',
                    'padding' => 12,
                    'cornerRadius' => 10,
                    'displayColors' => true,
                    'usePointStyle' => true
                ],
            ],
            'cutout' => $config['cutout'],
        ]);

        return $chart;
    }
}