<?php
namespace Drupal\easy_email_password\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\easy_email\Service\EmailSender;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @Action(
 *   id = "set_random_password_and_send_easy_email",
 *   label = @Translation("Set random password and send Easy Email"),
 *   type = "user"
 * )
 */
class SetPwdAndEmail extends ActionBase implements ContainerFactoryPluginInterface {
  protected EmailSender $sender;

  public function __construct(array $config, $pid, $pd, EmailSender $sender) {
    parent::__construct($config, $pid, $pd);
    $this->sender = $sender;
  }

  public static function create(ContainerInterface $c, array $config, $pid, $pd) {
    return new static($config, $pid, $pd, $c->get('easy_email.email_sender'));
  }

  public function execute($entity = NULL) {
    if ($entity instanceof UserInterface) {
      $pwd = \Drupal\Component\Utility\Crypt::randomBytesBase64(8);
      $entity->setPassword($pwd);
      $entity->save();

      $params = [
        'to' => $entity->getEmail(),
        'token_data' => ['user' => $entity],
        'replacements' => ['[custom:password]' => $pwd],
      ];
      $this->sender->sendEmailByTemplateId('your_template_id', $params);
    }
  }

  public function access($object, AccountInterface $user = NULL, $return_as_object = FALSE) {
    return $object?->access('update', $user, $return_as_object) ?? FALSE;
  }
}
