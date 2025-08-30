<?php

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'custom_inline' formatter.
 *
 * Renders the items inline using a simple separator and no additional wrapper
 * markup.
 *
 * @FieldFormatter(
 *   id = "custom_inline",
 *   label = @Translation("Inline"),
 *   weight = 1,
 *   field_types = {
 *     "custom"
 *   }
 * )
 */
class CustomInlineFormatter extends BaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'show_labels' => FALSE,
      'label_separator' => ': ',
      'item_separator' => ', ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $id = 'customfield-show-labels';

    $form['show_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show labels?'),
      '#default_value' => $this->getSetting('show_labels'),
      '#attributes' => [
        'data-id' => $id,
      ],
    ];

    $form['label_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label separator'),
      '#default_value' => $this->getSetting('label_separator'),
      '#states' => [
        'visible' => [
          ':input[data-id="' . $id . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['item_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item separator'),
      '#default_value' => $this->getSetting('item_separator'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('Show labels: @show_labels', ['@show_labels' => $this->getSetting('label_display') ? 'Yes' : 'No']);
    if ($this->getSetting('label_display')) {
      $summary[] = $this->t('Label separator: @sep', ['@sep' => $this->getSetting('label_separator')]);
    }
    $summary[] = $this->t('Item separator: @sep', ['@sep' => $this->getSetting('item_separator')]);

    return $summary;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item): array {
    $output = [];
    $values = $this->getFormattedValues($item, $item->getLangcode());

    foreach ($values as $value) {
      // Skip 'map' custom field types.
      if ($value['type'] == 'map') {
        continue;
      }
      $markup = $value['value']['#markup'];
      if ($this->getSetting('show_labels')) {
        $output[] = implode($this->getSetting('label_separator'), [
          $value['label'],
          $markup,
        ]);
      }
      else {
        $output[] = $markup;
      }
    }

    return ['#markup' => implode($this->getSetting('item_separator'), $output)];
  }

}
