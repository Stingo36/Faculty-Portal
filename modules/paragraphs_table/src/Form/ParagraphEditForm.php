<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph Edit Form class.
 */
class ParagraphEditForm extends ContentEntityForm {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $translateManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a paragraphs edit form object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $translate_manager
   *   The translation manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityFieldManagerInterface $entity_field_manager, LanguageManagerInterface $language_manager, ContentTranslationManagerInterface $translate_manager = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityFieldManager = $entity_field_manager;
    $this->languageManager = $language_manager;
    $this->translateManager = $translate_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
      $container->get('language_manager'),
      $container->has('content_translation.manager') ? $container->get('content_translation.manager') : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    $targetLangcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $form_state->set('langcode', $targetLangcode);

    if ($this->entity->hasTranslation($targetLangcode)) {
      parent::init($form_state);
      return;
    }

    $translationSource = $this->entity;
    $parentEntity = $this->entity->getParentEntity();
    $parentSourceLangcode = $parentEntity->language()->getId();

    if ($parentEntity->hasTranslation($targetLangcode)) {
      $parentEntity = $parentEntity->getTranslation($targetLangcode);
      $parentSourceLangcode = $this->translationManager->getTranslationMetadata($parentEntity)->getSource();
    }

    if ($this->entity->hasTranslation($parentSourceLangcode)) {
      $translationSource = $this->entity->getTranslation($parentSourceLangcode);
    }

    $this->entity = $this->entity->addTranslation($targetLangcode, $translationSource->toArray());
    $this->translationManager->getTranslationMetadata($this->entity)->setSource($translationSource->language()->getId());

    parent::init($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $field_name = $this->entity->get('parent_field_name')->value;
    $host = $this->entity->getParentEntity();
    $entity_type = $host->getEntityTypeId();
    $bundle = $host->bundle();
    $entityFieldManager = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $form['#title'] = $this->t('Edit %type item %id', [
      '%type' => $entityFieldManager[$field_name]->getLabel(),
      '%id' => $this->entity->id(),
    ]);
    $form = parent::form($form, $form_state);
    $form['#entity_parent_type'] = $entity_type;
    $form['#entity_field'] = $field_name;
    $form['#entity_id'] = $this->entity->id();
    $bundle_entity_type = $host->getEntityType()->getBundleEntityType();
    $bundle_entity = $this->entityTypeManager
      ->getStorage($bundle_entity_type)
      ?->load($host->bundle());
    $form['#new_revision'] = method_exists($bundle_entity, 'shouldCreateNewRevision') ? $bundle_entity?->shouldCreateNewRevision() : FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!empty($form['#new_revision'])) {
      $this->entity->setNewRevision();
    }
    return $this->entity->save();
  }

}
