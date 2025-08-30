<?php

namespace Drupal\paragraphs_table\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check access page.
 */
class ParagraphAccessController extends ControllerBase {

  /**
   * Constructs a new paragraph table access.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(protected RouteMatchInterface $currentRouteMatch, protected EntityFieldManagerInterface $entityFieldManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   Paragraph item.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, Paragraph $paragraph) {
    $route_name = $this->currentRouteMatch->getRouteName();
    if ($account->hasPermission('administer paragraphs_item fields')) {
      return AccessResult::allowedIf(TRUE);
    }
    // Check entity paragraphs access.
    if ($this->moduleHandler()->moduleExists('paragraphs_type_permissions')) {
      $bundle = $paragraph->bundle();
      if ($account->hasPermission('bypass paragraphs type content access')) {
        return AccessResult::allowedIf(TRUE);
      }
      $permission = [
        'paragraphs_item.add_page' => 'create paragraph content ' . $bundle,
        'entity.paragraphs_item.edit_form' => 'update paragraph content ' . $bundle,
        'entity.paragraphs_item.clone_form' => 'update paragraph content ' . $bundle,
        'entity.paragraphs_item.delete_form' => 'delete paragraph content ' . $bundle,
      ];
      $entityAccess = $account->hasPermission($permission[$route_name]);
      return AccessResult::allowedIf($entityAccess);
    }
    // Check field permission.
    if ($this->moduleHandler()->moduleExists('field_permissions')) {
      if ($account->hasPermission('access private fields')) {
        return AccessResult::allowedIf(TRUE);
      }
      $field_permission = TRUE;
      $field_name = $paragraph->parent_field_name->value;
      $parent = $paragraph->getParentEntity();
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions($parent->getEntityTypeId(), $parent->bundle());
      $field_definition = $bundle_fields[$field_name];
      $permissionSetting = $field_definition->getFieldStorageDefinition()
        ->getThirdPartySetting('field_permissions', 'permission_type');
      if ($permissionSetting == 'custom') {
        $permission = [
          'paragraphs_item.add_page' => 'create ' . $field_name,
          'entity.paragraphs_item.edit_form' => 'edit ' . $field_name,
          'entity.paragraphs_item.clone_form' => 'edit own ' . $field_name,
          'entity.paragraphs_item.delete_form' => 'edit ' . $field_name,
        ];
        $field_permission = $account->hasPermission($permission[$route_name]);
      }
      if ($permissionSetting == 'private') {
        $field_permission = FALSE;
      }
      return AccessResult::allowedIf($field_permission);
    }
    return AccessResult::allowedIf(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function accessAdd(AccountInterface $account) {
    if ($account->hasPermission('administer paragraphs_item fields')) {
      return AccessResult::allowedIf(TRUE);
    }
    $paragraph_type = $this->currentRouteMatch->getParameter('paragraph_type');
    $entity_type = $this->currentRouteMatch->getParameter('entity_type');
    $field_name = $this->currentRouteMatch->getParameter('entity_field');
    $entity_id = $this->currentRouteMatch->getParameter('entity_id');
    $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $bundle = method_exists($entity, 'bundle') ? $entity->bundle() : $entity_type;
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $field_definition = $bundle_fields[$field_name];
    $fieldType = $field_definition->getType();
    if ($fieldType != 'entity_reference_revisions') {
      return AccessResult::allowedIf(FALSE);
    }
    if ($this->moduleHandler()->moduleExists('paragraphs_type_permissions')) {
      $bundle = $paragraph_type->getOriginalId();
      $entityAccess = $account->hasPermission('create paragraph content ' . $bundle);
      return AccessResult::allowedIf($entityAccess);
    }

    if ($this->moduleHandler()->moduleExists('field_permissions')) {
      $field_permission = $this->checkFieldPermission($field_name, $account, $entity);
      return AccessResult::allowedIf($field_permission);
    }
    return $entity->access('update', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function accessParagraph(AccountInterface $account) {
    if ($account->hasPermission('view unpublished paragraphs')) {
      return AccessResult::allowedIf(TRUE);
    }
    $entity = $this->currentRouteMatch->getParameter('paragraph');
    $check = $entity->access('view', $account);
    return AccessResult::allowedIf($check);
  }

  /**
   * {@inheritdoc}
   */
  public function accessItem(AccountInterface $account) {
    if ($account->hasPermission('view unpublished paragraphs')) {
      return AccessResult::allowedIf(TRUE);
    }
    $entity_type = $this->currentRouteMatch->getParameter('host_type');
    $entity_id = $this->currentRouteMatch->getParameter('host_id');
    $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
    if ($this->moduleHandler()->moduleExists('field_permissions')) {
      $field_name = $this->currentRouteMatch->getParameter('field_name');
      $field_permission = $this->checkFieldPermission($field_name, $account, $entity, 'view');
      return AccessResult::allowedIf($field_permission);
    }
    $check = $entity->access('view', $account);
    return AccessResult::allowedIf($check);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldPermission($field_name, $account, $entity, $permissionType = 'create') {
    $field_permission = TRUE;
    if ($account->hasPermission('access private fields')) {
      return TRUE;
    }
    $entity_type = $entity->getEntityTypeId();
    $bundle = method_exists($entity, 'bundle') ? $entity->bundle() : $entity_type;
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $field_definition = $bundle_fields[$field_name];
    $permissionSetting = $field_definition->getFieldStorageDefinition();
    $field_permissions_type = $permissionSetting->getThirdPartySettings('field_permissions');
    $permission = !empty($field_permissions_type['permission_type']) ? $field_permissions_type['permission_type'] : FALSE;
    if ($permission == 'custom') {
      $field_permission = $account->hasPermission($permissionType . ' ' . $field_name);
      if (!$field_permission) {
        $field_permission = $account->hasPermission($permissionType . ' own ' . $field_name);
      }
    }
    if ($permission == 'private') {
      $field_permission = FALSE;
    }
    return $field_permission;
  }

}
