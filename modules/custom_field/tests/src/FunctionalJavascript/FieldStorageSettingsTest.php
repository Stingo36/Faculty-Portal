<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Field storage settings form tests for custom field.
 *
 * @group custom_field
 */
class FieldStorageSettingsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'user',
    'system',
    'field',
    'field_ui',
    'text',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The custom fields on the test entity bundle.
   *
   * @var array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected $fields = [];

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The bundle type.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * URL to field's storage configuration form.
   *
   * @var string
   */
  protected $fieldStorageConfigUrl;

  /**
   * The custom field generate data service.
   *
   * @var \Drupal\custom_field\CustomFieldGenerateDataInterface
   */
  protected $customFieldDataGenerator;

  /**
   * Entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldName = 'field_custom_field_test';
    $this->bundle = 'custom_field_entity_test';
    $this->fieldStorageConfigUrl = '/admin/structure/types/manage/' . $this->bundle . '/fields/node.' . $this->bundle . '.' . $this->fieldName . '/storage';
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->customFieldDataGenerator = $this->container->get('custom_field.generate_data');

    $this->fields = $this->entityFieldManager
      ->getFieldDefinitions('node', 'custom_field_entity_test');

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

  }

  /**
   * Tests the settings form with stored configuration.
   */
  public function testFormSettings() {
    $field = $this->fields[$this->fieldName];
    $this->drupalGet($this->fieldStorageConfigUrl);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $columns = $field->getSetting('columns');

    // Verify the clone settings field exists.
    $assert_session->elementExists('css', '[name="settings[clone]"]');

    // Iterate over each column stored in config to test form element.
    foreach ($columns as $name => $column) {
      $type = $column['type'];
      $max_length = $page->findField('settings[items][' . $name . '][max_length]');
      $max_length_message = 'The max length field is visible';
      $size = $page->findField('settings[items][' . $name . '][size]');
      $size_message = 'The size field is visible';
      $precision = $page->findField('settings[items][' . $name . '][precision]');
      $precision_message = 'The precision field is visible';
      $scale = $page->findField('settings[items][' . $name . '][scale]');
      $scale_message = 'The scale field is visible';
      $unsigned = $page->findField('settings[items][' . $name . '][unsigned]');
      $unsigned_message = 'The unsigned checkbox is visible';
      $datetime_type = $page->findField('settings[items][' . $name . '][datetime_type]');
      $datetime_type_message = 'The datetime type field is visible';

      // Verify the type field is present and required.
      $this->assertNotEmpty((bool) $this->xpath('//select[@name="settings[items][' . $name . '][type]" and boolean(@required)]'), 'Type is shown as required.');
      // Verify the type field selected option matches the stored config value.
      $this->assertOptionSelected('settings[items][' . $name . '][type]', $type, 'The configured data type is selected.');

      // Perform special assertions based on column type.
      switch ($type) {
        case 'string':
          $this->assertNotTrue($size->isVisible(), $size_message);
          $this->assertNotTrue($precision->isVisible(), $precision_message);
          $this->assertNotTrue($scale->isVisible(), $scale_message);
          $this->assertNotTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);
          $this->assertTrue($max_length->isVisible(), $max_length_message);
          $this->assertTrue($max_length->getValue() === $column['max_length'], 'The configured value equals the form value.');
          break;

        case 'integer':
        case 'float':
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);
          $this->assertTrue($size->isVisible(), $size_message);
          $this->assertOptionSelected('settings[items][' . $name . '][size]', $column['size'], 'The configured size is selected.');
          $this->assertTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertTrue($unsigned->isChecked() === (bool) $unsigned->getValue(), 'The unsigned field is checked if the value is true.');
          break;

        case 'decimal':
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);
          $this->assertTrue($precision->isVisible(), $precision_message);
          $this->assertTrue($scale->isVisible(), $scale_message);
          $this->assertTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertTrue($unsigned->isChecked() === (bool) $unsigned->getValue(), 'The unsigned field is checked if the value is true.');
          break;

        case 'datetime':
          $this->assertTrue($datetime_type->isVisible(), $datetime_type_message);
          $this->assertOptionSelected('settings[items][' . $name . '][datetime_type]', $column['datetime_type'], 'The configured datetime type is selected.');
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($size->isVisible(), $size_message);
          $this->assertNotTrue($precision->isVisible(), $precision_message);
          $this->assertNotTrue($scale->isVisible(), $scale_message);
          $this->assertNotTrue($unsigned->isVisible(), $unsigned_message);
          break;

        case 'entity_reference':
          $target_type = $page->findField('settings[items][' . $name . '][target_type]');
          $target_type_message = 'The target type field is visible';
          $this->assertTrue($target_type->isVisible(), $target_type_message);
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($size->isVisible(), $size_message);
          $this->assertNotTrue($precision->isVisible(), $precision_message);
          $this->assertNotTrue($scale->isVisible(), $scale_message);
          $this->assertNotTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);
          break;

        case 'file':
        case 'image':
          $uri_scheme = $page->findField('settings[items][' . $name . '][uri_scheme]');
          $uri_scheme_message = 'The uri scheme field is visible';
          $this->assertTrue($uri_scheme->isVisible(), $uri_scheme_message);
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($size->isVisible(), $size_message);
          $this->assertNotTrue($precision->isVisible(), $precision_message);
          $this->assertNotTrue($scale->isVisible(), $scale_message);
          $this->assertNotTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);

        default:
          $this->assertNotTrue($max_length->isVisible(), $max_length_message);
          $this->assertNotTrue($size->isVisible(), $size_message);
          $this->assertNotTrue($precision->isVisible(), $precision_message);
          $this->assertNotTrue($scale->isVisible(), $scale_message);
          $this->assertNotTrue($unsigned->isVisible(), $unsigned_message);
          $this->assertNotTrue($datetime_type->isVisible(), $datetime_type_message);
          break;
      }
    }
  }

  /**
   * Tests the add/remove columns buttons with stored configuration.
   */
  public function testAddRemoveColumns() {
    $this->drupalGet($this->fieldStorageConfigUrl);
    $field = $this->fields[$this->fieldName];
    $columns = $field->getSetting('columns');
    $column_count = count($columns);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Remove elements in descending order until getting to the last one.
    foreach ($columns as $i => $column) {
      $index = $column_count - 1;
      $this->click('[name="remove:' . $i . '"]');
      $assert_session->assertWaitOnAjaxRequest();
      // Verify all items were removed except the first one.
      if ($index > 0) {
        $assert_session->elementNotExists('css', '[name="remove:' . $i . '"]');
      }
      // Verify the first item still exists and remove button is disabled.
      else {
        $assert_session->elementExists('css', '[name="remove:' . $i . '"]');
        $assert_session->elementAttributeExists('css', '[name="remove:' . $i . '"]', 'disabled');
      }
      $column_count--;
    }
    // Click the Add another button and verify the new element exists.
    $this->click('#edit-settings-actions-add');
    $assert_session->assertWaitOnAjaxRequest();
    $fields = $this->getSession()->getPage()->findAll('css', '.js-form-type-machine-name input');
    // Get the last field from the list.
    $new_field = end($fields)->getAttribute('value');
    $this->getSession()->getPage()->fillField('settings[items][' . $new_field . '][name]', 'new_field');
    $this->getSession()->getPage()->fillField('settings[items][' . $new_field . '][type]', 'string');
    $assert_session->assertWaitOnAjaxRequest();
    // Save the form.
    $page->findButton('Save field settings')->click();
    $this->drupalGet($this->fieldStorageConfigUrl);
    $new_field_name = $page->findField('settings[items][new_field][name]');
    // Verify the new field name value matches config.
    $this->assertTrue($new_field_name->getValue() === 'new_field');
  }

  /**
   * Tests cloning field settings from another field.
   */
  public function testCloneSettings() {
    $field_copy = $this->fields[$this->fieldName];
    $field_copy_columns = $field_copy->getSetting('columns');
    // Create node bundle for tests.
    $type = NodeType::create(['name' => 'Article', 'type' => 'article']);
    $type->save();

    // Create a generic custom field for validation.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_custom_generic',
      'entity_type' => 'node',
      'type' => 'custom',
    ]);
    $field_storage->save();

    $field_instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Generic custom field',
    ]);
    $field_instance->save();

    // Set article's form display.
    $this->formDisplay = EntityFormDisplay::load('node.article.default');

    if (!$this->formDisplay) {
      EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ])->save();
      $this->formDisplay = EntityFormDisplay::load('node.article.default');
    }
    $this->formDisplay->setComponent('field_custom_generic', [
      'type' => 'custom_stacked',
    ])->save();

    $field_config_url = '/admin/structure/types/manage/article/fields/node.article.field_custom_generic/storage';
    $this->drupalGet($field_config_url);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Verify the clone settings field exists.
    $assert_session->elementExists('css', '[name="settings[clone]"]');
    $page->fillField('settings[clone]', 'node.' . $this->bundle . '.' . $this->fieldName);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContainsOnce('The selected custom field field settings will be cloned. Any existing settings for this field will be overwritten. Field widget and formatter settings will not be cloned.');
    // Save the form.
    $page->findButton('Save field settings')->click();
    $this->drupalGet($field_config_url);
    $field = FieldConfig::loadByName('node', 'article', 'field_custom_generic');
    $columns = $field->getSetting('columns');
    $this->assertSame($field_copy_columns, $columns, 'The cloned columns match the source columns.');
  }

  /**
   * Tests the settings form with existing data.
   */
  public function testFormExistingData() {
    $field = $this->fields[$this->fieldName];
    $this->drupalGet('/node/add/custom_field_entity_test');
    $assert_session = $this->assertSession();
    // Fill out the single cardinality field.
    $generator = $this->customFieldDataGenerator;
    $form_values = $generator->generateSampleFormData($field);
    $this->submitForm($form_values, 'Save');

    // Ensure the values were properly persisted.
    $this->drupalGet('/node/1/edit');

    foreach ($form_values as $key => $expected) {
      $actual = $assert_session->waitForField($key)->getValue();
      static::assertEquals($expected, $actual);
    }

    // Load the settings page now to evaluate existing data.
    $this->drupalGet($this->fieldStorageConfigUrl);
    // Verify the clone settings field no longer exists.
    $assert_session->elementNotExists('css', '[name="settings[clone]"]');
    // Verify the add another button is hidden.
    $assert_session->elementNotExists('css', '#edit-settings-actions-add');
  }

  /**
   * Asserts that a select field has a selected option.
   *
   * @param string $id
   *   ID of select field to assert.
   * @param string $option
   *   Option to assert.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertOptionSelected($id, $option, $message = '') {
    $elements = $this->xpath('//select[@name=:id]//option[@value=:option]', [
      ':id' => $id,
      ':option' => $option,
    ]);
    foreach ($elements as $element) {
      $this->assertNotEmpty($element->isSelected(), $message ? $message : new FormattableMarkup('Option @option for field @id is selected.', [
        '@option' => $option,
        '@id' => $id,
      ]));
    }
  }

}
