<?php

namespace Drupal\ncbs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'User Content Status' Block.
 *
 * @Block(
 *   id = "user_content_status_block",
 *   admin_label = @Translation("User Content Status Block"),
 *   category = @Translation("Custom")
 * )
 */
class NcbsCustomBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = \Drupal::currentUser();
    $uid = $user->id();
    $entity_type_manager = \Drupal::entityTypeManager()->getStorage('node');

    // Get current internal path.
    $path = \Drupal::service('path.current')->getPath();
    $alias_manager = \Drupal::service('path_alias.manager');
    $current_internal_path = $alias_manager->getPathByAlias($path);

    // Submit application status
    $query = $entity_type_manager->getQuery()
      ->condition('type', 'submit_application')
      ->condition('uid', $uid)
      ->accessCheck(FALSE)
      ->range(0, 1);
    $nodes = $query->execute();
    $node_id = !empty($nodes) ? reset($nodes) : NULL;

    $show_limited_links = FALSE;
    $submit_application_url = NULL;
    $submit_application_label = 'Submit Application';
    $is_edit = FALSE;

    if ($node_id) {
      $node = $entity_type_manager->load($node_id);
      if ($node && $node->get('field_session_key')->value) {
        $show_limited_links = TRUE;
        $submit_application_url = Url::fromRoute('entity.node.canonical', ['node' => $node_id])->toString();
        $submit_path = "/node/$node_id";
        $active = ($submit_path === $current_internal_path) ? ' active' : '';
        $submit_application_label = 'View Application';
        $submit_class = 'filled' . $active;
      } else {
        $submit_application_url = Url::fromRoute('entity.node.edit_form', ['node' => $node_id])->toString();
        $submit_path = "/node/$node_id/edit";
        $active = ($submit_path === $current_internal_path) ? ' active' : '';
        $submit_application_label = 'Submit Application';
        $is_edit = TRUE;
        $submit_class = 'edit' . $active;
      }
    } else {
      $submit_application_url = Url::fromRoute('node.add', ['node_type' => 'submit_application'])->toString();
      $submit_path = '/node/add/submit_application';
      $active = ($submit_path === $current_internal_path) ? ' active' : '';
      $submit_class = 'edit' . $active;
    }

    // Content types to show
    $content_types = [
      'basic_information' => 'Basic Information',
      'update_publications' => 'Publications',
    ];

    if (!$show_limited_links) {
      $content_types += [
        'academic_qualification' => 'Academic Qualification',
        'work_experience' => 'Work Experience',
        'other_relevant_information' => 'Research Areas',
        'list_of_referees_' => 'List of Referees',
        'research_proposal' => 'Research Proposal',
      ];
    }

    $buttons = '';

    foreach ($content_types as $type => $label) {
      $query = $entity_type_manager->getQuery()
        ->condition('type', $type)
        ->condition('uid', $uid)
        ->accessCheck(FALSE)
        ->range(0, 1);
      $nodes = $query->execute();
      $node_id = !empty($nodes) ? reset($nodes) : NULL;

      if ($node_id) {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id])->toString();
        $path_check = "/node/$node_id";
        $active = ($path_check === $current_internal_path) ? ' active' : '';
        $buttons .= '<a href="' . $url . '" class="custom-btn filled' . $active . '">' . $label . '</a>';
      } else {
        $url = Url::fromRoute('node.add', ['node_type' => $type])->toString();
        $path_check = "/node/add/$type";
        $active = ($path_check === $current_internal_path) ? ' active' : '';
        $buttons .= '<a href="' . $url . '" class="custom-btn edit' . $active . '">' . $label . '</a>';
      }
    }

    // Submit Application Button
    $buttons .= '<a href="' . $submit_application_url . '" class="custom-btn ' . $submit_class . '">' . $submit_application_label . '</a>';


    // My Account
    $account_url = Url::fromRoute('entity.user.canonical', ['user' => $uid])->toString();
    $account_path = "/user/$uid";
    $active = ($account_path === $current_internal_path) ? ' active' : '';
    $buttons .= '<a href="' . $account_url . '" class="custom-btn warning' . $active . '">My Account</a>';


    // Change Password
    $change_password_url = Url::fromRoute('entity.user.edit_form', ['user' => $uid])->toString();
    $change_path = "/user/$uid/edit";
    $active = ($change_path === $current_internal_path) ? ' active' : '';
    $buttons .= '<a href="' . $change_password_url . '" class="custom-btn warning' . $active . '">Change Password</a>';

    // Logout
    $logout_url = Url::fromUserInput('/user/logout')->toString();
    $active = ('/user/logout' === $current_internal_path) ? ' active' : '';
    $buttons .= '<a href="' . $logout_url . '" class="custom-btn danger' . $active . '">Logout</a>';

    return [
      '#type' => 'inline_template',
      '#template' => '
        <div class="custom-status-wrapper">
          {{ buttons|raw }}
        </div>
      ',
      '#context' => [
        'buttons' => $buttons,
      ],
      '#attached' => [
        'library' => ['ncbs/user_content_block'],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.path'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
