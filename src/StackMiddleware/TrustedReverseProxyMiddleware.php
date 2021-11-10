<?php

declare(strict_types=1);

namespace Drupal\trusted_reverse_proxy\StackMiddleware;

use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Massages the Settings to provide sensible defaults for cloud-native.
 */
class TrustedReverseProxyMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   HTTP Kernel.
   * @param \Drupal\Core\Site\Settings $settings
   *   Settings.
   */
  public function __construct(HttpKernelInterface $http_kernel, Settings $settings) {
    $this->httpKernel = $http_kernel;
    $this->settings = $settings;
  }

  /**
   * {@inheritDoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if (
      // Reverse proxy is not explicitly disabled (is unset/NULL otherwise)
      $this->settings->get('reverse_proxy') !== FALSE
      // No explicit addresses configured.
      && count($this->settings->get('reverse_proxy_addresses', [])) === 0
      // The reverse proxy is acting appropriately and sending a forwarded IP.
      && $request->headers->has('x-forwarded-for')
      // We are in a context where PHP can tell us the first hop.
      && $request->server->has('REMOTE_ADDR')
    ) {
      // The settings constructor re-sets the singleton.
      new Settings([
        'reverse_proxy' => TRUE,
        'reverse_proxy_addresses' => $this->detectReverseProxies($request),
      ] + $this->settings->getAll());
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Detect reverse proxies from an x-forwarded-for header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The detected reverse proxies.
   */
  protected function detectReverseProxies(Request $request): array {
    // First hop is assumed to be a reverse proxy in its own right.
    $proxies = [$request->server->get('REMOTE_ADDR')];
    // We may be further behind another reverse proxy (e.g., Traefik, Varnish)
    // Commas may or may not be followed by a space.
    // @see https://tools.ietf.org/html/rfc7239#section-7.1
    $forwardedFor = explode(
      ',',
      str_replace(', ', ',', $request->headers->get('x-forwarded-for'))
    );
    if (count($forwardedFor) > 1) {
      // The first value will be the actual client IP.
      array_shift($forwardedFor);
      array_unshift($proxies, ...$forwardedFor);
    }
    return $proxies;
  }

}
