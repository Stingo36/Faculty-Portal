<?php

namespace Drupal\paragraphs_table\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs_table\Plugin\Field\FieldFormatter\ParagraphsTableFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for paragraphs item routes.
 */
class AjaxController extends ControllerBase {

  /**
   * The entity render build.
   *
   * @var object
   */
  protected $renderBuild;

  /**
   * The field definition.
   *
   * @var object
   */
  protected $fieldsDefinition;

  /**
   * Ajax Controller constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionManager
   *   The entity reference selection service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(protected RendererInterface $renderer, protected EntityDisplayRepositoryInterface $entityDisplayRepository, protected SelectionPluginManagerInterface $selectionManager, protected RequestStack $requestStack) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('entity_display.repository'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('request_stack'),
    );
  }

  /**
   * Return output JSON object value.
   */
  public function json($field_name, $host_type, $host_id, $typeData = FALSE) {
    $message = [];
    $entity = $this->entityTypeManager()->getStorage($host_type)->load($host_id);
    if (empty($entity)) {
      $message[] = $this->t("%type %id doesn't exist", [
        "%type" => $host_type,
        "%id" => $host_id,
      ]);
    }
    $paragraph_field = $entity->hasField($field_name) ? $entity->$field_name : FALSE;
    if (empty($paragraph_field)) {
      $message[] = $this->t("%field that does not exist on entity type %type", [
        "%field" => $field_name,
        "%type" => $host_type,
      ]);
    }

    if ($paragraph_field && $paragraph_field_items = $paragraph_field->referencedEntities()) {
      $setting = $this->getFieldSetting($entity, $field_name);
      $hasPermission = ParagraphsTableFormatter::checkPermissionOperation($entity, $field_name);
      if ($hasPermission) {
        $setting['show_operation'] = TRUE;
      }
      $components = $this->getComponents($paragraph_field, $setting['view_mode']);
      $typeLabel = $this->requestStack->getCurrentRequest()->get('type');
      if (empty($typeLabel)) {
        $typeLabel = 'data';
      }
      switch ($typeData) {
        case 'table':
          $data = $this->getTable($paragraph_field_items, $components, $setting);
          break;

        case 'data':
          $data = $this->getData($paragraph_field_items, $components, $setting);
          break;

        default:
          $data = [
            $typeLabel => $this->getResults($paragraph_field_items, $components, $setting),
          ];
          break;
      }
      return new JsonResponse($data);
    }
    else {
      $message[] = $this->t("%field is not paragraphs", [
        "%field" => $field_name,
      ]);
    }
    return new JsonResponse([
      'error' => $message,
    ]);
  }

  /**
   * Return output JSON data value.
   */
  public function jsondata($field_name, $host_type, $host_id) {
    return $this->json($field_name, $host_type, $host_id, 'data');
  }

