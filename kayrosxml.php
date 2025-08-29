<?php

/**
 * Plugin Name:       Kayros XML Export 1.0
 * Plugin URI:        https://github.com/kamiloCervantes/kayrosxml/
 * Description:       Generación de un archivo XML con una estructura fija a partir de los registros de propiedades almacenados en el sitio. 
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Camilo Cervantes Salazar
 * Author URI:        https://github.com/kamiloCervantes/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://inmobiliaria.kayros.in/plugins/kayrosxml/
 * Text Domain:       kayrosxml
 * Domain Path:       /kayrosxml
 */

register_activation_hook( __FILE__, 'kayrosxml_activate_plugin' );
function kayrosxml_activate_plugin(){
  $active = get_option('kayrosxml_setting_active'); 
  if ( !wp_next_scheduled( 'kayrosxml_cron_generate' ) ) {
    $frequency = 'daily';
    switch($active){
      case "1":
        $frequency = 'five_seconds';
        break;
      case "2":
        $frequency = 'hourly';
        break;
      case "3":
        $frequency = 'six_hours';
        break;
      case "4":
        $frequency = 'daily';
        break;
    }
    wp_schedule_event( time(), $frequency, 'kayrosxml_cron_generate' );
  }
  
}

register_deactivation_hook( __FILE__, 'kayrosxml_deactivate_plugin' );
function kayrosxml_deactivate_plugin(){
  $timestamp = wp_next_scheduled( 'kayrosxml_cron_generate' );
  wp_unschedule_event( $timestamp, 'kayrosxml_cron_generate' );
}

//Registro de intervalos 
add_filter( 'cron_schedules', 'kayrosxml_custom_schedule');
function kayrosxml_custom_schedule( $schedules ) {
     $schedules['five_seconds'] = array(
        'interval' => 5,
        'display' =>__('5 seconds','kayrosxml_lang_domain')
     );
     $schedules['six_hours'] = array(
        'interval' => 21600,
        'display' =>__('6 hours','kayrosxml_lang_domain')
     );
     return $schedules;
}

add_action( 'kayrosxml_update_cron_task', 'kayrosxml_update_cron_task_callback');
function kayrosxml_update_cron_task_callback(){
  $active = get_option('kayrosxml_setting_active');   
 
  
  $timestamp = wp_next_scheduled( 'kayrosxml_cron_generate' );  
  wp_unschedule_event( $timestamp, 'kayrosxml_cron_generate' );
  if((isset($active) && $active != "0" && $active != "")){
    $frequency = 'daily';
    switch($active){
      case "1":
        $frequency = 'five_seconds';
        break;
      case "2":
        $frequency = 'hourly';
        break;
      case "3":
        $frequency = 'six_hours';
        break;
      case "4":
        $frequency = 'daily';
        break;
    }  
    wp_schedule_event( time(), $frequency, 'kayrosxml_cron_generate' );
  }
  
}


add_action( 'kayrosxml_cron_generate', 'kayrosxml_cron_callback');
function kayrosxml_cron_callback(){
    $active = get_option('kayrosxml_setting_active');    
    if((isset($active) && $active != "0" && $active != "")){
      do_action('kayrosxml_manual_generate', 0);
    }
}

add_action( 'kayrosxml_manual_generate', 'kayrosxml_manual_callback');
function kayrosxml_manual_callback($manual){
    $data = kayrosxml_generate_xml_data();      
    $xf = fopen(plugin_dir_path(__FILE__).'files/'.time().($manual > 0 ? '-manual' : '')."-file.xml", "w") or die("Unable to open file!");
    $xc = fopen(plugin_dir_path(__FILE__).'kayros_propit_current.xml', "w") or die("Unable to open file!");
    fwrite($xf, $data);
    fwrite($xc, $data);
    fclose($xf);
    fclose($xc);
}


