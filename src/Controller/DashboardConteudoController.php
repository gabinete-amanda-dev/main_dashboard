<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardConteudoController extends AbstractController
{
    #[Route('/dashboard/conteudo', name: 'app_dashboard_conteudo')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $user = $this->getUser();

// 1) Carrega JSON
        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social_conteudo.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        $posts = $data['content']['recent_posts'] ?? [];
        $themes = $data['content']['themes_breakdown'] ?? [];
        $timeline = $data['content']['last_posts_timeline'] ?? [];
        $hours = $data['content']['best_posting_hours']['avg_engagement_by_hour'] ?? [];
        $weekdays = $data['content']['best_posting_hours']['avg_engagement_by_weekday'] ?? [];

        // ---------- (A) Scatter: Eficiência por Post (likes vs views), por rede ----------
        // Cria datasets por rede para colorir diferente
        $netColors = [
            'tiktok' => ['border'=>'#60a5fa','bg'=>'rgba(96,165,250,0.5)'],
            'instagram' => ['border'=>'#34d399','bg'=>'rgba(52,211,153,0.5)'],
            'youtube' => ['border'=>'#fbbf24','bg'=>'rgba(251,191,36,0.5)'],
            'kwai' => ['border'=>'#f87171','bg'=>'rgba(248,113,113,0.5)'],
        ];

        $byNetwork = [];
        foreach ($posts as $p) {
            $net = strtolower($p['network']);
            $point = [
                'x' => (int)($p['views'] ?? 0),
                'y' => (int)($p['likes'] ?? 0),
                // Usaremos o tamanho do ponto como proxy de outros engajamentos
                'r' => max(4, min(14, (int)(($p['comments'] + $p['shares'] + $p['saves']) / 50)))
            ];
            $byNetwork[$net]['points'][] = $point;
            $byNetwork[$net]['label'] = ucfirst($net);
        }

        $scatterDatasets = [];
        foreach ($byNetwork as $net => $bucket) {
            $scatterDatasets[] = [
                'type' => 'bubble',  // bubble permite radius 'r'
                'label' => $bucket['label'],
                'data' => $bucket['points'],
                'borderColor' => $netColors[$net]['border'] ?? '#6b7280',
                'backgroundColor' => $netColors[$net]['bg'] ?? 'rgba(107,114,128,0.5)'
            ];
        }

        $efficiencyScatter = $chartBuilder->createChart(Chart::TYPE_SCATTER);
        $efficiencyScatter->setData([
            'datasets' => $scatterDatasets
        ]);
        $efficiencyScatter->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Eficiência por Post (Likes vs Views)'],'legend'=>['position'=>'bottom']],
            'scales'=>[
                'x'=>['title'=>['display'=>true,'text'=>'Views']],
                'y'=>['title'=>['display'=>true,'text'=>'Likes'], 'beginAtZero'=>true]
            ]
        ]);

        // ---------- (B) Barras: Engajamento por Tema ----------
        // themes_breakdown já é { tema: valor }
        arsort($themes); // ordena desc
        $themeLabels = array_keys($themes);
        $themeValues = array_values($themes);

        $themesBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $themesBar->setData([
            'labels' => $themeLabels,
            'datasets' => [[
                'label' => 'Engajamento por Tema',
                'data'  => $themeValues,
                'backgroundColor' => '#60a5fa'
            ]]
        ]);
        $themesBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Engajamento por Tema'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // ---------- (C) Linha: Desempenho dos Últimos Posts (views) ----------
        // Ordena por data ascendente para linha temporal
        usort($timeline, fn($a,$b)=>strtotime($a['posted_at']) <=> strtotime($b['posted_at']));
        $tlLabels = array_map(fn($p)=>date('d/m H:i', strtotime($p['posted_at'])), $timeline);
        $tlValues = array_map(fn($p)=> (int)($p['views'] ?? 0), $timeline);

        $postsLine = $chartBuilder->createChart(Chart::TYPE_LINE);
        $postsLine->setData([
            'labels' => $tlLabels,
            'datasets' => [[
                'label' => 'Views por Post (ordem temporal)',
                'data'  => $tlValues,
                'borderColor' => '#34d399',
                'backgroundColor' => 'rgba(52,211,153,0.2)',
                'fill' => false,
                'tension' => 0.3
            ]]
        ]);
        $postsLine->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Desempenho dos Últimos Posts'],'legend'=>['position'=>'bottom']],
            'scales'=>['y'=>['beginAtZero'=>false]]
        ]);

        // ---------- (D) Barras: Melhores Horários (por hora) ----------
        // hours é { "0": valor, ..., "23": valor }
        $hourLabels = range(0,23);
        $hourValues = array_map(fn($h)=> (int)($hours[(string)$h] ?? 0), $hourLabels);

        $hoursBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $hoursBar->setData([
            'labels' => array_map(fn($h)=> sprintf('%02d:00',$h), $hourLabels),
            'datasets' => [[
                'label' => 'Engajamento Médio por Hora',
                'data'  => $hourValues,
                'backgroundColor' => '#fbbf24'
            ]]
        ]);
        $hoursBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Melhores Horários (por Hora)'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // ---------- (E) Barras: Melhores Dias da Semana ----------
        // week labels na ordem relevante
        $weekdayOrder = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        $weekdayLabels = $weekdayOrder;
        $weekdayValues = array_map(fn($d)=> (int)($weekdays[$d] ?? 0), $weekdayLabels);

        $weekdaysBar = $chartBuilder->createChart(Chart::TYPE_BAR);
        $weekdaysBar->setData([
            'labels' => $weekdayLabels,
            'datasets' => [[
                'label' => 'Engajamento Médio por Dia da Semana',
                'data'  => $weekdayValues,
                'backgroundColor' => '#a78bfa'
            ]]
        ]);
        $weekdaysBar->setOptions([
            'plugins'=>['title'=>['display'=>true,'text'=>'Melhores Dias (por Semana)'],'legend'=>['display'=>false]],
            'scales'=>['y'=>['beginAtZero'=>true]]
        ]);

        // 3) Top posts (ordenados por views desc — para a tabela)
        $topPosts = $posts;
        usort($topPosts, fn($a,$b)=>($b['views'] ?? 0) <=> ($a['views'] ?? 0));
        $topPosts = array_slice($topPosts, 0, 10);


        return $this->render('dashboard_conteudo/index.html.twig', [
            'controller_name' => 'DashboardConteudoController',
            'user' => $user,
            'profile'           => $data['profile'] ?? null,
            'topPosts'          => $topPosts,
            'efficiencyScatter' => $efficiencyScatter,
            'themesBar'         => $themesBar,
            'postsLine'         => $postsLine,
            'hoursBar'          => $hoursBar,
            'weekdaysBar'       => $weekdaysBar,
        ]);
    }
}
