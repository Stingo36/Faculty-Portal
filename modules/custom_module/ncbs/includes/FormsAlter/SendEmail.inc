<?php

use Drupal\Component\Utility\Html;

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
use Drupal\Core\Database\Database;
use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;


use Drupal\file\Entity\File;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Render\Markup;



/* -------------------------------------------------------------------------- */
/*     //!  Form Alter Hook: Customize Send Email Form Based on Email Type    */
/* -------------------------------------------------------------------------- */

/**
 * Workflow:
 * 1. Triggered for `node_send_email_form` when the form is built.
 * 2. Reads query parameters `nid` (node ID) and `type` (email target).
 * 3. If `type` is provided:
 *    - Sets it as the default and disables editing on the `field_email_type` field.
 *
 * 4. Cleans up form UI:
 *    - Hides vertical tabs like "Status", "Authoring info", "Published", and revision metadata.
 *    - Removes Drupal's default node submit handler to avoid duplicate node creation.
 *    - Replaces it with a custom handler: `ncbs_send_email_form_submit`.
 *    - Renames the submit button to "Send Email".
 *
 * 5. Dynamically controls which fields are visible based on the email `type`:
 *    - Defines a map of visible fields for each recipient type (Dean, Director, Faculty, etc.).
 *    - Shows only the relevant fields along with `field_email_subject`.
 *    - Hides all other fields prefixed with `field_email_`.
 *
 * Purpose:
 * Tailors the send email node form to different roles/recipients by showing only the relevant
 * fields and ensuring a clean and role-specific UI. It also enforces a safe submission workflow.
 *
 */

function ncbs_form_node_send_email_form_alter(&$form, FormStateInterface $form_state, $form_id)
{
  if ($form_id === 'node_send_email_form') {
    $request = \Drupal::requestStack()->getCurrentRequest();
    $nid = $request->query->get('nid');
    $type = $request->query->get('type');

    // Set default value for email type field and disable it.
    if (!empty($type) && isset($form['field_email_type']['widget'])) {
      $form['field_email_type']['widget']['#default_value'] = [$type];
      $form['field_email_type']['widget']['#disabled'] = TRUE;
    }

    // Hide the vertical tab "Status" group (includes author and last saved).
    unset($form['meta']);


    if (isset($form['author'])) {
      $form['author']['#access'] = FALSE;
    }

    if (isset($form['options'])) {
      $form['options']['#access'] = FALSE;
    }

    // Hide "Published" checkbox.
    if (isset($form['status'])) {
      unset($form['status']);
    }
    // Hide revision info.
    unset($form['revision_information']);
    unset($form['revision_log']);

    // Remove default submit handlers to avoid double node creation.
    unset($form['actions']['submit']['#submit']);

    // Attach custom submit handler.
    $form['actions']['submit']['#submit'][] = 'ncbs_send_email_form_submit';

    // Rename submit button
    $form['actions']['submit']['#value'] = t('Send Email');
  }



  //SECTION -  - //!the below code is for show/hide fields in Send email

  //// Base field to always show
  // $always_visible_fields = ['field_email_subject'];

  // // Map visible fields for each type
  // $type_field_map = [
  //   'Dean' => [
  //     'field_email_body_to_dean',
  //     'field_email_to_dean',
  //   ],
  //   'Director' => [
  //     'field_email_body_director',
  //     'field_email_to_director',
  //   ],
  //   'Referee/Expert' => [
  //     'field_email_body_referee',
  //   ],
  //   'Faculty' => [
  //     'field_email_body_to_faculty',
  //     'field_email_to_faculties',
  //   ],
  //   'Candidate' => [
  //     'field_email_body_to_candidate',
  //   ],
  // ];

  // if (isset($type_field_map[$type])) {
  //   // Merge type-specific and always-visible fields
  //   $allowed_fields = array_merge($type_field_map[$type], $always_visible_fields);

  //   // Hide everything else that starts with "field_email_"
  //   foreach ($form as $key => &$element) {
  //     if (strpos($key, 'field_email_') === 0 && !in_array($key, $allowed_fields)) {
  //       if (is_array($element)) {
  //         $element['#access'] = FALSE;
  //         $element['#required'] = FALSE;
  //       }
  //     }
  //   }
  // }

  //! Ends here




    //REVIEW - Adding Candidate Templates - change 1
    // 1) Always-visible fields
    $always_visible_fields = ['field_email_subject'];

    // 2) Map visible fields by type
    $type_field_map = [
      'Dean' => [
        'field_email_body_to_dean',
        'field_email_to_dean',
      ],
      'Director' => [
        'field_email_body_director',
        'field_email_to_director',
      ],
      'Referee/Expert' => [
        'field_email_body_referee',
      ],
      'Faculty' => [
        'field_email_body_to_faculty',
        'field_email_to_faculties',
      ],
      'Candidate' => [
        'field_email_body_to_candidate',
        'field_candidate_templates',            //  Template dropdown
        'field_candidate_prescreen_temp',       //  pre screen template
        'field_candidate_closing_template',     //  closing dropdown
        'field_candidate_closing_after_fu',     //  Candidate Closing After Full Faculty meeting
        'field_candidate_closing_after_vi',     //  Candidate Closing After in-person Visit	
        'field_candidate_closing_after_',       //  Candidate Closing After Prescreen Interview	
        'field_candidate_closing_prelimin',     //  Candidate Closing Preliminary
      ],
    ];

    if (isset($type_field_map[$type])) {
      // Merge type-specific and always-visible fields
      $allowed_fields = array_merge($type_field_map[$type], $always_visible_fields);

      // Build a list of managed prefixes
      $managed_prefixes = ['field_email_', 'field_candidate_'];

      foreach ($form as $key => &$element) {
        // Check if key starts with any managed prefix
        $is_managed = false;
        foreach ($managed_prefixes as $prefix) {
          if (strpos($key, $prefix) === 0) {
            $is_managed = true;
            break;
          }
        }

        // Hide if managed but not allowed
        if ($is_managed && !in_array($key, $allowed_fields, true) && is_array($element)) {
          $element['#access'] = FALSE;
          $element['#required'] = FALSE;
        }
      }
    }
    //REVIEW -  - Ends here



}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */










