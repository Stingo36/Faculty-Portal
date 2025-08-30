<?php

namespace Drupal\ncbs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure email settings for NCBS.
 */
class EmailSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ncbs.email_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ncbs_email_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ncbs.email_settings');

    $form['from_email_dean'] = [
      '#type' => 'email',
      '#title' => $this->t('From Email ID for Dean/Faculty/Director'),
      '#default_value' => $config->get('from_email_dean'),
      '#required' => TRUE,
    ];

    $form['from_email_candidate'] = [
      '#type' => 'email',
      '#title' => $this->t('From Email ID for Candidate/Referee'),
      '#default_value' => $config->get('from_email_candidate'),
      '#required' => TRUE,
    ];

    $form['reply_to_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Reply to Email ID'),
      '#default_value' => $config->get('reply_to_email'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ncbs.email_settings')
      ->set('from_email_dean', $form_state->getValue('from_email_dean'))
      ->set('from_email_candidate', $form_state->getValue('from_email_candidate'))
      ->set('reply_to_email', $form_state->getValue('reply_to_email'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
