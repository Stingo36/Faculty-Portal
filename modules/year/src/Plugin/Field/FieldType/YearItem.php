<?php

namespace Drupal\year\Plugin\Field\FieldType;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase;

/**
 * Plugin implementation of the 'year' field type.
 *
 * @FieldType(
 *   id = "year",
 *   label = @Translation("Year"),
 *   description = @Translation("This field provide the ways to collect year only in provided date range."),
 *   list_class = "\Drupal\year\Plugin\Field\FieldType\YearFieldItemList",
 *   default_widget = "year_default",
 *   default_formatter = "year_default",
 * )
 */
class YearItem extends NumericItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'length' => 'normal',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'min' => 1900,
      'max' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Year value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $year_range = $this->getYearRange();
    $label = $this->getFieldDefinition()->getLabel();

    // Add min constraint.
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Range' => [
          'min' => $year_range['min'],
          'minMessage' => $this->t('%name: The value must be larger or equal to %min.', [
            '%name' => $label,
            '%min' => $year_range['min'],
          ]),
        ],
      ],
    ]);

    // Add max constraint.
    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Range' => [
          'max' => $year_range['max'],
          'maxMessage' => $this->t('%name: the value may be no greater than %max.', [
            '%name' => $label,
            '%max' => $year_range['max'],
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum year'),
      '#default_value' => $settings['min'],
      '#required' => TRUE,
      '#description' => $this->t('Provide a minimum valid year as an integer greater than zero (e.g. <strong>530</strong>, <strong>1900</strong>, etc.).'),
    ];
    $element['max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum year'),
      '#default_value' => $settings['max'],
      '#required' => TRUE,
      '#description' => $this->t("Provide a <strong>specific year</strong> as an integer greater than zero
        <strong>- OR -</strong> describe a <strong>time relative</strong> to the current date.<br>
        Example: '<strong>1978</strong>' (specific year), <strong>now</strong>' (current year), or '<strong>+5 years</strong>' (five years from the current date).
        See <a href=\"https://www.php.net/manual/datetime.formats.relative.php\">Relative formats</a> for details."),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $min = $field_definition->getSetting('min') ?: 1900;

    $max = $field_definition->getSetting('max') ?: 2050;
    if (!is_numeric($max)) {
      $max = date('Y', strtotime($max));
    }

    $values['value'] = mt_rand($min, $max);
    return $values;
  }

  /**
   * Provide min/max range of valid years.
   *
   * @return array
   *   Array of valid min and max year values.
   */
  public function getYearRange() {
    $settings = $this->getSettings();
    return [
      'min' => (int) $settings['min'],
      'max' => (int) $this->calculateYear($settings['max']),
    ];
  }

  /**
   * Calculate a year value based on provide numeric or relative string.
   *
   * @param string $year
   *   String representation of a specific year or relative strtotime format.
   *
   * @return int
   *   The calculated year value as an integer.
   */
  public function calculateYear(string $year) {
    if (!is_numeric($year)) {
      $year = date('Y', strtotime($year));
    }
    return $year;
  }

}
