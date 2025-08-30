<?php

namespace Drupal\ncbs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a 'Custom Block Example' block.
 *
 * @Block(
 *   id = "custom_block_example",
 *   admin_label = @Translation("Custom Block Example"),
 * )
 */
class NcbsUserBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new NcbsUserBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, \Drupal\Core\Render\RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get the theme service.
    $theme_manager = \Drupal::service('theme.manager');
    // Get the path to the active theme.
    $theme_path = $theme_manager->getActiveTheme()->getPath();
    // Construct the path to the twig file.
    $twig_file_path = $theme_path . '/templates/user/user.html.twig';

    // Load the twig file contents.
    $twig_content = '';
    if (file_exists($twig_file_path)) {
      $twig_content = file_get_contents($twig_file_path);
    }

    // Set cache tags for the block to invalidate cache when the user template changes.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheTags(['config:user.theme']);

    // Render the twig template.
    $rendered_content = [
      '#type' => 'inline_template',
      '#template' => $twig_content,
      '#context' => [],
      '#cache' => [
        'keys' => ['ncbs_user_block'],
        'tags' => $cache_metadata->getCacheTags(),
      ],
    ];

    return $rendered_content;
  }
}