  /**
   * Return html render field paragraphs.
   */
  public function ajax($field_name, $host_type, $host_id) {
    return $this->json($field_name, $host_type, $host_id, 'table');
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldSetting($entity, $field_name) {
    $bundle = $entity->bundle();
    $viewDisplay = $this->entityDisplayRepository->getViewDisplay($entity->getEntityTypeId(), $bundle, 'default');
    $fieldComponent = $viewDisplay->getComponent($field_name);
    return $fieldComponent['settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getComponents($paragraph_field, $view_mode = 'default') {
    $field_definition = $paragraph_field->getFieldDefinition();
    $targetBundle = array_key_first($field_definition->getSetting("handler_settings")["target_bundles"]);
    $targetType = $field_definition->getSetting('target_type');
    $fieldsDefinitions = $this->entityTypeManager()->getStorage($targetType)
      ->create(['type' => $targetBundle])->getFieldDefinitions();
    $viewDisplay = $this->entityDisplayRepository->getViewDisplay($targetType, $targetBundle, $view_mode);
    $components = $viewDisplay->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($components as $field_name => $component) {
      if ($fieldsDefinitions[$field_name] instanceof FieldConfigInterface) {
        $this->fieldsDefinition[$field_name] = $fieldsDefinitions[$field_name];
        $components[$field_name]['title'] = $fieldsDefinitions[$field_name]->getLabel();
      }
    }
    $storage = $this->entityTypeManager()->getStorage('entity_view_display');
    $this->renderBuild = $storage->load(implode('.', [
      $targetType, $targetBundle, $view_mode,
    ]));

    return $components;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults($entities, $components, $setting = []) {
    $data = [];
    foreach ($entities as $delta => $entity) {
      $table_entity = $this->renderBuild->build($entity);
      $objectData = new \stdClass();
      foreach ($components as $field_name => $field) {
        $table_entity[$field_name]['#label_display'] = 'hidden';
        $value = trim(strip_tags($this->renderer->render($table_entity[$field_name])));
        if (in_array($this->fieldsDefinition[$field_name]->getType(), [
          'integer',
          'list_integer',
          'number_integer',
        ])) {
          $value = (int) $value;
        }
        if (in_array($this->fieldsDefinition[$field_name]->getType(), ['boolean'])) {
          $list_value = $table_entity[$field_name]["#items"]->getValue();
          $value = (int) $list_value[0]['value'];
        }
        if (in_array($this->fieldsDefinition[$field_name]->getType(), [
          'decimal',
          'list_decimal',
          'number_decimal',
          'float',
          'list_float',
          'number_float',
        ])) {
          $value = (float) $value;
        }
        if (!is_numeric($value) && empty($value) && !empty($setting["empty_cell_value"])) {
          $value = $setting["empty_cell_value"];
        }

        $objectData->$field_name = $value;
      }

      if (!empty($setting['show_operation'])) {
        $parent = $entity->getParentEntity();
        $destination = implode('/', [$parent->getEntityTypeId(), $parent->id()]);
        $objectData->operation = $this->paragraphsTableLinksAction($entity->id(), $destination);
      }
      $data[$delta] = $objectData;
    }
    return !empty($data) ? $data : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTable($entities, $components, $setting = []) {
    $data = [];
    foreach ($entities as $delta => $entity) {
      $table_entity = $this->renderBuild->build($entity);
      foreach ($components as $field_name => $field) {
        $table_entity[$field_name]['#label_display'] = 'hidden';
        $value = trim($this->renderer->render($table_entity[$field_name]));
        if (!is_numeric($value) && empty($value) && !empty($setting["empty_cell_value"])) {
          $value = $setting["empty_cell_value"];
        }
        $data[$delta][] = $value;
      }
      if (!empty($setting['show_operation'])) {
        $parent = $entity->getParentEntity();
        $destination = implode('/', [$parent->getEntityTypeId(), $parent->id()]);
        $data[$delta]['operation'] = $this->paragraphsTableLinksAction($entity->id(), $destination);
      }
    }
    return !empty($data) ? $data : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($entities, $components, $setting = []) {
    $data = [];
    $header = [];
    foreach ($components as $field_name => $field) {
      $header[] = $field['title'];
    }
    foreach ($entities as $delta => $entity) {
      $table_entity = $this->renderBuild->build($entity);

      foreach ($components as $field_name => $field) {
        $table_entity[$field_name]['#label_display'] = 'hidden';
        $value = trim(strip_tags($this->renderer->render($table_entity[$field_name])));
        if (in_array($this->fieldsDefinition[$field_name]->getType(), [
          'integer',
          'list_integer',
          'number_integer',
        ])) {
          $value = (int) $value;
        }
        if (in_array($this->fieldsDefinition[$field_name]->getType(), ['boolean'])) {
          $list_value = $table_entity[$field_name]["#items"]->getValue();
          $value = (int) $list_value[0]['value'];
        }
        if (in_array($this->fieldsDefinition[$field_name]->getType(), [
          'decimal',
          'list_decimal',
          'number_decimal',
          'float',
          'list_float',
          'number_float',
        ])) {
          $value = (float) $value;
        }
        if (!is_numeric($value) && empty($value) && !empty($setting["empty_cell_value"])) {
          $value = $setting["empty_cell_value"];
        }

        $data[$delta][] = $value;
      }

      if (!empty($setting['show_operation'])) {
        $parent = $entity->getParentEntity();
        $destination = implode('/', [$parent->getEntityTypeId(), $parent->id()]);
        $data[$delta]['operation'] = $this->paragraphsTableLinksAction($entity->id(), $destination);
      }
    }

    array_unshift($data, $header);

    return count($data) ? $data : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  private function paragraphsTableLinksAction($paragraphsId = FALSE, $destination = '') {
    $route_params = [
      'paragraph' => $paragraphsId,
    ];
    if (!empty($destination)) {
      $route_params['destination'] = $destination;
    }
    $operation = [
      '#type' => 'dropbutton',
      '#links' => [
        'view' => [
          'title' => $this->t('View'),
          'url' => Url::fromRoute('entity.paragraphs_item.canonical', $route_params),
        ],
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity.paragraphs_item.edit_form', $route_params),
        ],
        'duplicate' => [
          'title' => $this->t('Duplicate'),
          'url' => Url::fromRoute('entity.paragraphs_item.clone_form', $route_params),
        ],
        'delete' => [
          'title' => $this->t('Remove'),
          'url' => Url::fromRoute('entity.paragraphs_item.delete_form', $route_params),
        ],
      ],
    ];

    // Alter row operation.
    $this->moduleHandler()->alter('paragraphs_table_operations', $operation, $paragraphsId);
    return $this->renderer->render($operation);
  }

  /**
   * Get entity for search.
   *
   * @param string $target_type
   *   The entity type.
   * @param string $selection_handler
   *   The selection handler.
   * @param string $selection_settings_key
   *   The key crypto.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Ajax to get select list.
   */
  public function fieldReference($target_type, $selection_handler, $selection_settings_key): JsonResponse {
    $selection_settings = $this->keyValue('entity_autocomplete')->get($selection_settings_key);
    $options['target_type'] = $target_type;
    $separator = '';
    $fields = [];
    if ($selection_handler == 'views') {
      $options += [
        'view' => $selection_settings["view"],
        'handler' => 'views',
      ];
      $view = Views::getView($selection_settings["view"]["view_name"]);
      $view->setDisplay($selection_settings["view"]["display_name"]);
      $displayHandler = $view->displayHandlers->get($selection_settings["view"]["display_name"]);
      $rowOptions = $displayHandler->getPlugin('row')->options;
      $separator = $rowOptions["separator"];
      $fields = array_keys($displayHandler->getOption('fields'));
    }
    else {
      $options += [
        'target_bundles' => $selection_settings['target_bundles'],
        'handler' => 'default:' . $target_type,
      ];
      if ($target_type == 'user') {
        $options['target_bundles'] = NULL;
      }
    }
    $handler = $this->selectionManager->getInstance($options);
    $entity_labels = $handler->getReferenceableEntities();
    $rows = [];
    foreach ($entity_labels as $values) {
      foreach ($values as $entity_id => $label) {
        $row = ['id' => $entity_id];
        if (!empty($separator)) {
          $fieldValues = explode($separator, strip_tags($label));
          foreach ($fields as $index => $fieldName) {
            $row[$fieldName] = $fieldValues[$index];
          }
        }
        else {
          $row['name'] = $label;
        }
        $rows[] = $row;
      }
    }
    return new JsonResponse($rows);
  }

}
