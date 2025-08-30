<?php

namespace Drupal\paragraphs_table\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Plugin implementation of the 'paragraphs_table_widget' widget.
 *
 * @FieldWidget(
 *   id = "paragraphs_table_widget",
 *   label = @Translation("Paragraphs table"),
 *   description = @Translation("Paragraphs table form widget."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ParagraphsTableWidget extends ParagraphsWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'vertical' => FALSE,
      'paste_clipboard' => FALSE,
      'field_reference' => '',
      'show_all' => FALSE,
      'duplicate' => FALSE,
      'features' => ['duplicate' => 'duplicate'],
      'form_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    $field_name = $this->fieldDefinition->getName();
    $elements['vertical'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Table vertical'),
      '#description' => $this->t('If checked, table data will show in vertical mode.'),
      '#default_value' => !empty($this->getSetting('vertical')) ? $this->getSetting('vertical') : FALSE,
    ];
    $elements['paste_clipboard'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Paste from clipboard'),
      '#description' => $this->t('Add multiple rows, you can paste data from Excel'),
      '#default_value' => !empty($this->getSetting('paste_clipboard')) ? $this->getSetting('paste_clipboard') : FALSE,
    ];
    $options = $this->getFieldsParagraphs();
    if (!empty($options)) {
      $elements['field_reference'] = [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#title' => $this->t('Reference field'),
        '#description' => $this->t('Add button multi search on reference'),
        '#default_value' => $this->getSetting('field_reference'),
        '#states' => [
          'invisible' => [":input[name='fields[$field_name][settings_edit_form][settings][paste_clipboard]']" => ['checked' => FALSE]],
        ],
      ];
    }

    $cardinality = $this->fieldDefinition->get('fieldStorage')
      ->get('cardinality');
    if ($cardinality > 1) {
      $elements['show_all'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show all %cardinality items in form', ['%cardinality' => $cardinality]),
        '#description' => $this->t('If checked, remove button add more.'),
        '#default_value' => !empty($this->getSetting('show_all')) ? $this->getSetting('show_all') : FALSE,
      ];
    }
    if (!in_array($cardinality, range(0, 3))) {
      $elements['features'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enable widget features'),
        '#options' => [
          'duplicate' => $this->t('Duplicate'),
          // Reserve for future features.
        ],
        '#default_value' => $this->getSetting('features'),
        '#multiple' => TRUE,
      ];
    }
    $display = \Drupal::service('entity_display.repository');
    $settings = $this->getFieldSettings();
    $bundle = NULL;
    if (!empty($settings["handler_settings"]["target_bundles"])) {
      $bundle = array_shift($settings["handler_settings"]["target_bundles"]);
    }
    if (!empty($bundle)) {
      $modes = $display->getFormModeOptionsByBundle("paragraph", $bundle);
    }
    else {
      $modes = ['default' => $this->t("Default")];
    }

    $elements['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#description' => $this->t('Select which form mode is displayed'),
      '#options' => $modes,
      '#default_value' => !empty($this->getSetting('form_mode')) ? $this->getSetting('form_mode') : 'default',
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if (!empty($this->getSetting('vertical'))) {
      $summary[] = $this->t('Table mode vertical');
    }
    if (!empty($this->getSetting('paste_clipboard'))) {
      $summary[] = $this->t('Paste from Excel');
    }
    if (!empty($this->getSetting('show_all'))) {
      $cardinality = $this->fieldDefinition->get('fieldStorage')
        ->get('cardinality');
      $summary[] = $this->t('Show all %cardinality elements in form', ['%cardinality' => $cardinality]);
    }
    $features = array_filter($this->getSetting('features'));
    if (!empty($features)) {
      $summary[] = $this->t('Features: @features', ['@features' => implode(', ', $features)]);
    }
    if (!empty($this->getSetting('form_mode'))) {
      $summary[] = $this->t('Mode: @mode', ['@mode' => $this->getSetting('form_mode')]);
    }

    return $summary;
  }

  /**
   * For multiple elements.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $form_mode = $this->getSetting('form_mode');
    $this->setSetting("form_display_mode", $form_mode);
    $elements = parent::formMultipleElements($items, $form, $form_state);
    $settings = $this->fieldDefinition->getSettings();
    $handler = $settings['handler_settings'];
    if (!empty($handler["target_bundles"]) && count($handler["target_bundles"]) > 1) {
      return $elements;
    }
    $target_type = $this->getFieldSetting('target_type');
    $default_type = $this->getDefaultParagraphTypeMachineName();
    $field_name = $this->fieldDefinition->getName();
    $widgetState = static::getWidgetState($this->fieldParents, $field_name, $form_state);
    $widgetState["paragraphs"][0]["mode"] = 'edit';
    $elements["#paragraphsTable"]["#widget_state"] = $widgetState;
    $elements["#paragraphsTable"]["#table_vertical"] = $this->getSetting('vertical');
    $elements["#paragraphsTable"]["#paste_clipboard"] = $this->getSetting('paste_clipboard');
    $elements["#paragraphsTable"]["#field_reference"] = $this->getSetting('field_reference');
    $elements["#paragraphsTable"]["#show_all"] = $this->getSetting('show_all');
    $elements["#paragraphsTable"]["#feature"] = $this->getSetting('features');
    if (empty($default_type)) {
      $default_type = array_key_first($this->getAllowedTypes());
    }
    if (empty($default_type)) {
      return $elements;
    }
    $formDisplay = \Drupal::service('entity_display.repository')
      ->getFormDisplay($target_type, $default_type, $form_mode);
    $components = $formDisplay->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraphs_entity */
    $paragraphs_entity = \Drupal::entityTypeManager()->getStorage($target_type)
      ->create(['type' => $default_type]);
    $field_definitions = $paragraphs_entity->getFieldDefinitions();

    foreach ($components as $name => $setting) {
      if (!empty($field_definitions[$name]) && $field_definitions[$name] instanceof FieldConfigInterface) {
        $elements["#paragraphsTable"]['#fields'][$name] = $field_definitions[$name];
      }
    }
    // Remove fieldgroups we don't need it.
    if (!empty($elements[0]) && $elements[0]['subform']) {
      foreach (range(0, $elements["#max_delta"]) as $delta) {
        $elements[$delta]["subform"]["#fieldgroups"] = [];
      }
    }
    if ($this->getSetting('show_all') && $elements["#max_delta"] < $elements["#cardinality"] - 1) {
      foreach (range($elements["#max_delta"], $elements["#cardinality"] - 1) as $delta) {
        if (empty($elements[$delta])) {
          $elements[$delta] = $elements[0];
          $elements[$delta]["#delta"] = $delta;
          $elements[$delta]["#weight"] = $delta;
          $elements[$delta]["#prefix"] = str_replace('-0-', "-$delta-", $elements[$delta]["#prefix"]);
          $elements[$delta]["_weight"]["#default_value"] = $delta;
          $elements[$delta]["_weight"]["#delta"] = $delta + 1;
          unset($elements[$delta]["top"]["actions"]);
          $this->setParentId($elements[$delta]["subform"], $delta);
        }
      }
      $elements["#max_delta"] = $elements["#cardinality"] - 1;
    }
    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  private function setParentId(&$element, $delta = 0) {
    foreach (Element::children($element) as $field) {
      if (!empty($element[$field]["widget"][0]["value"]["#default_value"])) {
        $element[$field]["widget"][0]["value"]["#default_value"] = '';
      }
      if (!empty($element[$field]['#parents'])) {
        foreach ($element[$field]["#parents"] as &$parent) {
          if (!$parent) {
            $parent = $delta;
            break;
          }
        }
        $this->setParentId($element[$field], $delta);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldsParagraphs() {
    $targetType = $this->getFieldSetting('target_type');
    $targetBundle = array_key_first($this->fieldDefinition->getSetting("handler_settings")["target_bundles"]);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraphs_entity */
    $paragraphs_entity = \Drupal::entityTypeManager()->getStorage($targetType)
      ->create(['type' => $targetBundle]);
    $field_definitions = $paragraphs_entity->getFieldDefinitions();
    $options = [];
    foreach ($field_definitions as $field_name => $field) {
      if ($field instanceof FieldConfig) {
        if ($field->getType() == 'entity_reference') {
          $options[$field_name] = $field->getLabel();
        }
      }
    }
    return $options;
  }

}
