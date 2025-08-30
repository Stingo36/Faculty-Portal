<?php 

// Read the selected candidate template.
// If it's a List (text) controller:
$selected = $form_state->getValue(['field_candidate_templates', 0, 'value']) ?? '';

// If it's an Entity Reference controller, use this instead and compare to the TID:
// $selected = $form_state->getValue(['field_candidate_templates', 0, 'target_id']) ?? '';

$is_prescreen = in_array(mb_strtolower(trim((string) $selected)), ['prescreen interview','prescreen_interview'], true);

// Never use any "closing" fields by mistake (defensive; we don't read them anyway).
foreach (array_keys($form_state->getValues()) as $k) {
  if (strpos($k, 'field_candidate_closing_') === 0) {
    $form_state->unsetValue($k);
  }
}

$raw_email_body = $is_prescreen
  ? ($form_state->getValue('field_candidate_prescreen_temp') ?: [])
  : ($form_state->getValue('field_email_body_to_candidate') ?: []);
