<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ChartService;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(ChartService $chartService): Response
    {
        $user = $this->getUser();
        // Carrega JSON mock (exemplo)
        $jsonPath = $this->getParameter('kernel.project_dir') . '/config/mock/social_overview.json';
        $data = json_decode(file_get_contents($jsonPath), true);

        // Redes
        $networks = ['tiktok', 'instagram', 'youtube', 'kwai'];
        $labels = array_map('ucfirst', $networks);

        // Extrair valores
        $likes = array_map(fn($n) => $data['networks'][$n]['engagement']['likes'] ?? 0, $networks);
        $comments = array_map(fn($n) => $data['networks'][$n]['engagement']['comments'] ?? 0, $networks);
        $views = array_map(fn($n) => $data['networks'][$n]['reach']['views'] ?? 0, $networks);

        // Cores padrão para redes sociais
        $networkColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'];

        // Criar gráficos usando apenas o método genérico
        $likesChart = $chartService->createDonutChart('Likes', $labels, $likes, $networkColors);
        $commentsChart = $chartService->createDonutChart('Comentários', $labels, $comments, $networkColors);
        $viewsChart = $chartService->createDonutChart('Visualizações', $labels, $views, $networkColors);

        // Sentimento
        $sent = $data['totals']['sentiment'] ?? ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        $sentimentChart = $chartService->createDonutChart(
            'Sentimento',
            ['Positivo', 'Neutro', 'Negativo'],
            [$sent['positive'], $sent['neutral'], $sent['negative']],
            ['#10b981', '#9ca3af', '#ef4444']
        );

        // Hashtags
        $hashtags = $data['totals']['hashtags'] ?? [];
        arsort($hashtags);
        $topHashtags = array_slice($hashtags, 0, 5, true);
        $hashtagsChart = $chartService->createDonutChart(
            'Top Hashtags',
            array_keys($topHashtags),
            array_values($topHashtags),
            ['#60a5fa', '#34d399', '#fbbf24', '#f87171', '#c084fc']
        );
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'profile' => $data['profile'] ?? null,
            'totals' => $data['totals'] ?? null,
            'likesChart' => $likesChart,
            'commentsChart' => $commentsChart,
            'viewsChart' => $viewsChart,
            'sentimentChart' => $sentimentChart,
            'hashtagsChart' => $hashtagsChart,
        ]);
    }
}
