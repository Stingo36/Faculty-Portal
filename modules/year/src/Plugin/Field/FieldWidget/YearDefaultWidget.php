<?php

namespace Drupal\year\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'year_default' widget.
 *
 * @FieldWidget(
 *   id = "year_default",
 *   label = @Translation("Textfield"),
 *   field_types = {
 *     "year"
 *   }
 * )
 */
class YearDefaultWidget extends YearWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();

    $element['#description'] = $this->t('Enter a year from @min to @max.', [
      '@min' => $field_settings['min'],
      '@max' => $field_settings['max'],
    ]);

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? '',
    ];

    return $element;
  }

}
