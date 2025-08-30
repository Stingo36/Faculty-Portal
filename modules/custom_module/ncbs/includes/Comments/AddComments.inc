<?php

use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements hook_form_BASE_FORM_ID_alter() for the node add_comments form.
 */
function ncbs_form_node_add_comments_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  // Only target the “add_comments” content type.
  if ($form_state->getFormObject()->getEntity()->bundle() !== 'add_comments') {
    return;
  }

  // Grab the role from ?role=…
  $role = \Drupal::request()->query->get('role');

  // Loop through each paragraph widget (including new ones via AJAX).
  if (!empty($form['field_add_comments_']['widget']) && is_array($form['field_add_comments_']['widget'])) {
    foreach ($form['field_add_comments_']['widget'] as $key => &$widget) {
      // Skip the “add_more” button, etc.
      if (!is_numeric($key) || empty($widget['subform'])) {
        continue;
      }

      // If role=dean, hide faculty list.
      if ($role === 'dean' && isset($widget['subform']['field_user_list_faculty'])) {
        $widget['subform']['field_user_list_faculty']['#access']   = FALSE;
        $widget['subform']['field_user_list_faculty']['#required'] = FALSE;
      }

      // If role=faculty, hide dean list.
      elseif ($role === 'faculty_member' && isset($widget['subform']['field_user_list_dean'])) {
        $widget['subform']['field_user_list_dean']['#access']   = FALSE;
        $widget['subform']['field_user_list_dean']['#required'] = FALSE;
      }
      // 3) No role: hide both lists.
      else {
        if (isset($widget['subform']['field_user_list_faculty'])) {
          $widget['subform']['field_user_list_faculty']['#access']   = FALSE;
          $widget['subform']['field_user_list_faculty']['#required'] = FALSE;
        }
        if (isset($widget['subform']['field_user_list_dean'])) {
          $widget['subform']['field_user_list_dean']['#access']   = FALSE;
          $widget['subform']['field_user_list_dean']['#required'] = FALSE;
        }
      }
    }
  }
  $form['actions']['submit']['#value'] = t('Submit');
  // Attach your custom submit handler.
}





//! Not working for Admin users
// /**
//  * Alters the node add_comments edit form.
//  */
// function ncbs_form_node_add_comments_edit_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
//   // 1) Only target the "add_comments" node edit form.
//   if ($form_id !== 'node_add_comments_edit_form' ||
//       $form_state->getFormObject()->getEntity()->bundle() !== 'add_comments') {
//     return;
//   }

//   $node = $form_state->getFormObject()->getEntity();
//   $role = \Drupal::request()->query->get('role', '');

//   // 2) Record how many comment‐paragraphs were originally on the entity
//   if (!$form_state->has('ncbs_original_comments_count')) {
//     $count = $node->hasField('field_add_comments_')
//       ? $node->get('field_add_comments_')->count()
//       : 0;
//     $form_state->set('ncbs_original_comments_count', $count);
//   }
//   $original_count = $form_state->get('ncbs_original_comments_count');

//   // 3) Walk each paragraph in the add_comments field.
//   if (!empty($form['field_add_comments_']['widget']) && is_array($form['field_add_comments_']['widget'])) {
//     foreach ($form['field_add_comments_']['widget'] as $delta => &$item) {
//       if (!is_numeric($delta) || empty($item['subform'])) {
//         continue;
//       }
//       $subform = &$item['subform'];

//       // — Lock only original paragraphs’ comments textarea —
//       if ($delta < $original_count) {
//         $default = $subform['field_comments']['widget'][0]['value']['#default_value'] ?? '';
//         if ($default !== '') {
//           $elem = &$subform['field_comments']['widget'][0]['value'];
//           $elem['#attributes']['readonly'] = 'readonly';
//           $elem['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
//           if (isset($item['top']['links']['remove_button'])) {
//             $item['top']['links']['remove_button']['#access'] = FALSE;
//           }
//         }
//       }

//       // — Role = dean: hide faculty list on all, but lock dean list only on original paragraphs —
//       if ($role === 'dean') {
//         // Hide faculty list everywhere
//         if (isset($subform['field_user_list_faculty']['widget'])) {
//           $subform['field_user_list_faculty']['widget']['#access']   = FALSE;
//           $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
//         }
//         // Only make dean‐list readonly on the paragraphs that existed originally
//         if ($delta < $original_count && isset($subform['field_user_list_dean']['widget'])) {
//           // Disable the widget input
//           $subform['field_user_list_dean']['widget']['#disabled'] = TRUE;
//           // Gray it out / emulate readonly
//           $subform['field_user_list_dean']['widget']['#attributes']['readonly'] = 'readonly';
//           $subform['field_user_list_dean']['widget']['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
//         }
//         // New paragraphs (delta >= original_count) keep an enabled dean list.
//       }

//       // — Role = faculty_member: hide dean list as before —
//       elseif ($role === 'faculty_member' && isset($subform['field_user_list_dean']['widget'])) {
//         $subform['field_user_list_dean']['widget']['#access']   = FALSE;
//         $subform['field_user_list_dean']['widget']['#required'] = FALSE;


//          // Only make dean‐list readonly on the paragraphs that existed originally
//         if ($delta < $original_count && isset($subform['field_user_list_faculty']['widget'])) {
//           // Disable the widget input
//           $subform['field_user_list_faculty']['widget']['#disabled'] = TRUE;
//           // Gray it out / emulate readonly
//           $subform['field_user_list_faculty']['widget']['#attributes']['readonly'] = 'readonly';
//           $subform['field_user_list_faculty']['widget']['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
//         }


//       }
//       // New: no-role case
//       elseif (empty($role)) {
//         // Hide and unrequire the dean list
//         if (isset($subform['field_user_list_dean']['widget'])) {
//           $subform['field_user_list_dean']['widget']['#access']   = FALSE;
//           $subform['field_user_list_dean']['widget']['#required'] = FALSE;
//         }
//         // Hide and unrequire the faculty list
//         if (isset($subform['field_user_list_faculty']['widget'])) {
//           $subform['field_user_list_faculty']['widget']['#access']   = FALSE;
//           $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
//         }
//       }
//       // Get the current user.
//       $current_user = \Drupal::currentUser();

//       // If user role = admin → hide
//       if ($current_user->hasRole('admin')) {
//         if (isset($subform['field_user_list_dean']['widget'])) {
//           $subform['field_user_list_dean']['widget']['#access'] = FALSE;
//           $subform['field_user_list_dean']['widget']['#required'] = FALSE;
//         }
//         if (isset($subform['field_user_list_faculty']['widget'])) {
//           $subform['field_user_list_faculty']['widget']['#access'] = FALSE;
//           $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
//         }
//       }
//       // If URL role = prescreen_committee OR faculty_search_committee → hide
//       elseif (in_array($role, ['prescreen_committee', 'faculty_search_committee'], true)) {
//         if (isset($subform['field_user_list_dean']['widget'])) {
//           $subform['field_user_list_dean']['widget']['#access'] = FALSE;
//           $subform['field_user_list_dean']['widget']['#required'] = FALSE;
//         }
//         if (isset($subform['field_user_list_faculty']['widget'])) {
//           $subform['field_user_list_faculty']['widget']['#access'] = FALSE;
//           $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
//         }
//       }
//       // If user role = administrator → show
//       elseif ($current_user->hasRole('administrator')) {
//         if (isset($subform['field_user_list_dean']['widget'])) {
//           $subform['field_user_list_dean']['widget']['#access'] = TRUE;
//           $subform['field_user_list_dean']['widget']['#required'] = TRUE;
//         }
//         if (isset($subform['field_user_list_faculty']['widget'])) {
//           $subform['field_user_list_faculty']['widget']['#access'] = TRUE;
//           $subform['field_user_list_faculty']['widget']['#required'] = TRUE;
//         }
//       }
            
//     }
//   }
//   // 4) Hide the revision tab if present.
//   if (isset($form['revision_information'])) {
//     $form['revision_information']['#access'] = FALSE;
//   }
//  $form['actions']['submit']['#value'] = t('Submit');
//   // 5) Attach your custom submit handler.
// }



//! new code


/**
 * Alters the node add_comments edit form.
 */
