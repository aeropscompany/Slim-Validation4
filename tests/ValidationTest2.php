<?php

namespace Inok\Slim\Validation\Tests;

use Inok\Slim\Validation\Validation;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use Slim\Http\Response;
use Respect\Validation\Validator as v;
use Slim\Psr7\Environment;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

class ValidationTest2 extends TestCase
{
  /**
   * PSR7 request object.
   *
   * @var ServerRequestInterface
   */
  protected RequestInterface $request;

  /**
   * PSR7 response object.
   *
   * @var ResponseInterface
   */
  protected ResponseInterface $response;

  /**
   * PSR request handler object
   *
   * @var RequestHandlerInterface
   */
  protected RequestHandlerInterface  $handler;

  /**
   * Run before each test.
   */
  public function setupGet(): void {
    $uriFactory = new UriFactory();
    $uri = $uriFactory->createUri('https://example.com:443/foo/bar?username=davidepastore&age=89&optional=value');
    $headers = new Headers();
    $cookies = [];
    $serverParams = Environment::mock();
    $streamFactory = new StreamFactory();
    $body = $streamFactory->createStreamFromFile('php://temp', 'r+');
    $this->request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
    $this->response = new Response();
  }

  /**
   * Setup for the POST JSON requests.
   *
   * @param array $json The JSON to use to mock the body of the request.
   */
  public function setupJson(array $json): void {
    $uriFactory = new UriFactory();
    $uri = $uriFactory->createUri('https://example.com:443/foo');
    $headers = new Headers();
    $headers->addHeader('Content-Type', 'application/json;charset=utf8');
    $cookies = [];
    $serverParams = Environment::mock([
      'SCRIPT_NAME' => '/index.php',
      'REQUEST_URI' => '/foo',
      'REQUEST_METHOD' => 'POST',
    ]);
    $streamFactory = new StreamFactory();
    $body = $streamFactory->createStream(json_encode($json));
    $this->request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);
    $this->response = new Response();
  }

  /**
   * Setup for the XML POST requests.
   *
   * @param string $xml The XML to use to mock the body of the request.
   */
  public function setupXml(string $xml): void {
    $uriFactory = new UriFactory();
    $uri = $uriFactory->createUri('https://example.com:443/foo');
    $headers = new Headers();
    $headers->addHeader('Content-Type', 'application/xml;charset=utf8');
    $cookies = [];
    $serverParams = Environment::mock([
      'SCRIPT_NAME' => '/index.php',
      'REQUEST_URI' => '/foo',
      'REQUEST_METHOD' => 'POST',
    ]);
    $streamFactory = new StreamFactory();
    $body = $streamFactory->createStream($xml);
    $this->request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);
    $this->response = new Response();
  }

  /**
   * Test for validation.
   *
   * @dataProvider validationProvider
   */
  public function testValidation($expectedValidators, $expectedTranslator, $expectedHasErrors, $expectedErrors, string $requestType = 'GET', $body = null, array $options = []) {
    if ($requestType === 'GET') {
      $this->setupGet();
    } elseif ($requestType === 'JSON') {
      $this->setupJson($body);
    } elseif ($requestType === 'XML') {
      $this->setupXml($body);
    }

    if (is_null($expectedValidators)) {
      $mw = new Validation();
      $expectedValidators = [];
    } elseif (is_null($expectedTranslator)) {
      $mw = new Validation($expectedValidators, null, $options);
    } else {
      $mw = new Validation($expectedValidators, $expectedTranslator, $options);
    }

    $errors = null;
    $hasErrors = null;
    $validators = null;
    $translator = null;
    $next = function ($req, $res) use (&$errors, &$hasErrors, &$validators, &$translator) {
      $errors = $req->getAttribute('errors');
      $hasErrors = $req->getAttribute('has_errors');
      $validators = $req->getAttribute('validators');
      $translator = $req->getAttribute('translator');

      return $res;
    };

    $response = $mw($this->request, $this->handler);

    $this->assertEquals($expectedHasErrors, $hasErrors);
    $this->assertEquals($expectedErrors, $errors);
    $this->assertEquals($expectedValidators, $validators);
    $this->assertEquals($expectedTranslator, $translator);
  }

  /**
   * The validation provider.
   */
  public function validationProvider(): array {
    return [
      //Validation without errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 15),
        ],
        null,
        false,
        [],
      ],
      //Validation with errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'username' => [
            '"davidepastore" must have a length between 1 and 5',
          ],
        ],
      ],
      //Validation not existing optional parameter
      [
        [
          'notExisting' => v::optional(v::alpha()),
        ],
        null,
        false,
        [],
      ],
      //Validation not existing parameter
      [
        [
          'notExisting' => v::alpha(),
        ],
        null,
        true,
        [
          'notExisting' => [
            'null must contain only letters (a-z)',
          ],
        ],
      ],
      //Validation without validators
      [
        null,
        null,
        false,
        [],
      ],
      //Multiple validation without errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 20),
          'age' => v::numericVal()->positive()->between(1, 100),
        ],
        null,
        false,
        [],
      ],
      //Multiple validation with errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
          'age' => v::numericVal()->positive()->between(1, 60),
        ],
        null,
        true,
        [
          'username' => [
            '"davidepastore" must have a length between 1 and 5',
          ],
          'age' => [
            '"89" must be less than or equal to 60',
          ],
        ],
      ],
      //Validation with callable translator
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        function ($message) {
          $messages = [
            'These rules must pass for {{name}}' => 'Queste regole devono passare per {{name}}',
            '{{name}} must be a string' => '{{name}} deve essere una stringa',
            '{{name}} must have a length between {{minValue}} and {{maxValue}}' => '{{name}} deve avere una dimensione di caratteri compresa tra {{minValue}} e {{maxValue}}',
          ];

          return $messages[$message];
        },
        true,
        [
          'username' => [
            '"davidepastore" deve avere una dimensione di caratteri compresa tra 1 e 5',
          ],
        ],
      ],
      //JSON validation without errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 15),
        ],
        null,
        false,
        [],
        'JSON',
        [
          'username' => 'jsonusername',
        ],
      ],
      //JSON validation with errors
      [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'username' => [
            '"jsonusername" must have a length between 1 and 5',
          ],
        ],
        'JSON',
        [
          'username' => 'jsonusername',
        ],
      ],
      //Complex JSON validation without errors
      [
        [
          'type' => v::alnum()->noWhitespace()->length(3, 8),
          'email' => [
            'id' => v::numericVal()->positive()->between(1, 20),
            'name' => v::alnum()->noWhitespace()->length(1, 5),
          ],
        ],
        null,
        false,
        [],
        'JSON',
        [
          'type' => 'emails',
          'objectid' => '1',
          'email' => [
            'id' => 1,
            'enable_mapping' => '1',
            'name' => 'rq3r',
            'created_at' => '2016-08-23 13:36:29',
            'updated_at' => '2016-08-23 14:36:47',
          ],
        ],
      ],
      //Complex JSON validation with errors
      [
        [
          'type' => v::alnum()->noWhitespace()->length(3, 5),
          'email' => [
            'name' => v::alnum()->noWhitespace()->length(1, 2),
          ],
        ],
        null,
        true,
        [
          'type' => [
            '"emails" must have a length between 3 and 5',
          ],
          'email.name' => [
            '"rq3r" must have a length between 1 and 2',
          ],
        ],
        'JSON',
        [
          'type' => 'emails',
          'objectid' => '1',
          'email' => [
            'id' => 1,
            'enable_mapping' => '1',
            'name' => 'rq3r',
            'created_at' => '2016-08-23 13:36:29',
            'updated_at' => '2016-08-23 14:36:47',
          ],
        ],
      ],
      //More complex JSON validation without errors
      [
        [
          'email' => [
            'sub' => [
              'sub-sub' => [
                'finally' => v::numericVal()->positive()->between(1, 200),
              ],
            ],
          ],
        ],
        null,
        false,
        [],
        'JSON',
        [
          'finally' => 'notvalid',
          'email' => [
            'finally' => 'notvalid',
            'sub' => [
              'finally' => 'notvalid',
              'sub-sub' => [
                'finally' => 123,
              ],
            ],
          ],
        ],
      ],
      //More complex JSON validation with errors
      [
        [
          'email' => [
            'sub' => [
              'sub-sub' => [
                'finally' => v::numericVal()->positive()->between(1, 200),
              ],
            ],
          ],
        ],
        null,
        true,
        [
          'email.sub.sub-sub.finally' => [
            '321 must be less than or equal to 200',
          ],
        ],
        'JSON',
        [
          'finally' => 22,
          'email' => [
            'finally' => 33,
            'sub' => [
              'finally' => 97,
              'sub-sub' => [
                'finally' => 321,
              ],
            ],
          ],
        ],
      ],
      //Nested complex JSON validation with errors
      [
        [
          'message' => [
            'notification' => [
              'title' => v::stringType()->length(1, null)->setName("notificationTitle"),
              'body' => v::stringType()->length(1, null)->setName("notificationBody"),
              'actionName' => v::optional(v::stringType()->length(1, null))->setName("notificationAction")
            ]
          ],
        ],
        null,
        true,
        [
          'message.notification.title' => [
            'notificationTitle must be a string',
            'notificationTitle must have a length greater than 1',
          ],
          'message.notification.body' => [
            'notificationBody must be a string',
            'notificationBody must have a length greater than 1',
          ],
        ],
        'JSON',
        [
          'message' => [
            'notification' => 1,
          ],
        ],
      ],

      //XML validation without errors
      [
        [
          'name' => v::alnum()->noWhitespace()->length(1, 15),
        ],
        null,
        false,
        [],
        'XML',
        '<person><name>Josh</name></person>',
      ],
      //XML validation with errors
      [
        [
          'name' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'name' => [
            '"xmlusername" must have a length between 1 and 5',
          ],
        ],
        'XML',
        '<person><name>xmlusername</name></person>',
      ],
      //Complex XML validation without errors
      [
        [
          'type' => v::alnum()->noWhitespace()->length(3, 8),
          'email' => [
            'id' => v::numericVal()->positive()->between(1, 20),
            'name' => v::alnum()->noWhitespace()->length(1, 5),
          ],
        ],
        null,
        false,
        [],
        'XML',
        '<person>
              <type>emails</type>
              <objectid>1</objectid>
              <email>
                <id>1</id>
                <enable_mapping>1</enable_mapping>
                <name>rq3r</name>
                <created_at>2016-08-23 13:36:29</created_at>
                <updated_at>2016-08-23 14:36:47</updated_at>
              </email>
            </person>',
      ],
      //Complex XML validation with errors
      [
        [
          'type' => v::alnum()->noWhitespace()->length(3, 5),
          'email' => [
            'name' => v::alnum()->noWhitespace()->length(1, 2),
          ],
        ],
        null,
        true,
        [
          'type' => [
            '"emails" must have a length between 3 and 5',
          ],
          'email.name' => [
            '"rq3r" must have a length between 1 and 2',
          ],
        ],
        'XML',
        '<person>
              <type>emails</type>
              <objectid>1</objectid>
              <email>
                <id>1</id>
                <enable_mapping>1</enable_mapping>
                <name>rq3r</name>
                <created_at>2016-08-23 13:36:29</created_at>
                <updated_at>2016-08-23 14:36:47</updated_at>
              </email>
            </person>',
      ],
      //More complex XML validation without errors
      [
        [
          'email' => [
            'sub' => [
              'sub-sub' => [
                'finally' => v::numericVal()->positive()->between(1, 200),
              ],
            ],
          ],
        ],
        null,
        false,
        [],
        'XML',
        '<person>
              <finally>notvalid</finally>
              <email>
                <finally>notvalid</finally>
                <sub>
                  <finally>notvalid</finally>
                  <sub-sub>
                    <finally>123</finally>
                  </sub-sub>
                </sub>
              </email>
            </person>',
      ],
      //More complex XML validation with errors
      [
        [
          'email' => [
            'sub' => [
              'sub-sub' => [
                'finally' => v::numericVal()->positive()->between(1, 200),
              ],
            ],
          ],
        ],
        null,
        true,
        [
          'email.sub.sub-sub.finally' => [
            '"321" must be less than or equal to 200',
          ],
        ],
        'XML',
        '<person>
              <finally>22</finally>
              <email>
                <finally>33</finally>
                <sub>
                  <finally>97</finally>
                  <sub-sub>
                    <finally>321</finally>
                  </sub-sub>
                </sub>
              </email>
            </person>',
      ],
    ];
  }

  public function testSetValidators() {
    $this->setupGet();
    $usernameValidator = v::alnum()->noWhitespace()->length(1, 20);
    $ageValidator = v::numericVal()->positive()->between(1, 100);
    $expectedValidators = [
      'username' => $usernameValidator,
      'age' => $ageValidator,
    ];
    $mw = new Validation($expectedValidators);

    $errors = null;
    $hasErrors = null;
    $validators = [];
    $next = function ($req, $res) use (&$errors, &$hasErrors, &$validators) {
      $errors = $req->getAttribute('errors');
      $hasErrors = $req->getAttribute('has_errors');
      $validators = $req->getAttribute('validators');

      return $res;
    };

    $newUsernameValidator = v::alnum()->noWhitespace()->length(1, 10);
    $newAgeValidator = v::numericVal()->positive()->between(1, 20);
    $newValidators = [
      'username' => $newUsernameValidator,
      'age' => $newAgeValidator,
    ];

    $mw->setValidators($newValidators);

    $response = $mw($this->request, $this->response, $next);

    $expectedErrors = [
      'username' => [
        '"davidepastore" must have a length between 1 and 10',
      ],
      'age' => [
        '"89" must be less than or equal to 20',
      ],
    ];

    $this->assertTrue($hasErrors);
    $this->assertEquals($expectedErrors, $errors);
    $this->assertEquals($newValidators, $validators);
  }

  public function testSetTranslator() {
    $this->setupGet();
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

    $errors = null;
    $hasErrors = null;
    $translator = null;
    $validators = [];
    $next = function ($req, $res) use (&$errors, &$hasErrors, &$translator, &$validators) {
      $errors = $req->getAttribute('errors');
      $hasErrors = $req->getAttribute('has_errors');
      $validators = $req->getAttribute('validators');
      $translator = $req->getAttribute('translator');

      return $res;
    };

    $newTranslator = function ($message) {
      $messages = [
        'These rules must pass for {{name}}' => 'Queste regole devono passare per {{name}} (nuovo)',
        '{{name}} must be a string' => '{{name}} deve essere una stringa (nuovo)',
        '{{name}} must have a length between {{minValue}} and {{maxValue}}' => '{{name}} deve avere una dimensione di caratteri compresa tra {{minValue}} e {{maxValue}} (nuovo)',
      ];

      return $messages[$message];
    };

    $mw->setTranslator($newTranslator);

    $response = $mw($this->request, $this->response, $next);

    $this->assertTrue($hasErrors);
    $expectedErrors = [
      'username' => [
        '"davidepastore" deve avere una dimensione di caratteri compresa tra 1 e 5 (nuovo)',
      ],
    ];
    $this->assertEquals($expectedErrors, $errors);
    $this->assertEquals($expectedValidators, $validators);
    $this->assertEquals($newTranslator, $translator);
  }

  public function requestFactory($envData = []): ServerRequestInterface {
    $serverParams = Environment::mock($envData);
    $uriFactory = new UriFactory();
    $uri = $uriFactory->createUri('https://example.com:443/foo/bar?abc=123');
    $headers = new Headers($serverParams);
    $cookies = [];
    $streamFactory = new StreamFactory();
    $body = $streamFactory->createStream();
    return new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
  }

  /**
   * Test for validation.
   *
   * @dataProvider routeParamValidationProvider
   */
  public function testRouteParamValidation($expectedValidators, $expectedHasErrors, $expectedErrors, $attributes): void {
    $this->setupGet();
    $attrProp = new ReflectionProperty($this->request, 'attributes');
    $attrProp->setAccessible(true);
    $attrProp->setValue($this->request, ['routeInfo' => [
      0,
      1,
      $attributes,
    ]]);

    $mw = new Validation($expectedValidators);

    $errors = null;
    $hasErrors = null;
    $validators = [];
    $next = function ($req, $res) use (&$errors, &$hasErrors, &$validators) {
      $errors = $req->getAttribute('errors');
      $hasErrors = $req->getAttribute('has_errors');
      $validators = $req->getAttribute('validators');

      return $res;
    };

    $response = $mw($this->request, $this->response, $next);

    $this->assertEquals($expectedValidators, $validators);
    $this->assertEquals($expectedHasErrors, $hasErrors);
    $this->assertEquals($expectedErrors, $errors);
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
            '"davidepastore" must have a length between 1 and 5',
          ],
        ],
        ['routeParam' => 'davidepastore'],
      ],
    ];
  }
}
