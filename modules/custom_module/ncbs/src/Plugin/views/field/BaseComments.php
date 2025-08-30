<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Path\PathValidatorInterface;

/**
 * Base class for comment reference fields.
 */
abstract class BaseComments extends FieldPluginBase {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The role to be added in the create link (override in subclasses).
   *
   * @var string|null
   */
  protected $role = null;

  /**
   * Constructs a new BaseComments instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, PathValidatorInterface $path_validator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration needed.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;

    if (!$node instanceof Node || !$node->hasField($this->fieldName)) {
      return '';
    }

    $references = $node->get($this->fieldName)->getValue();
    $current_user_name = $this->currentUser->getAccountName();
    $matching_node_id = null;

    // Check if current user has an existing comment.
    foreach ($references as $reference) {
      $referenced_node = Node::load($reference['target_id']);
      if ($referenced_node && $referenced_node->hasField('field_comment_name')) {
        $comment_name = $referenced_node->get('field_comment_name')->getString();
        if ($comment_name === $current_user_name) {
          $matching_node_id = $reference['target_id'];
          break;
        }
      }
    }

    // Determine destination path based on user role.
    $user_roles = $this->currentUser->getRoles();
    if (in_array('admin', $user_roles) || in_array('administrator', $user_roles)) {
      $raw_destination = "/add-comments-for-all-by-admin/{$node->id()}";
    } else {
      $raw_destination = "/node/{$node->id()}";
    }

    $destination = $this->pathValidator->getUrlIfValid($raw_destination);
    $destination_str = $destination ? $destination->toString() : '';

    // Build query parameters.
    $session_key = $node->hasField('field_session_key') ? $node->get('field_session_key')->getString() : '';
    $query_params = [
      'session' => $session_key,
      'nid' => $node->id(),
    ];

    if (!empty($destination_str)) {
      $query_params['destination'] = $destination_str;
    }

    if ((in_array('admin', $user_roles) || in_array('administrator', $user_roles)) && !empty($this->role)) {
      $query_params['role'] = $this->role;
    }

    if ($matching_node_id) {
      $url = Url::fromUserInput("/node/{$matching_node_id}/edit", ['query' => $query_params]);
      return Link::fromTextAndUrl('Edit', $url)->toString();
    }
    else {
      $url = Url::fromUserInput("/node/add/add_comments", ['query' => $query_params]);
      return Link::fromTextAndUrl('Add', $url)->toString();
    }
  }

}
