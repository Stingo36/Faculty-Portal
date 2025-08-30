<?php

namespace Drupal\year\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a year field mapper.
 *
 * @FeedsTarget(
 *   id = "year",
 *   field_types = {"year"}
 * )
 */
class Year extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value')
      ->markPropertyUnique('value');

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    $value = is_string($values['value']) ? trim($values['value']) : $values['value'];
    $values['value'] = is_numeric($value) ? (int) $value : '';
  }

}
