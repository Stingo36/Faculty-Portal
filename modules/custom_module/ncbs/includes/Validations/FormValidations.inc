<?php
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;

use Drupal\taxonomy\Entity\Term;


//!  Custom Validation: Checks User Profile Completion & Minimum Referees
/**
 * 
 * ✅ Purpose:
 * Ensures that required sections of the user profile are completed before the 
 * application is allowed to be submitted. Also enforces a minimum number of referees.
 *
 * 🔍 Validation Workflow:
 * -----------------------
 * • Loads the current user and the node entity associated with the form.
 * • Defines all required user reference fields
 * • Loops through each field and collects that are empty.
 *
 * 🧠 Referee Validation Logic:
 * ----------------------------
 * • Determines the required number of referees:
 *   – Defaults to `8`.
 *   – If a `field_user_research_programs` term is selected, overrides the count using `field_minimum_referee`.
 * • Checks the user’s referenced node in `field_user_list_of_referees_ref`.
 * • Counts the number of referee paragraphs in `field_list_of_referees_`.
 * • Compares the count against the required minimum.
 *   – If not enough referees are added, sets an error with the remaining count.
 *
 * ❌ Error Handling:
 * ------------------
 * • Adds validation errors for each missing field.
 * • Adds error if referee requirement is not satisfied.
 *
 * ✅ On Successful Validation:
 * ----------------------------
 * • Generates a secure random string and:
 *   – Assigns it to `field_session_key` on the form.
 *   – Saves it to the user’s `field_user_session_key`.
 * • Sets `field_date_received` to today.
 * • Updates the body field with a success message.
 * • Clears block caches to reflect the changes.
 * • Shows success messages through Drupal messenger.
 *
 * ❗ On Failure:
 * -------------
 * • Shows a generic "Submission failed" message via Drupal messenger.
 */

function ncbs_custom_validation(array &$form, FormStateInterface $form_state) {
    // Load the current user and node entity.
    $current_user = \Drupal::currentUser();
    $user = \Drupal\user\Entity\User::load($current_user->id());
    $node = $form_state->getFormObject()->getEntity();

    $empty_fields = []; // Array to hold empty field names.

    // Fields to be validated, with labels for error messages.
    $fields = [
        'field_user_academic_qualifi_ref' => 'Academic Qualification',
        'field_user_work_experience_ref'=> 'Work Experience',
        'field_user_other_rel_info_ref' => 'Research Areas',
        'field_user_list_of_referees_ref' => 'List of Referees',
        'field_user_research_proposal_ref' => 'Research Proposal',
        'field_user_update_pub_ref' => 'Update Publications',
        'field_user_basic_info_ref' => 'Basic Information',
    ];

    // Check if each field is empty and add to empty_fields array if necessary.
    foreach ($fields as $field_name => $field_label) {
        $field_value = $user->get($field_name)->getValue();
        if (empty($field_value)) {
            $empty_fields[] = $field_name;
        }
    }

    // Get minimum referee count dynamically from field_user_research_programs.
    $min_referees_required = 8; // Default fallback
    if (!$user->get('field_user_research_programs')->isEmpty()) {
        $term = Term::load($user->get('field_user_research_programs')->target_id);
        if ($term && !$term->get('field_minimum_referee')->isEmpty()) {
            $min_referees_required = (int) $term->get('field_minimum_referee')->value;
        }
    }

    // Validation check for 'field_user_list_of_referees_ref'.
    $referees_check_passed = false;
    if (!$user->get('field_user_list_of_referees_ref')->isEmpty()) {
        $list_of_referees_nid = $user->get('field_user_list_of_referees_ref')->target_id;
        $list_of_referees_node = Node::load($list_of_referees_nid);

        if ($list_of_referees_node && !$list_of_referees_node->get('field_list_of_referees_')->isEmpty()) {
            $referees_paragraphs = $list_of_referees_node->get('field_list_of_referees_')->referencedEntities();
            $count_referees = count($referees_paragraphs);

            if ($count_referees >= $min_referees_required) {
                $referees_check_passed = true;
            } else {
                $remaining_count = $min_referees_required - $count_referees;
                $form_state->setErrorByName('field_list_of_referees_', t('Minimum @min referees required. Count remaining: @count', [
                    '@min' => $min_referees_required,
                    '@count' => $remaining_count,
                ]));
            }
        } else {
            $form_state->setErrorByName('field_list_of_referees_', t('Minimum @min referees required. Count remaining: @count', [
                '@min' => $min_referees_required,
                '@count' => $min_referees_required,
            ]));
        }
    } else {
        $form_state->setErrorByName('field_user_list_of_referees_ref', t('Minimum @min referees required. Count remaining: @count', [
            '@min' => $min_referees_required,
            '@count' => $min_referees_required,
        ]));
    }

    // Set errors for fields that are empty.
    if (!empty($empty_fields)) {
        foreach ($empty_fields as $field_name) {
            $form_state->setErrorByName($field_name, t('@field_label has not been submitted.', ['@field_label' => $fields[$field_name]]));
        }
    } else {
        // Proceed with further actions if referees check passes and form is successfully submitted.
        if ($referees_check_passed && $form_state->isSubmitted()) {
            // Generate an 8-character random string for session key.
            $random_string = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(8 / strlen($x)))), 1, 8);

            // Save the generated string to 'field_session_key'.
            $form_state->setValue(['field_session_key', 0, 'value'], $random_string);

            // Save the session key to the user's 'field_user_session_key'.
            $user->set('field_user_session_key', $random_string)->save();

            // Set 'field_date_received' if the session key is set correctly.
            if ($form_state->getValue(['field_session_key', 0, 'value']) === $random_string) {
                $current_date = date('Y-m-d');
                $form_state->setValue(['field_date_received', 0, 'value'], $current_date);
            }

            // Update the body field with a custom success message.
            $form_state->setValue(['body', 0, 'value'], 'Application has been submitted successfully.');

            // Clear block caches to reflect changes immediately.
            \Drupal::service('cache_tags.invalidator')->invalidateTags(['block_view']);

            // Display success messages.
            // \Drupal::messenger()->addMessage(t('All fields successfully submitted.'));
            \Drupal::messenger()->addMessage('Application submitted successfully.');
        } else {
            // If validation fails, display a submission failure message.
            \Drupal::messenger()->addMessage('Submission failed.');
        }
    }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */