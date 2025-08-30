<?php


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Drupal\Component\Utility\Crypt;
use Drupal\taxonomy\Entity\Term;


use Drupal\Component\Utility\Random;


/* -------------------------------------------------------------------------- */
/*      //! Form Alter Hook: Attach Custom Submit Handler to Registration     */
/* -------------------------------------------------------------------------- */

function ncbs_form_user_register_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  if ($form_id == 'user_register_form' && \Drupal::currentUser()->isAnonymous()) {
    $form['actions']['submit']['#submit'][] = 'ncbs_user_register_form_submit';
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*      //! Custom Submit Handler: User Registration & Related Node Setup     */
/* -------------------------------------------------------------------------- */

/**
 * Workflow:
 * 1. Get the newly registered user entity from the form.
 * 2. Activate the user and assign them the "user" role.
 * 3. Retrieve the selected research program taxonomy term ID from user's field.
 * 4. Save the user early to ensure a user ID is available.
 * 5. Clear the temporary password field if it exists.
 * 6. Create a 'submit_application' node:
 *    - Set title to user’s full name.
 *    - Reference the user and their selected program.
 * 7. Save a reference to the submit_application node in the user profile.
 * 8. Create a 'seminar_details' node:
 *    - Set title using user’s full name.
 *    - Reference the user and the submit_application node.
 * 9. Save a reference to the seminar node in the user profile.
 * 10. Link back from the submit_application node to the seminar node.
 * 11. Save the user again to store all final references.
 */
function ncbs_user_register_form_submit(array &$form, FormStateInterface $form_state) {
  $user = $form_state->getFormObject()->getEntity();

  // Activate user and assign "user" role.
  $user->set('status', 1);
  $user->addRole('user');
  $user->notify = FALSE;

  // Get the program term ID directly from user field_user_research_programs.
  $program_tid = NULL;
  if (!$user->get('field_user_research_programs')->isEmpty()) {
    $term = $user->get('field_user_research_programs')->entity;
    if ($term instanceof \Drupal\taxonomy\Entity\Term) {
      $program_tid = $term->id();
    }
  }

  // Save the user so it has an ID before linking to nodes.
  $user->save();

  // Clear temporary password field.
  if ($user->hasField('field_temp_password')) {
    $user->set('field_temp_password', NULL);
    $user->save();
  }

  // Create the submit_application node.
  $submit_node = \Drupal\node\Entity\Node::create([
    'type' => 'submit_application',
    'title' => $user->get('field_user_full_name')->value,
    'field_user_reference' => ['target_id' => $user->id()],
    'uid' => $user->id(),
  ]);
  $submit_node->setOwner($user);

  // Set program taxonomy term on field_program_name.
  if ($program_tid && $submit_node->hasField('field_program_name')) {
    $submit_node->set('field_program_name', ['target_id' => $program_tid]);
  }

  $submit_node->save();

  // Save reference to submit_application node on user profile.
  if ($user->hasField('field_user_submit_app_ref')) {
    $user->set('field_user_submit_app_ref', ['target_id' => $submit_node->id()]);
  }

  // Create the seminar_details node.
  $seminar_node = \Drupal\node\Entity\Node::create([
    'type' => 'seminar_details',
    'title' => 'Seminar Details - ' . $user->get('field_user_full_name')->value,
    'uid' => $user->id(),
  ]);
  $seminar_node->setOwner($user);

  // Set user reference on seminar node.
  if ($seminar_node->hasField('field_user_reference')) {
    $seminar_node->set('field_user_reference', ['target_id' => $user->id()]);
  }

  // Link seminar_details to submit_application.
  if ($seminar_node->hasField('field_candidate_ref')) {
    $seminar_node->set('field_candidate_ref', ['target_id' => $submit_node->id()]);
  }

  $seminar_node->save();

  // Save reference to seminar_details node in user profile.
  if ($user->hasField('field_user_seminar_details_ref')) {
    $user->set('field_user_seminar_details_ref', ['target_id' => $seminar_node->id()]);
  }

  // Link back from submit_application to seminar_details.
  if ($submit_node->hasField('field_seminar_details_ref')) {
    $submit_node->set('field_seminar_details_ref', ['target_id' => $seminar_node->id()]);
    $submit_node->save();
  }

  // ✅ Log message before final save
  \Drupal::logger('user_registration')->notice('User %name (UID: %uid) registered successfully. Application Node ID: %app_nid, Seminar Node ID: %seminar_nid.', [
    '%name' => $user->getDisplayName(),
    '%uid' => $user->id(),
    '%app_nid' => $submit_node->id(),
    '%seminar_nid' => $seminar_node->id(),
  ]);

  // Final save of user with all references updated.
  $user->save();
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */


/* -------------------------------------------------------------------------- */
/*    //!  User Insert Hook: Auto-Gen Password for Non-Admin Registrations    */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Triggered after a new user is created (hook_user_insert).
 * 2. Check if the current user creating the account is NOT an admin or administrator.
 * 3. If not an admin:
 *    - Generate a secure random base64 password (8 bytes).
 *    - Set this password as the new user's password.
 *    - If the user entity has the field `field_temp_password`, store the generated password there.
 * 4. Save the updated user entity.
 *
 * Note: This ensures non-admin-created accounts get an auto-generated temporary password.
 */
function ncbs_user_insert(\Drupal\user\UserInterface $user) {

  // Check if current user is admin/administrator.
  $current_user = \Drupal::currentUser();
  $user_roles = $current_user->getRoles();

  if (!in_array('admin', $user_roles) && !in_array('administrator', $user_roles)) {
    // Generate a random base64 password (8 bytes).
    // $random_password = Crypt::randomBytesBase64(8);
    $random_password = generate_alphanumeric_password(8);

    // Set the generated password for the user.
    $user->setPassword($random_password);

    // Optionally store the temporary password in a custom field if it exists.
    if ($user->hasField('field_temp_password')) {
      $user->set('field_temp_password', $random_password);
    }

    // Save the user entity with the updated password and field.
    $user->save();
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */



/**
 * Generate a random alphanumeric password with at least 2 digits.
 */
function generate_alphanumeric_password($length = 8) {
  if ($length < 3) {
    throw new \InvalidArgumentException('Password length must be at least 3 to include 2 digits.');
  }

  $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  $digits = '0123456789';
  $random = new Random();

  // Step 1: Generate (length - 2) letters
  $base = $random->word($length - 2, $letters);

  // Step 2: Add 2 random digits
  $digit1 = $digits[random_int(0, 9)];
  $digit2 = $digits[random_int(0, 9)];

  // Step 3: Insert digits at random positions in the base string
  $positions = array_rand(range(0, strlen($base)), 2);
  sort($positions);

  $part1 = substr($base, 0, $positions[0]);
  $part2 = substr($base, $positions[0], $positions[1] - $positions[0]);
  $part3 = substr($base, $positions[1]);

  // Reconstruct with digits inserted
  $password = $part1 . $digit1 . $part2 . $digit2 . $part3;

  return $password;
}




/* -------------------------------------------------------------------------- */
/*       //!  * Form Alter: Restrict Fields for "user" Role on Edit Form      */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Triggered when the user edit form (`user_form`) is loaded.
 * 2. Check if the current user has the "user" role.
 * 3. If yes:
 *    - Hide selected profile fields like gender, date of birth, research programs, etc.
 *    - Also hide the email field inside the "Account" section.
 *    - Add a custom submit handler (`ncbs_user_form_submit_handler`) for redirection.
 *
 * Purpose: Prevent regular users from editing sensitive or restricted profile fields.
 */
function ncbs_form_user_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
  $user = \Drupal::currentUser();

  // Apply changes only if it's the user profile form and user has the "user" role.
  if ($form_id === 'user_form' && $user->hasRole('user')) {
    // Hide specific fields.
    $form['field_gender']['#access'] = FALSE;
    $form['field_centres']['#access'] = FALSE;
    $form['field_date_of_birth']['#access'] = FALSE;
    $form['field_valid_indian_passport']['#access'] = FALSE;
    $form['field_user_research_programs']['#access'] = FALSE;
    $form['field_prefix']['#access'] = FALSE;

    // Hide email field under "Account" section.
    $form['account']['mail']['#access'] = FALSE;

    // Attach a custom submit handler (optional redirection or logic).
    $form['actions']['submit']['#submit'][] = 'ncbs_user_form_submit_handler';
  }
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*      //! Custom Submit: Redirect After User Profile Update (Edit Form)     */
/* -------------------------------------------------------------------------- */
/**
 * Workflow:
 * 1. Triggered when the user edit form is submitted (as a custom submit handler).
 * 2. Get the currently logged-in user.
 * 3. Build a redirect URL to that user's own profile page (`user/{uid}`).
 * 4. Set this URL as the redirect destination after form submission.
 *
 * Purpose: After editing their profile, users are redirected back to their profile view page.
 */
function ncbs_user_form_submit_handler(array &$form, FormStateInterface $form_state) {
  $current_user = \Drupal::currentUser();

  // Redirect to internal user profile route.
  $redirect_url = Url::fromRoute('entity.user.canonical', [
    'user' => $current_user->id(),
  ]);

  // Set redirect.
  $form_state->setRedirectUrl($redirect_url);
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */





























