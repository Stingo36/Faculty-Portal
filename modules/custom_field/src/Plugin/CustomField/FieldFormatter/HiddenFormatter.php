<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'hidden' formatter.
 *
 * @FieldFormatter(
 *   id = "hidden",
 *   label = @Translation("Hidden"),
 *   field_types = {
 *     "boolean",
 *     "string",
 *     "string_long",
 *     "uri",
 *     "email",
 *     "map",
 *     "telephone",
 *     "uuid",
 *     "color",
 *     "integer",
 *     "float",
 *     "datetime",
 *     "file",
 *     "entity_reference",
 *     "image",
 *   }
 * )
 */
class HiddenFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    return NULL;
  }

}
