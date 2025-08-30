<?php

namespace Drupal\year\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'year_default' widget.
 *
 * @FieldWidget(
 *   id = "year_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "year"
 *   }
 * )
 */
class YearSelectWidget extends YearWidgetBase {

  /**
   * Sort options.
   *
   * @var array
   */
  protected const SORT_OPTIONS = [
    'asc' => 'Ascending',
    'desc' => 'Descending',
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'sort_order' => 'asc',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['sort_order'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select list sort order'),
      '#options' => $this->getSortOptions(),
      '#default_value' => $this->getSetting('sort_order'),
      '#description' => $this->t('Choose a sort order for years in the select list.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $sort_options = $this->getSortOptions(FALSE);
    $summary[] = $this->t('Sort order: @sort_order', [
      '@sort_order' => $sort_options[$this->getSetting('sort_order')],
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();

    $element['#description'] = $this->t('Select a year from @min to @max.', [
      '@min' => $field_settings['min'],
      '@max' => $field_settings['max'],
    ]);

    // Build options from the fields min/max settings. This will be ascending
    // sort by default.
    $options = array_combine(
      range($field_settings['min'], $field_settings['max']),
      range($field_settings['min'], $field_settings['max'])
    );

    // Reverse the sort if requested.
    if ($this->getSetting('sort_order') == 'desc') {
      $options = array_reverse($options, TRUE);
    }

    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#empty_value' => '',
      '#default_value' => $items[$delta]->value ?? '',
      '#required' => $this->fieldDefinition->isRequired(),
    ];

    return $element;
  }

  /**
   * Get the widget sort options.
   *
   * @param bool $translate
   *   Should the labels be run through the t() function.
   *
   * @return array
   *   The options array.
   */
  protected function getSortOptions(bool $translate = TRUE) {
    $options = self::SORT_OPTIONS;
    if ($translate) {
      // @todo Update to arrow function syntax when we drop Drupal 8.
      $translated = array_map(
        function (string $label) {
          return $this->t('@label', ['@label' => $label]);
        },
        array_values($options)
      );
      $options = array_combine(array_keys($options), $translated);
    }
    return $options;
  }

}
