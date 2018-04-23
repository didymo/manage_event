<?php

namespace Drupal\manage_event\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Form\UserPasswordResetForm;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\node\Entity\Node;

/**
 * OneTimeLoginController.
 *
 * @ingroup manage_event
 */
class OneTimeLoginController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a UserController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(DateFormatterInterface $date_formatter, UserStorageInterface $user_storage, UserDataInterface $user_data, LoggerInterface $logger) {
    $this->dateFormatter = $date_formatter;
    $this->userStorage = $user_storage;
    $this->userData = $user_data;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('user.data'),
      $container->get('logger.factory')->get('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function OneTimeLogin(Request $request, $uid, $timestamp, $hash, $eid, $type) {
    $account = $this->currentUser();
    // When processing the one-time login link, we have to make sure that a user
    // isn't already logged in.
    if ($account->isAuthenticated()) {
      // The current user is already logged in.
      if ($account->id() == $uid) {
        user_logout();
        // We need to begin the redirect process again because logging out will
        // destroy the session.
        return $this->redirect(
          'user.reset',
          [
            'uid' => $uid,
            'timestamp' => $timestamp,
            'hash' => $hash,
          ]
        );
      }
      // A different user is already logged in on the computer.
      else {
        /** @var \Drupal\user\UserInterface $reset_link_user */
        if ($reset_link_user = $this->userStorage->load($uid)) {
          drupal_set_message($this->t('Another user (%other_user) is already logged into the site on this computer, but you tried to use a one-time link for user %resetting_user. Please <a href=":logout">log out</a> and try using the link again.',
          [
            '%other_user' => $account->getUsername(),
            '%resetting_user' => $reset_link_user->getUsername(),
            ':logout' => $this->url('user.logout')
          ]
          ),
          'warning');
        }
        else {
          // Invalid one-time link specifies an unknown user.
          drupal_set_message($this->t('The one-time login link you clicked is invalid.'), 'error');
        }
        return $this->redirect('<front>');
      }
    }

    $session = $request->getSession();
    $session->set('pass_reset_hash', $hash);
    $session->set('pass_reset_timeout', $timestamp);
    return $this->redirect(
      'manage_event.one_time_login.getForm', [
        'uid' => $uid,
        'eid' => $eid,
        'type' => $type,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getResetPassForm(Request $request, $uid, $eid, $type) {
    $session = $request->getSession();
    $timestamp = $session->get('pass_reset_timeout');
    $hash = $session->get('pass_reset_hash');
    // As soon as the session variables are used they are removed to prevent the
    // hash and timestamp from being leaked unexpectedly. This could occur if
    // the user does not click on the log in button on the form.
    $session->remove('pass_reset_timeout');
    $session->remove('pass_reset_hash');
    if (!$hash || !$timestamp) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');

    $expiration_date = $user->getLastLoginTime() ? $this->dateFormatter->format($timestamp + $timeout) : NULL;
    return $this->redirect(
      'manage_event.one_time_login.login', [
        'uid' => $uid,
        'eid' => $eid,
        'timestamp' => $timestamp,
        'hash' => $hash,
        'type' => $type,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resetPassLogin($uid, $timestamp, $hash, $eid, $type) {
    // The current user is not logged in, so check the parameters.
    $current = REQUEST_TIME;
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);

    // Verify that the user exists and is active.
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    // No time out for first time login.
    if ($user->getLastLoginTime() && $current - $timestamp > $timeout) {
      drupal_set_message($this->t('You have tried to use a one-time login link that has either been used or is no longer valid.'), 'error');
      return $this->redirect('<front>');
    }
    elseif ($user->isAuthenticated() && ($timestamp >= $user->getLastLoginTime()) && ($timestamp <= $current) && Crypt::hashEquals($hash, user_pass_rehash($user, $timestamp))) {
      user_login_finalize($user);

      if ($type == 2) {
        $this->logger->notice('User %name used one-time login link at time %timestamp.', ['%name' => $user->getDisplayName(), '%timestamp' => $timestamp]);
        return $this->redirect('rng.event.node.register', [
          'node' => $eid,
          'registration_type' => 'default_registration',
        ]
        );
      }
      elseif ($type == 0 || $type == 1) {
        return $this->redirect('entity.node.canonical', [
          'node' => $eid,
        ]);
        $this->logger->notice('User %name used one-time login link at time %timestamp.', ['%name' => $user->getDisplayName(), '%timestamp' => $timestamp]);
      }
    }
    else {
      drupal_set_message($this->t('You have tried to use a one-time login link that has either been used or is no longer valid.'), 'error');
      return $this->redirect('<front>');
    }
  }

}
