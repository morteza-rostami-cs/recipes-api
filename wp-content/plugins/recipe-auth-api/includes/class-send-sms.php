<?php
/**
 * SMS sender for Melipayamak REST API
 */
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
   * Send OTP SMS
   */
  public function send_sms($to, $message, $is_flash = false) {
    // Ensure the phone number is in the correct international format
    $to = $this->sanitize_phone_for_sms($to);

    // Prepare API URL and data
    $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
    $data = [
      'username' => $this->username,
      'password' => $this->api_key, // API Key
      'to'       => $to,
      'from'     => $this->from,
      'text'     => $message,
      'isFlash'  => $is_flash ? 'true' : 'false',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($data),
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      error_log("Melipayamak SMS error: " . $error);
      return false;
    }

    error_log("Melipayamak SMS response: " . $response);

    // Parse the response to ensure success
    $response_data = json_decode($response, true); // Try parsing it as JSON
    if (isset($response_data['Value']) && $response_data['Value'] === '0') {
      error_log("Melipayamak: Invalid credentials or sender.");
      return false;
    }

    return true; // Success
  }

  /**
   * Sanitizes phone number for Melipayamak
   */
  private function sanitize_phone_for_sms($phone) {
    // Strip non-numeric characters
    $phone = preg_replace('/[^\d\+]/', '', (string) $phone);

    // Convert local numbers starting with 0 to international format (+98 for Iran)
    if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
      $phone = '+98' . $matches[1];
    }

    return $phone;
  }
}

