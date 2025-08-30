<?php

namespace Drupal\views_field_permissions;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\PermissionHandlerInterface;

/**
 * The views field permissions service.
 */
class ViewsUiFormService implements ViewsUiFormServiceInterface {

  use StringTranslationTrait;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a object.
   *
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(PermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation) {
    $this->permissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array &$form, FormStateInterface &$form_state) {
    $state_options = $form_state->getValue('options', []);
    $handler_options = $form_state->getStorage()['handler']->options;
    $wrapper_id = Html::getUniqueId('views-field-permissions-add-more-wrapper');

    $perm_values = [];
    if (!empty($handler_options['views_field_permissions']['perms'])) {
      $perm_values = $handler_options['views_field_permissions']['perms'];
    }

    // Get list of permissions.
    $perms = ['' => $this->t('- None -')];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $perm => $perm_item) {
      $provider = $perm_item['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $perms[$display_name][$perm] = strip_tags($perm_item['title']);
    }

    // The default values.
    if (empty($perm_values)) {
      $perm_values[] = '';
    }

    $elements = [
      '#title' => $this->t('Views Field Permissions'),
      '#type' => 'details',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $elements['control'] = [
      '#type' => 'select',
      '#options' => [
        '' => $this->t('- None -'),
        'role' => $this->t('Role'),
        'perm' => $this->t('Permission'),
      ],
      '#title' => $this->t('Access control based on:'),
      '#parents' => ['options', 'views_field_permissions', 'control'],
      '#default_value' => $handler_options['views_field_permissions']['control'] ?? '',
    ];

    // Get list of roles.
    $elements['role'] = [
      '#type' => 'checkboxes',
      '#options' => user_role_names(),
      '#title' => $this->t('Role'),
      '#parents' => ['options', 'views_field_permissions', 'role'],
      '#default_value' => $handler_options['views_field_permissions']['role'] ?? [],
      '#states' => [
        'visible' => [
          ':input[name="options[views_field_permissions][control]"]' => [
            'value' => 'role',
          ],
        ],
      ],
    ];

    $elements['role_negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate the condition'),
      '#parents' => ['options', 'views_field_permissions', 'role_negate'],
      '#default_value' => $handler_options['views_field_permissions']['role_negate'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="options[views_field_permissions][control]"]' => [
            'value' => 'role',
          ],
        ],
      ],
    ];

    $elements['perms'] = [
      '#title' => $this->t('Permissions'),
      '#type' => 'fieldset',
      '#states' => [
        'visible' => [
          ':input[name="options[views_field_permissions][control]"]' => [
            'value' => 'perm',
          ],
        ],
      ],
    ];

    $items = [];
    foreach ($perm_values as $delta => $value) {
      $item = [
        '#type' => 'select',
        '#options' => $perms,
        '#title' => $this->t('Permission'),
        '#parents' => ['options', 'views_field_permissions', 'perms', $delta],
        '#default_value' => $value,
      ];
      $items[] = $item;
    }

    $elements['perms'] += $items;

    $elements['perms']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => [[get_class($this), 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [get_class($this), 'addMoreAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      '#parents' => ['options', 'views_field_permissions', 'add_more'],
    ];

    $condition = 'and';
    if (!empty($state_options['views_field_permissions']['condition'])) {
      $condition = $state_options['views_field_permissions']['condition'];
    }
    elseif (!empty($handler_options['views_field_permissions']['condition'])) {
      $condition = $handler_options['views_field_permissions']['condition'];
    }
    $elements['condition'] = [
      '#title' => $this->t('Condition'),
      '#type' => 'select',
      '#options' => [
        'and' => $this->t('AND'),
        'or' => $this->t('OR'),
      ],
      '#parents' => ['options', 'views_field_permissions', 'condition'],
      '#default_value' => $condition,
    ];

    $form['options']['expose']['views_field_permissions'] = $elements;
    $form['actions']['submit']['#submit'][] = [get_class($this), 'submit'];
  }

  /**
   * Ajax callback for the "Add more" button.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $element['#open'] = TRUE;
    return $element;
  }

  /**
   * Submission handler for the "Add more" button.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\views_ui\ViewUI $view */
    $view = $form_state->get('view');
    $display_id = $form_state->get('display_id');
    $id = $form_state->get('id');
    $type = $form_state->get('type');
    $executable = $view->getExecutable();
    $handler = $executable->getHandler($display_id, $type, $id);

    // Set values.
    $handler['views_field_permissions']['perms'][] = '';
    $executable->setHandler($display_id, $type, $id, $handler);

    // Write to cache.
    $view->cacheSet();
    $form_state->set('rerender', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submit(array &$form, FormStateInterface &$form_state) {
    /** @var \Drupal\views_ui\ViewUI $view */
    $view = $form_state->get('view');
    $display_id = $form_state->get('display_id');
    $id = $form_state->get('id');
    $type = $form_state->get('type');
    $executable = $view->getExecutable();
    $handler = $executable->getHandler($display_id, $type, $id);

    // Set values.
    if (!empty($form_state->getValue('options', [])['views_field_permissions']['control'])) {
      $options = $form_state->getValue('options', [])['views_field_permissions'];
      switch ($options['control']) {
        case 'perm':
          $perms = array_filter($options['perms']);
          if (!empty($perms)) {
            $handler['views_field_permissions']['perms'] = $perms;
          }
          break;

        case 'role':
          foreach ($options['role'] as $key => $value) {
            if (empty($value)) {
              unset($options['role'][$key]);
            }
          }
          $handler['views_field_permissions']['role'] = $options['role'];
          $handler['views_field_permissions']['role_negate'] = $options['role_negate'];
          break;
      }
      $handler['views_field_permissions']['condition'] = $options['condition'];
      $handler['views_field_permissions']['control'] = $options['control'];
    }
    else {
      unset($handler['views_field_permissions']);
    }
    $executable->setHandler($display_id, $type, $id, $handler);

    // Write to cache.
    $view->cacheSet();
  }

}