/* -------------------------------------------------------------------------- */
/*   //! Submit Handler: Sends Emails Based on Recipient Type and Logs Them   */
/* -------------------------------------------------------------------------- */
/**
 * Purpose:
 * This function handles sending customized emails to different roles 
 * (Referee/Expert, Candidate, Dean, Director, Faculty) based on selected type.
 * It also logs the email as a node (`send_email`) and optionally updates related data.
 *
 * ------------------------
 * 📌 Global Workflow Steps:
 * ------------------------
 * 1. Read required query parameters: `type`, `nid` (candidate), `email`, `paraId`.
 * 2. Load candidate node using `nid`. If invalid, exit early.
 * 3. Load candidate user reference and extract tokens: full name, email, prefix, program.
 * 4. Apply token replacements using custom token replacer (`[[FULLNAME]]`, `[[PROGRAM]]`, etc.).
 *
 * ------------------------------------------
 * 📨 Referee/Expert Email Handling (if type):
 * ------------------------------------------
 * - Ensures the `email` param is passed.
 * - Finds the matching referee paragraph by matching email.
 * - Loads optional CV PDF from `field_upload_updated_cv`.
 * - Applies `[[REFEREENAME]]`, `[[REFEREEPREFIX]]` tokens.
 * - Generates a feedback link (URL) and sets it in `field_ref_link`.
 * - Sends email and attaches PDF (if present).
 * - Updates `field_email_status` in paragraph if email was sent.
 * - Creates a `send_email` node to log the email.
 *
 * -------------------------------------
 * 📨 Candidate Email Handling (if type):
 * -------------------------------------
 * - Validates candidate node and user reference.
 * - Loads and decodes `field_email_body_to_candidate`.
 * - Sends email using user’s preferred language.
 * - Logs result using `custom_email` logger.
 * - Creates a `send_email` node with subject, body, and status.
 *
 * ------------------------------------------------
 * 📨 Other Types: Dean, Director, Faculty (default):
 * ------------------------------------------------
 * - Maps `field_email_to_*` and `field_email_body_to_*` fields.
 * - Iterates over selected users from form field.
 * - Replaces tokens (`[[USERNAME]]`, `[[USERPREFIX]]`) in body.
 * - Optionally loads candidate's CV as attachment.
 * - Sends email with subject and body.
 * - Creates `send_email` log nodes for each recipient.
 *
 * ---------------------------------------
 * ✅ Additional Role-Specific Logic:
 * ---------------------------------------
 * ➤ Faculty:
 *   - Appends candidate to faculty's `field_review_candidates` if not present.
 *   - If candidate node has `field_faculty_member_comment_ref`, updates matching comment node's flag.
 *
 * ➤ Dean:
 *   - Appends candidate to dean’s `field_review_candidates`.
 *   - If candidate node has `field_dean_comment_reference`, sets `field_check_pending_flag` = TRUE.
 *
 * ---------------------------------------
 * 🔗 Final Linking and Redirection:
 * ---------------------------------------
 * - Merges newly created email log nodes into `field_send_email_reference` on the candidate node.
 * - Redirects user to `/send-emails/{candidate_nid}` after processing.
 */

