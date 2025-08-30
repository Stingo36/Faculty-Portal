<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'file_default' formatter.
 *
 * @FieldFormatter(
 *   id = "file_default",
 *   label = @Translation("Generic file"),
 *   field_types = {
 *     "file",
 *     "image",
 *   }
 * )
 */
class GenericFileFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $entity = $settings['value'];

    if (!$entity instanceof EntityInterface) {
      return NULL;
    }

    $access = $this->checkAccess($entity);
    if (!$access->isAllowed()) {
      return NULL;
    }

    $build = [
      '#theme' => 'file_link',
      '#file' => $entity,
      '#description' => NULL,
      '#cache' => [
        'tags' => $entity->getCacheTags(),
      ],
    ];

    return $this->renderer->render($build);
  }

}
