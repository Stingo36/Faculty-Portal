<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

/**
 * Plugin implementation of the 'number_integer' formatter.
 *
 * The 'Default' formatter is different for integer fields on the one hand, and
 * for decimal and float fields on the other hand, in order to be able to use
 * different settings.
 *
 * @FieldFormatter(
 *   id = "number_integer",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "integer",
 *   }
 * )
 */
class IntegerFormatter extends NumericFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'thousand_separator' => ',',
      'prefix_suffix' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function numberFormat($number, array $settings) {
    $settings += static::defaultSettings();
    return number_format($number, 0, '', $settings['thousand_separator']);
  }

}
