<?php
namespace Drupal\manage_event\Form;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\Language;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use \Drupal\file\Entity\File;
use Drupal\Core\TypedData\Plugin\DataType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Datetime\DatetimeFormatter;

/**
 * Form controller for the manage_event entity edit forms.
 *
 * @ingroup manage_event
 */
class EventForm extends ContentEntityForm {
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        /* @var $entity \Drupal\manage_event\Entity\Event */
        $form = parent::buildForm($form, $form_state);
        $entity = $this->entity;
        $form['langcode'] = array(
            '#title' => $this->t('Language'),
            '#type' => 'language_select',
            '#default_value' => $entity->getUntranslated()->language()->getId(),
            '#languages' => Language::STATE_ALL,
        );

        $form['recurring'] = array(
            '#title' => $this->t('This is a recurring event.'),
            '#type' => 'checkbox',
            '#weight' => 8,
        );

        $form['extra'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => 'recurring',
            ],
            '#weight' => 9,
            '#states' => [
                'invisible' => [
                    'input[name="recurring"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['extra']['recurring_option'] = [
            '#type' => 'details',
            '#title' => $this->t('Recurring event options'),
        ];

        $form['extra']['recurring_option']['type'] = [
            '#type' => 'radios',
            '#title' => $this->t('Type:'),
            '#options' => [
                'daily' => 'Daily',
                'weekly' => 'Weekly',
            ]
        ];

        $form['extra']['recurring_option']['end_repeat'] = [
            '#type' => 'radios',
            '#title' => $this->t('End repeats:'),
            '#options' => [
                1 => "On Date",
                2 => 'After',
            ],
        ];

        $options = array();
        foreach(range(1, 20) as $i) {
            if($i == 1)
                $options[$i] = $i . " time";
            else
                $options[$i] = $i . " times";
        }

        $form['extra']['recurring_option']['end_repeat_on'] = [
            '#type' => 'date',
            '#title' => $this->t('End repeats on:'),
            '#states' => [
                'visible' => [
                    'input[name="end_repeat"]' => array('value' => '1'),
                ],
            ],
        ];

        $form['extra']['recurring_option']['end_repeat_after'] = [
            '#type' => 'select',
            '#title' => $this->t('End repeats after:'),
            '#options' => $options,
            '#states' => [
                'visible' => [
                    'input[name="end_repeat"]' => array('value' => '2'),
                ],
            ],
        ];

        $form['actions']['submit']['#value'] = 'Create Event';
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state) {

        $form_state->setRedirect('<front>');
        $entity = $this->getEntity();
        $entity->save();

        $datetime_member = db_query("SELECT e_datetime_member FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
        $datetime_general = db_query("SELECT e_datetime_general FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
        $datetime_member_end = db_query("SELECT e_datetime_member_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
        $datetime_general_end = db_query("SELECT e_datetime_general_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();

        if(is_null($datetime_general) && is_null($datetime_member)){
            drupal_set_message('Event is not created.','error');
            drupal_set_message('Either one of the datetime fields has to be filled.','error');
        }
        else if(!is_null($datetime_member) && is_null($datetime_member_end)){
            drupal_set_message('Event is not created.','error');
            drupal_set_message('End date and time has to be filled.','error');
        }
        else if(!is_null($datetime_member) && !is_null($datetime_member_end) && $datetime_member_end < $datetime_member)
        {
            drupal_set_message('Event is not created.','error');
            drupal_set_message('End Date and Time must be greater than Start Date and Time.','error');
        }
        else if(!is_null($datetime_general) && is_null($datetime_general_end)){
            drupal_set_message('Event is not created.','error');
            drupal_set_message('End date and time has to be filled.','error');
        }
        else if(!is_null($datetime_general) && !is_null($datetime_general_end) && $datetime_general_end < $datetime_general)
        {
            drupal_set_message('Event is not created.','error');
            drupal_set_message('End Date and Time must be greater than Start Date and Time.','error');
        }
        else
        {
            $event_name = db_query("SELECT e_name FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();

            $image_id = db_query("SELECT e_image__target_id FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $image_alt_text = db_query("SELECT e_image__alt FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $image_title = db_query("SELECT e_image__title FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();

            $country = db_query("SELECT address__country_code FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $address_line1 = db_query("SELECT address__address_line1 FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $address_line2 = db_query("SELECT address__address_line2 FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $locality = db_query("SELECT address__locality FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
            $postal_code = db_query("SELECT address__postal_code FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();

            $repeat = $form_state->getValue('type');

            if($form_state->getValue('end_repeat') == 1)
            {
                $repeat_value = $form_state->getValue('end_repeat_on');
                $repeat_value = strtotime($repeat_value);
                $diff = $repeat_value - strtotime($datetime_member);
                if($repeat == "daily") {
                    $diff = $diff / (24*60*60);
                }
                else {
                    $diff = $diff / (7*24*60*60);
                }
            }
            else if($form_state->getValue('end_repeat') == 2){
                $diff = $form_state->getValue('end_repeat_after');
            }
            else {
                $diff =0;
            }

            for ($x = 0; $x <= $diff; $x++)
            {
                if($x != 0)
                {
                    if($repeat == 'daily')
                    {
                        if(!is_null($datetime_member))
                        {
                            $datetime_member = db_query("SELECT e_datetime_member FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_member = strtotime($datetime_member);
                            $datetime_member = $datetime_member + $x * (24*60*60);
                            $datetime_member = date("Y-m-d\TH:i:s",$datetime_member);

                            $datetime_member_end = db_query("SELECT e_datetime_member_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_member_end = strtotime($datetime_member_end);
                            $datetime_member_end = $datetime_member_end + $x * (24*60*60);
                            $datetime_member_end = date("Y-m-d\TH:i:s",$datetime_member_end);
                        }

                        if(!is_null($datetime_general))
                        {
                            $datetime_general = db_query("SELECT e_datetime_general FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_general = strtotime($datetime_general);
                            $datetime_general = $datetime_general + $x * (24*60*60);
                            $datetime_general = date("Y-m-d\TH:i:s",$datetime_general);

                            $datetime_general_end = db_query("SELECT e_datetime_general_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_general_end = strtotime($datetime_general_end);
                            $datetime_general_end = $datetime_general_end + $x * (24*60*60);
                            $datetime_general_end = date("Y-m-d\TH:i:s",$datetime_general_end);
                        }

                    }
                    else if($repeat == "weekly")
                    {
                        if(!is_null($datetime_member))
                        {
                            $datetime_member = db_query("SELECT e_datetime_member FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_member = strtotime($datetime_member);
                            $datetime_member = $datetime_member + $x * (7*24*60*60);
                            $datetime_member = date("Y-m-d\TH:i:s",$datetime_member);

                            $datetime_member_end = db_query("SELECT e_datetime_member_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_member_end = strtotime($datetime_member_end);
                            $datetime_member_end = $datetime_member_end + $x * (7*24*60*60);
                            $datetime_member_end = date("Y-m-d\TH:i:s",$datetime_member_end);
                        }

                        if(!is_null($datetime_general))
                        {
                            $datetime_general = db_query("SELECT e_datetime_general FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_general = strtotime($datetime_general);
                            $datetime_general = $datetime_general + $x * (7*24*60*60);
                            $datetime_general = date("Y-m-d\TH:i:s",$datetime_general);

                            $datetime_general_end = db_query("SELECT e_datetime_general_end FROM event WHERE id = (SELECT MAX(id) FROM event)")->fetchField();
                            $datetime_general_end = strtotime($datetime_general_end);
                            $datetime_general_end = $datetime_general_end + $x * (7*24*60*60);
                            $datetime_general_end = date("Y-m-d\TH:i:s",$datetime_general_end);
                        }
                    }
                }

                /* Event Overview creation */
                $node_overview = Node::create([
                    'type' => 'event_overview',
                    'field_description' => $form_state->getValue('e_description'),
                    'field_venue' => [
                        'country_code' => $country,
                        'address_line1' => $address_line1,
                        'address_line2' => $address_line2,
                        'locality' => $locality,
                        'postal_code' => $postal_code,
                    ],
                    'field_image' => [
                        'target_id' => $image_id,
                        'alt' => $image_alt_text,
                        'title' => $image_title,
                    ],
                ]);
                $node_overview->setTitle($event_name);
                $node_overview->save();

                $assets = $form_state->getValue('asset_id');
                $asset_target_id = array();
                foreach($assets as $key => $asset) {
                    array_push($asset_target_id,$asset['target_id']);
                }
                foreach($asset_target_id as $id) {
                    $node_overview->field_asset[] = ['target_id' => $id];
                }
                $node_overview->save();

                /* Members and Friends' event creation */
                if(!is_null($datetime_member))
                {
                    $node_member = Node::create([
                        'type' => 'event',
                        'title' => $event_name,
                        'field_description' => $form_state->getValue('e_description'),
                        'field_event_date' => $datetime_member,
                        'field_event_date_end' => $datetime_member_end,
                        'field_venue' => [
                            'country_code' => $country,
                            'address_line1' => $address_line1,
                            'address_line2' => $address_line2,
                            'locality' => $locality,
                            'postal_code' => $postal_code,
                        ],
                        'field_image' => [
                            'target_id' => $image_id,
                            'alt' => $image_alt_text,
                            'title' => $image_title,
                        ],
                        'field_reference' => [
                            'target_id' => $node_overview->id(),
                            'target_type' => 'node',
                        ],
                        'field_delete_rule_id' => [
                            'value' => 0
                        ],
                        'field_update_rule_id' => [
                            'value' => 0
                        ],
                        'field_registrant_count' => [
                            'value' => 0
                        ],
                    ]);
                    $node_member->set('rng_status',array(
                        'value' => 1,
                    ));
                    $node_member->set('rng_registration_type',array(
                        'target_id' => "default_registration",
                    ));
                    $node_member->set('rng_registrants_minimum',array(
                        'value' => 1,
                    ));
                    $node_member->set('rng_registrants_maximum',array(
                        'value' => -1,
                    ));
                    $node_member -> save();
                    $node_member->set('field_message',array(
                        'uri' => 'internal:/node/' . $node_member->id() . '/event/messages',
                        'title' => 'Message members & friends'
                    ));
                    $node_member -> save();

                    $node_email_update = Node::create([
                            'type' => 'email_confirmation',
                            'title' => 'Update: ' . $event_name,
                            'body' => 'Thank you for acknowledging that you have received the email update for the event "' . $event_name . '"!',
                            'field_reference' => [
                                'target_id' => $node_overview->id(),
                                'target_type' => 'node',
                            ],
                            'field_email_confirmation_type' => [
                                'value' => 0
                            ],
                        ]
                    );
                    $node_email_update->setPublished(FALSE);
                    $node_email_update->save();

                    $node_email_delete = Node::create([
                            'type' => 'email_confirmation',
                            'title' => 'Cancellation : ' . $event_name,
                            'body' => 'Thank you for acknowledging that you have received the email regarding the cancellation of the event "' . $event_name .'"!',
                            'field_reference' => [
                                'target_id' => $node_overview->id(),
                                'target_type' => 'node',
                            ],
                            'field_email_confirmation_type' => [
                                'value' => 1
                            ],
                        ]
                    );
                    $node_email_delete->setPublished(FALSE);
                    $node_email_delete->save();

                    /* add register link for MF teaser */
                    $uri = "internal:" . "/node/". $node_member->id() . "/register/default_registration";
                    $node_member->set('field_register_link',['uri' => $uri, 'title' => 'Register']);
                    /* add view registration link for MF entity reference in admin full content view */
                    $uri = "internal:" . "/node/" . $node_member->id(). "/registrations";
                    $node_member->set('field_view_registration_list',['uri' => $uri, 'title' => 'View registration list']);
                    $node_member->save();


                    /* Update previously created event overview */
                    $node_overview->set('field_event_date_overview',$datetime_member);
                    $node_overview->set('field_event_date_end_member',$datetime_member_end);
                    $node_overview->set('field_reference_member',['target_id' => $node_member->id(), 'target_type' => 'node']);
                    $node_overview->set('field_reference_update',['target_id' => $node_email_update->id(), 'target_type' => 'node']);
                    $node_overview->set('field_reference_delete',['target_id' => $node_email_delete->id(), 'target_type' => 'node']);

                    if($x != 0)
                    {
                        $node_member = Node::load($node_member->id());
                        $node_member->setPublished(FALSE);
                        $datetime_member = strtotime($datetime_member);
                        $datetime_member = $datetime_member - (7*24*60*60);
                        $node_member->set('publish_on',$datetime_member);
                        $node_member->save();
                        $datetime_member = date("Y-m-d\TH:i:s",$datetime_member);

                    }
                    $node_overview->save();

                    /* Set up email body to be sent to all members and friends */
                    $datetime = db_query("SELECT e_datetime_member FROM event WHERE id = :id",array(":id" => $entity->id()))->fetchField();
                    $datetime = new \DateTime($datetime, new \DateTimeZone("GMT"));
                    $datetime->setTimezone(new \DateTimeZone('Australia/Sydney'));
                    $datetime = $datetime->format("l, j F Y - g:i A");

                    $datetime_end = db_query("SELECT e_datetime_member_end FROM event WHERE id = :id",array(":id" => $entity->id()))->fetchField();
                    $datetime_end = new \DateTime($datetime_end, new \DateTimeZone("GMT"));
                    $datetime_end->setTimezone(new \DateTimeZone('Australia/Sydney'));
                    $datetime_end = $datetime_end->format("l, j F Y - g:i A");

                    $member_id = $node_member->id();
                    $entity = $this->getEntity();
                    if($x == 0)
                    {
                        /* Email template for event creation */
                        $entity->e_email_body->value =
                            "A new event has just been created!<br>
                        &nbsp;<br>
                        Event Details<br>
                        &nbsp;<br>
                        Start Date and Time : <br>
                        $datetime
                        &nbsp;<br>
                        End Date and Time : <br>
                        $datetime_end
                        &nbsp;<br>
                        Venue : <br>
                        $address_line1<br>
                        $address_line2<br>
                        $postal_code<br>
                        $country
                        &nbsp;<br>
                        Interested in this event?<br>
                        Click on the link below to register for the event!";
                        $entity->save();
                    }
                }

                /* General public's event creation */
                if(!is_null($datetime_general))
                {
                    $node_general = Node::create([
                        'type' => 'event_1',
                        'title' => $event_name,
                        'field_description' => $form_state->getValue('e_description'),
                        'field_event_date' => $datetime_general,
                        'field_event_date_end' => $datetime_general_end,
                        'field_venue' => [
                            'country_code' => $country,
                            'address_line1' => $address_line1,
                            'address_line2' => $address_line2,
                            'locality' => $locality,
                            'postal_code' => $postal_code,
                        ],
                        'field_image' => [
                            'target_id' => $image_id,
                            'alt' => $image_alt_text,
                            'title' => $image_title,
                        ],
                        'field_reference' => [
                            'target_id' => $node_overview->id(),
                            'target_type' => 'node',
                        ],
                        'field_delete_rule_id' => [
                            'value' => 0
                        ],
                        'field_update_rule_id' => [
                            'value' => 0
                        ],
                        'field_registrant_count' => [
                            'value' => 0
                        ],
                    ]);
                    $node_general->set('rng_status',array(
                        'value' => 1,
                    ));
                    $node_general->set('rng_registration_type',array(
                        'target_id' => "default_registration",
                    ));
                    $node_general->set('rng_registrants_minimum',array(
                        'value' => 1,
                    ));
                    $node_general->set('rng_registrants_maximum',array(
                        'value' => -1,
                    ));
                    $node_general->save();
                    /* add register link for GP teaser */
                    $uri = "internal:" . "/node/". $node_general->id() . "/register/default_registration";
                    $node_general->set('field_register_link',['uri' => $uri, 'title' => 'Register']);
                    /* add view registration link for GP entity reference in admin full content view */
                    $uri = "internal:" . "/node/" . $node_general->id(). "/registrations";
                    $node_general->set('field_view_registration_list',['uri' => $uri, 'title' => 'View registration list']);
                    $node_general->set('field_message',array(
                        'uri' => 'internal:/node/' . $node_general->id() . '/event/messages',
                        'title' => 'Message general public'
                    ));
                    $node_general->save();

                    /* Update previously created event overview */
                    $node_overview->set('field_event_date_general',$datetime_general);
                    $node_overview->set('field_event_date_end_general',$datetime_general_end);
                    $node_overview->set('field_reference_general',['target_id' => $node_general->id(), 'target_type' => 'node']);
                    if($x != 0)
                    {
                        $node_general = Node::load($node_general->id());
                        $node_general->setPublished(FALSE);
                        $datetime_general = strtotime($datetime_general);
                        $datetime_general = $datetime_general - (7*24*60*60);
                        $node_general->set('publish_on',$datetime_general);
                        $node_general->save();
                        $datetime_general = date("Y-m-d\TH:i:s",$datetime_general);
                    }
                    $node_overview->save();
                }

                if(!is_null($datetime_member))
                {
                    if($x != 0 )
                    {
                        $datetime_member = strtotime($datetime_member);
                        $datetime_member = $datetime_member + (7*24*60*60);
                        $datetime_member = date("Y-m-d\TH:i:s",$datetime_member);
                    }
                    $node_overview->set('field_event_date_member',$datetime_member);
                }
                else
                {
                    if($x != 0 )
                    {
                        $datetime_general = strtotime($datetime_general);
                        $datetime_general = $datetime_general + (7*24*60*60);
                        $datetime_general = date("Y-m-d\TH:i:s",$datetime_general);
                    }

                    $node_overview->set('field_event_date_member',$datetime_general);
                }
                $node_overview->save();
            }
        }
    }
}
?>