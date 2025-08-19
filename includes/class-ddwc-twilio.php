<?php
/**
 * Twilio helper class.
 *
 * Provides a wrapper around the Twilio PHP SDK for sending SMS messages.
 *
 * @link       https://www.deviodigital.com
 * @since      2.4.3
 *
 * @package    DDWC
 * @subpackage DDWC/includes
 */

if ( ! class_exists( 'Delivery_Drivers_Twilio' ) ) {
/**
 * Helper class to send SMS messages via Twilio.
 */
class Delivery_Drivers_Twilio {

/**
 * Twilio REST client instance.
 *
 * @var \Twilio\Rest\Client|null
 */
protected $client = null;

/**
 * Setup Twilio client using saved credentials.
 */
public function __construct() {
$sid   = get_option( 'ddwc_settings_twilio_account_sid' );
$token = get_option( 'ddwc_settings_twilio_auth_token' );

if ( $sid && $token && class_exists( '\\Twilio\\Rest\\Client' ) ) {
$this->client = new \Twilio\Rest\Client( $sid, $token );
}
}

/**
 * Send an SMS message via Twilio.
 *
 * @param string $to      Recipient phone number.
 * @param string $from    Sender phone number.
 * @param string $message Message body.
 *
 * @return bool True on success, false on failure.
 */
public function send_sms( $to, $from, $message ) {
if ( empty( $this->client ) ) {
return false;
}

try {
$this->client->messages->create(
$to,
array(
'from' => $from,
'body' => $message,
)
);
return true;
} catch ( \Exception $e ) {
return false;
}
}
}
}