function kayrosxml_generate_xml_data(){
  $properties = get_posts([
    'numberposts' => 100000,
    'post_type' => 'property'
  ]); 

  $data = "<?xml version=\"1.0\"?><ads>";
  foreach($properties as $p){
    $meta = get_post_meta($p->ID);
    //var_dump($meta['REAL_HOMES_property_size_postfix'][0]);
    $agent_post_id = $meta['REAL_HOMES_agents'][0];
    $agent_post_data = get_post($agent_post_id);
    $agent_post_meta = get_post_meta($agent_post_id);
    
    $location_city_data = $meta['REAL_HOMES_property_address'][0];
    $location_city_data = explode(',', $location_city_data);
    //var_dump($meta['REAL_HOMES_property_address']);
    $location_geo_data = $meta['REAL_HOMES_property_location'][0];
    $location_geo_data = explode(',', $location_geo_data);
    $images_raw = $meta['REAL_HOMES_property_images'];
    $images_data = "";
    foreach($images_raw as $i){
      $images_data .= "<url_photo><![CDATA[ ".get_post($i)->guid." ]]></url_photo>";        
    }
    
    $amenities_raw = get_the_terms($p->ID, 'property-feature');
    $amenities_data = "";
    $slugs = array('air_conditioning', 'alarm', 'balcony','build-in_wardrobe', 'parking', 'cellar', 'children_area', 'concierge',
      'disabled_access', 'electricity', 'equipped_kitchen', 'fireplace', 'garden', 'bbq', 'guardhouse', 'gym', 'heating', 'integral_kitchen',
      'internet', 'jacuzzi', 'library', 'lift', 'natural_gas', 'office', 'outside_view', 'panoramic_view', 'roof_garden', 'sauna',
      'security','service_room', 'swimming_pool', 'tennis_court', 'terrace', 'video_cable','water','water_tank','natural_gas', 'yard');
    foreach($slugs as $s){
      $amenity_debug = '';
      $amenity_data = 0;
      foreach($amenities_raw as $a){        
        $amenity_debug .= sprintf('<debug><var name="slug">%s</var><var name="property-data">%s</var><var name="comparation">%s</var></debug>',str_replace('_', '-', $s), $a->slug, str_replace('_', '-', $s) == $a->slug);
        if(str_replace('_', '-', $s) == $a->slug){
          $amenity_data = $amenity_data + 1;
        }                
      }
      $amenity = sprintf('<%s><![CDATA[%s]]></%s>', $s, $amenity_data, $s);
      $amenities_data .= $amenity;
      //$amenities_data .= '<debug-data>'.$amenity_debug.'</debug-data>';
    }




    $data .= sprintf("<ad>
  <id><![CDATA[ %s ]]></id>
  <agency_id><![CDATA[ N/A ]]></agency_id>
  <title><![CDATA[ %s ]]></title>
  <description><![CDATA[ %s ]]></description>
  <price><![CDATA[ %s ]]></price>
  <currency><![CDATA[ COP ]]></currency>
  <rooms><![CDATA[ %s ]]></rooms>
  <bathrooms><![CDATA[ %s ]]></bathrooms>
  <bathroom_half><![CDATA[ N/A ]]></bathroom_half>
  <operation_type><![CDATA[ %s ]]></operation_type>
  <property_type><![CDATA[ %s ]]></property_type>
  <property_type_land><![CDATA[ N/A ]]></property_type_land>
  <contact_phone><![CDATA[ %s ]]></contact_phone>
  <contact_cellphone><![CDATA[ %s ]]></contact_cellphone>
  <whatsapp><![CDATA[ %s ]]></whatsapp>
  <contact_mail><![CDATA[ %s ]]></contact_mail>
  <contact_name><![CDATA[ %s ]]></contact_name>
  <location>
    <location_region><![CDATA[ %s ]]></location_region>
    <location_city><![CDATA[ %s ]]></location_city>
    <location_neighbourhood><![CDATA[ N/A ]]></location_neighbourhood>
    <location_address><![CDATA[ N/A ]]></location_address>
    <location_postcode><![CDATA[ N/A ]]></location_postcode>
    <location_visibility><![CDATA[ N/A ]]></location_visibility>
    <latitude><![CDATA[ %s ]]></latitude>
    <longitude><![CDATA[ %s ]]></longitude>
    <country_code><![CDATA[ CO ]]></country_code>
  </location>
  <floor><![CDATA[ N/A ]]></floor>
  <floor_area_unit><![CDATA[ %s ]]></floor_area_unit>
  <floor_area><![CDATA[ %s ]]></floor_area>
  <plot_area_unit><![CDATA[ N/A ]]></plot_area_unit>
  <plot_area><![CDATA[ N/A ]]></plot_area>
  <photos>
    %s
  </photos>
  <multimedia_url><![CDATA[ N/A ]]></multimedia_url>
  <virtualtour_url><![CDATA[ N/A ]]></virtualtour_url>
  <community_fee><![CDATA[ N/A ]]></community_fee>
  <community_fee_currency><![CDATA[ N/A ]]></community_fee_currency>
  <condition><![CDATA[ N/A ]]></condition>
  <year><![CDATA[ N/A ]]></year>
  <is_furnished><![CDATA[ N/A ]]></is_furnished>
  <amenities>
    %s
  </amenities>
  <parkings><![CDATA[ N/A ]]></parkings>
  <ground_type><![CDATA[ N/A ]]></ground_type>
  <near_mainstreet><![CDATA[ N/A ]]></near_mainstreet>
  <near_park><![CDATA[ N/A ]]></near_park>
  <near_schools><![CDATA[ N/A ]]></near_schools>
  <near_sea><![CDATA[ N/A ]]></near_sea>
  <near_shopping_mall><![CDATA[ N/A ]]></near_shopping_mall>
  <near_train_station><![CDATA[ N/A ]]></near_train_station>
</ad>", $p->ID, $p->post_title, $p->post_content, $meta['REAL_HOMES_property_price'][0],
        $meta['REAL_HOMES_property_bedrooms'][0] ? $meta['REAL_HOMES_property_bedrooms'][0] : 'N/A',
        $meta['REAL_HOMES_property_bathrooms'][0] ? $meta['REAL_HOMES_property_bathrooms'][0] : 'N/A',
        get_the_terms($p->ID, 'property-status')[0]->name, 
        get_the_terms($p->ID, 'property-type')[0]->name,$agent_post_meta['REAL_HOMES_whatsapp_number'][0],
        $agent_post_meta['REAL_HOMES_whatsapp_number'][0],$agent_post_meta['REAL_HOMES_whatsapp_number'][0],
        $agent_post_meta['REAL_HOMES_agent_email'][0],$agent_post_data->post_title,
        $location_city_data[count($location_city_data)-2], $location_city_data[0],
        $location_geo_data[0], $location_geo_data[1],
        $meta['REAL_HOMES_property_size_postfix'][0],
        $meta['REAL_HOMES_property_size'][0],$images_data, $amenities_data);
  }



    $data .= "</ads>";
    return $data;
}


