<?php


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;


/* -------------------------- //! Form Alter Hook: -------------------------- */
/**
 * This function provides dynamic form alterations across multiple node and views forms,
 * focusing on improving user experience, enforcing submission rules, and simplifying UI.
 *
 * Summary of Functional Blocks:
 *
 * 1. Comment Form
 *    - Update Title in Comment form
 * 
 * 2. Referee Feedback Form:
 *    - Changes the submit button label from "Save" to "Submit".
 *    - Update Title in Referee Feefback form
 *
 * 3. Work Experience Form (Add/Edit):
 *    - Applies custom date validation logic through `ncbs_alter_work_experience_date_fields()`.
 *
 * 4. Views Exposed Filter Form:
 *    - Limits taxonomy term filter (`field_program_name_target_id`) to parent terms only
 *      for cleaner dropdown options.
 *
 * 5. Candidate Node Forms (Add/Edit):
 *    - Dynamically sets user-friendly titles based on the form ID.
 *    - Attaches reference validation and submit handlers for linking nodes to users.
 *
 * 6. Candidate Edit Forms:
 *    - Hides the "revision" checkbox to simplify the interface.
 *    - Flushes render and twig caches to reflect the change immediately.
 *
 * 7. Basic Information Form:
 *    - Limits the Date of Birth field to today or earlier to prevent future dates.
 *
 * 8. Basic Information Edit Form:
 *    - For non-admin users, conditionally hides a list of sensitive fields
 *      based on the value of the `field_access` field on the node.
 *    - Supports nested form containers via recursive field hiding.
 *
 */

