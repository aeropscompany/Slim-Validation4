<?php

namespace Inok\Slim\Validation\Tests;

use Inok\Slim\Validation\Validation;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator as v;

class ValidationTest extends SlimTestCase
{
  public function testSetValidators() {
    $usernameValidator = v::alnum()->noWhitespace()->length(1, 20);
    $ageValidator = v::numericVal()->positive()->between(1, 100);
    $expectedValidators = [
      'username' => $usernameValidator,
      'age' => $ageValidator,
    ];
    $mw = new Validation($expectedValidators);
    $newUsernameValidator = v::alnum()->noWhitespace()->length(1, 10);
    $newAgeValidator = v::numericVal()->positive()->between(1, 20);
    $newValidators = [
      'username' => $newUsernameValidator,
      'age'      => $newAgeValidator,
    ];
    $mw->setValidators($newValidators);

    $expectedErrors = [
      'username' => [
        'length' => '"davidepastore" must have a length between 1 and 10',
      ],
      'age' => [
        'between' => '"89" must be between 1 and 20',
      ],
    ];

    $app = $this->getAppInstance();

    $app->get('/foo', function (ServerRequestInterface $request, ResponseInterface $response) use ($expectedErrors, $newValidators) {
      $errors = $request->getAttribute('errors');
      $hasErrors = $request->getAttribute('has_errors');
      $validators = $request->getAttribute('validators');

      Assert::assertTrue($hasErrors);
      Assert::assertEquals($expectedErrors, $errors);
      Assert::assertEquals($newValidators, $validators);

      $response->getBody()->write('Hello world!');

      return $response;
    })->add($mw);

    $params = [
      'username' => 'davidepastore',
      'age'      => 89,
    ];

    $url = sprintf('/foo?%s', http_build_query($params));
    $request = $this->createRequest('GET', $url);
    $response = $app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testSetTranslator() {
    $usernameValidator = v::alnum()->noWhitespace()->length(1, 5);
    $expectedValidators = [
      'username' => $usernameValidator,
    ];
    $translator = function ($message) {
      $messages = [
        'These rules must pass for {{name}}' => 'Queste regole devono passare per {{name}}',
        '{{name}} must be a string' => '{{name}} deve essere una stringa',
        '{{name}} must have a length between {{minValue}} and {{maxValue}}' => '{{name}} deve avere una dimensione di caratteri compresa tra {{minValue}} e {{maxValue}}',
      ];

      return $messages[$message];
    };

    $mw = new Validation($expectedValidators, $translator);
    $newTranslator = function ($message) {
      $messages = [
        'These rules must pass for {{name}}' => 'Queste regole devono passare per {{name}} (nuovo)',
        '{{name}} must be a string' => '{{name}} deve essere una stringa (nuovo)',
        '{{name}} must have a length between {{minValue}} and {{maxValue}}' => '{{name}} deve avere una dimensione di caratteri compresa tra {{minValue}} e {{maxValue}} (nuovo)',
      ];

      return $messages[$message];
    };
    $expectedErrors = [
      'username' => [
        'length' => '"davidepastore" deve avere una dimensione di caratteri compresa tra 1 e 5',
      ],
    ];

    $app = $this->getAppInstance();

    $app->post('/translator', function (ServerRequestInterface $request, ResponseInterface $response) use ($expectedErrors, $expectedValidators, $newTranslator) {
      $errors = $request->getAttribute('errors');
      $hasErrors = $request->getAttribute('has_errors');
      $validators = $request->getAttribute('validators');
      $translator = $request->getAttribute('translator');

      Assert::assertTrue($hasErrors);
      Assert::assertEquals($expectedErrors, $errors);
      Assert::assertEquals($expectedValidators, $validators);
      Assert::assertEquals($newTranslator, $translator);

      $response->getBody()->write('Hello world!');

      return $response;
    })->add($mw);

    $data = [
      'username' => 'davidepastore',
    ];

    $request = $this->createJsonRequest('POST', '/translator', $data);
    $response = $app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Test for validation.
   *
   * @dataProvider routeParamValidationProvider
   */
  public function testRouteParamValidation($expectedValidators, $expectedHasErrors, $expectedErrors, $attributes): void {
    $mw = new Validation($expectedValidators);

    $app = $this->getAppInstance();

    $urlPath = $url = ['route'];
    foreach ($attributes as $name => $value) {
      $urlPath[] .= '{'.$name.'}';
      $url[] = $value;
    }
    $app->get('/'.join('/', $urlPath), function (ServerRequestInterface $request, ResponseInterface $response) use ($expectedValidators, $expectedHasErrors, $expectedErrors) {
      $errors = $request->getAttribute('errors');
      $hasErrors = $request->getAttribute('has_errors');
      $validators = $request->getAttribute('validators');

      Assert::assertEquals($expectedValidators, $validators);
      Assert::assertEquals($expectedHasErrors, $hasErrors);
      Assert::assertEquals($expectedErrors, $errors);

      $response->getBody()->write('Hello world!');

      return $response;
    })->add($mw);

    $request = $this->createRequest('GET', '/'.join('/', $url));
    $response = $app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * The validation provider for the route parameters.
   */
  public function routeParamValidationProvider(): array {
    return [
      //Validation without errors
      [
        [
          'routeParam' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        false,
        [],
        ['routeParam' => 'test'],
      ],
      //Validation with errors
      [
        [
          'routeParam' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        true,
        [
          'routeParam' => [
            'length' => '"davidepastore" must have a length between 1 and 5',
          ],
        ],
        ['routeParam' => 'davidepastore'],
      ],
    ];
  }
}
