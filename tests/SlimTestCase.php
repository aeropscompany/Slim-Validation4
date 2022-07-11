<?php

namespace Inok\Slim\Validation\Tests;

use DI\ContainerBuilder;
use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class SlimTestCase extends TestCase
{
  /**
   * @var App|null
   */
  protected ?App $app;
  protected int $testIndex = 0;

  public static $expectedValidators;
  public static $expectedHasErrors;
  public static $expectedTranslator;
  public static $expectedErrors;

  final protected function setUp(): void {
    parent::setUp();
    $this->setExpectedValues(null, null, null, null);
    $this->app = $this->getAppInstance();
  }

  final protected function tearDown(): void {
    parent::tearDown();
    $this->app = null;
    $this->testIndex++;
  }

  /**
   * @throws Exception
   *
   * @return App
   */
  final private function getAppInstance(): App {
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();

    AppFactory::setContainer($container);
    $app = AppFactory::create();
    $app->addRoutingMiddleware();
    return $app;
  }

  final protected function createRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface {
    return (new ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);
  }

  final protected function createEmptyRequest(string $method, $uri, array $data = null): ServerRequestInterface {
    return $this->createRequest($method, $uri);
  }

  final protected function createJsonRequest(string $method, $uri, array $data = null): ServerRequestInterface {
    $request = $this->createRequest($method, $uri);
    if ($data !== null) {
      $request = $request->withParsedBody($data);
    }
    return $request->withHeader('Content-Type', 'application/json;charset=utf8');
  }

  final protected function createXMLRequest(string $method, $uri, string $data = null): ServerRequestInterface {
    $request = $this->createRequest($method, $uri);
    if ($data !== null) {
      $request = $request->withParsedBody(simplexml_load_string($data));
    }
    return $request->withHeader('Content-Type', 'application/xml;charset=utf8');
  }

  public static function createAppResponse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    $errors = $request->getAttribute('errors');
    $hasErrors = $request->getAttribute('has_errors');
    $validators = $request->getAttribute('validators');
    $translator = $request->getAttribute('translator');

    Assert::assertEquals(self::$expectedValidators, $validators);
    Assert::assertEquals(self::$expectedHasErrors, $hasErrors);
    Assert::assertEquals(self::$expectedTranslator, $translator);
    Assert::assertEquals(self::$expectedErrors, $errors);

    $response->getBody()->write('Hello world!');

    return $response;
  }

  final protected function setExpectedValues($expectedValidators, $expectedHasErrors, $expectedTranslator, $expectedErrors): void {
    self::$expectedValidators = $expectedValidators;
    self::$expectedTranslator = $expectedTranslator;
    self::$expectedHasErrors = $expectedHasErrors;
    self::$expectedErrors = $expectedErrors;
  }
}
