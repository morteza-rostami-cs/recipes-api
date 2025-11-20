<?php
/**
 * SMS Service for AmootSMS REST API
 * Works with your API Token and supports OTP or bulk SMS.
 */
class SMS_Service {
  private $token;
  private $from; // optional, can be "public" or a specific line number

  public function __construct( string $token, string $from = 'public' ) {
    $this->token = $token;
    $this->from  = $from;
  }

  /**
   * Send a single SMS message.
   *
   * @param string $to       The recipient's phone number (e.g. "09120000000")
   * @param string $message  The SMS body text
   * @return bool            True if successful, false otherwise
   */
  public function send_sms( string $to, string $message ): bool {
    try {
      // Prepare request URL
      $baseUrl = "https://portal.amootsms.com/rest/SendSimple";
      $nowIran = new DateTime('now', new DateTimeZone('Asia/Tehran'));
      
      $query = http_build_query([
        'Token'          => $this->token,
        'SendDateTime'   => $nowIran->format('c'),
        'SMSMessageText' => $message,
        'LineNumber'     => $this->from,
        'Mobiles'        => $to,
      ]);

      $url = $baseUrl . '?' . $query;

      // Send request
      $response = wp_remote_get($url, [
        'timeout' => 15,
        'sslverify' => false,
      ]);

      if (is_wp_error($response)) {
        error_log('❌ AmootSMS error: ' . $response->get_error_message());
        return false;
      }

      $body = wp_remote_retrieve_body($response);
      error_log('📨 AmootSMS raw response: ' . $body);

      $json = json_decode($body);

      if ($json && isset($json->Status) && $json->Status == 1) {
        error_log("✅ SMS sent successfully to {$to}");
        return true;
      } else {
        error_log("❌ AmootSMS failed: " . $body);
        return false;
      }

    } catch (Exception $e) {
      error_log("❌ AmootSMS exception: " . $e->getMessage());
      return false;
    }
  }
}
