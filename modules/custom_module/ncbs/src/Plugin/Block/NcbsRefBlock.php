<?php

namespace Drupal\ncbs\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * @Block(
 *   id = "ncbs_ref_block",
 *   admin_label = @Translation("NCBS Ref ID Block")
 * )
 */
class NcbsRefBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected RequestStack $requestStack;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected CurrentRouteMatch $routeMatch;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    CurrentRouteMatch $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Only allow the block on /node/add/referee_feedback_form (your aliased path)
   * and when ?id=<numeric> is present.
   */
  public function blockAccess(AccountInterface $account): AccessResult {
    $route_name = $this->routeMatch->getRouteName();
    $node_type_param = $this->routeMatch->getParameter('node_type');

    // Route should be node.add and bundle should be referee_feedback_form.
    $is_ref_form = FALSE;
    if ($route_name === 'node.add' && $node_type_param) {
      // $node_type_param is NodeType entity for node.add.
      $bundle_id = is_object($node_type_param) && method_exists($node_type_param, 'id')
        ? $node_type_param->id()
        : (string) $node_type_param;
      $is_ref_form = ($bundle_id === 'referee_feedback_form');
    }

    // Require a numeric id in query.
    $id_raw = (string) $this->requestStack->getCurrentRequest()->query->get('id', '');
    $has_numeric_id = $id_raw !== '' && ctype_digit($id_raw);

    // Cache varies by route + path + the id query arg.
    $cache_contexts = ['route.name', 'url.path', 'url.query_args:id'];

    if (!$is_ref_form) {
      return AccessResult::forbidden()->addCacheContexts($cache_contexts);
    }
    return AccessResult::allowedIf($has_numeric_id)->addCacheContexts($cache_contexts);
  }

  public function build() {
    $request = $this->requestStack->getCurrentRequest();
    $nid = (int) $request->query->get('id');

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return [
        '#markup' => $this->t('Referenced content not found.'),
        '#cache' => ['contexts' => ['url.query_args:id', 'url.path']],
      ];
    }

    // Render full node regardless of default node access—your link already
    // gates access via session/email logic on the form page.
    $build = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
    $build['#access'] = TRUE;

    // Proper caching.
    $build['#cache']['contexts'][] = 'url.query_args:id';
    $build['#cache']['contexts'][] = 'url.path';
    $build['#cache']['tags'] = array_merge($build['#cache']['tags'] ?? [], $node->getCacheTags());
    $build['#cache']['max-age'] = $node->getCacheMaxAge();

    return $build;
  }

}
