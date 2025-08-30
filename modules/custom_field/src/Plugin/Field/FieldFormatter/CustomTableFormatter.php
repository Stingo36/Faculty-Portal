<?php

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'custom_table' formatter.
 *
 * Formats the custom field items as html table.
 *
 * @FieldFormatter(
 *   id = "custom_table",
 *   label = @Translation("Table"),
 *   weight = 2,
 *   field_types = {
 *     "custom"
 *   }
 * )
 */
class CustomTableFormatter extends BaseFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    foreach ($this->getCustomFieldItems() as $name => $custom_item) {
      unset($form['fields'][$name]['label_display']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $component = Html::cleanCssIdentifier($this->fieldDefinition->get('field_name'));
    $custom_items = $this->getCustomFieldItems();
    $header = [];
    foreach ($custom_items as $custom_item) {
      $header[] = $custom_item->getLabel();
    }

    // Jam the whole table in the first row since we're rendering the main field
    // items as table rows.
    $elements[0] = [
      '#theme' => 'table',
      '#header' => $header,
      '#attributes' => [
        'class' => [$component],
      ],
      '#rows' => [],
    ];

    // Build the table rows and columns.
    foreach ($items as $delta => $item) {
      $elements[0]['#rows'][$delta]['class'][] = $component . '__item';
      $values = $this->getFormattedValues($item, $langcode);
      foreach ($custom_items as $name => $custom_item) {
        $markup = $values[$name]['value']['#markup'] ?? NULL;
        $elements[0]['#rows'][$delta]['data'][$name] = [
          'data' => [
            '#markup' => $markup,
          ],
          'class' => [$component . '__' . Html::cleanCssIdentifier($name)],
        ];
      }
    }

    return $elements;
  }

}
