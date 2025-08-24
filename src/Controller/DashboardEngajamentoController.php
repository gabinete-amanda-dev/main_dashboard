<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardEngajamentoController extends AbstractController
{
    #[Route('/dashboard/engajamento', name: 'app_dashboard_engajamento')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $user = $this->getUser();

                // 1) Carrega JSON
        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social_engajamento.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        // 2) Bases
        $networks = ['tiktok','instagram','youtube','kwai'];
        $labels = array_map('ucfirst', $networks);

        $likes    = array_map(fn($n)=>$data['networks'][$n]['engagement']['likes']    ?? 0, $networks);
        $comments = array_map(fn($n)=>$data['networks'][$n]['engagement']['comments'] ?? 0, $networks);
        $shares   = array_map(fn($n)=>$data['networks'][$n]['engagement']['shares']   ?? 0, $networks);
        $saves    = array_map(fn($n)=>$data['networks'][$n]['engagement']['saves']    ?? 0, $networks);

        // 3) Barras empilhadas — Engajamento por rede
        $engStacked = $chartBuilder->createChart(Chart::TYPE_BAR);
        $engStacked->setData([
            'labels' => $labels,
            'datasets' => [
                ['label'=>'Likes',       'data'=>$likes,    'backgroundColor'=>'#60a5fa'],
                ['label'=>'Comentários', 'data'=>$comments, 'backgroundColor'=>'#34d399'],
                ['label'=>'Compart.',    'data'=>$shares,   'backgroundColor'=>'#fbbf24'],
                ['label'=>'Salvos',      'data'=>$saves,    'backgroundColor'=>'#f87171'],
            ],
        ]);
        $engStacked->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Engajamento por Rede (Empilhado)'],'legend'=>['position'=>'bottom']],
            'responsive'=>true,
            'scales'=>[
                'x'=>['stacked'=>true],
                'y'=>['stacked'=>true,'beginAtZero'=>true]
            ]
        ]);

        // 4) Radar — Taxa de Engajamento (%)
        $rate = $data['comparisons']['engagement_rate_by_network_pct'] ?? [];
        $rateValues = array_map(fn($n)=> (float)($rate[$n] ?? 0), $networks);

        $engRateRadar = $chartBuilder->createChart(Chart::TYPE_RADAR);
        $engRateRadar->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Taxa de Engajamento (%)',
                'data'  => $rateValues,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59,130,246,0.2)',
                'pointBackgroundColor' => '#3b82f6',
            ]],
        ]);
        $engRateRadar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Taxa de Engajamento por Rede (%)'],'legend'=>['position'=>'bottom']],
            'scales'=>['r'=>['beginAtZero'=>true]]
        ]);

        // 5) Linha — Tendência diária de engajamento (por rede)
        $trend = $data['history']['engagement_trend'] ?? [];
        $trendLabels = array_map(fn($d)=>$d['date'] ?? '', $trend);

        $series = [
            'TikTok'    => array_map(fn($d)=>$d['tiktok']    ?? 0, $trend),
            'Instagram' => array_map(fn($d)=>$d['instagram'] ?? 0, $trend),
            'YouTube'   => array_map(fn($d)=>$d['youtube']   ?? 0, $trend),
            'Kwai'      => array_map(fn($d)=>$d['kwai']      ?? 0, $trend),
        ];

        $engLine = $chartBuilder->createChart(Chart::TYPE_LINE);
        $engLine->setData([
            'labels' => $trendLabels,
            'datasets' => [
                ['label'=>'TikTok',    'data'=>$series['TikTok'],    'borderColor'=>'#60a5fa', 'backgroundColor'=>'rgba(96,165,250,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Instagram', 'data'=>$series['Instagram'], 'borderColor'=>'#34d399', 'backgroundColor'=>'rgba(52,211,153,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'YouTube',   'data'=>$series['YouTube'],   'borderColor'=>'#fbbf24', 'backgroundColor'=>'rgba(251,191,36,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Kwai',      'data'=>$series['Kwai'],      'borderColor'=>'#f87171', 'backgroundColor'=>'rgba(248,113,113,0.2)', 'fill'=>false, 'tension'=>0.3],
            ],
        ]);
        $engLine->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Evolução do Engajamento (últimos dias)'],'legend'=>['position'=>'bottom']],
            'scales'=>['y'=>['beginAtZero'=>false]]
        ]);

        return $this->render('dashboard_engajamento/index.html.twig', [
            'controller_name' => 'DashboardEngajamentoController',
            'user' => $user,
            'profile'     => $data['profile'] ?? null,
            'engStacked'  => $engStacked,
            'engRateRadar'=> $engRateRadar,
            'engLine'     => $engLine,
        ]);
    }
}
