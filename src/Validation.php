<?php

namespace Inok\Slim\Validation;

use ArrayAccess;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Factory;
use SimpleXMLElement;
use Slim\Routing\RouteContext;

/**
 * Validation for Slim.
 */
class Validation
{
  /**
   * Validators.
   *
   * @var array
   */
  protected array $validators = [];

  /**
   * Options.
   *
   * @var array
   */
  protected array $options = [];

  /**
   * The translator to use for the exception message.
   *
   * @var callable
   */
  protected $translator = null;

  /**
   * Errors from the validation.
   *
   * @var array
   */
  protected array $errors = [];

  /**
   * The 'errors' attribute name.
   *
   * @var string
   */
  protected string $errors_name = 'errors';

  /**
   * The 'has_error' attribute name.
   *
   * @var string
   */
  protected string $has_errors_name = 'has_errors';

  /**
   * The 'validators' attribute name.
   *
   * @var string
   */
  protected string $validators_name = 'validators';

  /**
   * The 'translator' attribute name.
   *
   * @var string
   */
  protected string $translator_name = 'translator';

  /**
   * Create new Validator service provider.
   *
   * @param null|array|ArrayAccess $validators
   * @param callable|null $translator
   * @param array $options
   */
  public function __construct($validators = null, callable $translator = null, array $options = []) {
    // Set the validators
    if (is_array($validators) || $validators instanceof ArrayAccess) {
      $this->validators = $validators;
    } elseif (is_null($validators)) {
      $this->validators = [];
    }
    $this->translator = $translator;
    $this->setTranslatorFactory();
    $this->options = array_merge($this->options, $options);
  }

  /**
   * Setting Translator Factory
   *
   * @return void
   */
  private function setTranslatorFactory(): void {
    $factoryInstance = Factory::getDefaultInstance()->withTranslator(
      is_null($this->translator) ? 'strval' : $this->translator
    );
    Factory::setDefaultInstance(
      $factoryInstance
    );
  }

  /**
   * Validation middleware invokable class.
   *
   * @param ServerRequestInterface $request PSR7 request
   * @param RequestHandlerInterface $handler PSR7 response
   *
   * @return ResponseInterface
   */
  public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $this->errors = [];
    $this->validate($this->retrieveParams($request), $this->validators);

    $request = $request->withAttribute($this->errors_name, $this->getErrors());
    $request = $request->withAttribute($this->has_errors_name, $this->hasErrors());
    $request = $request->withAttribute($this->validators_name, $this->getValidators());
    $request = $request->withAttribute($this->translator_name, $this->getTranslator());

    return $handler->handle($request);
  }

  /**
   * Validate the parameters by the given params, validators and actual keys.
   * This method populates the $errors attribute.
   *
   * @param array $params The array of parameters.
   * @param array $validators The array of validators.
   * @param array $actualKeys An array that will save all the keys of the tree to retrieve the correct value.
   *
   * @return void
   */
  private function validate(array $params = [], array $validators = [], array $actualKeys = []): void {
    //Validate every parameter in the validators array
    foreach ($validators as $key => $validator) {
      $actualKeys[] = $key;
      $param = $this->getNestedParam($params, $actualKeys);
      if (is_array($validator)) {
        $this->validate($params, $validator, $actualKeys);
      } else {
        try {
          $validator->assert($param);
        } catch (NestedValidationException $exception) {
          $this->errors[implode('.', $actualKeys)] = $exception->getMessages();
        }
      }
      //Remove the key added in this foreach
      array_pop($actualKeys);
    }
  }

  /**
   * Get the nested parameter value.
   *
   * @param array|mixed $params An array that represents the values of the parameters.
   * @param array|mixed $keys An array that represents the tree of keys to use.
   *
   * @return array|void The nested parameter value by the given params and tree of keys.
   */
  private function getNestedParam($params = [], $keys = []) {
    if (empty($keys)) {
      return $params;
    }
    $firstKey = array_shift($keys);
    if (!$this->isArrayLike($params)) {
      return;
    }
    $params = (array)$params;
    if (!array_key_exists($firstKey, $params)) {
      return;
    }
    $paramValue = $params[$firstKey];
    return $this->getNestedParam($paramValue, $keys);
  }

  /**
   * Check if the given $params is an array like variable.
   *
   * @param $params array|SimpleXMLElement The variable to check.
   *
   * @return bool Returns true if the given $params parameter is array like.
   */
  private function isArrayLike($params): bool {
    return is_array($params) || $params instanceof SimpleXMLElement;
  }

  /**
   * Check if there are any errors.
   *
   * @return bool
   */
  public function hasErrors(): bool {
    return !empty($this->errors);
  }

  /**
   * Get errors.
   *
   * @return array The errors array.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Get validators.
   *
   * @return array The validators array.
   */
  public function getValidators(): array {
    return $this->validators;
  }

  /**
   * Set validators.
   *
   * @param array $validators The validators array.
   */
  public function setValidators(array $validators): void {
    $this->validators = $validators;
  }

  /**
   * Get translator.
   *
   * @return callable The translator.
   */
  public function getTranslator(): ?callable {
    return $this->translator;
  }

  /**
   * Set translator.
   *
   * @param callable $translator The translator.
   */
  public function setTranslator(callable $translator): void {
    $this->translator = $translator;
    $this->setTranslatorFactory();
  }

  /**
   * Merge Slim Params.
   *
   * @param ServerRequestInterface $request
   *
   * @return array Merged requested params
   */
  private function retrieveParams(ServerRequestInterface $request): array {
    return array_merge(
      $request->getQueryParams(),
      $this->retrieveBodyParams($request),
      $this->retrieveRouteParams($request)
    );
  }

  /**
   * Retrieve Route Params.
   *
   * @param ServerRequestInterface $request
   *
   * @return array Route params
   */
  private function retrieveRouteParams(ServerRequestInterface $request): array {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();
    return $route->getArguments();
  }

  /**
   * Retrieve Body Params (XML, JSON)
   *
   * @param ServerRequestInterface $request
   *
   * @return array Body params
   */
  private function retrieveBodyParams(ServerRequestInterface $request): array {
    $params = $request->getParsedBody();
    return (array) $params;
  }
}