function ncbs_send_email_form_submit(array &$form, FormStateInterface $form_state)
{
  $request = \Drupal::request();
  $type = $request->query->get('type');
  $candidate_nid = $request->query->get('nid');
  $email_from_url = $request->query->get('email');
  $para_id = $request->query->get('paraId');

  if (empty($type) || empty($candidate_nid)) {
    \Drupal::messenger()->addError('Missing required URL parameters.');
    return;
  }

  $candidate_node = Node::load($candidate_nid);
  if (!$candidate_node) {
    \Drupal::messenger()->addError('Candidate node not found.');
    return;
  }

  $mail_manager = \Drupal::service('plugin.manager.mail');
  $created_node_ids = [];

  $subject = $form_state->getValue(['field_email_subject', 0, 'value']) ?? 'Notification: Email Template Created';

  // Token setup
  $full_name = $prefix  = $program = $user_email = '';
  if ($candidate_node->hasField('field_user_reference') && !$candidate_node->get('field_user_reference')->isEmpty()) {
    $user_ref = $candidate_node->get('field_user_reference')->entity;
    if ($user_ref instanceof User) {
      $user_email = $user_ref->getEmail();
      $prefix = $user_ref->get('field_prefix_user')->value ?? '';
      $full_name = $user_ref->get('field_user_full_name')->value ?? '';
      // $program = $user_ref->get('field_user_research_programs')->value ?? '';
      $term = $user_ref->field_user_research_programs->entity;

      if ($term) {
        // Set $program to the human‑readable label.
        $program = $term->getName();
      } else {
        // No term referenced? Use an empty string (or a default).
        $program = '';
      }
    }
  }

  $base_replacements = [
    '[[PREFIX]]' => $prefix,
    '[[FULLNAME]]' => $full_name,
    '[[PROGRAM]]' => $program,
    '[[USEREMAIL]]' => $user_email,
  ];

  $apply_replacements = function ($value, array $custom_tokens = []) use ($base_replacements) {
    return strtr($value, $custom_tokens + $base_replacements);
  };

  // ---------- Referee/Expert Email ----------
  if ($type === 'Referee/Expert') {
    if (empty($email_from_url)) {
      \Drupal::messenger()->addWarning('Email parameter is missing for Referee/Expert.');
      return;
    }

    $matched_paragraph = NULL;
    $raw_email_body = $form_state->getValue('field_email_body_referee');
    $email_body = $raw_email_body[0]['value'] ?? '';

    // Match paragraph by email
    if ($candidate_node->hasField('field_list_of_referees_ref')) {
      foreach ($candidate_node->get('field_list_of_referees_ref')->referencedEntities() as $ref_node) {
        if ($ref_node->hasField('field_list_of_referees_')) {
          foreach ($ref_node->get('field_list_of_referees_')->referencedEntities() as $paragraph) {
            if ($paragraph->hasField('field_email') && $paragraph->get('field_email')->value === $email_from_url) {
              $matched_paragraph = $paragraph;
              break 2;
            }
          }
        }
      }
    }

    $pdf_attachment = NULL;

    if ($candidate_node->hasField('field_basic_information_referenc') && !$candidate_node->get('field_basic_information_referenc')->isEmpty()) {
      $ref_nid = $candidate_node->get('field_basic_information_referenc')->target_id;
      $ref_node = Node::load($ref_nid);

      if (
        $ref_node &&
        $ref_node->hasField('field_upload_updated_cv') &&
        !$ref_node->get('field_upload_updated_cv')->isEmpty()
      ) {
        $file = File::load($ref_node->get('field_upload_updated_cv')->target_id);
        if ($file) {
          $pdf_attachment = [
            'filepath' => $file->getFileUri(),
            'filename' => $file->getFilename(),
            'filemime' => $file->getMimeType(),
          ];
        }
      }
    }





    // Referee-specific token replacements
    $ref_tokens = [];
    if ($matched_paragraph instanceof Paragraph) {
      $ref_tokens = [
        '[[REFEREEPREFIX]]' => $matched_paragraph->get('field_prefix')->value ?? '',
        '[[REFEREENAME]]' => $matched_paragraph->get('field_name')->value ?? '',
      ];
    }

    $email_body = $apply_replacements($email_body, $ref_tokens);

    $session = \Drupal::service('session')->getId();
    $link = Url::fromUri('internal:/node/add/referee_feedback_form', [
      'query' => [
        'session' => $session,
        'email' => $email_from_url,
        'id' => $candidate_nid,
      ],
    ])->setAbsolute()->toString();

    if ($matched_paragraph && $matched_paragraph->hasField('field_ref_link')) {
      $matched_paragraph->set('field_ref_link', [
        'uri' => $link,
        'title' => 'Feedback Link',
      ]);
      $matched_paragraph->save();
    }

    $email_body_replaced = str_replace('[[LINK]]', '<a href="' . $link . '">' . $link . '</a>', $email_body);
    $params = [
      'subject' => $subject,
      'message' => $email_body_replaced,
      'attachments' => [],
    ];

    // Attach PDF if available
    if ($pdf_attachment) {
      $params['attachments'][] = [
        'filecontent' => file_get_contents($pdf_attachment['filepath']),
        'filename' => $pdf_attachment['filename'],
        'filemime' => $pdf_attachment['filemime'],
      ];
    }

    $result = $mail_manager->mail('ncbs', 'role_based_email', $email_from_url, 'en', $params, NULL, TRUE);

    $email_status = ($result['result'] === TRUE) ? 'Sent' : 'Failed';

    if ($email_status === 'Sent') {
      \Drupal::messenger()->addStatus("Email sent to {$email_from_url}.");
      if ($matched_paragraph->hasField('field_email_status')) {
        $matched_paragraph->set('field_email_status', 'Sent');
        $matched_paragraph->save();
      }
    } else {
      \Drupal::messenger()->addWarning("Failed to send email to {$email_from_url}.");
    }

    $new_node = Node::create([
      'type' => 'send_email',
      'title' => 'Email Template - Referee/Expert',
      'field_email_type' => $type,
      'field_candidate_ref' => [['target_id' => $candidate_nid]],
      'field_user_email_id' => $email_from_url,
      'field_email_sent_date' => ($email_status === 'Sent') ? date('Y-m-d\TH:i:s') : NULL,
      'field_email_status' => $email_status,
      'field_email_body_referee' => $email_body_replaced,
      'field_email_subject' => $subject,
      'status' => 1,
    ]);
    $new_node->save();
    $created_node_ids[] = ['target_id' => $new_node->id()];
  }

  //jul 10
  elseif ($type === 'Candidate') {
    $candidate_nid = \Drupal::request()->query->get('nid');

    if (empty($candidate_nid) || !is_numeric($candidate_nid)) {
      \Drupal::messenger()->addWarning('Candidate ID is missing or invalid.');
      \Drupal::logger('custom_email')->warning('Missing or invalid Candidate NID in URL.');
      return;
    }

    $candidate_node = \Drupal\node\Entity\Node::load($candidate_nid);
    if (!$candidate_node) {
      \Drupal::messenger()->addWarning('Candidate node not found.');
      \Drupal::logger('custom_email')->warning("Candidate node not found for NID: {$candidate_nid}");
      return;
    }

    if ($candidate_node->hasField('field_user_reference') && !$candidate_node->get('field_user_reference')->isEmpty()) {
      $user = $candidate_node->get('field_user_reference')->entity;
      if ($user) {
        $user_email = $user->getEmail();
        $username = $user->getAccountName();
        $langcode = $user->getPreferredLangcode();
        \Drupal::logger('custom_email')->notice("Email for Candidate (User ID: {$user->id()}): {$user_email}");
      } else {
        \Drupal::messenger()->addWarning('User entity could not be loaded.');
        \Drupal::logger('custom_email')->warning("User entity could not be loaded from Candidate NID: {$candidate_nid}");
        return;
      }
    } else {
      \Drupal::messenger()->addWarning('User reference is missing on the candidate node.');
      \Drupal::logger('custom_email')->warning("Missing field_user_reference on Candidate NID: {$candidate_nid}");
      return;
    }

    // Get email body from form and decode
    // $raw_email_body = $form_state->getValue('field_email_body_to_candidate');


// Read the exact stored key from the List (text) field.
$selected_key = (string) ($form_state->getValue(['field_candidate_templates', 0, 'value']) ?? '');

// Exact match — no lowercasing, no regex.
$is_prescreen = ($selected_key === 'Prescreen Interview');

// Pick the source field based on selection.
$raw_email_body = $is_prescreen
  ? ($form_state->getValue('field_candidate_prescreen_temp') ?: [])
  : ($form_state->getValue('field_email_body_to_candidate') ?: []);











    $email_body = $raw_email_body[0]['value'] ?? '';
    $email_body_replaced = str_replace('[[LINK]]', '', $email_body);
    $decoded_email_body = html_entity_decode($email_body_replaced);

    // Debug: Log email content before sending
    \Drupal::logger('custom_email')->debug('<pre>Email body (raw): ' . print_r($email_body_replaced, TRUE) . '</pre>');
    \Drupal::logger('custom_email')->debug('<pre>Email body (decoded): ' . print_r($decoded_email_body, TRUE) . '</pre>');

    $params = [
      'subject' => $subject,
      'message' => $decoded_email_body,
    ];

    $result = $mail_manager->mail('ncbs', 'role_based_email', $user_email, 'en', $params, NULL, TRUE);

    // Debug: Log result from mail manager
    \Drupal::logger('custom_email')->notice('Mail send result: <pre>' . print_r($result, TRUE) . '</pre>');

    $email_status = ($result['result'] === TRUE) ? 'Sent' : 'Failed';

    \Drupal::logger('custom_email')->notice("Email status for {$user_email}: {$email_status}");

    if ($email_status === 'Sent') {
      \Drupal::messenger()->addStatus("Email sent to {$user_email}.");
    } else {
      \Drupal::messenger()->addWarning("Failed to send email to {$user_email}.");
    }

    // Create 'send_email' node to log the email
    $new_node = Node::create([
      'type' => 'send_email',
      'title' => "Email Template - {$username}",
      'field_email_type' => $type,
      'field_candidate_ref' => [['target_id' => $candidate_nid]],
      'field_user_email_id' => $user_email,
      'field_email_sent_date' => ($email_status === 'Sent') ? date('Y-m-d\TH:i:s') : NULL,
      'field_email_status' => $email_status,
      'field_email_body_to_candidate' => $decoded_email_body,
      'field_email_subject' => $subject,
      'status' => 1,
    ]);
    $new_node->save();

    \Drupal::logger('custom_email')->notice("Created send_email node ID: {$new_node->id()} for Candidate NID: {$candidate_nid}");

    $created_node_ids[] = ['target_id' => $new_node->id()];
  }
  else {
    // Get selected email type
    $email_type = $form_state->getValue('field_email_type');
    $email_type = is_array($email_type) ? ($email_type[0]['value'] ?? '') : (string) $email_type;

    // Map email type to related user and body fields
    $email_type_map = [
      'Dean' => [
        'user_field' => 'field_email_to_dean',
        'body_field' => 'field_email_body_to_dean',
      ],
      'Director' => [
        'user_field' => 'field_email_to_director',
        'body_field' => 'field_email_body_director',
      ],
      'Faculty' => [
        'user_field' => 'field_email_to_faculties',
        'body_field' => 'field_email_body_to_faculty',
      ],
    ];

    // Exit if invalid email type
    if (!isset($email_type_map[$email_type])) {
      \Drupal::messenger()->addError('Invalid email type selected.');
      return;
    }

    // Load mapping values
    $user_field_key = $email_type_map[$email_type]['user_field'];
    $body_field_key = $email_type_map[$email_type]['body_field'];
    $user_refs = $form_state->getValue($user_field_key);
    $body_array = $form_state->getValue($body_field_key);
    $base_email_body = $body_array[0]['value'] ?? '';

    // Exit if no users selected
    if (!is_array($user_refs) || empty($user_refs)) {
      \Drupal::messenger()->addError("No users provided for {$email_type}.");
      return;
    }

    $mail_manager = \Drupal::service('plugin.manager.mail');
    $created_node_ids = [];

    foreach ($user_refs as $ref) {
      if (!isset($ref['target_id'])) {
        continue;
      }

      $user = User::load($ref['target_id']);
      if (!$user) {
        continue;
      }

      // Token replacements
      $username = $user->getAccountName();
      $user_email = $user->getEmail();
      $langcode = $user->getPreferredLangcode();
      $userprefix = $user->get('field_prefix_user')->value ?? '';
      $custom_tokens = [
        '[[USERNAME]]' => $username,
        '[[USERPREFIX]]' => $userprefix,
      ];

      // Replace tokens in email body
      $email_body = $apply_replacements($base_email_body, $custom_tokens);

      $pdf_attachment = NULL;

      if (
        $candidate_node->hasField('field_basic_information_referenc') &&
        !$candidate_node->get('field_basic_information_referenc')->isEmpty()
      ) {
        $ref_nid = $candidate_node->get('field_basic_information_referenc')->target_id;
        $ref_node = Node::load($ref_nid);

        if (
          $ref_node &&
          $ref_node->hasField('field_upload_updated_cv') &&
          !$ref_node->get('field_upload_updated_cv')->isEmpty()
        ) {
          $file = File::load($ref_node->get('field_upload_updated_cv')->target_id);
          if ($file) {
            $pdf_attachment = [
              'filepath' => $file->getFileUri(),
              'filename' => $file->getFilename(),
              'filemime' => $file->getMimeType(),
            ];
          }
        }
      }



      // Send email
      $params = [
        'subject' => $subject,
        'message' => $email_body,
        'attachments' => [],
      ];


      // Attach PDF if available
      if ($pdf_attachment) {
        $params['attachments'][] = [
          'filecontent' => file_get_contents($pdf_attachment['filepath']),
          'filename' => $pdf_attachment['filename'],
          'filemime' => $pdf_attachment['filemime'],
        ];
      }


      $result = $mail_manager->mail('ncbs', 'role_based_email_others', $user_email, $langcode, $params, NULL, TRUE);
      $email_status = ($result['result'] === TRUE) ? 'Sent' : 'Failed';

      // Create log node for email
      $email_node = Node::create([
        'type' => 'send_email',
        'title' => "Email Template - {$username}",
        'field_email_type' => $email_type,
        $user_field_key => [['target_id' => $user->id()]],
        'field_candidate_ref' => [['target_id' => $candidate_nid]],
        'field_user_email_id' => $user_email,
        'field_email_sent_date' => ($email_status === 'Sent') ? date('Y-m-d\TH:i:s') : NULL,
        'field_email_status' => $email_status,
        $body_field_key => [['value' => $email_body]],
        'field_email_subject' => $subject,
        'status' => 1,
      ]);
      $email_node->save();
      $created_node_ids[] = ['target_id' => $email_node->id()];

      /* ---------------------------- //! Review logic only faculty---------------------------- */
      // Faculty-specific logic: Add candidate to faculty's review list if not already present
      if ($email_type === 'Faculty') {
        $candidate_node = Node::load($candidate_nid);

        // Append candidate to faculty's review list
        if ($user->hasField('field_review_candidates')) {
          $existing_refs = $user->get('field_review_candidates')->getValue();
          $already_exists = array_filter($existing_refs, fn($item) => (int) $item['target_id'] === (int) $candidate_nid);

          if (empty($already_exists)) {
            $existing_refs[] = ['target_id' => $candidate_nid];
            $user->set('field_review_candidates', $existing_refs);
            $user->save();
          }
        }

        // Update faculty comment node if present
        if ($candidate_node && $candidate_node->hasField('field_faculty_member_comment_ref')) {
          $comment_refs = $candidate_node->get('field_faculty_member_comment_ref')->referencedEntities();
          foreach ($comment_refs as $comment_node) {
            if ($comment_node instanceof Node && $comment_node->getOwnerId() === $user->id()) {
              if ($comment_node->hasField('field_check_pending_flag')) {
                $comment_node->set('field_check_pending_flag', TRUE);
                $comment_node->save();
              }
            }
          }
        }
      }
      if ($email_type === 'Dean') {
        $candidate_node = Node::load($candidate_nid);

        // Append candidate to faculty's review list
        if ($user->hasField('field_review_candidates')) {
          $existing_refs = $user->get('field_review_candidates')->getValue();
          $already_exists = array_filter($existing_refs, fn($item) => (int) $item['target_id'] === (int) $candidate_nid);

          if (empty($already_exists)) {
            $existing_refs[] = ['target_id' => $candidate_nid];
            $user->set('field_review_candidates', $existing_refs);
            $user->save();
          }
        }

        // Update faculty comment node if present
        if ($candidate_node && $candidate_node->hasField('field_dean_comment_reference')) {
          $comment_refs = $candidate_node->get('field_dean_comment_reference')->referencedEntities();
          foreach ($comment_refs as $comment_node) {
            if ($comment_node instanceof Node && $comment_node->getOwnerId() === $user->id()) {
              if ($comment_node->hasField('field_check_pending_flag')) {
                $comment_node->set('field_check_pending_flag', TRUE);
                $comment_node->save();
              }
            }
          }
        }
      }

      /* ------------------------------ //! Ends here ----------------------------- */

      /* ---------------- //! new review logic for dean and faculty --------------- */

      // Map email types to their comment‐reference field.
      // $commentFieldMap = [
      //   'Faculty' => 'field_faculty_member_comment_ref',
      //   'Dean'    => 'field_dean_comment_reference',
      // ];

      // // Only run for Faculty or Dean.
      // if (isset($commentFieldMap[$email_type])) {
      //   // Load once.
      //   $candidate_node = Node::load($candidate_nid);
      //   $uid            = $user->id();

      //   // ——— 1) Append candidate to review list if not already present ———
      //   $reviewField = 'field_review_candidates';
      //   if ($user->hasField($reviewField)) {
      //     $refs = $user->get($reviewField)->getValue();
      //     $exists = array_filter($refs, fn($item) => (int) $item['target_id'] === $candidate_nid);

      //     if (empty($exists)) {
      //       $refs[] = ['target_id' => $candidate_nid];
      //       $user->set($reviewField, $refs);
      //       $user->save();
      //     }
      //   }

      //   // ——— 2) Flip the pending flag on any of the user’s comments ———
      //   $commentField = $commentFieldMap[$email_type];
      //   if ($candidate_node && $candidate_node->hasField($commentField)) {
      //     foreach ($candidate_node->get($commentField)->referencedEntities() as $comment_node) {
      //       if (
      //         $comment_node instanceof Node
      //         && $comment_node->getOwnerId() === $uid
      //         && $comment_node->hasField('field_check_pending_flag')
      //       ) {
      //         $comment_node->set('field_check_pending_flag', TRUE);
      //         $comment_node->save();
      //       }
      //     }
      //   }
      // }



      /* ----------------------------- //!  Ends here ----------------------------- */
    }
  }



  if (!empty($created_node_ids)) {
    $existing_refs = $candidate_node->get('field_send_email_reference')->getValue();
    $merged_refs = array_reduce(array_merge($existing_refs, $created_node_ids), function ($carry, $item) {
      $carry[$item['target_id']] = $item;
      return $carry;
    }, []);
    $candidate_node->set('field_send_email_reference', array_values($merged_refs));
    $candidate_node->save();
    \Drupal::messenger()->addStatus('Email has been sent.');
  } else {
    \Drupal::messenger()->addWarning('No email nodes were created or linked.');
  }

  $url = Url::fromUserInput('/send-emails/' . $candidate_nid)->toString();
  (new \Symfony\Component\HttpFoundation\RedirectResponse($url))->send();
  exit;
}
/* -------------------------------------------------------------------------- */
/*                                //! Ends here                               */
/* -------------------------------------------------------------------------- */
