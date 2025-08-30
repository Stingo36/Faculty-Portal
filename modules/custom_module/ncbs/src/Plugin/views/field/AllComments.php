<?php

namespace Drupal\ncbs\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Utility\Xss;

/**
 * @ViewsField("all_comments")
 */
class AllComments extends FieldPluginBase {

  /**
   * Prevent Views from trying to SELECT a non‑existent column.
   */
  public function query() {
    // Computed field: nothing to add to the SQL query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // 1) Get the node ID from the Views row.
    if (isset($values->nid)) {
      $nid = $values->nid;
    }
    elseif (isset($values->_entity) && $values->_entity->getEntityTypeId() === 'node') {
      $nid = $values->_entity->id();
    }
    else {
      return ['#markup' => ''];
    }

    // 2) Load the node.
    $node = Node::load($nid);
    if (! $node) {
      return ['#markup' => ''];
    }

    // 3) Define your reference fields and their labels.
    $ref_fields = [
      'field_admin_comment_reference'    => 'Admin Comments',
      'field_dean_comment_reference'     => 'Dean Comments',
      'field_faculty_member_comment_ref' => 'Faculty Comments',
      'field_faculty_search_comit_coref' => 'Faculty Search Commitee Comments',
      'field_prescreen_comment_ref' => 'Pre Screen Comments',
    ];

    // 4) Build HTML output.
    $output = '';
    foreach ($ref_fields as $field_name => $label) {
      // Always print the label.
      $output .= '<b>' . Xss::filterAdmin($label) . '</b><br>';

      // Check for referenced nodes.
      if ($node->hasField($field_name) && ! $node->get($field_name)->isEmpty()) {
        $referenced = $node->get($field_name)->referencedEntities();
      }
      else {
        $referenced = [];
      }

      $printed_any = FALSE;

      foreach ($referenced as $ref_node) {
        if (! $ref_node->hasField('field_add_comments_') || $ref_node->get('field_add_comments_')->isEmpty()) {
          continue;
        }
        /** @var ParagraphInterface[] $paras */
        $paras = $ref_node->get('field_add_comments_')->referencedEntities();
        foreach ($paras as $para) {
          $author  = Xss::filterAdmin($para->get('field_comment_author')->value);
          $raw_date = $para->get('field_comment_date')->value;
          $date = date('d-m-Y', strtotime($raw_date));
          $date = Xss::filterAdmin($date);
          $comment = Xss::filterAdmin($para->get('field_comments')->value);

          // Underlined meta
          $output .= '<u>' . $this->t('Comment by @author on @date', [
            '@author' => $author,
            '@date'   => $date,
          ]) . '</u><br>';

          // The comment body
          $output .= '<p>' . nl2br($comment) . '</p>';

          $printed_any = TRUE;
        }
      }

      // If no comments were printed for this label, show placeholder.
      if (! $printed_any) {
        $output .= '<div>' . $this->t('Comments Not Given') . '</div><br>';
      }

      // Spacing between sections
      $output .= '<br>';
    }

    // 5) Return the assembled HTML.
    return [
      '#type'   => 'markup',
      '#markup' => $output,
    ];
  }

}
