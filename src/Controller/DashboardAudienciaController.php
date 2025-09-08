<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Service\ChartService;

final class DashboardAudienciaController extends AbstractController
{
    #[Route('/dashboard/audiencia', name: 'app_dashboard_audiencia')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartBuilderInterface $chartBuilder, ChartService $chartService): Response
    {
        $user = $this->getUser();

                // 1) Carrega JSON
        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social_audiencia.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        // 2) Bases
        $networks = ['tiktok','instagram','youtube','kwai'];
        $labels   = array_map('ucfirst', $networks);

        // ---------- (A) Linha: crescimento de seguidores ----------
        $trend = $data['comparisons']['follower_growth_trend'] ?? [];
        $trendLabels = array_map(fn($d) => $d['date'] ?? '', $trend);

        $seriesFollowers = [
            'TikTok'    => array_map(fn($d)=>$d['tiktok']    ?? 0, $trend),
            'Instagram' => array_map(fn($d)=>$d['instagram'] ?? 0, $trend),
            'YouTube'   => array_map(fn($d)=>$d['youtube']   ?? 0, $trend),
            'Kwai'      => array_map(fn($d)=>$d['kwai']      ?? 0, $trend),
        ];

        $followersLine = $chartBuilder->createChart(Chart::TYPE_LINE);
        $followersLine->setData([
            'labels' => $trendLabels,
            'datasets' => [
                ['label'=>'TikTok',    'data'=>$seriesFollowers['TikTok'],    'borderColor'=>'#60a5fa', 'backgroundColor'=>'rgba(96,165,250,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Instagram', 'data'=>$seriesFollowers['Instagram'], 'borderColor'=>'#34d399', 'backgroundColor'=>'rgba(52,211,153,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'YouTube',   'data'=>$seriesFollowers['YouTube'],   'borderColor'=>'#fbbf24', 'backgroundColor'=>'rgba(251,191,36,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Kwai',      'data'=>$seriesFollowers['Kwai'],      'borderColor'=>'#f87171', 'backgroundColor'=>'rgba(248,113,113,0.2)', 'fill'=>false, 'tension'=>0.3],
            ],
        ]);
        $followersLine->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Crescimento de Seguidores (últimos dias)'],'legend'=>['position'=>'bottom']],
            'scales'=>['y'=>['beginAtZero'=>false]]
        ]);

        // ---------- (B) Donut: distribuição de views no dia por rede ----------
        $viewsToday = $data['views_today'] ?? [];
        $viewsValues = array_map(fn($n)=> (int)($viewsToday[$n] ?? 0), $networks);

        // $viewsDistribution = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        // $viewsDistribution->setData([
        //     'labels'   => $labels,
        //     'datasets' => [[
        //         'label'           => 'Views (Dia)',
        //         'data'            => $viewsValues,
        //         'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171'],
        //     ]],
        // ]);
        // $viewsDistribution->setOptions([
        //     'plugins'=>['title'=>['display'=>true,'text'=>'Distribuição de Visualizações no Dia'],'legend'=>['position'=>'bottom']]
        // ]);
        $viewsDistribution = $chartService->createDonutChart(
            'Views (Dia)',
            $labels,
            $viewsValues,
            ['#60a5fa','#34d399','#fbbf24','#f87171']
        );

        // ---------- (C) Barras: audiência por região ----------
        $regions = $data['segments']['audience_by_region'] ?? [];
        // ordena por valor desc e pega top 8 (ex.: evita gráfico poluído)
        arsort($regions);
        $regions = array_slice($regions, 0, 8, true);

        $audienceRegionBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $audienceRegionBar->setData([
            'labels' => array_keys($regions),
            'datasets' => [[
                'label' => 'Seguidores por Região',
                'data'  => array_values($regions),
                'backgroundColor' => '#60a5fa',
            ]],
        ]);
        $audienceRegionBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Distribuição por Região'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // ---------- (D) Barras: audiência por faixa etária ----------
        $ages = $data['segments']['audience_by_age'] ?? [];
        // mantém a ordem típica de faixas
        $ageOrder = ['18-24','25-34','35-44','45-54','55+'];
        $ageLabels = array_values(array_filter($ageOrder, fn($k)=>array_key_exists($k,$ages)));
        $ageValues = array_map(fn($k)=>$ages[$k] ?? 0, $ageLabels);

        $audienceAgeBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $audienceAgeBar->setData([
            'labels' => $ageLabels,
            'datasets' => [[
                'label' => 'Seguidores por Faixa Etária',
                'data'  => $ageValues,
                'backgroundColor' => '#34d399',
            ]],
        ]);
        $audienceAgeBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Distribuição por Faixa Etária'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // ---------- (E) Donut: audiência por gênero (percentual) ----------
        $gender = $data['segments']['audience_by_gender_pct'] ?? [];
        $genderLabels = ['masculino','feminino','outros/nd'];
        $genderValues = array_map(fn($k)=> (float)($gender[$k] ?? 0), $genderLabels);

        // $audienceGenderDonut = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        // $audienceGenderDonut->setData([
        //     'labels' => ['Masculino','Feminino','Outros/ND'],
        //     'datasets' => [[
        //         'label' => 'Audiência por Gênero (%)',
        //         'data'  => $genderValues,
        //         'backgroundColor' => ['#60a5fa','#f472b6','#9ca3af'],
        //     ]],
        // ]);
        // $audienceGenderDonut->setOptions([
        //     'plugins'=>['title'=>['display'=>true,'text'=>'Audiência por Gênero (%)'],'legend'=>['position'=>'bottom']]
        // ]);
        $audienceGenderDonut = $chartService->createDonutChart(
            'Audiência por Gênero (%)',
            ['Masculino','Feminino','Outros/ND'],
            $genderValues,
            ['#60a5fa','#f472b6','#9ca3af']
        );

        return $this->render('dashboard_audiencia/index.html.twig', [
            'controller_name' => 'DashboardAudienciaController',
            'user' => $user,
            'profile'               => $data['profile'] ?? null,
            'followersLine'         => $followersLine,
            'viewsDistribution'     => $viewsDistribution,
            'audienceRegionBar'     => $audienceRegionBar,
            'audienceAgeBar'        => $audienceAgeBar,
            'audienceGenderDonut'   => $audienceGenderDonut,
            'networks'              => $data['networks'] ?? null,
        ]);
    }
}
