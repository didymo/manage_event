<?php

namespace Drupal\manage_event\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for email confirmation.
 */
class EmailConfirmationController extends ControllerBase {

  /**
   * Render a list of entries in the database.
   */
  public function entryList($node, $type) {
    $content = [];

    $content['message'] = [
      '#markup' => $this->t('The following is the list of members and friends who have received and acknowledged the email.'),
    ];

    $rows = [];
    $headers = [t('ID'), t('User ID'), t('Email')];

    $select = db_select('email_confirmation', 'email');
    $select->join('users_field_data', 'user', 'email.uid = user.uid');
    $select->addField('email', 'id');
    $select->addField('email', 'uid');
    $select->addField('user', 'mail');
    $select->condition('email.eid', $node, '=');
    $select->condition('email.type', $type, '=');
    $select->distinct(TRUE);
    $entries = $select->execute()->fetchAll();

    $count = db_query("SELECT COUNT(uid) FROM email_confirmation WHERE eid = :eid AND uid != 0 AND type = $type", [":eid" => $node])->fetchField();
    $content['message']['#markup'] .= $count . " members and friends have acknowledged the email.";

    foreach ($entries as $entry) {
      // Sanitize each entry.
      if ($entry->uid != 0) {
        $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
      }
    };

    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('No entries available.'),
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

}