function ncbs_form_alter(&$form, FormStateInterface $form_state, $form_id)
{


  //  Comment Title
  if ($form_id == 'node_add_comments_form') {
    // Get nid from the query string (e.g., ?nid=123)
    $nid = \Drupal::request()->query->get('nid');

    if ($nid) {
      $node = Node::load($nid);

      if ($node) {
        $full_name = '';
        $program_name = '';

        // Load user full name from field_user_reference.
        if ($node->hasField('field_user_reference') && !$node->get('field_user_reference')->isEmpty()) {
          $user_id = $node->get('field_user_reference')->target_id;
          $user = User::load($user_id);

          if ($user && $user->hasField('field_user_full_name') && !$user->get('field_user_full_name')->isEmpty()) {
            $full_name = $user->get('field_user_full_name')->value;
          } else {
            \Drupal::messenger()->addWarning(t('User full name is missing.'));
          }
        } else {
          \Drupal::messenger()->addWarning(t('User reference field is missing or empty.'));
        }

        // Load taxonomy term name from field_program_name.
        // if ($node->hasField('field_program_name') && !$node->get('field_program_name')->isEmpty()) {
        //   $term_id = $node->get('field_program_name')->target_id;
        //   $term = Term::load($term_id);

        //   if ($term) {
        //     $program_name = $term->label();
        //   } else {
        //     \Drupal::messenger()->addWarning(t('Program term could not be loaded.'));
        //   }
        // } else {
        //   \Drupal::messenger()->addWarning(t('Program name field is missing or empty.'));
        // }

        if ($full_name) {
          // Set the form title.
          $form['#title'] = t('Please Enter Comment for @full_name', [
            '@full_name' => $full_name,
            // '@program' => $program_name,
          ]);
        } else {
          \Drupal::messenger()->addWarning(t('Could not set form title due to missing data.'));
        }

      } else {
        \Drupal::messenger()->addError(t('Node not found.'));
      }
    } else {
      \Drupal::messenger()->addError(t('Missing node ID in URL.'));
    }
  }

  //  Referee 
  if ($form_id === 'node_referee_feedback_form_form') {
    $form['actions']['submit']['#value'] = t('Submit');

    // Get the node ID from the URL.
    $nid = \Drupal::request()->query->get('id');
    if ($nid && $node = \Drupal\node\Entity\Node::load($nid)) {

      // Check if user reference field is present.
      if ($node->hasField('field_user_reference') && !$node->get('field_user_reference')->isEmpty()) {
        $user_id = $node->get('field_user_reference')->target_id;
        $user = \Drupal\user\Entity\User::load($user_id);

        if ($user) {
          // Get full name.
          $full_name = $user->get('field_user_full_name')->value ?? 'the candidate';

          // Set title.
          $form['#title'] = t('Recommendation for @name', ['@name' => $full_name]);

          // Optional: Show name in message for confirmation/debug.
          \Drupal::messenger()->addStatus("👤 Full Name: {$full_name}");
        }
      }
    }
  }

  //  Work Experience
  if (in_array($form_id, ['node_work_experience_form', 'node_work_experience_edit_form'])) {
    ncbs_alter_work_experience_date_fields($form, $form_state);
  }

  //  Views Form Alter: Limit Filter Options to Only Parent Terms  
  // if ($form_id === 'views_exposed_form' && isset($form['field_program_name_target_id'])) {
  //   // Get the existing default (e.g. '' or 'All')
  //   $existing_default = $form['field_program_name_target_id']['#default_value'] ?? '';

  //   // Load parent (depth-0) terms from the vocabulary
  //   $terms = \Drupal::entityTypeManager()
  //     ->getStorage('taxonomy_term')
  //     ->loadTree('research_areas', 0, 1, TRUE); // Adjust to your vocabulary

  //   // Build new options array starting with the default
  //   $options = [$existing_default => t('- Any -')];
  //   foreach ($terms as $term) {
  //     $options[$term->id()] = $term->getName();
  //   }

  //   // Replace the select options safely
  //   $form['field_program_name_target_id']['#options'] = $options;
  // }


  if ($form_id === 'views_exposed_form' && isset($form['field_program_name_target_id'])) {
  // Get the existing default (e.g. '' or 'All')
  $existing_default = $form['field_program_name_target_id']['#default_value'] ?? '';

  // Load published top-level (depth-0) terms only
  $term_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'research_areas')
    ->condition('status', 1)
    ->accessCheck(FALSE)
    ->condition('parent', 0) // Top-level terms
    ->execute();

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($term_ids);

  // Build new options array starting with the default
  $options = [$existing_default => t('- Any -')];
  foreach ($terms as $term) {
    $options[$term->id()] = $term->label();
  }

  // Replace the select options
  $form['field_program_name_target_id']['#options'] = $options;
}


  

  $titles = [
    'node_basic_information_form' => 'Please Enter Your Basic Information Details',
    'node_academic_qualification_form' => 'Please Enter Your Academic Qualification Details',
    'node_work_experience_form' => 'Please Enter Your Work Experience Details',
    'node_other_relevant_information_form' => 'Please Enter Your Research Area Details ',
    'node_update_publications_form' => 'Please Enter Your Publication Details',
    'node_list_of_referees__form' => 'Please Enter Your Referees Details',
    'node_research_proposal_form' => 'Please Enter Your Research Proposal Details',

    'node_basic_information_edit_form' => 'Update Your Basic Information Details',
    'node_academic_qualification_edit_form' => 'Update Your Academic Qualification Details',
    'node_work_experience_edit_form' => 'Update Your Work Experience Details',
    'node_other_relevant_information_edit_form' => 'Update Your Research Area Details ',
    'node_update_publications_edit_form' => 'Update Your Publication Details',
    'node_list_of_referees__edit_form' => 'Update Your Referees Details',
    'node_research_proposal_edit_form' => 'Update Your Research Proposal Details',

    'node_submit_application_edit_form' => 'Submit Applicaiton',


  ];
  if (isset($titles[$form_id])) {
    $form['#title'] = $titles[$form_id];
  }
  // Add guideline message under the title for a specific form.
  if (($form_id == 'node_work_experience_form') || ($form_id == 'node_work_experience_edit_form')){
    $form['work_experience_guidelines'] = [
      '#markup' => '<div class="guidelines">Work experience obtained after PhD to be included here.</div>',
      '#weight' => -99, // Places it right after the title but before fields.
    ];
  }


  $form_field_mapping = [
    'node_basic_information_form' => 'field_user_basic_info_ref',
    'node_academic_qualification_form' => 'field_user_academic_qualifi_ref',
    'node_other_relevant_information_form' => 'field_user_other_rel_info_ref',
    'node_update_publications_form' => 'field_user_update_pub_ref',
    'node_research_proposal_form' => 'field_user_research_proposal_ref',
    'node_list_of_referees__form' => 'field_user_list_of_referees_ref',
    'node_work_experience_form' => 'field_user_work_experience_ref',
  ];
  // Add submit handler and assign the relevant user field name for specific forms.
  if (isset($form_field_mapping[$form_id])) {
    $form['#validate'][] = 'ncbs_generic_reference_validation';
    $form['#user_reference_field'] = $form_field_mapping[$form_id];
    $form['actions']['submit']['#submit'][] = 'ncbs_handle_form_submission';
    $form['#field_name'] = $form_field_mapping[$form_id];
  }



  $target_form_ids = [
    'node_basic_information_edit_form',
    'node_academic_qualification_edit_form',
    'node_other_relevant_information_edit_form',
    'node_list_of_referees__edit_form',
    'node_update_publications_edit_form',
    'node_research_proposal_edit_form',
    'node_work_experience_edit_form',
  ];
  // Hide the revision field for edit forms.
  if (in_array($form_id, $target_form_ids)) {
    $form['revision']['#access'] = FALSE;
    \Drupal::service('twig')->invalidate();
    \Drupal::service('cache.render')->invalidateAll();
  }

  if ($form_id === 'node_basic_information_form') {
    // Set a custom title for the page.
    \Drupal::service('page_cache_kill_switch')->trigger(); // Ensure caching is bypassed.
    // Change this to your date field's machine name
    $date_field_name = 'field_date_of_birth';
    if (isset($form[$date_field_name])) {
      // Restrict date picker to today or earlier dates
      $form[$date_field_name]['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
    }
  }

  if ($form_id === 'node_basic_information_edit_form') {
    $date_field_name = 'field_date_of_birth';

    if (isset($form[$date_field_name])) {
      // Restrict date picker to today or earlier dates
      $form[$date_field_name]['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
    }

    // Only apply the hide/show logic for non‑administrators
    if (!\Drupal::currentUser()->hasRole('administrator')) {
      // Specify the field you want to check
      $field_name = 'field_access'; // List field machine name

      // Fields to hide
      $hide_fields = [
        'field_centers',
        'field_mobile_number',
        'field_are_you_an_oci_card_holder',
        'field_correspondence_address',
        'field_current_address',
        'field_date_of_birth',
        'field_email',
        'field_gender',
        'field_mobile',
        'field_name',
        'field_full_name',
        'field_nationality',
        'field_other',
        'field_other_gender',
        'field_programs',
        'field_recent_passport_photograph',
        'field_telephone',
      ];

      // Check if the field is present in the form
      if (isset($form[$field_name])) {
        // Get the node entity
        $node = $form_state->getFormObject()->getEntity();

        // Check field access
        if ($node->hasField($field_name)) {
          // Get the value of the field directly, without checking access
          $field_value = $node->get($field_name)->value;

          // Check if the value is 'edit'
          if ($field_value === 'edit') {
            // Function to recursively hide fields
            $hide_form_fields = function (&$element, $fields_to_hide) use (&$hide_form_fields) {
              foreach ($fields_to_hide as $hide_field) {
                if (isset($element[$hide_field])) {
                  $element[$hide_field]['#access'] = FALSE;
                }

                // Check for nested containers
                foreach ($element as $key => &$sub_element) {
                  if (is_array($sub_element) && isset($sub_element['#type']) && $sub_element['#type'] == 'container') {
                    $hide_form_fields($sub_element, $fields_to_hide);
                    \Drupal::service('twig')->invalidate();
                    \Drupal::service('cache.render')->invalidateAll();
                  }
                }
              }
            };

            // Hide the fields
            $hide_form_fields($form, $hide_fields);
          }
          else {
            //\Drupal::messenger()->addMessage("Field '$field_name' value is '$field_value'. No fields hidden.");
          }
        }
        else {
          \Drupal::messenger()->addMessage("Field '$field_name' is not present on the node.");
        }
      }
      else {
        \Drupal::messenger()->addMessage("Field '$field_name' is not present in the form.");
      }
    }
  }

}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*     //! Validation: Prevent Duplicate Submissions of Candidate Sections    */
/* -------------------------------------------------------------------------- */
/**
 * Used:
 *    In Form_alter Function
 * Workflow:
 * 1. Load the currently logged-in user entity.
 * 2. Check if the form contains a `#user_reference_field` value, which maps
 *    to a reference field on the user profile.
 * 3. If the corresponding field on the user already has a value (i.e. a node
 *    reference exists), prevent submission with a validation error.
 *
 * Purpose:
 * Ensures that users cannot submit the same application section (e.g., basic
 * information, work experience, etc.) multiple times.
 *
 * Note:
 * - This validation relies on `$form['#user_reference_field']` being set.
 * - Typically paired with forms mapped via `ncbs_form_alter()` logic.
 */
function ncbs_generic_reference_validation(array &$form, FormStateInterface $form_state) {
  $user = User::load(\Drupal::currentUser()->id());

  if ($user && isset($form['#user_reference_field'])) {
    $field_name = $form['#user_reference_field'];
    $existing = $user->get($field_name)->getValue();

    if (!empty($existing)) {
      $form_state->setErrorByName('', t('You have already submitted this section.'));
    }
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*    //! Generic Submit Handler: Link Submitted Node to User Profile Field   */
/* -------------------------------------------------------------------------- */
/**

 * Workflow:
 * 1. Get the node entity being submitted from the form state.
 * 2. Retrieve the user reference field name from the form's `#field_name`.
 * 3. Call a helper function (`ncbs_update_user_field`) to update the logged-in
 *    user's field with the submitted node's ID.
 *
 * Purpose:
 * Automatically updates the appropriate field on the user profile with a
 * reference to the newly submitted node (e.g., basic info, publications, etc.).
 *
 * Note:
 * - This handler assumes that `#field_name` is set on the form, which maps
 *   to a reference field on the user entity.
 * - Commonly used with custom candidate node forms in conjunction with
 *   `ncbs_form_alter()` and `ncbs_generic_reference_validation()`.
 */

function ncbs_handle_form_submission(array &$form, FormStateInterface $form_state)
{
  $node = $form_state->getFormObject()->getEntity();
  $field_name = $form['#field_name'];
  ncbs_update_user_field($field_name, $node->id());
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*        //!  Utility Function: Update User Field with Node Reference        */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Load the currently logged-in user entity.
 * 2. Retrieve the current value of the specified field on the user.
 * 3. If the field is empty:
 *    - Set it to reference the given node ID.
 *    - Save the updated user entity.
 *
 * Purpose:
 * Ensures a one-time, automatic linkage between a user and a submitted node,
 * such as basic information or academic qualifications.
 *
 * Notes:
 * - The function is intentionally non-destructive (won’t overwrite existing data).
 * - Typically called by `ncbs_handle_form_submission()` to finalize linkage
 *   after a node form is submitted.
 */
function ncbs_update_user_field($field_name, $node_id) {
  $user = User::load(\Drupal::currentUser()->id());

  if ($user) {
    $current_value = $user->get($field_name)->getValue();

    // Only update the field if it's empty.
    if (empty($current_value)) {
      $user->set($field_name, ['target_id' => $node_id]);
      $user->save();
    }
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*   //! Form Alter: Customize Submit Application Edit Form for "user" Role   */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Triggered when the `node_submit_application_edit_form` is being built.
 * 2. Checks if the current user has the `user` role.
 * 3. If yes:
 *    - Adds a custom validation handler (`ncbs_custom_validation`).
 *    - Changes the submit button label to "Submit Application".
 *    - Pre-fills the body field with a default message: "Your Application is not submitted".
 *    - Hides the "revision" checkbox from the form.
 *
 * Purpose:
 * Tailors the submit application edit experience for candidates by simplifying the UI,
 * enforcing validation, and providing helpful content guidance.
 *
 * Notes:
 * - Only applies to users with the "user" role.
 * - Validation and content hinting help ensure correct submission flow.
 */

function ncbs_form_node_submit_application_edit_form_alter(&$form, FormStateInterface $form_state, $form_id)
{
  if ($form_id == 'node_submit_application_edit_form') {
    $current_user = \Drupal::currentUser();

    // Add custom validation and modify form for 'user' role.
    if (in_array('user', $current_user->getRoles())) {
      $form['#validate'][] = 'ncbs_custom_validation';
      $form['actions']['submit']['#value'] = 'Submit Application';
      $form['body']['widget'][0]['value']['#default_value'] = 'Your Application is not submitted';
      $form['revision']['#access'] = FALSE;
    }
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */









/* -------------------------------------------------------------------------- */
/*   //! Utility Function: Get Research Area Terms Assigned to Current User   */
/* -------------------------------------------------------------------------- */
/**

 * Workflow:
 * 1. Load the currently logged-in user.
 * 2. Check if the user has any terms assigned in `field_user_research_programs`.
 *    - If not, return empty arrays and show a warning message.
 * 3. For each assigned parent term:
 *    - Collect the term's name and ID.
 *    - Load its child terms (depth = 1) from the `research_areas` vocabulary.
 *    - Store child term names indexed by their term IDs.
 * 4. Return an associative array with:
 *    - `parents`: list of parent term names,
 *    - `parent_ids`: list of parent term IDs,
 *    - `children`: associative array of child term IDs => names.
 *
 * Purpose:
 * Used to retrieve a user's associated research area terms, including
 * both directly assigned (parent) and their immediate children (depth = 1).
 * Enables filtering, UI display, or access control based on taxonomy structure.
 *
 * Notes:
 * - Assumes the vocabulary machine name is `research_areas`.
 * - Will show a warning if the user has no program assigned.
 */

function _ncbs_get_user_research_area_terms()
{
  $current_user = \Drupal::currentUser();
  $user = User::load($current_user->id());

  if (!$user || !$user->hasField('field_user_research_programs') || $user->get('field_user_research_programs')->isEmpty()) {
    \Drupal::messenger()->addWarning("Your profile does not have a research program assigned.");
    return ['parents' => [], 'parent_ids' => [], 'children' => []];
  }

  $parent_labels = [];
  $parent_ids = [];
  $child_terms = [];

  $user_term_ids = array_column($user->get('field_user_research_programs')->getValue(), 'target_id');

  foreach ($user_term_ids as $parent_tid) {
    $parent_term = Term::load($parent_tid);
    if ($parent_term) {
      $parent_labels[] = $parent_term->getName();
      $parent_ids[] = $parent_tid;
    }

    $children = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('research_areas', $parent_tid, 1, TRUE);

    foreach ($children as $term) {
      $child_terms[$term->id()] = $term->getName();
    }
  }

  return [
    'parents' => $parent_labels,
    'parent_ids' => $parent_ids,
    'children' => $child_terms,
  ];
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */



/* -------------------------------------------------------------------------- */
/*    //! Field Alter: Customize Display and Options Based on User's  Terms   */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. If the field `field_new_research_areas` is not present in the form, exit early.
 * 2. Load the current user's assigned parent research program terms and their child terms
 *    using `_ncbs_get_user_research_area_terms()`.
 * 3. Remove all default labels for the field and its widget elements for a cleaner UI.
 *
 * 4. If there are **no child terms available**:
 *    - Render a message explaining no "Research of Interest" is available.
 *    - Insert hidden elements to store parent term IDs (so they are saved).
 *    - Exit after modifying the form.
 *
 * 5. If child terms are available:
 *    - Display parent term names as a heading above the field.
 *    - Populate the field widget with only the filtered child term options.
 *    - If the form structure uses individual checkbox widgets, convert and populate them as needed.
 *
 * Purpose:
 * This function customizes the `field_new_research_areas` field on forms so that:
 * - Users only see research areas relevant to their assigned program.
 * - Parent terms are shown as a contextual heading.
 * - Only child terms are selectable via checkboxes.
 *
 * Notes:
 * - Assumes the vocabulary used is `research_areas`.
 * - Must be used in a form alter hook after the full form structure is built.
 * - Supports dynamic rendering and fallback when no child terms are found.
 */

function _ncbs_alter_new_research_areas_field(array &$form)
{
  if (!isset($form['field_new_research_areas'])) {
    return;
  }

  $data = _ncbs_get_user_research_area_terms();
  $parent_labels = $data['parents'];
  $child_terms = $data['children'];

  // ✅ Always remove default field label
  $form['field_new_research_areas']['#title'] = '';
  $form['field_new_research_areas']['#title_display'] = 'none';

  if (isset($form['field_new_research_areas']['widget'])) {
    $form['field_new_research_areas']['widget']['#title'] = '';
    $form['field_new_research_areas']['widget']['#title_display'] = 'none';
  }

  foreach ($form['field_new_research_areas']['widget'] as $key => &$element) {
    if (is_array($element) && isset($element['#title'])) {
      $element['#title'] = '';
      $element['#title_display'] = 'none';
    }
  }

  // ✅ If no children: show parent heading and the message
  if (empty($child_terms)) {
    // $form['field_new_research_areas'] = [
    //   '#type' => 'markup',
    //   '#markup' => '<div><h4>' . implode('<br>', array_map('htmlspecialchars', $parent_labels)) . '</h4>'
    //     . '<p class="text-muted">No Research of Interest Available for this Hiring.<br>Click save to continue the application.</p></div>',
    //   '#weight' => 0,
    // ];
    //added newly
    $form['field_new_research_areas'] = [
      '#type' => 'container',
      '#weight' => 0,
    ];

    // Insert hidden elements for parent terms so they're saved
    foreach ($data['parent_ids'] as $delta => $tid) {
      $form['field_new_research_areas'][$delta] = [
        '#type' => 'hidden',
        '#value' => $tid,
        '#parents' => ['field_new_research_areas', $delta, 'target_id'],
      ];
    }

    // Display the message
    $form['field_new_research_areas']['_info'] = [
      '#type' => 'markup',
      '#markup' => '<div><h4>' . implode('<br>', array_map('htmlspecialchars', $parent_labels)) . '</h4>'
        . '<p class="text-muted">No Research of Interest Available for this Hiring.<br>Click save to continue the application.</p></div>',
    ];
    //added newly end
    return;
  }

  // ✅ Show parent term(s) above child checkboxes
  $form['field_new_research_areas']['parent_label'] = [
    '#type' => 'markup',
    '#markup' => '<h4>' . implode('<br>', array_map('htmlspecialchars', $parent_labels)) . '</h4>',
    '#weight' => -10,
  ];

  // ✅ Populate widget with filtered child options
  if (isset($form['field_new_research_areas']['widget']['#options'])) {
    $form['field_new_research_areas']['widget']['#options'] = $child_terms;
  } else {
    foreach ($form['field_new_research_areas']['widget'] as $key => &$item) {
      if (is_numeric($key) && isset($item['target_id'])) {
        $item['target_id']['#type'] = 'checkboxes';
        $item['target_id']['#options'] = $child_terms;
        unset($item['target_id']['#default_value']); // Let Drupal handle defaults
      }
    }
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */




/* -------------------------------------------------------------------------- */
/*          //! Form Alter: Customize `field_new_research_areas` Create form  */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. This hook targets the `node_other_relevant_information_form` (node add form).
 * 2. It calls `_ncbs_alter_new_research_areas_field($form)` to:
 *    - Remove default field labels,
 *    - Show the parent research term(s) as a heading,
 *    - Conditionally render only relevant child terms as selectable checkboxes,
 *    - Show an alternate message if no child terms are found.
 *
 * Purpose:
 * Dynamically tailors the `field_new_research_areas` field to each user’s assigned research program,
 * ensuring relevant taxonomy terms are presented cleanly in the form.
 *
 * Note:
 * - This relies on the user's `field_user_research_programs` being set.
 * - Actual field logic lives in `_ncbs_alter_new_research_areas_field()`.
 */

function ncbs_form_node_other_relevant_information_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
{
  _ncbs_alter_new_research_areas_field($form);
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*     //! Form Alter: Customize `field_new_research_areas` on  Edit Form     */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. This hook targets the `node_other_relevant_information_edit_form` (node edit form).
 * 2. It invokes `_ncbs_alter_new_research_areas_field($form)` to:
 *    - Remove default labels from the `field_new_research_areas` field.
 *    - Display parent research program terms as section headers.
 *    - Show only child terms of the assigned program as selectable checkboxes.
 *    - Provide fallback UI if no child terms are found.
 *
 * Purpose:
 * Ensures that during editing, users only see and select research areas relevant
 * to their previously assigned research program(s).
 *
 * Note:
 * - This complements the node creation form alter hook.
 * - Core logic is handled by the `_ncbs_alter_new_research_areas_field()` utility.
 */

function ncbs_form_node_other_relevant_information_edit_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
{
  _ncbs_alter_new_research_areas_field($form);
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*         //! Utility Function: Restrict Work Experience Date Fields         */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Target the `field_work_experience` paragraph field on the form.
 * 2. Loop through each paragraph item (`widget`) and:
 *    - Skip non-numeric keys and elements without `subform`.
 *    - Add a `max` date attribute (today’s date) to both:
 *      - `field_from_date`
 *      - `field_to_date`
 * 3. Attach a custom validation handler (`ncbs_validate_no_future_dates_in_paragraph`)
 *    to ensure dates are not in the future on form submission.
 *
 * Purpose:
 * Prevent users from entering future dates in "From" and "To" fields
 * for each work experience entry within a multi-paragraph field.
 *
 * Notes:
 * - This function is called from `ncbs_form_alter()` for relevant forms.
 * - Assumes the paragraph structure uses standard widgets.
 */

function ncbs_alter_work_experience_date_fields(&$form, FormStateInterface $form_state) {
  $paragraph_field = 'field_work_experience';
  $to_date_field = 'field_to_date';
  $from_date_field = 'field_from_date';

  if (isset($form[$paragraph_field]['widget']) && is_array($form[$paragraph_field]['widget'])) {
    foreach ($form[$paragraph_field]['widget'] as $delta => &$widget) {
      // Skip special keys like '#type', '#title' etc.
      if (!is_numeric($delta) || !isset($widget['subform'])) {
        continue;
      }

      // Set max attribute for To Date
      if (isset($widget['subform'][$to_date_field]['widget'][0]['value'])) {
        $widget['subform'][$to_date_field]['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
      }

      // Set max attribute for From Date
      if (isset($widget['subform'][$from_date_field]['widget'][0]['value'])) {
        $widget['subform'][$from_date_field]['widget'][0]['value']['#attributes']['max'] = date('Y-m-d');
      }
    }

    // Attach validation
    $form['#validate'][] = 'ncbs_validate_no_future_dates_in_paragraph';
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*     //! Custom Validation: Enforce Valid Date Ranges in Work Experience    */
/* -------------------------------------------------------------------------- */
/*
 * Workflow:
 * 1. Target the `field_work_experience` paragraph values submitted in the form state.
 * 2. For each paragraph item:
 *    - Validate that `field_from_date` and `field_to_date` are not in the future.
 *    - Validate that `from_date` is not greater than `to_date`.
 *    - Log the dates being validated for debugging/auditing purposes.
 * 3. If any of the above rules are violated:
 *    - Add validation errors to the appropriate field with meaningful messages.
 *
 * Purpose:
 * Ensures that users cannot enter:
 * - Dates that lie in the future for past work experience.
 * - Invalid ranges where the "from" date is after the "to" date.
 *
 * Notes:
 * - This function is attached via `ncbs_alter_work_experience_date_fields()`.
 * - Logs are written to the `ncbs` logger for monitoring each input block.
 * - The field names are hardcoded but can be generalized if needed.
 */

function ncbs_validate_no_future_dates_in_paragraph(array &$form, FormStateInterface $form_state)
{
  $paragraph_field = 'field_work_experience';
  $to_date_field = 'field_to_date';
  $from_date_field = 'field_from_date';

  $values = $form_state->getValue($paragraph_field);
  $today = date('Y-m-d');

  foreach ($values as $delta => $item) {
    $from_date_value = '';
    $to_date_value = '';

    if (!empty($item['subform'][$from_date_field][0]['value'])) {
      $from_date_value = date('Y-m-d', strtotime($item['subform'][$from_date_field][0]['value']));
      \Drupal::logger('ncbs')->info("From Date [{$delta}]: $from_date_value");
      if ($from_date_value > $today) {
        $form_state->setErrorByName("{$paragraph_field}][{$delta}][subform][{$from_date_field}][0][value", t('The "From date" cannot be in the future.'));
      }
    }

    if (!empty($item['subform'][$to_date_field][0]['value'])) {
      $to_date_value = date('Y-m-d', strtotime($item['subform'][$to_date_field][0]['value']));
      \Drupal::logger('ncbs')->info("To Date [{$delta}]: $to_date_value");
      if ($to_date_value > $today) {
        $form_state->setErrorByName("{$paragraph_field}][{$delta}][subform][{$to_date_field}][0][value", t('The "To date" cannot be in the future.'));
      }
    }

    // NEW check: From should not be after To
    if (!empty($from_date_value) && !empty($to_date_value) && $from_date_value > $to_date_value) {
      \Drupal::logger('ncbs')->warning("❌ From date is after To date on item #$delta");
      $form_state->setErrorByName("{$paragraph_field}][{$delta}][subform][{$from_date_field}][0][value", t('The "From date" cannot be after "To date".'));
    }
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */