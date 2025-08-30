<?php 
namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ViewsField("ncbs_custom_field")
 */
class AddComments extends FieldPluginBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a AddComments object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Creates an instance of the AddComments class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return \Drupal\ncbs\Plugin\views\field\AddComments
   *   The constructed AddComments object.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   * This field does not require grouping.
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   * Override the parent query method to do nothing.
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   * Define options for the field.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Load the node using the entity ID from the result row.
    $node = $values->_entity;

    // Get the current user.
    $current_user = \Drupal::currentUser();

    // Check if the node and field_session_key field exist and are not empty.
    if ($node && $node->hasField('field_session_key') && !$node->get('field_session_key')->isEmpty()) {
      // Get the session value from the field.
      $session_value = $node->get('field_session_key')->value;

      // Check if the node has the 'field_admin_comment_reference' field and if it has a value.
      if ($node->hasField('field_admin_comment_reference') && !$node->get('field_admin_comment_reference')->isEmpty()) {
        // Get all the referenced nodes.
        $referenced_nodes = $node->get('field_admin_comment_reference')->referencedEntities();

        // Iterate through the referenced nodes and check if the current user is the author of any of them.
        foreach ($referenced_nodes as $referenced_node) {
          if ($referenced_node->getOwnerId() == $current_user->id()) {
            // If the current user authored one of the referenced nodes, generate the edit link.
            $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $referenced_node->id()], ['absolute' => TRUE])->toString();

            // Return the "Add Comments" link, even for editing.
            return [
              '#type' => 'link',
              '#title' => $this->t('Add Comments'),
              '#url' => Url::fromUri($edit_url),
              '#attributes' => [
                'target' => '_blank',
                'class' => ['edit-comments-link'],
              ],
            ];
          }
        }
      }

      // If no node was found that the current user authored, generate the URL for the "Add Comments" page.
      $add_comments_url = Url::fromRoute('node.add', ['node_type' => 'add_comments'], ['absolute' => TRUE])
        ->setOption('query', ['nid' => $node->id(), 'session' => $session_value])
        ->toString();

      // Return the "Add Comments" link for new comments.
      return [
        '#type' => 'link',
        '#title' => $this->t('Add Comments'),
        '#url' => Url::fromUri($add_comments_url),
        '#attributes' => [
          'target' => '_blank',
          'class' => ['add-comments-link'],
        ],
      ];
    } else {
      // Return an empty message if the field 'field_session_key' is not present or empty.
      return [
        '#markup' => $this->t(''),
      ];
    }
  }

}
