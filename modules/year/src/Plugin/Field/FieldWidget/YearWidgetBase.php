<?php

namespace Drupal\year\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;

/**
 * Base class for Year widgets.
 */
abstract class YearWidgetBase extends WidgetBase implements WidgetInterface {

  /**
   * {@inheritdoc}
   */
  protected function getFieldSettings() {
    $settings = $this->fieldDefinition->getSettings();

    $max = $settings['max'];
    if (!is_numeric($max)) {
      $settings['max'] = date('Y', strtotime($max));
    }

    return $settings;
  }

}
