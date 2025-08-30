<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Annotation\ViewsField;

/**
 * @ViewsField("field_faculty_search_comit_coref")
 */
class FacultySearchCommitee extends BaseComments {

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName = 'field_faculty_search_comit_coref';

  /**
   * The role to be added in the create link.
   *
   * @var string
   */
  protected $role = 'faculty_search_committee';

}
