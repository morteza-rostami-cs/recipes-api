<?php
use Melipayamak\MelipayamakApi;

class SMS_Service {
  private $username;
  private $api_key;
  private $from;

  public function __construct($username, $api_key, $from) {
    $this->username = $username;
    $this->api_key = $api_key;
    $this->from = $from;
  }

  /**
   * Send SMS using Melipayamak official SDK
   */
  public function send_sms($to, $message) {

    error_log($this->username . "\n" . $this->api_key . "\n" . $this->from . "\n" . $to . "\n" . $message);

    try {
      // Initialize SDK
      $api = new MelipayamakApi($this->username, $this->api_key);
      $sms = $api->sms();

      // Send the message
      $response = $sms->send($to, $this->from, $message);

      // Parse JSON response
      $json = json_decode($response);
      if (isset($json->RetStatus) && intval($json->RetStatus) === 1) {
        error_log("✅ SMS sent successfully: RecId {$json->Value}");
        return true;
      } else {
        error_log("❌ SMS failed: " . json_encode($json));
        return false;
      }
    } catch (Exception $e) {
      error_log("Melipayamak error: " . $e->getMessage());
      return false;
    }
  }
}
