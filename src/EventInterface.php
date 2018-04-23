<?php

namespace Drupal\manage_event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Event entity.
 *
 * @ingroup manage_event
 */
interface EventInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {
}
