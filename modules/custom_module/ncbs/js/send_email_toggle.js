(function ($, Drupal) {
  let behaviorLogged = false;

  Drupal.behaviors.sendEmailToggle = {
    attach: function (context, settings) {
      if (!behaviorLogged) {
        console.log('🔁 Behavior attached: sendEmailToggle');
        behaviorLogged = true;
      }

      // Hide all fields by default
      hideReferenceField('field_to_dean', context);
      hideReferenceField('field_to_director', context);
      hideReferenceField('field_to_board', context);

      $('select[name="field_send_email_"]', context).each(function () {
        const $select = $(this);

        if ($select.data('send-email-toggle-attached')) return;
        $select.data('send-email-toggle-attached', true);

        $select.on('change', function () {
          const selected = $(this).val();
          console.log(`📤 field_send_email_ changed to: ${selected}`);

          // Always hide all first
          hideReferenceField('field_to_dean', context);
          hideReferenceField('field_to_director', context);
          hideReferenceField('field_to_board', context);

          // Then show relevant field
          if (selected === 'Dean') {
            showReferenceField('field_to_dean', context);
          } else if (selected === 'Director') {
            showReferenceField('field_to_director', context);
          } else if (selected === 'Board') {
            showReferenceField('field_to_board', context);
          }
        });
      });

      function clearReferenceField(fieldName, context) {
        const selector = '[data-drupal-selector^="edit-' + fieldName.replace(/_/g, '-') + '"]';
        const fieldWrapper = $(selector, context);
        if (fieldWrapper.length === 0) return;

        fieldWrapper.find('input.form-autocomplete').val('');
        fieldWrapper.find('input[type="hidden"]').val('');
      }

      function hideReferenceField(fieldName, context) {
        const selector = '[data-drupal-selector^="edit-' + fieldName.replace(/_/g, '-') + '"]';
        $(selector, context).closest('.form-item').hide();
      }

      function showReferenceField(fieldName, context) {
        const selector = '[data-drupal-selector^="edit-' + fieldName.replace(/_/g, '-') + '"]';
        $(selector, context).closest('.form-item').show();
      }
    }
  };
})(jQuery, Drupal);
