<?php

namespace Drupal\year\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a configurable entity datetime field.
 */
class YearFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    if (empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      $default_value = $this->getFieldDefinition()->getDefaultValueLiteral();

      $element = [
        // '#parents' => ['default_value_input'],
        'default_value_input' => [
          '#type' => 'textfield',
          '#title' => $this->t('Default year'),
          '#default_value' => $default_value[0]['value'] ?? '',
          '#description' => $this->t("Provide a <strong>specific year</strong> within the range specified above
            <strong>- OR -</strong> describe a <strong>time relative</strong> to the current date. Leave blank for no default.<br>
            Example: '<strong>now</strong>' (current year), or '<strong>+5 years</strong>' (five years from the current date).
            See <a href=\"https://www.php.net/manual/datetime.formats.relative.php\">Relative formats</a> for details."),
          '#weight' => 1,
        ],
      ];

      return $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {

    $default_year = $form_state->getValue([
      'default_value',
      'default_value_input',
    ]);
    $min_year = $form_state->getValue(['settings', 'min']);
    $max_year = $form_state->getValue(['settings', 'max']);

    // Validate min_year.
    if (empty($min_year)) {
      $form_state->setErrorByName('settings][min', $this->t('Please provide a minimum value greater than zero.'));
      return;
    }

    // Validate max_year.
    if (is_numeric($max_year)) {
      if (((int) $max_year == $max_year) && $max_year < $min_year) {
        $form_state->setErrorByName('settings][max',
        $this->t('The maximum must be less than or equal to minimum (@min_year)', ['@min_year' => $min_year]));
        return;
      }
    }
    elseif (!$this->isStringToTime($max_year)) {
      $form_state->setErrorByName('settings][max', $this->t('The relative time string is not valid.'));
      return;
    }

    // Validate default_year.
    if ($default_year || $default_year === 0 || $default_year === '0') {
      $max_year = $this->calculateYear($max_year);
      // Handle specific, numeric year.
      if (is_numeric($default_year)) {
        if (((int) $default_year == $default_year)) {
          // We have an integer, so check that it's within the min/max range.
          if (!($min_year <= $default_year && $default_year <= $max_year)) {
            $form_state->setErrorByName('default_value][default_value_input',
            $this->t('Enter a year between @min and @max', [
              '@min' => $min_year,
              '@max' => $max_year,
            ]));
          }
        }
        // Default year is numeric, but doesn't resolve as an integer.
        else {
          $form_state->setErrorByName('default_value][default_value_input', $this->t('Please provide a valid integer for the year.'));
        }
      }
      // Default year is not numeric, so check for valid strtotime format.
      else {
        if (!$this->isStringToTime($default_year)) {
          $form_state->setErrorByName('default_value][default_value_input', $this->t('The relative time string is not valid.'));
        }
        // Check that calculated date is in range.
        else {
          $default_year = date('Y', strtotime($default_year));
          if (!($min_year <= $default_year && $default_year <= $max_year)) {
            $form_state->setErrorByName('default_value][default_value_input',
            $this->t('The calculated year (@year) is not the the valid range (@min - @max).', [
              '@year' => $default_year,
              '@min' => $min_year,
              '@max' => $max_year,
            ]));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    $default_value = parent::defaultValuesFormSubmit($element, $form, $form_state);
    if ($form_state->getValue(['default_value', 'default_value_input'])) {
      $default_value[0]['value'] = $form_state->getValue([
        'default_value',
        'default_value_input',
      ]);
      return $default_value;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if (isset($default_value[0]['value']) && !(is_numeric($default_value[0]['value']))) {
      $default_value[0]['value'] = date('Y', strtotime($default_value[0]['value']));
    }

    return $default_value;
  }

  /**
   * Helper to validate a strtotime value.
   *
   * @param string $value
   *   String representation of a relative strtotime format.
   *
   * @return bool
   *   Whether or not the string is valid.
   */
  protected function isStringToTime(string $value) {
    return @strtotime($value);
  }

  /**
   * Helper to calculate a year value from numeric or relative string.
   *
   * @param string $value
   *   String representation of a specific year or relative strtotime format.
   *
   * @return int
   *   The calculated year value as an integer.
   */
  protected function calculateYear(string $value) {
    if (!is_numeric($value)) {
      $value = date('Y', strtotime($value));
    }
    return $value;
  }

}
