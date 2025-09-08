<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Service\FacebookService;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartBuilderInterface $chartBuilder, FacebookService $fbService): Response
    {
        $user = $this->getUser();

        $pageId = 'amandavettorazzo.sp';
        $name = $fbService->getPageName($pageId);
        $followers = (int) ($fbService->getPageFolloewers($pageId) ?? 0);
        $posts = $fbService->getPosts($pageId, 10);

        $topPosts = array_slice($posts, 0, 5);
        $labelsPosts = [];
        $likesData = [];
        $commentsData = [];
        foreach ($topPosts as $idx => $p) {
            $label = !empty($p['text']) ? mb_substr($p['text'], 0, 18) . (mb_strlen($p['text']) > 18 ? '…' : '') : 'Post '.($idx+1);
            $labelsPosts[] = $label;
            $likesData[] = (int) ($p['likes'] ?? 0);
            $commentsData[] = (int) ($p['comments'] ?? 0);
        }

        $likesChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $likesChart->setData([
            'labels'   => $labelsPosts,
            'datasets' => [[
                'label'           => 'Likes por Post (Facebook)',
                'data'            => $likesData,
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171','#c084fc'],
            ]],
        ]);
        $likesChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Likes por Post (Facebook)']],
        ]);

        $commentsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $commentsChart->setData([
            'labels'   => $labelsPosts,
            'datasets' => [[
                'label'           => 'Comentários por Post (Facebook)',
                'data'            => $commentsData,
                'backgroundColor' => ['#60a5fa','#34d399','#fbbf24','#f87171','#c084fc'],
            ]],
        ]);
        $commentsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Comentários por Post (Facebook)']],
        ]);

        $viewsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $viewsChart->setData([
            'labels'   => ['Seguidores','Posts Considerados'],
            'datasets' => [[
                'label'           => 'Seguidores x Posts',
                'data'            => [ $followers, max(count($topPosts), 1) ],
                'backgroundColor' => ['#60a5fa','#9ca3af'],
            ]],
        ]);
        $viewsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Seguidores x Posts']],
        ]);

        $totals = [
            'likes' => array_sum($likesData),
            'comments' => array_sum($commentsData),
            'views' => 0,
            'followers' => [ 'total' => $followers, 'new_today' => 0 ],
            'mentions' => 0,
        ];

        $sentimentChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $sentimentChart->setData([
            'labels'   => ['Positivo','Neutro','Negativo'],
            'datasets' => [[
                'label'           => 'Sentimento',
                'data'            => [0, 1, 0],
                'backgroundColor' => ['#10b981','#9ca3af','#ef4444'],
            ]],
        ]);
        $sentimentChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Sentimento (placeholder)']],
        ]);

        $hashtagsChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $hashtagsChart->setData([
            'labels'   => ['#facebook'],
            'datasets' => [[
                'label'           => 'Hashtags',
                'data'            => [1],
                'backgroundColor' => ['#60a5fa'],
            ]],
        ]);
        $hashtagsChart->setOptions([
            'plugins' => ['title' => ['display' => true, 'text' => 'Top Hashtags (placeholder)']],
        ]);

        return $this->render('dashboard/index.html.twig', [
            'user'           => $user,
            'profile'        => ['name' => $name ?? 'Facebook'],
            'totals'         => $totals,
            'likesChart'     => $likesChart,
            'commentsChart'  => $commentsChart,
            'viewsChart'     => $viewsChart,
            'sentimentChart' => $sentimentChart,
            'hashtagsChart'  => $hashtagsChart,
        ]);
    }
}

