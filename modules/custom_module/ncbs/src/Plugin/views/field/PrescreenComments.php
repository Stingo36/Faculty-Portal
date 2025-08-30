<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Annotation\ViewsField;

/**
 * @ViewsField("field_prescreen_comment_ref")
 */
class PrescreenComments extends BaseComments {

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName = 'field_prescreen_comment_ref';

  /**
   * The role to be added in the create link.
   *
   * @var string
   */
  protected $role = 'prescreen_committee';

}
