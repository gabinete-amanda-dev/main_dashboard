<?php

namespace App\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redirects unknown pages (404) to login if guest, or to dashboard if logged in.
 */
final class ExceptionListener implements EventSubscriberInterface
{
	public function __construct(
		private UrlGeneratorInterface $urlGenerator,
		private Security $security,
		// private bool $debug = false, // injected from parameter: kernel.debug
	) {
	}

	public function onKernelException(ExceptionEvent $event): void
	{
		// Keep Symfony's detailed error pages in debug environments
		// if ($this->debug) {
		// 	return;
		// }

		$exception = $event->getThrowable();
		if (!$exception instanceof NotFoundHttpException) {
			return;
		}

		$request = $event->getRequest();

		// Only handle typical browser HTML requests
		if (!$this->isHtmlRequest($request)) {
			return;
		}

		$path = $request->getPathInfo();
		if ($this->shouldIgnorePath($path)) {
			return;
		}

		$isLoggedIn = $this->security->isGranted('IS_AUTHENTICATED_REMEMBERED');
		$route = $isLoggedIn ? 'app_dashboard' : 'app_login';

		$event->setResponse(new RedirectResponse($this->urlGenerator->generate($route)));
	}

	private function isHtmlRequest($request): bool
	{
		if (method_exists($request, 'isXmlHttpRequest') && $request->isXmlHttpRequest()) {
			return false;
		}
		$acceptable = $request->getAcceptableContentTypes();
		return empty($acceptable) || in_array('text/html', $acceptable, true) || in_array('*/*', $acceptable, true);
	}

	private function shouldIgnorePath(string $path): bool
	{
		$ignorePrefixes = [
			'/auth/login',
			'/logout',
			'/_profiler',
			'/_wdt',
			'/build',
			'/css',
			'/js',
			'/images',
			'/favicon.ico',
		];

		foreach ($ignorePrefixes as $prefix) {
			if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
				return true;
			}
		}

		return false;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::EXCEPTION => ['onKernelException', 0],
		];
	}
}
