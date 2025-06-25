<?php

/**
 * Plugin Name:       Kayros XML Proppit
 * Description:       Generación XML compatible con Proppit para propiedades.
 * Version:           2.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Kayros
 * License:           GPL v2 or later
 */

add_action('init', 'kayros_proppit_generate_xml');

function kayros_proppit_generate_xml(){

  if(isset($_GET['generate_proppit_xml'])){

    header('Content-type: text/xml; charset=utf-8');

    $properties = get_posts([
      'numberposts' => -1,
      'post_type' => 'property'
    ]);

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><listings></listings>');

    foreach($properties as $p){

      $meta = get_post_meta($p->ID);
      $agent_id = $meta['REAL_HOMES_agents'][0];
      $agent_meta = get_post_meta($agent_id);
      $agent_data = get_post($agent_id);
      $geo = explode(',', $meta['REAL_HOMES_property_location'][0]);

      $listing = $xml->addChild('listing');
      $listing->addChild('reference_id', $p->ID);

      $contact = $listing->addChild('contact');
      $contact->addChild('phone', '+57'.$agent_meta['REAL_HOMES_whatsapp_number'][0]);
      $contact->addChild('email', $agent_meta['REAL_HOMES_agent_email'][0]);
      $contact->addChild('whatsapp', '+57'.$agent_meta['REAL_HOMES_whatsapp_number'][0]);
      $contact->addChild('name', $agent_data->post_title);

      $listing->addChild('title', htmlspecialchars($p->post_title));
      $listing->addChild('description', htmlspecialchars(strip_tags($p->post_content)));

      $prices = $listing->addChild('prices');
      $price = $prices->addChild('price', (int)$meta['REAL_HOMES_property_price'][0]);
      $price->addAttribute('currency', 'COP');
      $price->addAttribute('operation', strtolower(get_the_terms($p->ID, 'property-status')[0]->name));

      $listing->addChild('propertyType', strtolower(get_the_terms($p->ID, 'property-type')[0]->name));

      $coordinates = $listing->addChild('coordinates');
      $coordinates->addChild('latitude', trim($geo[0]));
      $coordinates->addChild('longitude', trim($geo[1]));

      $listing->addChild('bedrooms', (int)($meta['REAL_HOMES_property_bedrooms'][0] ?: 0));
      $listing->addChild('bathrooms', (int)($meta['REAL_HOMES_property_bathrooms'][0] ?: 0));

      $listing->addChild('floorArea', (int)$meta['REAL_HOMES_property_size'][0])->addAttribute('unit', 'sqm');
      $listing->addChild('usableArea', (int)$meta['REAL_HOMES_property_size'][0])->addAttribute('unit', 'sqm');

      $pictures = $listing->addChild('pictures');
      foreach($meta['REAL_HOMES_property_images'] as $img){
        $pictures->addChild('url', get_post($img)->guid);
      }

      $amenities = get_the_terms($p->ID, 'property-feature');
      if($amenities){
        $amenities_node = $listing->addChild('amenities');
        foreach($amenities as $amenity){
          $amenities_node->addChild('amenity', strtolower($amenity->name));
        }
      }

    }

    echo $xml->asXML();
    exit;
  }
}
