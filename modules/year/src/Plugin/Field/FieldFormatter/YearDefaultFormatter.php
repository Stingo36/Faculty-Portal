<?php

namespace Drupal\year\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'year_default' formatter.
 *
 * @FieldFormatter (
 *   id = "year_default",
 *   label = @Translation("Year"),
 *   field_types = {
 *     "year"
 *   }
 * )
 */
class YearDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $item->value,
      ];
    }

    return $element;
  }

}
