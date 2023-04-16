<?php

namespace Inok\Slim\Validation\Tests\Validators\Rules;

use Psr\Http\Message\UploadedFileInterface;
use Respect\Validation\Rules\AbstractRule;

class FileVal extends AbstractRule {
  private ?int $size;
  private ?array $mediaTypes;
  private UploadedFileInterface $file;

  public function __construct(?int $size = null, ?array $mediaTypes = null) {
    $this->size = $size;
    $this->mediaTypes = $mediaTypes;
  }

  public function validate($input): bool {
    if (!($input instanceof UploadedFileInterface)) {
      return false;
    }

    $this->file = $input;
    return ($this->file->getError() === UPLOAD_ERR_OK && $this->checkFileSize() && $this->checkFileMediaType());
  }

  private function checkFileSize(): bool {
    if (is_null($this->size)) {
      return true;
    }
    return ($this->file->getSize() <= $this->size);
  }

  private function checkFileMediaType(): bool {
    if (is_null($this->mediaTypes)) {
      return true;
    }
    return in_array($this->file->getClientMediaType(), $this->mediaTypes);
  }
}
