<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\Annotation\ViewsField;

/**
 * @ViewsField("admin_comment_ref")
 */
class AdminComments extends BaseComments {

  /**
   * The field name to retrieve.
   *
   * @var string
   */
  protected $fieldName = 'field_admin_comment_reference';

}
