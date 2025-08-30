<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Annotation\ViewsField;

/**
 * @ViewsField("faculty_comment_ref")
 */
class FacultyComments extends BaseComments {

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName = 'field_faculty_member_comment_ref';

  /**
   * The role to be added in the create link.
   *
   * @var string
   */
  protected $role = 'faculty_member';

}
