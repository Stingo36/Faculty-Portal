<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Annotation\ViewsField;

/**
 * @ViewsField("director_comment_ref")
 */
class DirectorComments extends BaseComments {

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName = 'field_director_comment_reference';

  /**
   * The role to be added in the create link.
   *
   * @var string
   */
  protected $role = 'director';

}
