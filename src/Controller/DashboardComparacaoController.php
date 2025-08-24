<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardComparacaoController extends AbstractController
{
    #[Route('/dashboard/comparacao', name: 'app_dashboard_comparacao')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $user = $this->getUser();

        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social_comparacao.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        // Bases
        $networks = ['tiktok','instagram','youtube','kwai'];
        $labels = array_map('ucfirst', $networks);
        $palette = ['#60a5fa','#34d399','#fbbf24','#f87171'];

        // (A) Donut — Distribuição do Engajamento (%)
        $dist = $data['comparisons']['engagement_distribution_pct'] ?? [];
        $distValues = array_map(fn($n)=> (float)($dist[$n] ?? 0), $networks);

        $distDonut = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $distDonut->setData([
            'labels' => $labels,
            'datasets' => [[ 'label'=>'% do Engajamento', 'data'=>$distValues, 'backgroundColor'=>$palette ]]
        ]);
        $distDonut->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Distribuição do Engajamento (%)'],'legend'=>['position'=>'bottom']]
        ]);

        // (B) Bubble — Eficiência por Rede (x=views, y=efficiency_index, r=followers)
        $netData = $data['networks'] ?? [];
        $effIdx = $data['comparisons']['efficiency_index'] ?? [];

        $bubblePoints = [];
        foreach ($networks as $i => $n) {
            $views = (int)($netData[$n]['views'] ?? 0);
            $eff   = (float)($effIdx[$n] ?? 0);
            $fol   = (int)($netData[$n]['followers'] ?? 0);
            // Normaliza raio (Chart.js espera 'r' numérico em pixels)
            $r = 6 + (12 * ($fol / max(1, ($netData['tiktok']['followers'] ?? 1)))); // escala simples pelo maior (TikTok aqui)
            $bubblePoints[] = [
                'label' => ucfirst($n),
                'data'  => [[ 'x'=>$views, 'y'=>$eff, 'r'=>$r ]],
                'borderColor' => $palette[$i],
                'backgroundColor' => preg_replace('/\)$/',' ,0.25)', str_replace(')', ',0.25)', 'rgba(0,0,0,0)')), // fallback; abaixo defino melhor
            ];
        }
        // Ajusta cores de background de forma clara
        foreach ($bubblePoints as $i => &$ds) {
            $hex = $palette[$i]; // ex.: #60a5fa
            // converte #rrggbb -> rgba(r,g,b,0.25)
            [$r,$g,$b] = sscanf($hex, "#%02x%02x%02x");
            $ds['backgroundColor'] = "rgba($r,$g,$b,0.25)";
        }

        $efficiencyBubble = $chartBuilder->createChart(Chart::TYPE_BUBBLE);
        $efficiencyBubble->setData(['datasets' => $bubblePoints]);
        $efficiencyBubble->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Eficiência por Rede (Views × Índice; raio = Seguidores)'],'legend'=>['position'=>'bottom']],
            'scales'=>[
                'x'=>['title'=>['display'=>true,'text'=>'Views (Dia)']],
                'y'=>['title'=>['display'=>true,'text'=>'Índice de Eficiência (por 1k views)'], 'beginAtZero'=>true]
            ]
        ]);

        // (C) Barras — ROI de Esforço (engajamento por post)
        $roi = $data['comparisons']['roi_effort'] ?? [];
        $roiValues = array_map(fn($n)=> (float)($roi[$n] ?? 0), $networks);

        $roiBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $roiBar->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Engajamento por Post',
                'data'  => $roiValues,
                'backgroundColor' => $palette
            ]]
        ]);
        $roiBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'ROI de Esforço (Engajamento/Post)'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // (D) Linha — Crescimento Relativo (base = 100)
        $rel = $data['comparisons']['relative_growth'] ?? [];
        $relLabels = array_map(fn($d)=>$d['date'] ?? '', $rel);
        $seriesRel = [
            'TikTok'    => array_map(fn($d)=> (float)($d['tiktok']    ?? 0), $rel),
            'Instagram' => array_map(fn($d)=> (float)($d['instagram'] ?? 0), $rel),
            'YouTube'   => array_map(fn($d)=> (float)($d['youtube']   ?? 0), $rel),
            'Kwai'      => array_map(fn($d)=> (float)($d['kwai']      ?? 0), $rel),
        ];

        $growthLine = $chartBuilder->createChart(Chart::TYPE_LINE);
        $growthLine->setData([
            'labels' => $relLabels,
            'datasets' => [
                ['label'=>'TikTok',    'data'=>$seriesRel['TikTok'],    'borderColor'=>$palette[0], 'backgroundColor'=>'rgba(96,165,250,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Instagram', 'data'=>$seriesRel['Instagram'], 'borderColor'=>$palette[1], 'backgroundColor'=>'rgba(52,211,153,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'YouTube',   'data'=>$seriesRel['YouTube'],   'borderColor'=>$palette[2], 'backgroundColor'=>'rgba(251,191,36,0.2)', 'fill'=>false, 'tension'=>0.3],
                ['label'=>'Kwai',      'data'=>$seriesRel['Kwai'],      'borderColor'=>$palette[3], 'backgroundColor'=>'rgba(248,113,113,0.2)', 'fill'=>false, 'tension'=>0.3]
            ]
        ]);
        $growthLine->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Crescimento Relativo de Seguidores (base=100)'],'legend'=>['position'=>'bottom']],
            'scales'=>['y'=>['beginAtZero'=>false]]
        ]);

        // (E) Peers — tabela simples (sem gráfico)
        $peers = $data['peers'] ?? [];

        return $this->render('dashboard_comparacao/index.html.twig', [
            'controller_name'   => 'DashboardComparacaoController',
            'user'              => $user,
            'profile'           => $data['profile'] ?? null,
            'distDonut'         => $distDonut,
            'efficiencyBubble'  => $efficiencyBubble,
            'roiBar'            => $roiBar,
            'growthLine'        => $growthLine,
            'peers'             => $peers
        ]);
    }
}
