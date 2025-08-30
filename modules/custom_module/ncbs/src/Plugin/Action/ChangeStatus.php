<?php
namespace Drupal\ncbs\Plugin\Action;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * @Action(
 *   id = "change_application_status",
 *   label = @Translation("Change Application Status"),
 *   type = "node",
 *   confirm = TRUE,
 *   api_version = "1",
 * )
 */
class ChangeStatus extends ViewsBulkOperationsActionBase implements PluginFormInterface {

    public function execute($entity = NULL) {
        $selected_status = $this->configuration['status'];

        if ($entity instanceof Node) {

            // --- 1. Save previous status to field_previous_status ---
            $previous_status = '';
            if ($entity->hasField('field_status') && !$entity->get('field_status')->isEmpty()) {
                $previous_status = $entity->get('field_status')->value;
            }
            if ($entity->hasField('field_previous_status')) {
                $entity->set('field_previous_status', $previous_status);
            }

            // --- 2. Set new status as usual ---
            if ($entity->hasField('field_status')) {
                $entity->set('field_status', $selected_status);
                $entity->save();
            }

            // --- 3. Handle status update paragraph field ---
            if ($entity->hasField('field_status_update')) {
                $current_time = \Drupal::time()->getRequestTime();
                $datetime_utc = new \DateTime('@' . $current_time);
                $datetime_utc->setTimezone(new \DateTimeZone('UTC'));
                $current_date_utc = $datetime_utc->format('Y-m-d\TH:i:s');

                // Retrieve existing paragraph references
                $existing_paragraph_refs = $entity->get('field_status_update')->getValue();
                $existing_paragraphs = $entity->get('field_status_update')->referencedEntities();
                $new_paragraph_needed = TRUE;

                foreach ($existing_paragraphs as $paragraph) {
                    $existing_statuses = [];
                    foreach ($paragraph->get('field_status_name')->getValue() as $item) {
                        $existing_statuses[] = $item['value'];
                    }
                    $existing_dates = [];
                    foreach ($paragraph->get('field_status_update_date')->getValue() as $item) {
                        $existing_dates[] = $item['value'];
                    }

                    if (!in_array($selected_status, $existing_statuses)) {
                        $paragraph->get('field_status_name')->appendItem(['value' => $selected_status]);
                    }
                    if (!in_array($current_date_utc, $existing_dates)) {
                        $paragraph->get('field_status_update_date')->appendItem(['value' => $current_date_utc]);
                    }

                    $paragraph->setNewRevision(TRUE);
                    $paragraph->save();
                }

                if ($new_paragraph_needed) {
                    // Create a new paragraph with the correct field values
                    $new_paragraph = Paragraph::create([
                        'type' => 'status_value',
                        'field_status_name' => [['value' => $selected_status]],
                        'field_status_update_date' => [['value' => $current_date_utc]],
                        'field_previous_status' => [['value' => $previous_status]], // <-- This line sets previous status in the paragraph
                    ]);


                    $new_paragraph->setNewRevision(TRUE);
                    $new_paragraph->save();

                    if ($new_paragraph->id()) {
                        // Ensure the paragraph is completely saved before attaching
                        $new_paragraph = Paragraph::load($new_paragraph->id());

                        // Append the new paragraph to existing ones without overwriting
                        $existing_paragraph_refs[] = [
                            'target_id' => $new_paragraph->id(),
                            'target_revision_id' => $new_paragraph->getRevisionId(),
                        ];
                        $entity->set('field_status_update', $existing_paragraph_refs);
                        $entity->setNewRevision(TRUE);
                        $entity->save();

                    } else {
                        \Drupal::messenger()->addMessage('Error: Paragraph creation failed.', 'error');
                    }
                }

                // Force cache clear to ensure visibility in UI
                \Drupal::cache()->invalidateAll();
            } else {
                \Drupal::messenger()->addMessage('Error: No field_status_update found on this node.', 'error');
            }
        }

        return $this->t('Action completed (configuration: @configuration)', [
            '@configuration' => Markup::create(\print_r($this->configuration, TRUE)),
        ]);
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        // Get the list of allowed values from the field_status list field.
        $field_values = [];
        $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'submit_application');

        if (isset($field_definitions['field_status'])) {
            $field_settings = $field_definitions['field_status']->getSettings();
            if (isset($field_settings['allowed_values'])) {
                $field_values = $field_settings['allowed_values'];
            }
        }

        // Create the dropdown list.
        $form['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Select Status'),
            '#options' => $field_values,
            '#required' => TRUE,
        ];

        return $form;
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $this->configuration['status'] = $form_state->getValue('status');
    }

    public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE)
    {
        return $object->access('update', $account, $return_as_object);
    }
}
