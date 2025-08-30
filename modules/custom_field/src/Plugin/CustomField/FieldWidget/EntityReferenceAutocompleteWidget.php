<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_autocomplete' field widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_autocomplete",
 *   label = @Translation("Autocomplete"),
 *   category = @Translation("Reference"),
 *   data_types = {
 *     "entity_reference",
 *   }
 * )
 */
class EntityReferenceAutocompleteWidget extends CustomFieldWidgetBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity reference selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionPluginManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->selectionPluginManager = $container->get('plugin.manager.entity_reference_selection');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'size' => 60,
        'placeholder' => '',
        'handler_settings' => [],
      ] + parent::defaultSettings()['settings'],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $field_name = $field->getName();
    $settings = $field->getWidgetSetting('settings') + self::defaultSettings()['settings'];
    $target_type = $field->getTargetType();
    if (!isset($settings['handler'])) {
      $settings['handler'] = 'default:' . $target_type;
    }
    // Get all selection plugins for this entity type.
    $selection_plugins = $this->selectionPluginManager->getSelectionGroups($target_type);
    $handlers_options = [];
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
      }
      elseif (array_key_exists($selection_group_id . ':' . $target_type, $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $target_type;
        $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
      }
    }
    $wrapper_id = 'reference-wrapper-' . $field_name;
    $element['settings']['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['settings']['#suffix'] = '</div>';

    $element['settings']['handler'] = [
      '#type' => 'details',
      '#title' => $this->t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[static::class, 'formProcessMergeParent']],
    ];

    $element['settings']['handler']['handler'] = [
      '#type' => 'select',
      '#title' => $this->t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $settings['handler'],
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => $wrapper_id,
        'callback' => [static::class, 'actionCallback'],
      ],
    ];

    $element['settings']['handler']['handler_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change handler'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'settingsAjaxSubmit']],
    ];

    $element['settings']['handler']['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity_reference-settings']],
    ];

    $handler = $this->getSelectionHandler($settings, $target_type);
    $configuration_form = $handler->buildConfigurationForm([], $form_state);

    // Alter configuration to use our custom callback.
    foreach ($configuration_form as $key => $item) {
      if (isset($item['#limit_validation_errors'])) {
        unset($item['#limit_validation_errors']);
      }
      if (isset($item['#ajax'])) {
        $item['#ajax'] = [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'actionCallback'],
        ];
      }
      if (is_array($item)) {
        foreach ($item as $prop_key => $prop) {
          if (!is_array($prop)) {
            continue;
          }
          if (isset($prop['#limit_validation_errors'])) {
            unset($prop['#limit_validation_errors']);
          }
          if (isset($prop['#ajax'])) {
            $prop['#ajax'] = [
              'wrapper' => $wrapper_id,
              'callback' => [static::class, 'actionCallback'],
            ];
          }
          $item[(string) $prop_key] = $prop;
        }
      }
      $configuration_form[(string) $key] = $item;
    }

    $element['settings']['handler']['handler_settings'] += $configuration_form;

    $element['settings']['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $settings['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];
    $element['settings']['match_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $settings['match_limit'],
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];
    $element['settings']['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of textfield'),
      '#default_value' => $settings['size'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    $element['settings']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $field->getWidgetSetting('settings') + self::defaultSettings()['settings'];
    $entity = $items->getEntity();
    $target_type = $field->getTargetType();
    if (!isset($settings['handler'])) {
      $settings['handler'] = 'default:' . $target_type;
    }

    // Append the match operation to the selection settings.
    $selection_settings = $settings['handler_settings'] + [
      'match_operator' => $settings['match_operator'],
      'match_limit' => $settings['match_limit'],
    ];

    // Append the entity if it is already created.
    if (!$entity->isNew()) {
      $selection_settings['entity'] = $entity;
    }

    if (isset($selection_settings['target_bundles']) && $selection_settings['target_bundles'] === []) {
      $selection_settings['target_bundles'] = NULL;
    }

    $element += [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#selection_handler' => $settings['handler'],
      '#selection_settings' => $selection_settings,
      // Entity reference field items are handling validation themselves via
      // the 'ValidReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => NULL,
      '#size' => $settings['size'],
      '#placeholder' => $settings['placeholder'],
    ];

    if (isset($element['#default_value'])) {
      $referenced_entity = $this->entityTypeManager
        ->getStorage($target_type)
        ->load($element['#default_value']);
      $element['#default_value'] = $referenced_entity;
    }

    if ($bundle = $this->getAutocreateBundle($settings['handler_settings'], $target_type, $field)) {
      $element['#autocreate'] = [
        'bundle' => $bundle,
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : $this->currentUser->id(),
      ];
    }

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    // The entity_autocomplete form element returns an array when an entity
    // was "autocreated", so we need to move it up a level.
    if (empty($value['target_id'])) {
      return NULL;
    }
    if (is_array($value['target_id'])) {
      $value += $value['target_id'];
      unset($value['target_id']);
    }

    return $value;
  }

  /**
   * Returns the name of the bundle which will be used for autocreated entities.
   *
   * @param array $handler_settings
   *   The field handler settings.
   * @param string $target_type
   *   The target_type setting for the field.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field.
   *
   * @return string|null
   *   The bundle name. If autocreate is not active, NULL will be returned.
   */
  protected function getAutocreateBundle(array $handler_settings, string $target_type, CustomFieldTypeInterface $field): ?string {
    $bundle = NULL;
    $auto_create = $handler_settings['auto_create'] ?? FALSE;
    if ($auto_create) {
      $target_bundles = $handler_settings['target_bundles'];
      // If there's no target bundle at all, use the target_type. It's the
      // default for bundleless entity types.
      if (empty($target_bundles)) {
        $bundle = $target_type;
      }
      // If there's only one target bundle, use it.
      elseif (count($target_bundles) == 1) {
        $bundle = reset($target_bundles);
      }
      // If there's more than one target bundle, use the autocreate bundle
      // stored in selection handler settings.
      elseif (!$bundle = $handler_settings['auto_create_bundle']) {
        // If no bundle has been set as auto create target means that there is
        // an inconsistency in entity reference field settings.
        trigger_error(sprintf(
          "The 'Create referenced entities if they don't already exist' option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
          $field->getLabel(),
          $field->getName()
        ), E_USER_WARNING);
      }
    }

    return $bundle;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

  /**
   * Render API callback that moves entity reference elements up a level.
   *
   * The elements (i.e. 'handler_settings') are moved for easier processing by
   * the validation and submission handlers.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent($element) {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

  /**
   * Ajax callback for the handler settings form.
   */
  public static function actionCallback(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $sliced_parents = array_slice($parents, 0, 5, TRUE);

    return NestedArray::getValue($form, $sliced_parents);
  }

  /**
   * Submit handler for the non-JS case.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsAjaxSubmit($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Gets the selection handler for a given entity_reference field.
   *
   * @param array $settings
   *   An array of field settings.
   * @param string $target_type
   *   The target entity type.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity containing the reference field.
   *
   * @return mixed
   *   The selection handler.
   */
  public function getSelectionHandler(array $settings, string $target_type, EntityInterface $entity = NULL) {
    $options = $settings['handler_settings'] ?: [];
    $options += [
      'target_type' => $target_type,
      'handler' => $settings['handler'],
      'entity' => $entity,
    ];

    return $this->selectionPluginManager->getInstance($options);
  }

}
