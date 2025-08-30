<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\file\FileInterface;

/**
 * Plugin implementation of the 'file_url_plain' formatter.
 *
 * @FieldFormatter(
 *   id = "file_url_plain",
 *   label = @Translation("URL to file"),
 *   field_types = {
 *     "file",
 *     "image",
 *   }
 * )
 */
class UrlPlainFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $file = $settings['value'];

    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $access = $this->checkAccess($file);
    if (!$access->isAllowed()) {
      return NULL;
    }

    $build = [
      '#markup' => $file->createFileUrl(),
      '#cache' => [
        'tags' => $file->getCacheTags(),
      ],
    ];

    return $this->renderer->render($build);
  }

}
