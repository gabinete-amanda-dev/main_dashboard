<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $user = $this->getUser();
        // Carrega JSON mock (exemplo)
        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        // Redes
        $networks = ['tiktok', 'instagram', 'youtube', 'kwai'];
        $labels   = array_map('ucfirst', $networks);

        // Extrair valores
        $likes     = array_map(fn($n) => $data['networks'][$n]['engagement']['likes'] ?? 0, $networks);
        $comments  = array_map(fn($n) => $data['networks'][$n]['engagement']['comments'] ?? 0, $networks);
        $views     = array_map(fn($n) => $data['networks'][$n]['reach']['views'] ?? 0, $networks);

        // Likes por rede (donut)
        $likesChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $likesChart->setData([
            'labels'   => $labels,
            'datasets' => [[
                'label'           => 'Likes',
                'data'            => $likes,
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171'],
            ]],
        ]);
        $likesChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Likes por Rede']],
        ]);

        // Comentários por rede (donut)
        $commentsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $commentsChart->setData([
            'labels'   => $labels,
            'datasets' => [[
                'label'           => 'Comentários',
                'data'            => $comments,
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171'],
            ]],
        ]);
        $commentsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Comentários por Rede']],
        ]);

        // Visualizações por rede (donut)
        $viewsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $viewsChart->setData([
            'labels'   => $labels,
            'datasets' => [[
                'label'           => 'Visualizações',
                'data'            => $views,
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171'],
            ]],
        ]);
        $viewsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Visualizações por Rede']],
        ]);

        // Sentimento (donut)
        $sent = $data['totals']['sentiment'] ?? ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        $sentimentChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $sentimentChart->setData([
            'labels'   => ['Positivo','Neutro','Negativo'],
            'datasets' => [[
                'label'           => 'Sentimento',
                'data'            => [$sent['positive'], $sent['neutral'], $sent['negative']],
                'backgroundColor' => ['#10b981','#9ca3af','#ef4444'],
            ]],
        ]);
        $sentimentChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Sentimento do Dia']],
        ]);

        // Top Hashtags (donut)
        $hashtags = $data['totals']['hashtags'] ?? [];
        arsort($hashtags);
        $topHashtags = array_slice($hashtags, 0, 5, true);
        $hashtagsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $hashtagsChart->setData([
            'labels'   => array_keys($topHashtags),
            'datasets' => [[
                'label'           => 'Hashtags',
                'data'            => array_values($topHashtags),
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171','#c084fc'],
            ]],
        ]);
        $hashtagsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Top Hashtags do Dia']],
        ]);
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'profile'        => $data['profile'] ?? null,
            'totals'         => $data['totals'] ?? null,
            'likesChart'     => $likesChart,
            'commentsChart'  => $commentsChart,
            'viewsChart'     => $viewsChart,
            'sentimentChart' => $sentimentChart,
            'hashtagsChart'  => $hashtagsChart,
        ]);
    }
}
