<?php

/**
 * @file
 * Post update functions for views_field_permissions.
 */

/**
 * Convert each permission setting to a list.
 */
function views_field_permissions_post_update_field_configs() {
  $config_factory = \Drupal::configFactory();
  $updated_fields = [];
  foreach ($config_factory->listAll('views.view.') as $view_config_name) {
    $view = $config_factory->getEditable($view_config_name);
    $view_updated = FALSE;
    foreach ($view->get('display') as $display_name => $display) {
      if (isset($display['display_options']['fields'])) {
        foreach ($display['display_options']['fields'] as $field_name => $field) {
          if (isset($field['views_field_permissions'])) {
            // Copy old permissions and add the new condition setting.
            $new_settings = $field['views_field_permissions'];
            $new_settings['condition'] = 'and';

            // Convert the old permission to an array if it was set.
            if (isset($field['views_field_permissions']['perm'])) {
              $new_settings['perms'] = [$field['views_field_permissions']['perm']];
              unset($new_settings['perm']);
            }

            // Save the new settings.
            // Log the change.
            // Mark the view for update.
            $view->set("display.$display_name.display_options.fields.$field_name.views_field_permissions", $new_settings);
            $updated_fields[] = "$view_config_name/$display_name ($field_name)";
            $view_updated = TRUE;
          }
        }
      }
    }
    if ($view_updated) {
      $view->save();
    }
  }
  if (!empty($updated_fields)) {
    return "Updated fields: " . implode(", ", $updated_fields);
  }
  return "No fields were updated.";
}
