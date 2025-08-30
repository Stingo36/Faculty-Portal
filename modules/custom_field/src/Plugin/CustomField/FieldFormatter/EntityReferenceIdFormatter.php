<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'entity reference ID' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_entity_id",
 *   label = @Translation("Entity ID"),
 *   description = @Translation("Display the ID of the referenced entity."),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class EntityReferenceIdFormatter extends EntityReferenceFormatterBase {

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
      '#plain_text' => $entity->id(),
      '#cache' => [
        'tags' => $entity->getCacheTags(),
      ],
    ];

    return $this->renderer->render($build);
  }

}
