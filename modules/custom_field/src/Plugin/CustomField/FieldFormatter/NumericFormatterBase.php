<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Parent plugin for decimal and integer formatters.
 */
abstract class NumericFormatterBase extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'thousand_separator' => '',
      'decimal_separator' => '.',
      'scale' => 2,
      'prefix_suffix' => TRUE,
      'key_label' => 'label',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $settings) {
    $settings += static::defaultSettings();
    $visible = $form['#visibility_path'];
    $options = [
      ''  => $this->t('- None -'),
      '.' => $this->t('Decimal point'),
      ',' => $this->t('Comma'),
      ' ' => $this->t('Space'),
      chr(8201) => $this->t('Thin space'),
      "'" => $this->t('Apostrophe'),
    ];
    $elements['thousand_separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Thousand marker'),
      '#options' => $options,
      '#default_value' => $settings['thousand_separator'] ?? ',',
      '#weight' => 0,
      '#states' => [
        'visible' => [
          ['select[name="' . $visible . '[format_type]"]' => ['value' => 'number_decimal']],
          ['select[name="' . $visible . '[format_type]"]' => ['value' => 'number_integer']],
        ],
      ],
    ];
    $elements['key_label'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Select the output when values are restricted in field widget.'),
      '#options' => [
        'key' => $this->t('Key'),
        'label' => $this->t('Label'),
      ],
      '#default_value' => $settings['key_label'] ?? self::defaultSettings()['key_label'],
    ];
    $elements['prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display prefix and suffix'),
      '#default_value' => $settings['prefix_suffix'] ?? FALSE,
      '#weight' => 10,
    ];

    return $elements;
  }

  /**
   * Formats a number.
   *
   * @param mixed $number
   *   The numeric value.
   * @param array $settings
   *   An array of settings.
   *
   * @return string
   *   The formatted number.
   */
  abstract protected function numberFormat($number, array $settings);

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $formatter_settings = $settings['formatter_settings'] + static::defaultSettings();
    $allowed_values = $settings['widget_settings']['allowed_values'] ?? [];
    $output = $this->numberFormat($settings['value'], $formatter_settings);
    if (!empty($allowed_values) && $formatter_settings['key_label'] == 'label') {
      $index = array_search($output, array_column($allowed_values, 'key'));
      $output = $index !== FALSE ? $allowed_values[$index]['value'] : $output;
    }
    elseif ($formatter_settings['prefix_suffix']) {
      $prefixes = isset($settings['widget_settings']['prefix']) ? array_map([
        'Drupal\Core\Field\FieldFilteredMarkup',
        'create',
      ], explode('|', $settings['widget_settings']['prefix'])) : [''];
      $suffixes = isset($settings['widget_settings']['suffix']) ? array_map([
        'Drupal\Core\Field\FieldFilteredMarkup',
        'create',
      ], explode('|', $settings['widget_settings']['suffix'])) : [''];
      $prefix = (count($prefixes) > 1) ? $this->formatPlural($settings['value'], $prefixes[0], $prefixes[1]) : $prefixes[0];
      $suffix = (count($suffixes) > 1) ? $this->formatPlural($settings['value'], $suffixes[0], $suffixes[1]) : $suffixes[0];
      $output = $prefix . $output . $suffix;
    }

    return $output;
  }

}