add_action( 'admin_menu', 'kayrosxml_options_page' );
function kayrosxml_options_page() {
    add_menu_page(
        'KayrosXML',
        'Kayros XML',
        'manage_options',
        'kayrosxml',
        'kayrosxml_options_page_html',
        '',
        20
    );
}


function kayrosxml_settings_init() {
    // register a new setting for "kayrosxml" page
    register_setting('kayrosxml', 'kayrosxml_setting_active');
 
    // register a new section in the "reading" page
    add_settings_section(
        'kayrosxml_settings_section',
        'Activar/Desactivar XML', 'kayrosxml_settings_section_callback',
        'kayrosxml'
    );
 
    // register a new field in the "kayrosxml_settings_section" section, inside the "reading" page
    add_settings_field(
        'kayrosxml_settings_field',
        'Frecuencia', 'kayrosxml_settings_field_callback',
        'kayrosxml',
        'kayrosxml_settings_section'
    );
    
    
    
}
 
/**
 * register kayrosxml_settings_init to the admin_init action hook
 */
add_action('admin_init', 'kayrosxml_settings_init');
 
/**
 * callback functions
 */
 
// section content cb
function kayrosxml_settings_section_callback() {
    echo '<p>Selecciona una opción para activar o desactivar la generación de XML de Kayros.</p>';
}
 
// field content cb
function kayrosxml_settings_field_callback() {
    // get the value of the setting we've registered with register_setting()
    $setting = get_option('kayrosxml_setting_active');
    //var_dump($setting);
    //do_action('kayrosxml_update_cron_task');
    // output the field
    ?>
    <select name="kayrosxml_setting_active" value="<?php echo isset( $setting ) ? esc_attr( $setting ) : ''; ?>">
        <option>Seleccione una opción...</option>
        <option value="0" <?php echo isset( $setting ) && esc_attr( $setting ) == 0 ? 'selected' : ''; ?>>Inactivo</option>
        <option value="1" <?php echo isset( $setting ) && esc_attr( $setting ) == 1 ? 'selected' : ''; ?>>Cada 5 segundos</option>
        <option value="2" <?php echo isset( $setting ) && esc_attr( $setting ) == 2 ? 'selected' : ''; ?>>Cada 60 minutos</option>
        <option value="3" <?php echo isset( $setting ) && esc_attr( $setting ) == 3 ? 'selected' : ''; ?>>Cada 6 horas</option>
        <option value="4" <?php echo isset( $setting ) && esc_attr( $setting ) == 4 ? 'selected' : ''; ?>>Cada 24 horas</option>
    </select>
    <?php
}

add_action('init', kayrosxml_form_generatexml_callback);
function kayrosxml_form_generatexml_callback(){
  if (!empty($_POST['nonce_custom_form']))
    {       
        if (!wp_verify_nonce($_POST['nonce_custom_form'], 'handle_custom_form'))
        {
            die('You are not authorized to perform this action.');
        }
        else
        {
            $error = null;
            if (empty($_POST['kayrosxml_generate_action']))
            {
                $error = new WP_Error('kayrosxml_generate_action_error', __('La acción no se encuentra permitida', 'kayrosxml_error'));
                wp_die($error->get_error_message(), __('Error!', 'kayrosxml_error'));
            }
            else{
              do_action('kayrosxml_manual_generate',1);
            }
                
        }
    }
    else{
      if (!empty($_POST['kayrosxml_generate_action']))
      {
        do_action('kayrosxml_manual_generate',1);
      }
    }
  
}
