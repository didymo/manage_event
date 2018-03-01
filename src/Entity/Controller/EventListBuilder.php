<?php
namespace Drupal\manage_event\Entity\Controller;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
/**
 * Provides a list controller for content_entity_manage_event entity.
 *
 * @ingroup manage_event
 */
class EventListBuilder extends EntityListBuilder {
    /**
     * {@inheritdoc}
     *
     * We override ::render() so that we can add our own content above the table.
     * parent::render() is where EntityListBuilder creates the table using our
     * buildHeader() and buildRow() implementations.
     */
    public function render() {
        $build['description'] = array(
            '#markup' => $this->t('manage the fields on the <a href="@adminlink">Here</a>.', array(
                '@adminlink' => \Drupal::urlGenerator()
                    ->generateFromRoute('manage_event.event_settings'),
            )),
        );
        $build['table'] = parent::render();
        return $build;
    }
    /**
     * {@inheritdoc}
     *
     * Building the header and content lines for the event list.
     *
     * Calling the parent::buildHeader() adds a column for the possible actions
     * and inserts the 'edit' and 'delete' links as defined for the entity type.
     */
    public function buildHeader() {
        $header['id'] = $this->t('Event ID');
        $header['e_name'] = $this->t('Event Name');
        $header['e_description__value'] = $this->t('Description');
        return $header + parent::buildHeader();
    }
    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity) {
        /* @var $entity \Drupal\manage_event\Entity\Event */
        $row['id'] = $entity->id();
        $row['e_name'] = $entity->link();
        $row['e_description__value'] = $entity->e_description->value;
        return $row + parent::buildRow($entity);
    }
}
?>