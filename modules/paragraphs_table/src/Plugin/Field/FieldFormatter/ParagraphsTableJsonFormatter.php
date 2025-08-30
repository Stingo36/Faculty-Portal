<?php

namespace Drupal\paragraphs_table\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\FileInterface;

/**
 * Plugin implementation of the 'Paragraphs table json' formatter.
 *
 * @FieldFormatter(
 *   id = "paragraphs_table_json_formatter",
 *   label = @Translation("Paragraphs table json"),
 *   description = @Translation("Useful for displaying the rest of the export"),
 *   field_types = {"entity_reference_revisions"},
 * )
 */
class ParagraphsTableJsonFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $setting = ['recursion_level' => 2];
    return $setting + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['recursion_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Recursion max level'),
      '#default_value' => $this->getSetting('recursion_level'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Recursion max level: @level', ['@level' => $this->getSetting('recursion_level')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $setting = $this->getSettings();
    $entities = $this->getEntitiesToView($items, $langcode);
    foreach ($entities as $delta => $entity) {
      $fields = $this->getFields($entity);
      foreach ($fields as $fieldName => $field) {
        if (!$field->isEmpty()) {
          $value = $field->getValue();
          if (method_exists($field, 'getString')) {
            $value = $field->getString();
          }
          if (method_exists($field, 'referencedEntities')) {
            $entities = $field->referencedEntities();
            $value = [];
            foreach ($entities as $entity) {
              $value[] = $this->serializeEntity($entity, $setting["recursion_level"]);
            }
          }
          $elements[$delta][$fieldName] = $value;
        }
      }
    }
    return [['#markup' => json_encode($elements, JSON_PRETTY_PRINT)]];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($entity) {
    $fields = [];
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      if ($definition instanceof FieldConfig) {
        $fields[$name] = $entity->get($name);
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function serializeEntity($entity, $depthMax = 1, $depth = 0) {
    $data = [];
    if ($depth > $depthMax) {
      return $data;
    }
    if (method_exists($entity, 'getFieldDefinitions')) {
      foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
        if (!$entity->hasField($field_name)) {
          continue;
        }
        $field = $entity->get($field_name);
        $fieldDefType = $field->getFieldDefinition()->getType();
        if ($fieldDefType == 'password') {
          continue;
        }
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          $referenced_entities = $field->referencedEntities();
          $data[$field_name] = [];
          foreach ($referenced_entities as $referenced_entity) {
            $subEntity = $this->serializeEntity($referenced_entity, $depthMax, $depth + 1);
            if (!empty($subEntity)) {
              $data[$field_name][] = $subEntity;
            }
          }
        }
        elseif (method_exists($field, 'getString')) {
          $data[$field_name] = $field->getString();
        }
        if ($data[$field_name] !== 0 && empty($data[$field_name])) {
          unset($data[$field_name]);
        }
      }
      $data['link'] = $this->getEntityUrl($entity);
    }
    return $data;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityUrl(ContentEntityInterface $entity) {
    if ($entity instanceof FileInterface) {
      return $entity->createFileUrl();
    }
    if ($entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl()->toString();
    }
    return NULL;
  }

}
