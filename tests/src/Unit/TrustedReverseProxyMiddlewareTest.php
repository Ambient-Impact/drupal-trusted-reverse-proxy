<?php

declare(strict_types=1);

namespace Drupal\Tests\trusted_reverse_proxy\Unit;

use Drupal\Core\Site\Settings;
use Drupal\Core\StackMiddleware\ReverseProxyMiddleware;
use Drupal\Tests\UnitTestCase;
use Drupal\trusted_reverse_proxy\StackMiddleware\TrustedReverseProxyMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Unit test the trusted reverse proxy stack middleware.
 *
 * @group StackMiddleware
 */
class TrustedReverseProxyMiddlewareTest extends UnitTestCase {

  /**
   * HTTP Kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockHttpKernel;

  /**
   * Reverse Proxy middleware.
   *
   * @var \Drupal\Core\StackMiddleware\ReverseProxyMiddleware|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockReverseProxyMiddleware;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->mockHttpKernel = $this->createMock(HttpKernelInterface::class);
    $this->mockReverseProxyMiddleware = $this->createMock(ReverseProxyMiddleware::class);
  }

  /**
   * Return a well-formed reverse proxied request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function getWellFormedReverseProxyRequest(): Request {
    $request = new Request();
    $request->server->set('REMOTE_ADDR', '192.0.2.1');
    $request->headers->set('x-forwarded-for', '192.0.2.100');
    return $request;
  }

  /**
   * Test the middleware is a no-op when reverse proxy is explicitly disabled.
   */
  public function testProxyDisabled() {
    $storage = ['reverse_proxy' => FALSE];
    $settings = new Settings($storage);
    $middleware = new TrustedReverseProxyMiddleware($this->mockHttpKernel, $settings);
    $middleware->handle(new Request());
    // Assert we have not added any settings.
    $this->assertArrayEquals($storage, $settings->getAll());
  }

  /**
   * Test the middleware is a no-op when reverse proxy is explicitly disabled.
   */
  public function testProxyDisabledBecauseExplicitlySet() {
    $storage = [
      'reverse_proxy' => FALSE,
      'reverse_proxy_addresses' => ['192.0.2.255'],
    ];
    $settings = new Settings($storage);
    $middleware = new TrustedReverseProxyMiddleware($this->mockHttpKernel, $settings);
    $middleware->handle($this->getWellFormedReverseProxyRequest());
    // Assert we have not added any settings.
    $this->assertArrayEquals($storage, $settings->getAll());
  }

  /**
   * Test we properly set a reverse proxy config with only a single hop.
   */
  public function testSingleReverseProxy() {
    $storage = [];
    $settings = new Settings($storage);
    $middleware = new TrustedReverseProxyMiddleware($this->mockHttpKernel, $settings);
    $request = $this->getWellFormedReverseProxyRequest();
    $middleware->handle($request);
    $this->assertArrayEquals(
      [
        'reverse_proxy' => TRUE,
        // Should contain only the first hop.
        'reverse_proxy_addresses' => ['192.0.2.1'],
      ],
      $settings->getAll()
    );
    // Ensure the Client IP is correct.
    ReverseProxyMiddleware::setSettingsOnRequest($request, $settings);
    $this->assertEquals('192.0.2.100', $request->getClientIp());
  }

  /**
   * Test we properly set a reverse proxy config with only a single hop.
   */
  public function testMultipleReverseProxiesByXffDetection() {
    $storage = [];
    $settings = new Settings($storage);
    $middleware = new TrustedReverseProxyMiddleware($this->mockHttpKernel, $settings);
    $request = $this->getWellFormedReverseProxyRequest();
    $request->headers->set(
      'x-forwarded-for',
      $request->headers->get('x-forwarded-for') . ', 192.0.2.2'
    );
    $middleware->handle($request);
    $this->assertArrayEquals(
      [
        'reverse_proxy' => TRUE,
        'reverse_proxy_addresses' => ['192.0.2.2', '192.0.2.1'],
      ],
      $settings->getAll()
    );
    // Ensure the Client IP is correct.
    ReverseProxyMiddleware::setSettingsOnRequest($request, $settings);
    $this->assertEquals('192.0.2.100', $request->getClientIp());
  }

}
