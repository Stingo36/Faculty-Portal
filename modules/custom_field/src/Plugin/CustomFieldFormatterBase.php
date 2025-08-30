<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CustomField formatter plugins.
 */
abstract class CustomFieldFormatterBase extends PluginSettingsBase implements CustomFieldFormatterInterface, ContainerFactoryPluginInterface {

  /**
   * The formatter settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, array $settings, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $configuration['settings'] ?? static::defaultSettings(),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $settings) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {}

  /**
   * {@inheritdoc}
   */
  public function calculateFormatterDependencies(array $settings): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onFormatterDependencyRemoval(array $dependencies, array $settings): array {
    return [];
  }

}
