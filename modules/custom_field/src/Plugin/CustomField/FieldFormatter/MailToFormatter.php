<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'email_mailto' formatter.
 *
 * @FieldFormatter(
 *   id = "email_mailto",
 *   label = @Translation("E-mail"),
 *   field_types = {
 *     "email",
 *   }
 * )
 */
class MailToFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, CustomFieldTypeInterface $field, array $settings) {
    $build = [
      '#type' => 'link',
      '#title' => $settings['value'],
      '#url' => Url::fromUri('mailto:' . $settings['value']),
    ];

    return $this->renderer->render($build);
  }

}
