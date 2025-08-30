<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'map_table' custom field formatter.
 *
 * @FieldFormatter(
 *   id = "map_table",
 *   label = @Translation("Table"),
 *   field_types = {
 *     "map",
 *   }
 * )
 */
class MapTableFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'key_label' => 'Key',
      'value_label' => 'Value',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $settings) {
    $settings += static::defaultSettings();
    $elements['key_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key label'),
      '#description' => $this->t('The table header label for key column'),
      '#default_value' => $settings['key_label'],
      '#maxlength' => 128,
    ];
    $elements['value_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value label'),
      '#description' => $this->t('The table header label for value column'),
      '#default_value' => $settings['value_label'],
      '#maxlength' => 128,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $formatter_settings = $settings['formatter_settings'] + static::defaultSettings();
    $values = $settings['value'];

    if (!is_array($values) || empty($values)) {
      return NULL;
    }

    $rows = [];
    foreach ($values as $mapping) {
      $rows[] = [
        $mapping['key'],
        $mapping['value'],
      ];
    }
    $build = [
      '#type' => 'table',
      '#header' => [
        'key' => $formatter_settings['key_label'],
        'value' => $formatter_settings['value_label'],
      ],
      '#rows' => $rows,
    ];

    return $this->renderer->render($build);
  }

}
