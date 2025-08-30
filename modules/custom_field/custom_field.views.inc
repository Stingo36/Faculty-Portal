<?php

/**
 * @file
 * Provide Views data for the Custom field module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 *
 * Views integration for custom_field fields.
 */
function custom_field_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  $entity_manager = \Drupal::entityTypeManager();
  $field_name = $field_storage->getName();
  $columns = $field_storage->getSetting('columns');
  $types_with_relationships = [
    'file',
    'image',
    'entity_reference',
  ];
  foreach ($data as $table_name => $table_data) {
    foreach ($columns as $name => $column) {
      $data[$table_name][$field_name . '_' . $name]['field'] = [
        'id' => 'standard',
        'field_name' => $field_name,
        'property' => $name,
      ];

      // Build views relationships.
      if (in_array($column['type'], $types_with_relationships)) {
        if ($entity_manager->hasHandler($column['target_type'], 'views_data')) {
          $target_entity_type = $entity_manager->getDefinition($column['target_type']);
          $entity_type_id = $field_storage->getTargetEntityTypeId();
          $entity_type = $entity_manager->getDefinition($entity_type_id);
          $target_base_table = $entity_manager->getHandler($column['target_type'], 'views_data')
            ->getViewsTableForEntityType($target_entity_type);
          $args = [
            '@label' => $target_entity_type->getLabel(),
            '@field_name' => $field_name . '_' . $name,
          ];

          $data[$table_name][$field_name . '_' . $name]['relationship'] = [
            'title' => t('@label referenced from @field_name', $args),
            'group' => $entity_type->getLabel(),
            'id' => 'standard',
            'base' => $target_base_table,
            'entity type' => $column['target_type'],
            'base field' => $target_entity_type->getKey('id'),
            'relationship field' => $field_name . '_' . $name,
            'label' => t('@field_name: @label', $args),
          ];

          // Provide a reverse relationship for the entity type that is
          // referenced by the field.
          $pseudo_field_name = 'reverse__' . $entity_type_id . '__' . $field_name . '_' . $name;
          /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
          $table_mapping = $entity_manager->getStorage($entity_type_id)
            ->getTableMapping();
          $args['@entity'] = $entity_type->getLabel();
          $args['@label'] = $target_entity_type->getSingularLabel();
          $data[$target_base_table][$pseudo_field_name]['relationship'] = [
            'title' => t('@entity using @field_name', $args),
            'label' => t('@field_name', ['@field_name' => $field_name]),
            'group' => $target_entity_type->getLabel(),
            'help' => t('Relate each @entity with a @field_name set to the @label.', $args),
            'id' => 'entity_reverse',
            'base' => $entity_manager->getHandler($entity_type_id, 'views_data')
              ->getViewsTableForEntityType($entity_type),
            'entity_type' => $entity_type_id,
            'base field' => $entity_type->getKey('id'),
            'field_name' => $field_name,
            'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
            'field field' => $field_name . '_' . $name,
          ];
        }
      }
    }
  }

  return $data;
}
