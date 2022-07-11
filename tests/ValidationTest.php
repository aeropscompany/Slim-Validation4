<?php

namespace Inok\Slim\Validation\Tests;

use Inok\Slim\Validation\Validation;
use Respect\Validation\Validator as v;

class ValidationTest extends SlimTestCase
{
  /**
   * Test for validation.
   *
   * @dataProvider validationProvider
   */
  public function testValidation(
    $expectedValidators,
    $expectedTranslator,
    $expectedHasErrors,
    $expectedErrors,
    string $requestType = 'GET',
    $body = null,
    array $options = []
  ): void {
    $this->setExpectedValues($expectedValidators, $expectedHasErrors, $expectedTranslator, $expectedErrors);

    $url = '/'.$this->testIndex;
    $urlParams = ["username" => 'davidepastore',
                  "age" => 89,
                  "optional" => 'value'];
    $requestUrl = sprintf($url.'?%s', http_build_query($urlParams));
    $createRequest = 'createEmptyRequest';
    $method = "POST";
    if ($requestType === 'GET') {
      $method = "GET";
    }
    elseif ($requestType === 'JSON') {
      $createRequest = 'createJsonRequest';
    } elseif ($requestType === 'XML') {
      $createRequest = 'createXMLRequest';
    }

    if (is_null($expectedValidators)) {
      $mv = new Validation();
      self::$expectedValidators = [];
    } elseif (is_null($expectedTranslator)) {
      $mv = new Validation($expectedValidators, null, $options);
    } else {
      $mv = new Validation($expectedValidators, $expectedTranslator, $options);
    }

    $this->app->map([$method], $url, [SlimTestCase::class, ':createAppResponse'])->add($mv);

    $request = $this->$createRequest($method, $requestUrl, $body);
    $response = $this->app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * The validation provider.
   */
  public function validationProvider(): array {
    return [
      'Validation without errors' => [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 15),
        ],
        null,
        false,
        [],
      ],
      'Validation with errors' => [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'username' => [
            'length' => '"davidepastore" must have a length between 1 and 5',
          ],
        ],
      ],
      'Validation not existing optional parameter' => [
        [
          'notExisting' => v::optional(v::alpha()),
        ],
        null,
        false,
        [],
      ],
      'Validation not existing parameter' => [
        [
          'notExisting' => v::alpha(),
        ],
        null,
        true,
        [
          'notExisting' => [
            'alpha' => '`NULL` must contain only letters (a-z)',
          ],
        ],
      ],
      'Validation without validators' => [
        null,
        null,
        false,
        [],
      ],
      'Multiple validation without errors' => [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 20),
          'age' => v::numericVal()->positive()->between(1, 100),
        ],
        null,
        false,
        [],
      ],
      'Multiple validation with errors' => [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
          'age' => v::numericVal()->positive()->between(1, 60),
        ],
        null,
        true,
        [
          'username' => [
            'length' => '"davidepastore" must have a length between 1 and 5',
          ],
          'age' => [
            'between' => '"89" must be between 1 and 60',
          ],
        ],
      ],
      'Validation with callable translator' => [
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
            'length' => '"davidepastore" deve avere una dimensione di caratteri compresa tra 1 e 5',
          ],
        ],
      ],
      'JSON validation without errors' => [
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
      'JSON validation with errors' => [
        [
          'username' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'username' => [
            'length' => '"jsonusername" must have a length between 1 and 5',
          ],
        ],
        'JSON',
        [
          'username' => 'jsonusername',
        ],
      ],
      'Complex JSON validation without errors' => [
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
      'Complex JSON validation with errors' => [
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
            'length' => '"emails" must have a length between 3 and 5',
          ],
          'email.name' => [
            'length' => '"rq3r" must have a length between 1 and 2',
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
      'More complex JSON validation without errors' => [
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
      'More complex JSON validation with errors' => [
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
            'between' => '321 must be between 1 and 200',
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
      'Nested complex JSON validation with errors' => [
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
            'notificationTitle' => 'notificationTitle must have a length greater than or equal to 1',
          ],
          'message.notification.body' => [
            'notificationBody' => 'notificationBody must have a length greater than or equal to 1',
          ],
        ],
        'JSON',
        [
          'message' => [
            'notification' => 1,
          ],
        ],
      ],
      'XML validation without errors' => [
        [
          'name' => v::alnum()->noWhitespace()->length(1, 15),
        ],
        null,
        false,
        [],
        'XML',
        '<person><name>Josh</name></person>',
      ],
      'XML validation with errors' => [
        [
          'name' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        null,
        true,
        [
          'name' => [
            'length' => '"xmlusername" must have a length between 1 and 5',
          ],
        ],
        'XML',
        '<person><name>xmlusername</name></person>',
      ],
      'Complex XML validation without errors' => [
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
      'Complex XML validation with errors' => [
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
            'length' => '"emails" must have a length between 3 and 5',
          ],
          'email.name' => [
            'length' => '"rq3r" must have a length between 1 and 2',
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
      'More complex XML validation without errors' => [
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
      'More complex XML validation with errors' => [
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
            'between' => '"321" must be between 1 and 200',
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
      ]
    ];
  }

  /**
   * Test for set validators validation.
   */
  public function testSetValidators(): void {
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
    $this->setExpectedValues($newValidators, true, null, $expectedErrors);

    $this->app->get('/foo', [SlimTestCase::class, ':createAppResponse'])->add($mw);

    $params = [
      'username' => 'davidepastore',
      'age'      => 89,
    ];

    $url = sprintf('/foo?%s', http_build_query($params));
    $request = $this->createRequest('GET', $url);
    $response = $this->app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Test for set translator validation.
   */
  public function testSetTranslator(): void {
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

    $this->setExpectedValues($expectedValidators, true, $newTranslator, $expectedErrors);

    $this->app->post('/translator', [SlimTestCase::class, ':createAppResponse'])->add($mw);

    $data = [
      'username' => 'davidepastore',
    ];

    $request = $this->createJsonRequest('POST', '/translator', $data);
    $response = $this->app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Test for route params validation.
   *
   * @dataProvider routeParamValidationProvider
   */
  public function testRouteParamValidation($expectedValidators, $expectedHasErrors, $expectedErrors, $attributes): void {
    $mw = new Validation($expectedValidators);

    $urlPath = $url = [$this->testIndex];
    foreach ($attributes as $name => $value) {
      $urlPath[] .= '{'.$name.'}';
      $url[] = $value;
    }

    $this->setExpectedValues($expectedValidators, $expectedHasErrors, null, $expectedErrors);

    $this->app->get('/' . join('/', $urlPath), [SlimTestCase::class, ':createAppResponse'])->add($mw);

    $request = $this->createRequest('GET', '/'.join('/', $url));
    $response = $this->app->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * The validation provider for the route parameters.
   */
  public function routeParamValidationProvider(): array {
    return [
      'Validation without errors' => [
        [
          'routeParam' => v::alnum()->noWhitespace()->length(1, 5),
        ],
        false,
        [],
        ['routeParam' => 'test'],
      ],
      'Validation with errors' => [
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