function ncbs_form_node_add_comments_edit_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  // 1) Only target the "add_comments" node edit form.
  if ($form_id !== 'node_add_comments_edit_form' ||
      $form_state->getFormObject()->getEntity()->bundle() !== 'add_comments') {
    return;
  }

  $node = $form_state->getFormObject()->getEntity();
  $role = \Drupal::request()->query->get('role', '');

  // 2) Record how many comment‐paragraphs were originally on the entity
  if (!$form_state->has('ncbs_original_comments_count')) {
    $count = $node->hasField('field_add_comments_')
      ? $node->get('field_add_comments_')->count()
      : 0;
    $form_state->set('ncbs_original_comments_count', $count);
  }
  $original_count = $form_state->get('ncbs_original_comments_count');

  // 3) Walk each paragraph in the add_comments field.
  if (!empty($form['field_add_comments_']['widget']) && is_array($form['field_add_comments_']['widget'])) {
    foreach ($form['field_add_comments_']['widget'] as $delta => &$item) {
      if (!is_numeric($delta) || empty($item['subform'])) {
        continue;
      }
      $subform = &$item['subform'];

      // — Lock only original paragraphs’ comments textarea —
      if ($delta < $original_count) {
        $default = $subform['field_comments']['widget'][0]['value']['#default_value'] ?? '';
        if ($default !== '') {
          $elem = &$subform['field_comments']['widget'][0]['value'];
          $elem['#attributes']['readonly'] = 'readonly';
          $elem['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
          if (isset($item['top']['links']['remove_button'])) {
            $item['top']['links']['remove_button']['#access'] = FALSE;
          }
        }
      }

      // — Role = dean: hide faculty list on all, but lock dean list only on original paragraphs —
      if ($role === 'dean') {
        // Hide faculty list everywhere
        if (isset($subform['field_user_list_faculty']['widget'])) {
          $subform['field_user_list_faculty']['widget']['#access']   = FALSE;
          $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
        }
        // Only make dean‐list readonly on the paragraphs that existed originally
        if ($delta < $original_count && isset($subform['field_user_list_dean']['widget'])) {
          // Disable the widget input
          $subform['field_user_list_dean']['widget']['#disabled'] = TRUE;
          // Gray it out / emulate readonly
          $subform['field_user_list_dean']['widget']['#attributes']['readonly'] = 'readonly';
          $subform['field_user_list_dean']['widget']['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
        }
        // New paragraphs (delta >= original_count) keep an enabled dean list.
      }

      // — Role = faculty_member: hide dean list as before —
      elseif ($role === 'faculty_member' && isset($subform['field_user_list_dean']['widget'])) {
        $subform['field_user_list_dean']['widget']['#access']   = FALSE;
        $subform['field_user_list_dean']['widget']['#required'] = FALSE;


         // Only make dean‐list readonly on the paragraphs that existed originally
        if ($delta < $original_count && isset($subform['field_user_list_faculty']['widget'])) {
          // Disable the widget input
          $subform['field_user_list_faculty']['widget']['#disabled'] = TRUE;
          // Gray it out / emulate readonly
          $subform['field_user_list_faculty']['widget']['#attributes']['readonly'] = 'readonly';
          $subform['field_user_list_faculty']['widget']['#attributes']['style']    = 'background-color: #f0f0f0; color: #888;';
        }


      }
      // New: no-role case
      else {
        // Hide and unrequire the dean list
        if (isset($subform['field_user_list_dean']['widget'])) {
          $subform['field_user_list_dean']['widget']['#access']   = FALSE;
          $subform['field_user_list_dean']['widget']['#required'] = FALSE;
        }
        // Hide and unrequire the faculty list
        if (isset($subform['field_user_list_faculty']['widget'])) {
          $subform['field_user_list_faculty']['widget']['#access']   = FALSE;
          $subform['field_user_list_faculty']['widget']['#required'] = FALSE;
        }
      }
            
    }
  }

  // 4) Hide the revision tab if present.
  if (isset($form['revision_information'])) {
    $form['revision_information']['#access'] = FALSE;
  }
 $form['actions']['submit']['#value'] = t('Submit Comments');
}

















use Drupal\Core\Entity\EntityInterface;

// /**
//  * Sets reference field on the parent node based on the current user's role.
//  *
//  * @param \Drupal\Core\Entity\EntityInterface $entity
//  *   The node entity of bundle `add_comments`.
//  * @param \Drupal\Core\Session\AccountInterface $current_user
//  *   The current logged-in user.
//  * @param \Drupal\Core\Messenger\MessengerInterface $messenger
//  *   Drupal messenger service for status messages.
//  * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
//  *   Request stack to get URL parameters.
//  */
// function AddCommentByRole(EntityInterface $entity, AccountInterface $current_user, MessengerInterface $messenger, RequestStack $request_stack) {
//   if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'add_comments') {
//     return;
//   }

//   // Get 'nid' from URL.
//   $request = $request_stack->getCurrentRequest();
//   $nid = $request->query->get('nid');

//   if (!$nid || !is_numeric($nid)) {
//     $messenger->addError('Referenced node ID (nid) is missing or invalid.');
//     return;
//   }

//   // Load the referenced (parent) node.
//   $parent_node = Node::load($nid);
//   if (!$parent_node) {
//     $messenger->addError("Node with ID $nid not found.");
//     return;
//   }

//   // Get user roles.
//   $roles = $current_user->getRoles();

//   // Assign comment node reference based on role.
//   if (in_array('faculty_member', $roles)) {
//     if ($parent_node->hasField('field_faculty_member_comment_ref')) {
//       $parent_node->set('field_faculty_member_comment_ref', ['target_id' => $entity->id()]);
//       $parent_node->save();
//       $messenger->addStatus('Comment saved as faculty member.');
//     }
//     else {
//       $messenger->addError('Field "field_faculty_member_comment_ref" not found on parent node.');
//     }
//   }
//   elseif (in_array('dean', $roles)) {
//     if ($parent_node->hasField('field_dean_comment_reference')) {
//       $parent_node->set('field_dean_comment_reference', ['target_id' => $entity->id()]);
//       $parent_node->save();
//       $messenger->addStatus('Comment saved as dean.');
//     }
//     else {
//       $messenger->addError('Field "field_dean_comment_reference" not found on parent node.');
//     }
//   }
//   elseif (in_array('administrator', $roles) || in_array('admin', $roles)) {
//     // If the current user is admin/administrator, optionally route by ?role=... in URL.
//     $url_role = $request->query->get('role');

//     if ($url_role === 'dean') {
//       if ($parent_node->hasField('field_dean_comment_reference')) {
//         $parent_node->set('field_dean_comment_reference', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as dean (via admin override).');
//       }
//       else {
//         $messenger->addError('Field "field_dean_comment_reference" not found on parent node.');
//       }
//     }
//     elseif ($url_role === 'faculty_member') {
//       if ($parent_node->hasField('field_faculty_member_comment_ref')) {
//         $parent_node->set('field_faculty_member_comment_ref', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as faculty member (via admin override).');
//       }
//       else {
//         $messenger->addError('Field "field_faculty_member_comment_ref" not found on parent node.');
//       }
//     }
//     elseif ($url_role === 'director') {
//       if ($parent_node->hasField('field_director_comment_reference')) {
//         $parent_node->set('field_director_comment_reference', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as director (via admin override).');
//       }
//       else {
//         $messenger->addError('Field "field_director_comment_reference" not found on parent node.');
//       }
//     }
//     elseif ($url_role === 'faculty_search_committee') {
//       if ($parent_node->hasField('field_faculty_search_comit_coref')) {
//         $parent_node->set('field_faculty_search_comit_coref', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as faculty search committee (via admin override).');
//       }
//       else {
//         $messenger->addError('Field "field_faculty_search_comit_coref" not found on parent node.');
//       }
//     }
//     elseif ($url_role === 'prescreen_committee') {
//       if ($parent_node->hasField('field_prescreen_comment_ref')) {
//         $parent_node->set('field_prescreen_comment_ref', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as prescreen committee (via admin override).');
//       }
//       else {
//         $messenger->addError('Field "field_prescreen_comment_ref" not found on parent node.');
//       }
//     }
//     else {
//       if ($parent_node->hasField('field_admin_comment_reference')) {
//         $parent_node->set('field_admin_comment_reference', ['target_id' => $entity->id()]);
//         $parent_node->save();
//         $messenger->addStatus('Comment saved as administrator.');
//       }
//       else {
//         $messenger->addError('Field "field_admin_comment_reference" not found on parent node.');
//       }
//     }
//   }


//   else {
//     $messenger->addWarning('Current user role does not have permission to update this node.');
//   }
// }























/**
 * Sets reference field on the parent node based on the current user's role.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The node entity of bundle `add_comments`.
 * @param \Drupal\Core\Session\AccountInterface $current_user
 *   The current logged-in user.
 * @param \Drupal\Core\Messenger\MessengerInterface $messenger
 *   Drupal messenger service for status messages.
 * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
 *   Request stack to get URL parameters.
 */
function AddCommentByRole(EntityInterface $entity, AccountInterface $current_user, MessengerInterface $messenger, RequestStack $request_stack) {
  if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'add_comments') {
    return;
  }

  // Get 'nid' from URL.
  $request = $request_stack->getCurrentRequest();
  $nid = $request->query->get('nid');

  if (!$nid || !is_numeric($nid)) {
    $messenger->addError('Referenced node ID (nid) is missing or invalid.');
    return;
  }

  // Load the referenced (parent) node.
  $parent_node = Node::load($nid);
  if (!$parent_node) {
    $messenger->addError("Node with ID $nid not found.");
    return;
  }

  // Get user roles.
  $roles = $current_user->getRoles();

  // Helper to append to multi-value reference fields.
  $appendReference = function(Node $node, $field_name, EntityInterface $ref_entity) {
    $existing = $node->get($field_name)->getValue();
    $existing[] = ['target_id' => $ref_entity->id()];
    $node->set($field_name, $existing);
    $node->save();
  };

  // Assign comment node reference based on role.
  if (in_array('faculty_member', $roles)) {
    if ($parent_node->hasField('field_faculty_member_comment_ref')) {
      $appendReference($parent_node, 'field_faculty_member_comment_ref', $entity);
     $messenger->addStatus('Comment saved as faculty member.');
    }
    else {
      $messenger->addError('Field "field_faculty_member_comment_ref" not found on parent node.');
    }
  }
  elseif (in_array('dean', $roles)) {
    if ($parent_node->hasField('field_dean_comment_reference')) {
      $appendReference($parent_node, 'field_dean_comment_reference', $entity);
      $messenger->addStatus('Comment saved as dean.');
    }
    else {
      $messenger->addError('Field "field_dean_comment_reference" not found on parent node.');
    }
  }
  elseif (in_array('administrator', $roles) || in_array('admin', $roles)) {
    $url_role = $request->query->get('role');

    if ($url_role === 'dean') {
      if ($parent_node->hasField('field_dean_comment_reference')) {
        $appendReference($parent_node, 'field_dean_comment_reference', $entity);
        $messenger->addStatus('Comment saved as dean (via admin override).');
      }
      else {
        $messenger->addError('Field "field_dean_comment_reference" not found on parent node.');
      }
    }
    elseif ($url_role === 'faculty_member') {
      if ($parent_node->hasField('field_faculty_member_comment_ref')) {
        $appendReference($parent_node, 'field_faculty_member_comment_ref', $entity);
        $messenger->addStatus('Comment saved as faculty member (via admin override).');
      }
      else {
        $messenger->addError('Field "field_faculty_member_comment_ref" not found on parent node.');
      }
    }
    elseif ($url_role === 'director') {
      if ($parent_node->hasField('field_director_comment_reference')) {
        $appendReference($parent_node, 'field_director_comment_reference', $entity);
        $messenger->addStatus('Comment saved as director (via admin override).');
      }
      else {
        $messenger->addError('Field "field_director_comment_reference" not found on parent node.');
      }
    }
    elseif ($url_role === 'faculty_search_committee') {
      if ($parent_node->hasField('field_faculty_search_comit_coref')) {
        $appendReference($parent_node, 'field_faculty_search_comit_coref', $entity);
        $messenger->addStatus('Comment saved as faculty search committee (via admin override).');
      }
      else {
        $messenger->addError('Field "field_faculty_search_comit_coref" not found on parent node.');
      }
    }
    elseif ($url_role === 'prescreen_committee') {
      if ($parent_node->hasField('field_prescreen_comment_ref')) {
        $appendReference($parent_node, 'field_prescreen_comment_ref', $entity);
        $messenger->addStatus('Comment saved as prescreen committee (via admin override).');
      }
      else {
        $messenger->addError('Field "field_prescreen_comment_ref" not found on parent node.');
      }
    }
    else {
      if ($parent_node->hasField('field_admin_comment_reference')) {
        $appendReference($parent_node, 'field_admin_comment_reference', $entity);
        $messenger->addStatus('Comment saved as administrator.');
      }
      else {
        $messenger->addError('Field "field_admin_comment_reference" not found on parent node.');
      }
    }
  }
  else {
    $messenger->addWarning('Current user role does not have permission to update this node.');
  }
}
