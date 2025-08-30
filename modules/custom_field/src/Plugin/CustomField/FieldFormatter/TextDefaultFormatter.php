<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "text_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "string_long",
 *   }
 * )
 */
class TextDefaultFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $output = $settings['value'];
    $widget_settings = $settings['widget_settings'];
    $formatted = $widget_settings['formatted'] ?? FALSE;
    if ($formatted) {
      // The ProcessedText element already handles cache context & tag bubbling.
      // @see \Drupal\filter\Element\ProcessedText::preRenderText()
      $build = [
        '#type' => 'processed_text',
        '#text' => $settings['value'],
        '#format' => $widget_settings['default_format'],
        '#langcode' => $item->getLangcode(),
      ];
      $output = $this->renderer->render($build);
    }

    return $output;
  }

}
