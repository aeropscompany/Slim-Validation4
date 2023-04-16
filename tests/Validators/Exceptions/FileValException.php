<?php

namespace Inok\Slim\Validation\Tests\Validators\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class FileValException extends ValidationException {
  protected $defaultTemplates = [
    self::MODE_DEFAULT => [
      self::STANDARD => 'This field must be correct file with parameters'
    ],
    self::MODE_NEGATIVE => [
      self::STANDARD => 'This field must not be correct file with parameters'
    ]
  ];
}
