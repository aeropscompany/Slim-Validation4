<?php

namespace Inok\Slim\Validation\Tests;

use DI\ContainerBuilder;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class SlimTestCase extends TestCase
{
  /**
   * @throws Exception
   *
   * @return App
   */
  protected function getAppInstance(): App {
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();

    AppFactory::setContainer($container);
    $app = AppFactory::create();
    $app->addRoutingMiddleware();

    return $app;
  }

  protected function createRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface {
    return (new ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);
  }

  protected function createJsonRequest(string $method, $uri, array $data = null): ServerRequestInterface {
    $request = $this->createRequest($method, $uri);
    if ($data !== null) {
      $request = $request->withParsedBody($data);
    }
    return $request->withHeader('Content-Type', 'application/json');
  }
}
