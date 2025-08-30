<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CustomField widget plugins.
 */
abstract class CustomFieldWidgetBase extends PluginSettingsBase implements CustomFieldWidgetInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The widget settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, array $settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $configuration['settings'] ?? self::defaultSettings()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'label' => '',
      'settings' => [
        'description' => '',
        'description_display' => 'after',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    // Prep the element base properties. Implementations of the plugin can
    // override as necessary or just set #type and be on their merry way.
    $settings = $field->getWidgetSetting('settings');
    $is_required = $items->getFieldDefinition()->isRequired();
    $description = !empty($settings['description']) ? $this->t('@description', ['@description' => $settings['description']]) : NULL;
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
    $item = $items[$delta];
    return [
      '#title' => $this->t('@label', ['@label' => $field->getLabel()]),
      '#description' => $description,
      '#description_display' => $settings['description_display'] ?: NULL,
      '#default_value' => $item->{$field->getName()} ?? NULL,
      '#required' => !($form_state->getBuildInfo()['base_form_id'] == 'field_config_form') && $is_required && $settings['required'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $label = $field->getLabel();
    $settings = $field->getWidgetSetting('settings') + self::defaultSettings()['settings'];

    // Some table columns containing raw markup.
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $label,
      '#required' => TRUE,
    ];
    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
    ];

    // Keep settings open during ajax updates.
    if ($form_state->isRebuilding()) {
      $trigger = $form_state->getTriggeringElement();
      $parents = $trigger['#parents'];
      if (in_array($field->getName(), $parents)) {
        $element['settings']['#open'] = TRUE;
      }
    }

    // Some table columns containing raw markup.
    $element['settings']['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
      '#description' => $this->t('This setting is only applicable when the field itself is required.'),
      '#default_value' => $settings['required'],
    ];

    // Some table columns containing raw markup.
    $element['settings']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Help text'),
      '#description' => $this->t('Instructions to present to the user below this field on the editing form.'),
      '#rows' => 2,
      '#default_value' => $settings['description'],
    ];

    $element['settings']['description_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Help text position'),
      '#options' => [
        'before' => $this->t('Before input'),
        'after' => $this->t('After input'),
      ],
      '#default_value' => $settings['description_display'],
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    return $value;
  }

  /**
   * Helper function to return widget settings label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->settings['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetSettings(): array {
    return $this->settings['settings'] ?? self::defaultSettings()['settings'];
  }

  /**
   * Helper function to return a property from settings.
   *
   * @param string $key
   *   The lookup key in the settings array.
   *
   * @return mixed
   *   The property value.
   */
  public function getWidgetSetting(string $key): mixed {
    return $this->settings['settings'][$key] ?? NULL;
  }

}
