<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomField\MapWidgetBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'Map (Key Value)' custom field widget.
 *
 * @FieldWidget(
 *   id = "map_key_value",
 *   label = @Translation("Map (Key Value)"),
 *   category = @Translation("General"),
 *   data_types = {
 *     "map",
 *   },
 * )
 */
class MapKeyValueWidget extends MapWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'settings' => [
        'key_label' => 'Key',
        'value_label' => 'Value',
      ] + parent::defaultSettings()['settings'],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $field->getWidgetSetting('settings') + self::defaultSettings()['settings'];

    $element['settings']['key_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key label'),
      '#description' => $this->t('The table header label for key column'),
      '#default_value' => $settings['key_label'],
      '#required' => TRUE,
      '#maxlength' => 128,
    ];
    $element['settings']['value_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value label'),
      '#description' => $this->t('The table header label for value column'),
      '#default_value' => $settings['value_label'],
      '#required' => TRUE,
      '#maxlength' => 128,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $element['#element_validate'] = [[static::class, 'validateArrayValues']];
    $settings = $field->getWidgetSetting('settings');
    $field_name = $items->getFieldDefinition()->getName();
    $custom_field_name = $field->getName();
    $is_config_form = $form_state->getBuildInfo()['base_form_id'] == 'field_config_form';
    $map_list = $element['#default_value'];

    if ($is_config_form) {
      $map_values = $form_state->getValue(
        ['default_value_input', $field_name, $delta, $custom_field_name]
      );
    }
    else {
      $map_values = $form_state->getValue([
        $field_name,
        $delta,
        $custom_field_name,
      ]);
    }

    if (!empty($map_values) && !isset($map_values['data'])) {
      $map_list = $map_values;
    }

    $options_wrapper_id = $field_name . $delta . $custom_field_name;
    $element['#attached'] = [
      'library' => ['custom_field/customfield-admin'],
    ];
    $element['#prefix'] = '<div class="form-type--map" id="' . $options_wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if ($form_state->isRebuilding()) {
      $trigger = $form_state->getTriggeringElement();
      if ($trigger['#name'] == 'add_item:' . $custom_field_name . $delta) {
        $map_list[] = ['key' => '', 'value' => ''];
        $form_state->set('add', NULL);
      }
      if ($form_state->get('remove')) {
        $remove = $form_state->get('remove');
        if ($remove['name'] == 'remove:' . $options_wrapper_id . $trigger['#delta']) {
          unset($map_list[$remove['key']]);
          $form_state->set('remove', NULL);
        }
      }
    }
    $element['data'] = [
      '#type' => 'table',
      '#header' => [
        $settings['key_label'] ?? $this->t('Key'),
        $settings['value_label'] ?? $this->t('Label'),
        '',
      ],
      '#attributes' => [
        'class' => ['customfield-map-table'],
      ],
    ];
    if (!empty($map_list)) {
      foreach ($map_list as $key => $value) {
        $element['data'][$key]['key'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Key'),
          '#title_display' => 'invisible',
          '#default_value' => $value['key'] ?? '',
          '#required' => TRUE,
        ];
        $element['data'][$key]['value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Value'),
          '#title_display' => 'invisible',
          '#default_value' => $value['value'] ?? '',
          '#required' => TRUE,
        ];
        $element['data'][$key]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => [get_class($this) . '::removeItem'],
          '#name' => 'remove:' . $options_wrapper_id . $key,
          '#delta' => $key,
          '#ajax' => [
            'callback' => [$this, 'actionCallback'],
            'wrapper' => $options_wrapper_id,
          ],
          '#limit_validation_errors' => [[$is_config_form ? 'default_value_input' : $field_name]],
        ];
      }
    }
    $element['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add item'),
      '#submit' => [get_class($this) . '::addItem'],
      '#name' => 'add_item:' . $custom_field_name . $delta,
      '#ajax' => [
        'callback' => [$this, 'actionCallback'],
        'wrapper' => $options_wrapper_id,
      ],
      '#limit_validation_errors' => [[$is_config_form ? 'default_value_input' : $field_name]],
    ];
    return $element;

  }

  /**
   * The #element_validate callback for map field array values.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElement::processPattern()
   */
  public static function validateArrayValues(array $element, FormStateInterface $form_state): void {
    $values = $element['data']['#value'] ?? NULL;
    $is_config_form = $form_state->getBuildInfo()['base_form_id'] == 'field_config_form';
    if (is_array($values)) {
      $unique_keys = [];
      foreach ($values as $value) {
        if (!is_array($value)) {
          continue;
        }
        // Make sure each key is unique.
        if (isset($value['key']) && in_array($value['key'], $unique_keys)) {
          $form_state->setError($element, t('All keys must be unique.'));
          break;
        }
        else {
          $unique_keys[] = $value['key'];
        }
      }
      $form_state->setValueForElement($element, $values);
    }
    elseif ($is_config_form) {
      $form_state->setValueForElement($element, NULL);
    }
  }

  /**
   * Submit handler for the "add item" button.
   */
  public static function addItem(array &$form, FormStateInterface $form_state): void {
    $form_state->set('add', $form_state->getTriggeringElement()['#name']);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove item" button.
   */
  public static function removeItem(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $form_state->set(
      'remove', ['name' => $trigger['#name'], 'key' => $trigger['#delta']]
    );
    $form_state->setRebuild();
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function actionCallback(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $sliced_parents = array_slice($parents, 0, 4, TRUE);

    return NestedArray::getValue($form, $sliced_parents);
  }

}
