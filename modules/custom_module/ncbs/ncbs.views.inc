<?php

/**
 * Implements hook_views_data().
 */
function ncbs_views_data() {
    $data = [];

    // // Check if the current user has the 'admin' or 'administrator' role
    // $current_user = \Drupal::currentUser();
    // if ($current_user->hasRole('admin') || $current_user->hasRole('administrator')) {
    //     // Add the field only if the user has the required role
    //     $data['views']['all_applications_comments'] = [
    //         'title' => \Drupal::translation()->translate('All Application Comments'),
    //         'help' => \Drupal::translation()->translate('All Application Comments'),
    //         'field' => [
    //             'id' => 'all_application_comments',
    //         ],
    //     ];
    // }

    $data['views']['ncbs_custom_field'] = [
        'title' => t('Add Comments'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'ncbs_custom_field',
        ],
      ];

    $data['views']['send_email'] = [
        'title' => t('Send Email'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'send_email',
        ],
      ];
    $data['views']['send_referee_email'] = [
        'title' => t('Send Referee Email'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'send_referee_email',
        ],
      ];



      //Comments fields
    $data['views']['admin_comment_ref'] = [
        'title' => t('Admin Comment Ref'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'admin_comment_ref',
        ],
      ];
    $data['views']['dean_comment_ref'] = [
        'title' => t('Dean Comment Ref'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'dean_comment_ref',
        ],
      ];
    $data['views']['director_comment_ref'] = [
        'title' => t('Director Comment Ref'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'director_comment_ref',
        ],
      ];
    $data['views']['faculty_comment_ref'] = [
        'title' => t('Faculty Comment Ref'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'faculty_comment_ref',
        ],
      ];
    $data['views']['field_faculty_search_comit_coref'] = [
        'title' => t('Faculty Search Comment Ref'),
        'help' => t('This is a custom field added by the NCBS module.'),
        'field' => [
          'id' => 'field_faculty_search_comit_coref',
        ],
      ];



  // Add a global “All Comments” field under the built‑in 'views' table.
  $data['views']['all_comments'] = [
    'title' => t('All Comments Custom Field'),
    'help'  => t('This is a custom field added by the NCBS module.'),
    'field' => [
      // Must match your plugin’s @ViewsField ID.
      'id' => 'all_comments',
    ],
  ];

  // Add a global “All Comments” field under the built‑in 'views' table.
  $data['views']['field_prescreen_comment_ref'] = [
    'title' => t('Pre Screen Custom Field'),
    'help'  => t('This is a custom field added by the NCBS module.'),
    'field' => [
      // Must match your plugin’s @ViewsField ID.
      'id' => 'field_prescreen_comment_ref',
    ],
  ];
    return $data;
}
