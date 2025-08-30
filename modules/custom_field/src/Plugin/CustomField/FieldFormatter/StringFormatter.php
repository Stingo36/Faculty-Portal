<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'string' custom field formatter.
 *
 * Value renders as it is entered by the user.
 *
 * @FieldFormatter(
 *   id = "string",
 *   label = @Translation("Plain text"),
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "uri",
 *     "email",
 *     "map",
 *     "telephone",
 *     "uuid",
 *     "color",
 *   }
 * )
 */
class StringFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'prefix_suffix' => FALSE,
      'key_label' => 'label',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $settings) {
    $settings += static::defaultSettings();
    $elements['key_label'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Select the output when values are restricted in field widget.'),
      '#options' => [
        'key' => $this->t('Key'),
        'label' => $this->t('Label'),
      ],
      '#default_value' => $settings['key_label'],
    ];
    $elements['prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display prefix/suffix'),
      '#default_value' => $settings['prefix_suffix'],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $formatter_settings = $settings['formatter_settings'] + static::defaultSettings();
    $allowed_values = $settings['widget_settings']['allowed_values'] ?? [];
    $output = $settings['value'];

    // Account for map data types.
    if (is_array($output)) {
      if (empty($output)) {
        return NULL;
      }
      return '<pre>' . json_encode($output, JSON_PRETTY_PRINT) . '</pre>';
    }

    if (!empty($allowed_values) && $formatter_settings['key_label'] == 'label') {
      $index = array_search($output, array_column($allowed_values, 'key'));
      $output = $index !== FALSE ? $allowed_values[$index]['value'] : $output;
    }
    elseif ($formatter_settings['prefix_suffix'] ?? FALSE) {
      $prefix = $settings['widget_settings']['prefix'] ?? '';
      $suffix = $settings['widget_settings']['suffix'] ?? '';
      $output = $prefix . $output . $suffix;
    }

    return $output;
  }

}
